<?php

/**
 * @package     LinkRepair
 *
 * @copyright   Copyright (C) 2026 Yepr, Herman Peeren. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

namespace Yepr\Plugin\Task\LinkRepair\Resolve;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * What a link turned out to mean: a LinkState, and for a repairable link the menu
 * item and the link to put in its place.
 */
final class Resolution
{
	public function __construct(
		public readonly string $state,
		public readonly ?MenuItem $menuItem = null,
		public readonly string $newHref = '',
		public readonly string $finalUrl = '',
		public readonly string $message = ''
	) {
	}
}
