<?php

/**
 * @package     LinkRepair
 *
 * @copyright   Copyright (C) 2026 Yepr, Herman Peeren. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

namespace Yepr\Plugin\Task\LinkRepair\Repair;

use Yepr\Plugin\Task\LinkRepair\Content\Article;
use Yepr\Plugin\Task\LinkRepair\Content\ArticleGateway;
use Yepr\Plugin\Task\LinkRepair\Html\LinkRewriter;
use Yepr\Plugin\Task\LinkRepair\Report\CsvReport;
use Yepr\Plugin\Task\LinkRepair\Resolve\LinkState;
use Yepr\Plugin\Task\LinkRepair\Run\Clock;
use Yepr\Plugin\Task\LinkRepair\Run\RunOutcome;
use Yepr\Plugin\Task\LinkRepair\Store\LinkRecord;
use Yepr\Plugin\Task\LinkRepair\Store\LinkStore;
use Yepr\Plugin\Task\LinkRepair\Store\Scan;
use Yepr\Plugin\Task\LinkRepair\Store\ScanStore;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * One run of a repair: rewrites the repairable links of the latest finished scan,
 * article by article, until they are all done or the time budget is used up.
 *
 * An article is only changed when its text is still what the scan saw and nobody
 * has it open. A dry run goes through the same steps and logs what it would change,
 * without saving or marking anything.
 */
final class Repairer
{
	/**
	 * @param   callable(string, string): void  $log  Receives a message and a priority.
	 */
	public function __construct(
		private readonly ArticleGateway $articles,
		private readonly ScanStore $scans,
		private readonly LinkStore $links,
		private readonly LinkRewriter $rewriter,
		private readonly CsvReport $report,
		private readonly Clock $clock,
		private readonly RepairSettings $settings,
		private $log
	) {
	}

	public function run(): RunOutcome
	{
		$deadline = $this->clock->now() + $this->settings->timeBudget;
		$scan     = $this->scans->latestFinished();

		if ($scan === null) {
			$this->log('There is no finished scan to repair from. Run a "Link repair: scan" task first.', 'warning');

			return new RunOutcome(true);
		}

		if ($scan->repairStatus !== Scan::REPAIR_RUNNING) {
			$scan->repairStatus = Scan::REPAIR_RUNNING;
			$scan->repairCursor = 0;
			$this->scans->save($scan);
			$this->log(\sprintf('Repair of scan %d started%s.', $scan->id, $this->settings->dryRun ? ' (dry run: nothing is saved)' : ''));
		}

		while (true) {
			if ($this->clock->now() >= $deadline) {
				$this->log(\sprintf('Repair of scan %d continues after article %d in the next run.', $scan->id, $scan->repairCursor));

				return new RunOutcome(false);
			}

			$articleId = $this->links->nextArticle($scan->id, LinkState::REPAIRABLE, $scan->repairCursor);

			if ($articleId === null) {
				return $this->finish($scan);
			}

			$this->repairArticle($scan, $articleId);

			$scan->repairCursor = $articleId;
			$this->scans->save($scan);
		}
	}

