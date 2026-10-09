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
 * The site's published menu items, looked up by the path of their URL.
 */
interface MenuIndex
{
	/**
	 * The menu item with this route ("user-guide/seo"), preferring one in the given
	 * language, then one for all languages.
	 */
	public function find(string $path, ?string $language = null): ?MenuItem;

	/**
	 * The home menu item, for the given language if there is one.
	 */
	public function home(?string $language = null): ?MenuItem;

	/**
	 * The language a URL prefix stands for on a multilingual site ("en" => "en-GB").
	 */
	public function languageForPrefix(string $prefix): ?string;

	/**
	 * The route of a menu item that shows this one article, if there is one.
	 */
	public function pathForArticle(int $articleId): ?string;
}
