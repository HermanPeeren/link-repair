<?php

/**
 * @package     LinkRepair
 *
 * @copyright   Copyright (C) 2026 Yepr, Herman Peeren. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

namespace Yepr\Plugin\Task\LinkRepair\Repair;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * A repair task's parameters.
 */
final class RepairSettings
{
	/**
	 * @param   bool   $dryRun      Log what would change, save nothing.
	 * @param   int    $userId      The user the changes are recorded for; 0 for none.
	 * @param   float  $timeBudget  Seconds per run.
	 */
	public function __construct(
		public readonly bool $dryRun,
		public readonly int $userId,
		public readonly float $timeBudget
	) {
	}
}
