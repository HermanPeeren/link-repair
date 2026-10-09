<?php

/**
 * @package     LinkRepair
 *
 * @copyright   Copyright (C) 2026 Yepr, Herman Peeren. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

namespace Yepr\Plugin\Task\LinkRepair\Resolve;

use Yepr\Plugin\Task\LinkRepair\Url\InternalLink;
use Yepr\Plugin\Task\LinkRepair\Url\InternalLinkFilter;
use Yepr\Plugin\Task\LinkRepair\Url\SiteAddress;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Works out which menu item an internal link means.
 *
 * First by its path, among the menu items' routes. When that finds nothing, by
 * asking the site where the link ends up (its redirects), and looking that up.
 * Answers are remembered for the rest of the run: the same link in fifty articles
 * costs one HTTP request.
 */
final class LinkResolver
{
	/**
	 * @var  array<string, Resolution>
	 */
	private array $resolved = [];

	public function __construct(
		private readonly MenuIndex $menus,
		private readonly ?RedirectFollower $redirects,
		private readonly InternalLinkFilter $filter,
		private readonly SiteAddress $site
	) {
	}

	public function resolve(InternalLink $link): Resolution
	{
		$key = $link->path . '?' . $link->query . '#' . $link->fragment;

		return $this->resolved[$key] ??= $this->work($link);
	}

	private function work(InternalLink $link): Resolution
	{
		if ($link->hasQuery()) {
			return new Resolution(LinkState::QUERY, message: 'has a query string; check by hand');
		}

		$item = $this->lookup($link->path);

		if ($item !== null) {
			return new Resolution(LinkState::REPAIRABLE, $item, $item->href($link->fragment), $this->site->absolute($link->path));
		}

		if ($this->redirects === null) {
			return new Resolution(LinkState::UNMATCHED, message: 'no menu item has this path');
		}

		$encoded = implode('/', array_map('rawurlencode', explode('/', $link->path)));
		$result  = $this->redirects->follow($this->site->absolute($encoded), $this->site);

		if (!$result->isPage()) {
			$why = $result->status > 0 ? 'HTTP ' . $result->status : $result->message;

			return new Resolution(LinkState::BROKEN, finalUrl: $result->finalUrl, message: $why);
		}

		$final = $this->filter->classify($result->finalUrl, $this->site);
		$item  = $final === null || $final->hasQuery() ? null : $this->lookup($final->path);

		if ($item === null) {
			$how = $result->redirects > 0 ? 'redirects to a page that is no menu item' : 'the page is no menu item';

			return new Resolution(LinkState::UNMATCHED, finalUrl: $result->finalUrl, message: $how);
		}

		$how = $result->redirects > 0 ? \sprintf('via %d redirect(s)', $result->redirects) : '';

		return new Resolution(LinkState::REPAIRABLE, $item, $item->href($link->fragment), $result->finalUrl, $how);
	}

	/**
	 * The menu item for a path: the path as it is, or after a language prefix ("en/...").
	 */
	private function lookup(string $path): ?MenuItem
	{
		if ($path !== '' && ($item = $this->menus->find($path)) !== null) {
			return $item;
		}

		$segments = $path === '' ? [] : explode('/', $path);
		$language = null;

		if ($segments !== [] && ($code = $this->menus->languageForPrefix($segments[0])) !== null) {
			$language = $code;
			array_shift($segments);
		}

		if ($segments === []) {
			return $this->menus->home($language);
		}

		return $this->menus->find(implode('/', $segments), $language);
	}
}
