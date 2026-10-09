<?php

declare(strict_types=1);

namespace Yepr\Plugin\Task\LinkRepair\Tests\Unit\Repair;

use PHPUnit\Framework\TestCase;
use Yepr\Plugin\Task\LinkRepair\Content\ContentItem;
use Yepr\Plugin\Task\LinkRepair\Content\ContentSources;
use Yepr\Plugin\Task\LinkRepair\Html\LinkRewriter;
use Yepr\Plugin\Task\LinkRepair\Repair\Repairer;
use Yepr\Plugin\Task\LinkRepair\Repair\RepairSettings;
use Yepr\Plugin\Task\LinkRepair\Report\CsvReport;
use Yepr\Plugin\Task\LinkRepair\Resolve\LinkState;
use Yepr\Plugin\Task\LinkRepair\Store\LinkRecord;
use Yepr\Plugin\Task\LinkRepair\Store\Scan;
use Yepr\Plugin\Task\LinkRepair\Tests\Support\FakeClock;
use Yepr\Plugin\Task\LinkRepair\Tests\Support\MemoryLinkStore;
use Yepr\Plugin\Task\LinkRepair\Tests\Support\MemoryScanStore;
use Yepr\Plugin\Task\LinkRepair\Tests\Support\MemorySource;

final class RepairerTest extends TestCase
{
    private MemorySource $articles;

    private MemorySource $categories;

    private MemorySource $modules;

    private MemoryScanStore $scans;

    private MemoryLinkStore $links;

    private string $folder;

    /** @var list<string> */
    private array $log = [];

