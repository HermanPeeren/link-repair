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
 * One scan, and where scanning and repairing it have got to.
 *
 * Mutable on purpose: a run moves the cursors on and saves the scan again.
 */
final class Scan
{
	public const RUNNING  = 'running';
	public const FINISHED = 'finished';

	public const REPAIR_IDLE    = 'idle';
	public const REPAIR_RUNNING = 'running';

	public function __construct(
		public readonly int $id,
		public readonly int $taskId,
		public string $status = self::RUNNING,
		public int $cursorId = 0,
		public int $articles = 0,
		public int $links = 0,
		public string $repairStatus = self::REPAIR_IDLE,
		public int $repairCursor = 0
	) {
	}
}
