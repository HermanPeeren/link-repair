<?php

/**
 * @package     LinkRepair
 *
 * @copyright   Copyright (C) 2026 Yepr, Herman Peeren. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

namespace Yepr\Plugin\Task\LinkRepair\Store;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Keeps the scans.
 */
interface ScanStore
{
	/**
	 * The scan this task has not finished yet, if any.
	 */
	public function running(int $taskId): ?Scan;

	/**
	 * Starts a new scan for this task. Scans older than the latest finished one are
	 * removed, with their links: only the latest result and the one in progress stay.
	 */
	public function start(int $taskId): Scan;

	public function save(Scan $scan): void;

	/**
	 * The most recently finished scan of any task: what a repair works from.
	 */
	public function latestFinished(): ?Scan;
}
