<?php

/**
 * @package     LinkRepair
 *
 * @copyright   Copyright (C) 2026 Yepr, Herman Peeren. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

namespace Yepr\Plugin\Task\LinkRepair\Run;

use Yepr\Plugin\Task\LinkRepair\Content\ContentSources;
use Yepr\Plugin\Task\LinkRepair\Html\LinkExtractor;
use Yepr\Plugin\Task\LinkRepair\Html\LinkRewriter;
use Yepr\Plugin\Task\LinkRepair\Repair\Repairer;
use Yepr\Plugin\Task\LinkRepair\Repair\RepairSettings;
use Yepr\Plugin\Task\LinkRepair\Report\CsvReport;
use Yepr\Plugin\Task\LinkRepair\Resolve\LinkResolver;
use Yepr\Plugin\Task\LinkRepair\Resolve\MenuIndex;
use Yepr\Plugin\Task\LinkRepair\Resolve\RedirectFollower;
use Yepr\Plugin\Task\LinkRepair\Scan\Scanner;
use Yepr\Plugin\Task\LinkRepair\Store\LinkStore;
use Yepr\Plugin\Task\LinkRepair\Store\ScanStore;
use Yepr\Plugin\Task\LinkRepair\Url\InternalLinkFilter;
use Yepr\Plugin\Task\LinkRepair\Url\SiteAddress;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Builds a Scanner or a Repairer for one task run.
 *
 * The services that are the same for every run are injected here once, by the
 * plugin's service provider. What differs per task - the site's address, whether to
 * follow redirects, dry run, time budget - comes from the task's parameters, so the
 * resolver, the scanner and the repairer are made here, per run, and nowhere else.
 */
final class RoutineFactory
{
	public const DEFAULT_BUDGET = 20;
	public const MIN_BUDGET     = 5;
	public const MAX_BUDGET     = 300;

	public function __construct(
		private readonly ContentSources $sources,
		private readonly ScanStore $scans,
		private readonly LinkStore $links,
		private readonly MenuIndex $menus,
		private readonly RedirectFollower $redirects,
		private readonly LinkExtractor $extractor,
		private readonly LinkRewriter $rewriter,
		private readonly InternalLinkFilter $filter,
		private readonly CsvReport $report,
		private readonly Clock $clock
	) {
	}

	/**
	 * @param   object                          $params  The task's parameters.
	 * @param   callable(string, string): void  $log     Receives a message and a priority.
	 *
	 * @throws  MissingConfiguration  Without a usable site address.
	 */
	public function scanner(object $params, callable $log): Scanner
	{
		$otherHosts = preg_split('/[\s,]+/', $this->text($params, 'other_hosts'), -1, PREG_SPLIT_NO_EMPTY) ?: [];

		try {
			$site = SiteAddress::fromUrl($this->text($params, 'site_url'), $otherHosts);
		} catch (\InvalidArgumentException $e) {
			throw new MissingConfiguration($e->getMessage(), 0, $e);
		}

		// On unless switched off.
		$follow   = (bool) ($params->follow_redirects ?? true);
		$resolver = new LinkResolver($this->menus, $follow ? $this->redirects : null, $this->filter, $site);

		return new Scanner(
			$this->sources,
			$this->scans,
			$this->links,
			$this->extractor,
			$this->filter,
			$resolver,
			$this->menus,
			$site,
			$this->report,
			$this->clock,
			$this->budget($params),
			$log
		);
	}

	/**
	 * @param   object                          $params  The task's parameters.
	 * @param   callable(string, string): void  $log     Receives a message and a priority.
	 */
	public function repairer(object $params, callable $log): Repairer
	{
		$settings = new RepairSettings(
			// On unless switched off: a task saved without the field changes nothing.
			(bool) ($params->dry_run ?? true),
			max(0, (int) ($params->as_user ?? 0)),
			$this->budget($params)
		);

		return new Repairer($this->sources, $this->scans, $this->links, $this->rewriter, $this->report, $this->clock, $settings, $log);
	}

	private function budget(object $params): float
	{
		$budget = (int) ($params->time_budget ?? self::DEFAULT_BUDGET);

		return (float) min(self::MAX_BUDGET, max(self::MIN_BUDGET, $budget));
	}

	private function text(object $params, string $name): string
	{
		$value = $params->{$name} ?? '';

		return \is_scalar($value) ? trim((string) $value) : '';
	}
}
