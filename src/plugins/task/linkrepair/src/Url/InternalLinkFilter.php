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
 * Decides whether a link points to a page of this site, and if so, which path.
 *
 * Left alone, because they are not typed page links or not this site's:
 * empty links and #anchors; mailto:, tel:, javascript: and any other scheme
 * than http(s); links to other hosts; links that are Joomla links already
 * ("index.php?..."); and links to files.
 *
 * A path without a leading slash counts from the site root, not from the page it
 * is on: Joomla's SEF plugin makes such links absolute that way when the page
 * is shown.
 */
final class InternalLinkFilter
{
	/**
	 * Extensions of pages rather than files. A path ending in anything else with a
	 * dot in its last segment is taken for a file.
	 */
	private const PAGE_EXTENSIONS = ['html', 'htm', 'php'];

	public function classify(string $href, SiteAddress $site): ?InternalLink
	{
		$href     = trim($href);
		$original = $href;

		if ($href === '' || $href[0] === '#' || $href[0] === '?') {
			return null;
		}

		if (str_starts_with($href, '//')) {
			$href = $site->scheme . ':' . $href;
		}

		if (preg_match('#^([a-z][a-z0-9+.\-]*):#i', $href, $scheme)) {
			if (!\in_array(strtolower($scheme[1]), ['http', 'https'], true)) {
				return null;
			}

			$host = (string) parse_url($href, PHP_URL_HOST);
			$port = parse_url($href, PHP_URL_PORT);

			if ($host === '' || !$site->isOwnHost($host . ($port ? ':' . $port : ''))) {
				return null;
			}
		}

		$parts = parse_url($href);

		if ($parts === false) {
			return null;
		}

		$path     = (string) ($parts['path'] ?? '');
		$query    = (string) ($parts['query'] ?? '');
		$fragment = (string) ($parts['fragment'] ?? '');

		if ($path !== '' && $path[0] === '/') {
			// An absolute path has to be inside the site's folder to be one of its pages.
			if ($site->basePath !== '') {
				if ($path !== $site->basePath && !str_starts_with($path, $site->basePath . '/')) {
					return null;
				}

				$path = substr($path, \strlen($site->basePath));
			}
		}

		$path = trim($path, '/');

		// "index.php?option=..." is a Joomla link already; "index.php/some/page" is a
		// SEF URL on a site without URL rewriting.
		if ($path === 'index.php') {
			if ($query !== '') {
				return null;
			}

			$path = '';
		} elseif (str_starts_with($path, 'index.php/')) {
			$path = trim(substr($path, \strlen('index.php/')), '/');
		}

		$last = (string) strrchr('/' . $path, '/');

		if (str_contains($last, '.')) {
			$extension = strtolower((string) pathinfo($last, PATHINFO_EXTENSION));

			if (!\in_array($extension, self::PAGE_EXTENSIONS, true)) {
				return null;
			}

			if ($extension === 'html' || $extension === 'htm') {
				$path = substr($path, 0, -(\strlen($extension) + 1));
			}
		}

		return new InternalLink($original, rawurldecode($path), $query, $fragment);
	}
}
