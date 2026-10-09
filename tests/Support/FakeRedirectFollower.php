<?php

declare(strict_types=1);

namespace Yepr\Plugin\Task\LinkRepair\Tests\Support;

use Yepr\Plugin\Task\LinkRepair\Resolve\RedirectFollower;
use Yepr\Plugin\Task\LinkRepair\Resolve\RedirectResult;
use Yepr\Plugin\Task\LinkRepair\Url\SiteAddress;

/**
 * Answers from a table the test fills: URL => result. Unknown URLs are a 404.
 */
final class FakeRedirectFollower implements RedirectFollower
{
    /** @var array<string, RedirectResult> */
    private array $answers = [];

    /** @var list<string> */
    public array $asked = [];

    public function redirects(string $from, string $to, int $hops = 1): void
    {
        $this->answers[$from] = new RedirectResult(200, $to, $hops);
    }

    public function answers(string $url, RedirectResult $result): void
    {
        $this->answers[$url] = $result;
    }

    public function follow(string $url, SiteAddress $site): RedirectResult
    {
        $this->asked[] = $url;

        return $this->answers[$url] ?? new RedirectResult(404, $url, 0);
    }
}
