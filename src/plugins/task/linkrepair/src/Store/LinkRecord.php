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
 * One link found by a scan, in an article, a category or a custom module, and what
 * became of it.
 */
final class LinkRecord
{
	public function __construct(
		public readonly int $id,
		public readonly int $scanId,
		public readonly string $itemKind,
		public readonly int $itemId,
		public readonly string $itemTitle,
		public readonly string $pageUrl,
		public readonly string $field,
		public readonly string $linkText,
		public readonly string $href,
		public readonly string $state,
		public readonly string $finalUrl,
		public readonly int $menuId,
		public readonly string $menuTitle,
		public readonly string $newHref,
		public readonly string $message,
		public readonly string $itemHash
	) {
	}
}
