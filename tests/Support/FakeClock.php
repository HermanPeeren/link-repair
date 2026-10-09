<?php

declare(strict_types=1);

namespace Yepr\Plugin\Task\LinkRepair\Tests\Support;

use Yepr\Plugin\Task\LinkRepair\Run\Clock;

/**
 * A clock that moves on a fixed step every time it is read.
 */
final class FakeClock implements Clock
{
    private float $time = 1000.0;

    public function __construct(private readonly float $step = 0.0)
    {
    }

    public function now(): float
    {
        $now = $this->time;
        $this->time += $this->step;

        return $now;
    }
}