	private function repairArticle(Scan $scan, int $articleId): void
	{
		$records = $this->links->forArticle($scan->id, $articleId, LinkState::REPAIRABLE);
		$article = $this->articles->load($articleId);

		if ($article === null) {
			$this->markAll($records, LinkState::FAILED, 'the article no longer exists');

			return;
		}

		if ($article->checkedOut > 0) {
			$this->markAll($records, LinkState::SKIPPED, 'the article is checked out; run the repair again when it is closed');

			return;
		}

		if ($records !== [] && $article->hash() !== $records[0]->articleHash) {
			$this->markAll($records, LinkState::SKIPPED, 'the article changed since the scan; scan again');

			return;
		}

		[$texts, $replaced] = $this->rewrite($article, $records);

		$repaired = array_filter($records, static fn (LinkRecord $record): bool => $replaced[$record->id]);
		$missing  = array_filter($records, static fn (LinkRecord $record): bool => !$replaced[$record->id]);

		$this->markAll($missing, LinkState::FAILED, 'the link was not found in the text');

		if ($repaired === []) {
			return;
		}

		if ($this->settings->dryRun) {
			foreach ($repaired as $record) {
				$this->log(\sprintf('Would change in article %d "%s": %s -> %s', $article->id, $article->title, $record->href, $record->newHref));
			}

			return;
		}

		$note = \sprintf('Link repair: %d link(s) now point to menu items', \count($repaired));

		try {
			$this->articles->save($article, $texts['introtext'], $texts['fulltext'], $this->settings->userId, $note);
		} catch (\RuntimeException $e) {
			$this->markAll($repaired, LinkState::FAILED, 'saving failed: ' . $e->getMessage());
			$this->log(\sprintf('Article %d "%s" could not be saved: %s', $article->id, $article->title, $e->getMessage()), 'warning');

			return;
		}

		foreach ($repaired as $record) {
			$this->links->mark($record->id, LinkState::REPAIRED, 'now links to menu item "' . $record->menuTitle . '"');
		}

		$this->log(\sprintf('Article %d "%s": %d link(s) repaired.', $article->id, $article->title, \count($repaired)));
	}

	/**
	 * Rewrites the records' links in the article's text.
	 *
	 * The same link can be in an article more than once, and then has a record for
	 * each; the first rewrite replaces them all, so the rest count as done too.
	 *
	 * @param   list<LinkRecord>  $records
	 *
	 * @return  array{0: array<string, string>, 1: array<int, bool>}  The new texts by
	 *          field, and per record id whether its link was replaced.
	 */
	private function rewrite(Article $article, array $records): array
	{
		$texts    = ['introtext' => $article->introtext, 'fulltext' => $article->fulltext];
		$replaced = [];
		$done     = [];

		foreach ($records as $record) {
			$field = $record->field === 'fulltext' ? 'fulltext' : 'introtext';
			$key   = $field . "\0" . $record->href;

			if (!isset($done[$key])) {
				[$texts[$field], $count] = $this->rewriter->rewrite($texts[$field], $record->href, $record->newHref);
				$done[$key]              = $count > 0;
			}

			$replaced[$record->id] = $done[$key];
		}

		return [$texts, $replaced];
	}

	/**
	 * @param   array<LinkRecord>  $records
	 */
	private function markAll(array $records, string $state, string $message): void
	{
		if ($records === []) {
			return;
		}

		if ($this->settings->dryRun) {
			$first = reset($records);
			$this->log(\sprintf(
				'Article %d "%s": %d link(s) would be %s: %s',
				$first->articleId,
				$first->articleTitle,
				\count($records),
				$state,
				$message
			));

			return;
		}

		foreach ($records as $record) {
			$this->links->mark($record->id, $state, $message);
		}
	}

	private function finish(Scan $scan): RunOutcome
	{
		$scan->repairStatus = Scan::REPAIR_IDLE;
		$scan->repairCursor = 0;
		$this->scans->save($scan);

		$counts = $this->links->counts($scan->id);
		ksort($counts);

		$this->log(\sprintf(
			'Repair of scan %d finished%s. Links by state: %s.',
			$scan->id,
			$this->settings->dryRun ? ' (dry run)' : '',
			$counts === [] ? 'none' : implode(', ', array_map(
				static fn (string $state, int $n): string => $n . ' ' . $state,
				array_keys($counts),
				$counts
			))
		));

		if (!$this->settings->dryRun) {
			try {
				$this->log('Report: ' . $this->report->write($scan));
			} catch (\RuntimeException $e) {
				$this->log($e->getMessage(), 'warning');
			}
		}

		return new RunOutcome(true);
	}

	private function log(string $message, string $priority = 'info'): void
	{
		($this->log)($message, $priority);
	}
}
