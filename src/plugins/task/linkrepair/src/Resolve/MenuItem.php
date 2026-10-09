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
 * A published site menu item, as far as linking to it is concerned.
 */
final class MenuItem
{
	/**
	 * @param   string  $language  A language code ("en-GB"), or "*" for all languages.
	 */
	public function __construct(
		public readonly int $id,
		public readonly string $title,
		public readonly string $path,
		public readonly string $language
	) {
	}

	/**
	 * The link the editor's "CMS Content > Menu" button inserts for this item.
	 */
	public function href(string $fragment = ''): string
	{
		$href = 'index.php?Itemid=' . $this->id;

		if ($this->language !== '*' && $this->language !== '') {
			$href .= '&lang=' . $this->language;
		}

		return $fragment === '' ? $href : $href . '#' . $fragment;
	}
}
