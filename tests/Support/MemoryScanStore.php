<?php

declare(strict_types=1);

namespace Yepr\Plugin\Task\LinkRepair\Tests\Support;

use Yepr\Plugin\Task\LinkRepair\Store\Scan;
use Yepr\Plugin\Task\LinkRepair\Store\ScanStore;

/**
 * Scans in an array. Saving stores a copy, as a database would.
 */
final class MemoryScanStore implements ScanStore
{
    /** @var array<int, Scan> */
    public array $scans = [];

    private int $nextId = 1;

    public function running(int $taskId): ?Scan
    {
        foreach (array_reverse($this->scans, true) as $scan) {
            if ($scan->taskId === $taskId && $scan->status === Scan::RUNNING) {
                return clone $scan;
            }
        }

        return null;
    }

    public function start(int $taskId): Scan
    {
        $scan                   = new Scan($this->nextId++, $taskId);
        $this->scans[$scan->id] = clone $scan;

        return $scan;
    }

    public function save(Scan $scan): void
    {
        $this->scans[$scan->id] = clone $scan;
    }

    public function latestFinished(): ?Scan
    {
        foreach (array_reverse($this->scans, true) as $scan) {
            if ($scan->status === Scan::FINISHED) {
                return clone $scan;
            }
        }

        return null;
    }
}
