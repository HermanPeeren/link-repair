<?php

declare(strict_types=1);

namespace Yepr\Plugin\Task\LinkRepair\Tests\Unit\Run;

use PHPUnit\Framework\TestCase;
use Yepr\Plugin\Task\LinkRepair\Html\LinkExtractor;
use Yepr\Plugin\Task\LinkRepair\Html\LinkRewriter;
use Yepr\Plugin\Task\LinkRepair\Report\CsvReport;
use Yepr\Plugin\Task\LinkRepair\Run\MissingConfiguration;
use Yepr\Plugin\Task\LinkRepair\Run\RoutineFactory;
use Yepr\Plugin\Task\LinkRepair\Tests\Support\FakeClock;
use Yepr\Plugin\Task\LinkRepair\Tests\Support\FakeMenuIndex;
use Yepr\Plugin\Task\LinkRepair\Tests\Support\FakeRedirectFollower;
use Yepr\Plugin\Task\LinkRepair\Tests\Support\MemoryArticles;
use Yepr\Plugin\Task\LinkRepair\Tests\Support\MemoryLinkStore;
use Yepr\Plugin\Task\LinkRepair\Tests\Support\MemoryScanStore;
use Yepr\Plugin\Task\LinkRepair\Url\InternalLinkFilter;

final class RoutineFactoryTest extends TestCase
{
    private function factory(): RoutineFactory
    {
        $links = new MemoryLinkStore();

        return new RoutineFactory(
            new MemoryArticles(),
            new MemoryScanStore(),
            $links,
            new FakeMenuIndex(),
            new FakeRedirectFollower(),
            new LinkExtractor(),
            new LinkRewriter(),
            new InternalLinkFilter(),
            new CsvReport($links, sys_get_temp_dir()),
            new FakeClock()
        );
    }

    public function testAScanNeedsTheSiteAddress(): void
    {
        $this->expectException(MissingConfiguration::class);

        $this->factory()->scanner((object) ['site_url' => ''], static function (): void {
        });
    }

    public function testAScanNeedsAnHttpAddress(): void
    {
        $this->expectException(MissingConfiguration::class);

        $this->factory()->scanner((object) ['site_url' => 'ftp://guide.joomla.org'], static function (): void {
        });
    }

    public function testAScanWithAnAddressIsMade(): void
    {
        $params = (object) ['site_url' => 'https://guide.joomla.org', 'other_hosts' => "www.guide.joomla.org\n"];

        $this->factory()->scanner($params, static function (): void {
        });

        $this->addToAssertionCount(1);
    }

    public function testARepairWithoutParametersIsADryRun(): void
    {
        $log = [];

        $this->factory()->repairer((object) [], static function (string $message) use (&$log): void {
            $log[] = $message;
        })->run();

        // Nothing to repair, and the message says so; the point is it ran without settings.
        self::assertNotSame([], $log);
    }
}