    protected function setUp(): void
    {
        $this->articles   = MemorySource::articles();
        $this->categories = MemorySource::categories();
        $this->modules    = MemorySource::modules();
        $this->scans      = new MemoryScanStore();
        $this->links      = new MemoryLinkStore();
        $this->folder     = sys_get_temp_dir() . '/linkrepair-test-' . bin2hex(random_bytes(4));
        mkdir($this->folder);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->folder . '/*') ?: []);
        rmdir($this->folder);
    }

    private function repairer(bool $dryRun = false, float $budget = 20.0, float $clockStep = 0.0): Repairer
    {
        return new Repairer(
            new ContentSources($this->articles, $this->categories, $this->modules),
            $this->scans,
            $this->links,
            new LinkRewriter(),
            new CsvReport($this->links, $this->folder),
            new FakeClock($clockStep),
            new RepairSettings($dryRun, 42, $budget),
            function (string $message, string $priority): void {
                $this->log[] = $message;
            }
        );
    }

    /**
     * A finished scan with one repairable link per given article, recorded as the
     * article is now.
     */
    private function scanned(int ...$articleIds): Scan
    {
        $scan         = $this->scans->start(1);
        $scan->status = Scan::FINISHED;
        $this->scans->save($scan);

        foreach ($articleIds as $id) {
            $this->links->add([$this->record($scan->id, $this->articles, $id, '/user-manual/seo', 'index.php?Itemid=12')]);
        }

        return $scan;
    }

    private function record(int $scanId, MemorySource $source, int $itemId, string $href, string $newHref, string $field = ''): LinkRecord
    {
        $item = $source->get($itemId);

        return new LinkRecord(
            0,
            $scanId,
            $item->kind,
            $itemId,
            $item->title,
            '',
            $field === '' ? (string) array_key_first($item->fields) : $field,
            'text',
            $href,
            LinkState::REPAIRABLE,
            '',
            12,
            'SEO',
            $newHref,
            '',
            $item->hash()
        );
    }

    public function testRewritesTheLinksAndSavesTheArticleWithANote(): void
    {
        $this->articles->put(1, '<p><a href="/user-manual/seo">SEO</a> and <a href="/user-manual/seo">again</a></p>');
        $this->scanned(1);

        $outcome = $this->repairer()->run();

        self::assertTrue($outcome->finished);
        self::assertSame(
            '<p><a href="index.php?Itemid=12">SEO</a> and <a href="index.php?Itemid=12">again</a></p>',
            $this->articles->get(1)->fields['introtext']
        );
        self::assertSame([['id' => 1, 'userId' => 42, 'note' => 'Link repair: 1 link(s) now point to menu items']], $this->articles->saves);
        self::assertSame([LinkState::REPAIRED], $this->links->states());
    }

    public function testRewritesIntroTextAndFullTextSeparately(): void
    {
        $this->articles->put(1, '<a href="/a">a</a>', '<a href="/b">b</a>');
        $scan = $this->scanned();
        $this->links->add([
            $this->record($scan->id, $this->articles, 1, '/a', 'index.php?Itemid=1', 'introtext'),
            $this->record($scan->id, $this->articles, 1, '/b', 'index.php?Itemid=2', 'fulltext'),
        ]);

        $this->repairer()->run();

        self::assertSame('<a href="index.php?Itemid=1">a</a>', $this->articles->get(1)->fields['introtext']);
        self::assertSame('<a href="index.php?Itemid=2">b</a>', $this->articles->get(1)->fields['fulltext']);
        self::assertCount(1, $this->articles->saves, 'one save per article');
    }

    public function testRepairsCategoriesAndCustomModulesThroughTheirOwnSource(): void
    {
        $this->articles->put(1, '<a href="/a">a</a>');
        $this->categories->put(8, '<a href="/b">b</a>');
        $this->modules->put(90, '<a href="/c">c</a>');
        $scan = $this->scanned();
        $this->links->add([
            $this->record($scan->id, $this->modules, 90, '/c', 'index.php?Itemid=3'),
            $this->record($scan->id, $this->categories, 8, '/b', 'index.php?Itemid=2'),
            $this->record($scan->id, $this->articles, 1, '/a', 'index.php?Itemid=1'),
        ]);

        $this->repairer()->run();

        self::assertSame('<a href="index.php?Itemid=2">b</a>', $this->categories->get(8)->fields['description']);
        self::assertSame('<a href="index.php?Itemid=3">c</a>', $this->modules->get(90)->fields['content']);
        self::assertCount(1, $this->articles->saves);
        self::assertCount(1, $this->categories->saves);
        self::assertCount(1, $this->modules->saves);
        self::assertSame([LinkState::REPAIRED, LinkState::REPAIRED, LinkState::REPAIRED], $this->links->states());
        self::assertStringContainsString('Module 90 "Module 90": 1 link(s) repaired.', implode("\n", $this->log));
    }

    public function testADryRunChangesNothing(): void
    {
        $this->articles->put(1, '<a href="/user-manual/seo">SEO</a>');
        $this->scanned(1);

        $this->repairer(dryRun: true)->run();

        self::assertSame([], $this->articles->saves);
        self::assertSame([LinkState::REPAIRABLE], $this->links->states());
        self::assertStringContainsString('Would change in article 1', implode("\n", $this->log));
    }

    public function testSkipsAnItemThatChangedSinceTheScan(): void
    {
        $this->articles->put(1, '<a href="/user-manual/seo">SEO</a>');
        $this->scanned(1);
        $this->articles->put(1, '<a href="/user-manual/seo">SEO</a> and a new sentence.');

        $this->repairer()->run();

        self::assertSame([], $this->articles->saves);
        self::assertSame([LinkState::SKIPPED], $this->links->states());
        self::assertStringContainsString('scan again', $this->links->records[1]->message);
    }

    public function testSkipsACheckedOutItem(): void
    {
        $this->articles->put(1, '<a href="/user-manual/seo">SEO</a>', '', checkedOut: 5);
        $this->scanned(1);

        $this->repairer()->run();

        self::assertSame([], $this->articles->saves);
        self::assertSame([LinkState::SKIPPED], $this->links->states());
    }

    public function testAFailedSaveMarksTheLinksFailed(): void
    {
        $this->articles->put(1, '<a href="/user-manual/seo">SEO</a>');
        $this->scanned(1);
        $this->articles->failSaveWith = 'database gone';

        $this->repairer()->run();

        self::assertSame([LinkState::FAILED], $this->links->states());
        self::assertSame('saving failed: database gone', $this->links->records[1]->message);
    }

    public function testStopsWhenTheTimeIsUpAndContinuesWhereItStopped(): void
    {
        foreach ([1, 2, 3] as $id) {
            $this->articles->put($id, '<a href="/user-manual/seo">SEO</a>');
        }

        $this->scanned(1, 2, 3);

        $first = $this->repairer(budget: 2.5, clockStep: 1.0)->run();

        self::assertFalse($first->finished);
        self::assertCount(2, $this->articles->saves);

        $second = $this->repairer()->run();

        self::assertTrue($second->finished);
        self::assertCount(3, $this->articles->saves);
        self::assertSame(Scan::REPAIR_IDLE, $this->scans->scans[1]->repairStatus);
    }

    public function testWithoutAFinishedScanThereIsNothingToDo(): void
    {
        self::assertTrue($this->repairer()->run()->finished);
        self::assertStringContainsString('no finished scan', implode("\n", $this->log));
    }

    public function testAnItemOfAnUnknownKindFails(): void
    {
        $scan = $this->scanned();
        $this->links->add([
            new LinkRecord(
                0,
                $scan->id,
                'weblink',
                3,
                'x',
                '',
                'description',
                'x',
                '/x',
                LinkState::REPAIRABLE,
                '',
                1,
                'x',
                'index.php?Itemid=1',
                '',
                ''
            ),
        ]);

        $this->repairer()->run();

        self::assertSame([LinkState::FAILED], $this->links->states());
        self::assertSame(ContentItem::ARTICLE, $this->articles->kind());
    }
}
