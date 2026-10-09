<?php

/**
 * @package     LinkRepair
 *
 * @copyright   Copyright (C) 2026 Yepr, Herman Peeren. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

namespace Yepr\Plugin\Task\LinkRepair\Scan;

use Yepr\Plugin\Task\LinkRepair\Content\ContentItem;
use Yepr\Plugin\Task\LinkRepair\Content\ContentSources;
use Yepr\Plugin\Task\LinkRepair\Html\LinkExtractor;
use Yepr\Plugin\Task\LinkRepair\Report\CsvReport;
use Yepr\Plugin\Task\LinkRepair\Resolve\LinkResolver;
use Yepr\Plugin\Task\LinkRepair\Resolve\MenuIndex;
use Yepr\Plugin\Task\LinkRepair\Run\Clock;
use Yepr\Plugin\Task\LinkRepair\Run\RunOutcome;
use Yepr\Plugin\Task\LinkRepair\Store\LinkRecord;
use Yepr\Plugin\Task\LinkRepair\Store\LinkStore;
use Yepr\Plugin\Task\LinkRepair\Store\Scan;
use Yepr\Plugin\Task\LinkRepair\Store\ScanStore;
use Yepr\Plugin\Task\LinkRepair\Url\InternalLinkFilter;
use Yepr\Plugin\Task\LinkRepair\Url\SiteAddress;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * One run of a scan: reads articles, categories and custom modules from where the last
 * run stopped, until they are all done or the time budget is used up.
 *
 * Items are read a page at a time, one kind after the other, and the position (kind and
 * id) is saved after every page, so a run that is cut off loses at most one page, which
 * the next run reads again. The links of an item are stored together, all or none.
 */
final class Scanner
{
	/**
	 * Items read per query.
	 */
	public const PAGE_SIZE = 20;

	/**
	 * @param   callable(string, string): void  $log  Receives a message and a priority.
	 */
	public function __construct(
		private readonly ContentSources $sources,
		private readonly ScanStore $scans,
		private readonly LinkStore $links,
		private readonly LinkExtractor $extractor,
		private readonly InternalLinkFilter $filter,
		private readonly LinkResolver $resolver,
		private readonly MenuIndex $menus,
		private readonly SiteAddress $site,
		private readonly CsvReport $report,
		private readonly Clock $clock,
		private readonly float $timeBudget,
		private $log
	) {
	}

	public function run(int $taskId): RunOutcome
	{
		$deadline = $this->clock->now() + $this->timeBudget;
		$scan     = $this->scans->running($taskId);

		if ($scan === null) {
			$scan = $this->scans->start($taskId);
			$this->log(\sprintf('Scan %d started.', $scan->id));
		} else {
			$this->log(\sprintf('Scan %d continues after %s %d.', $scan->id, $scan->cursorKind, $scan->cursorId));
		}

		if ($scan->cursorKind === '') {
			$scan->cursorKind = (string) $this->sources->first();
			$scan->cursorId   = 0;
		}

		while (true) {
			$page = $this->sources->get($scan->cursorKind)->page($scan->cursorId, self::PAGE_SIZE);

			if ($page === []) {
				$next = $this->sources->after($scan->cursorKind);

				if ($next === null) {
					return $this->finish($scan);
				}

				$scan->cursorKind = $next;
				$scan->cursorId   = 0;
				$this->scans->save($scan);

				continue;
			}

			foreach ($page as $item) {
				if ($this->clock->now() >= $deadline) {
					$this->scans->save($scan);
					$this->log(\sprintf('Scan %d: %d items so far; continues in the next run.', $scan->id, $scan->items));

					return new RunOutcome(false);
				}

				$scan->links += $this->scanItem($scan, $item);
				$scan->items++;
				$scan->cursorId = $item->id;
			}

			$this->scans->save($scan);
		}
	}

	/**
	 * @return  int  The number of internal links found.
	 */
	private function scanItem(Scan $scan, ContentItem $item): int
	{
		$path    = $this->menus->pathForItem($item->kind, $item->id);
		$pageUrl = $path === null ? '' : $this->site->absolute($path);
		$records = [];

		foreach ($item->fields as $field => $html) {
			foreach ($this->extractor->extract($html) as $link) {
				$internal = $this->filter->classify($link->href, $this->site);

				if ($internal === null) {
					continue;
				}

				$resolution = $this->resolver->resolve($internal);

				$records[] = new LinkRecord(
					0,
					$scan->id,
					$item->kind,
					$item->id,
					$item->title,
					$pageUrl,
					$field,
					$link->text,
					$link->href,
					$resolution->state,
					$resolution->finalUrl,
					$resolution->menuItem->id ?? 0,
					$resolution->menuItem->title ?? '',
					$resolution->newHref,
					$resolution->message,
					$item->hash()
				);
			}
		}

		$this->links->add($records);

		return \count($records);
	}

	private function finish(Scan $scan): RunOutcome
	{
		$scan->status = Scan::FINISHED;
		$this->scans->save($scan);

		$counts = $this->links->counts($scan->id);
		ksort($counts);

		$this->log(\sprintf(
			'Scan %d finished: %d items (articles, categories, custom modules), %d internal links (%s).',
			$scan->id,
			$scan->items,
			$scan->links,
			$counts === [] ? 'none' : implode(', ', array_map(
				static fn (string $state, int $n): string => $n . ' ' . $state,
				array_keys($counts),
				$counts
			))
		));

		try {
			$this->log('Report: ' . $this->report->write($scan));
		} catch (\RuntimeException $e) {
			$this->log($e->getMessage(), 'warning');
		}

		return new RunOutcome(true);
	}

	private function log(string $message, string $priority = 'info'): void
	{
		($this->log)($message, $priority);
	}
}
