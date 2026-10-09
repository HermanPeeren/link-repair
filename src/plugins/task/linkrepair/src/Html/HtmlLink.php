<?php

/**
 * @package     LinkRepair
 *
 * @copyright   Copyright (C) 2026 Yepr, Herman Peeren. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

namespace Yepr\Plugin\Task\LinkRepair\Html;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * One `<a href>` as found in an article: the link with HTML entities decoded
 * (`&amp;` is `&` here), and the text a reader sees.
 */
final class HtmlLink
{
	public function __construct(
		public readonly string $href,
		public readonly string $text
	) {
	}
}
