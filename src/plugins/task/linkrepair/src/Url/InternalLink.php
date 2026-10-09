<?php

/**
 * @package     LinkRepair
 *
 * @copyright   Copyright (C) 2026 Yepr, Herman Peeren. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

namespace Yepr\Plugin\Task\LinkRepair\Url;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * A link that points into this site, taken apart.
 *
 * The path is relative to the site root, without leading or trailing slash,
 * without "index.php/" and without a ".html" suffix: the form Joomla keeps in
 * `#__menu.path`. The root itself is ''.
 */
final class InternalLink
{
	public function __construct(
		public readonly string $href,
		public readonly string $path,
		public readonly string $query,
		public readonly string $fragment
	) {
	}

	public function hasQuery(): bool
	{
		return $this->query !== '';
	}
}
