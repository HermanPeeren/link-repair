<?php

declare(strict_types=1);

namespace Yepr\Plugin\Task\LinkRepair\Tests\Unit\Scan;

use PHPUnit\Framework\TestCase;
use Yepr\Plugin\Task\LinkRepair\Html\LinkExtractor;
use Yepr\Plugin\Task\LinkRepair\Report\CsvReport;
use Yepr\Plugin\Task\LinkRepair\Resolve\LinkResolver;
use Yepr\Plugin\Task\LinkRepair\Resolve\LinkState;
use Yepr\Plugin\Task\LinkRepair\Scan\Scanner;
use Yepr\Plugin\Task\LinkRepair\Store\Scan;
use Yepr\Plugin\Task\LinkRepair\Tests\Support\FakeClock;
use Yepr\Plugin\Task\LinkRepair\Tests\Support\FakeMenuIndex;
use Yepr\Plugin\Task\LinkRepair\Tests\Support\FakeRedirectFollower;
use Yepr\Plugin\Task\LinkRepair\Tests\Support\MemoryArticles;
use Yepr\Plugin\Task\LinkRepair\Tests\Support\MemoryLinkStore;
use Yepr\Plugin\Task\LinkRepair\Tests\Support\MemoryScanStore;
use Yepr\Plugin\Task\LinkRepair\Url\InternalLinkFilter;
use Yepr\Plugin\Task\LinkRepair\Url\SiteAddress;

final class ScannerTest extends TestCase
{
    private MemoryArticles $articles;

    private MemoryScanStore $scans;

    private MemoryLinkStore $links;

    private FakeMenuIndex $menus;

    private FakeRedirectFollower $redirects;

    private string $folder;

    /** @var list<string> */
    private array $log = [];

    protected function setUp(): void
    {
        $this->articles  = new MemoryArticles();
        $this->scans     = new MemoryScanStore();
        $this->links     = new MemoryLinkStore();
        $this->menus     = new FakeMenuIndex();
        $this->redirects = new FakeRedirectFollower();
        $this->folder    = sys_get_temp_dir() . '/linkrepair-test-' . bin2hex(random_bytes(4));
        mkdir($this->folder);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->folder . '/*') ?: []);
        rmdir($this->folder);
    }

    private function scanner(float $budget = 20.0, float $clockStep = 0.0): Scanner
    {
        $site   = SiteAddress::fromUrl('https://guide.joomla.org');
        $filter = new InternalLinkFilter();

        return new Scanner(
            $this->articles,
            $this->scans,
            $this->links,
            new LinkExtractor(),
            $filter,
            new LinkResolver($this->menus, $this->redirects, $filter, $site),
            $this->menus,
            $site,
            new CsvReport($this->links, $this->folder),
            new FakeClock($clockStep),
            $budget,
            function (string $message, string $priority): void {
                $this->log[] = $message;
            }
        );
    }

    public function testRecordsTheInternalLinksOfEveryArticle(): void
    {
        $this->menus->add(12, 'user-guide/seo', 'SEO');
        $this->menus->showsArticle(1, 'tutorials/intro');
        $this->articles->put(1, '<a href="/user-guide/seo">SEO</a> <a href="https://docs.joomla.org/x">old docs</a>', '<a href="/gone">gone</a>');
        $this->articles->put(2, '<p>No links.</p>');

        $outcome = $this->scanner()->run(7);

        self::assertTrue($outcome->finished);
        self::assertSame([LinkState::REPAIRABLE, LinkState::BROKEN], $this->links->states());

        $first = $this->links->records[1];
        self::assertSame('introtext', $first->field);
        self::assertSame('SEO', $first->linkText);
        self::assertSame(12, $first->menuId);
        self::assertSame('index.php?Itemid=12', $first->newHref);
        self::assertSame('https://guide.joomla.org/tutorials/intro', $first->pageUrl);
        self::assertSame($this->articles->get(1)->hash(), $first->articleHash);
        self::assertSame('fulltext', $this->links->records[2]->field);

        self::assertStringContainsString('Scan 1 finished: 2 articles, 2 internal links (1 broken, 1 repairable).', implode('
', $this->log));

        $scan = $this->scans->scans[1];
        self::assertSame(Scan::FINISHED, $scan->status);
        self::assertSame(2, $scan->articles);
        self::assertSame(2, $scan->links);
    }

    public function testWritesTheReportWhenFinished(): void
    {
        $this->articles->put(1, '<a href="/gone">gone</a>');

        $this->scanner()->run(7);

        $csv = (string) file_get_contents($this->folder . '/linkrepair-scan-1.csv');
        self::assertStringStartsWith("\xEF\xBB\xBF\"Article id\",\"Article title\"", $csv);
        self::assertStringContainsString(',/gone,broken,', $csv);
    }

    public function testStopsWhenTheTimeIsUpAndContinuesNextRun(): void
    {
        for ($id = 1; $id <= 5; $id++) {
            $this->articles->put($id, '<a href="/gone-' . $id . '">x</a>');
        }

        // Every clock reading takes a second; a budget of 2.5 seconds leaves room for two articles.
        $first = $this->scanner(2.5, 1.0)->run(7);

        self::assertFalse($first->finished);
        self::assertSame(2, $this->scans->scans[1]->cursorId);
        self::assertCount(2, $this->links->records);

        $second = $this->scanner()->run(7);

        self::assertTrue($second->finished);
        self::assertCount(5, $this->links->records);
        self::assertCount(1, $this->scans->scans, 'the second run continued the same scan');
    }

    public function testANewRunAfterAFinishedScanStartsANewScan(): void
    {
        $this->articles->put(1, '<a href="/gone">x</a>');

        $this->scanner()->run(7);
        $this->scanner()->run(7);

        self::assertCount(2, $this->scans->scans);
        self::assertSame(Scan::FINISHED, $this->scans->scans[2]->status);
    }
}
