<?php

/**
 * @package     LinkRepair
 *
 * @copyright   Copyright (C) 2026 Yepr, Herman Peeren. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

namespace Yepr\Plugin\Task\LinkRepair\Resolve;

use Joomla\Http\Http;
use Yepr\Plugin\Task\LinkRepair\Url\SiteAddress;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * RedirectFollower over HTTP, following the redirects by hand.
 *
 * By hand, because every hop is checked: a redirect may only lead to one of the
 * site's own hosts, so the plugin never sends a request anywhere else, whatever an
 * article or a redirect rule says. HEAD requests, so no page is downloaded; a server
 * that refuses HEAD gets a GET. The Http client must be made with automatic
 * redirects switched off (option follow_location = false); the service provider
 * does that.
 */
final class HttpRedirectFollower implements RedirectFollower
{
	public const MAX_REDIRECTS = 5;

	public function __construct(private readonly Http $http, private readonly int $timeout = 10)
	{
	}

	public function follow(string $url, SiteAddress $site): RedirectResult
	{
		$current = $url;

		for ($redirects = 0; $redirects <= self::MAX_REDIRECTS; $redirects++) {
			$host = strtolower((string) parse_url($current, PHP_URL_HOST));
			$port = parse_url($current, PHP_URL_PORT);

			if ($host === '' || !$site->isOwnHost($host . ($port ? ':' . $port : ''))) {
				return new RedirectResult(0, $current, $redirects, 'redirects to another site');
			}

			try {
				$response = $this->http->head($current, [], $this->timeout);

				if (\in_array($response->getStatusCode(), [405, 501], true)) {
					$response = $this->http->get($current, [], $this->timeout);
				}
			} catch (\Throwable $e) {
				return new RedirectResult(0, $current, $redirects, 'no answer: ' . $e->getMessage());
			}

			$status   = $response->getStatusCode();
			$location = $response->getHeaderLine('Location');

			if ($status < 300 || $status >= 400 || $location === '') {
				return new RedirectResult($status, $current, $redirects);
			}

			$current = $this->absolute($location, $current);
		}

		return new RedirectResult(0, $current, $redirects - 1, 'too many redirects');
	}

	/**
	 * A Location header made absolute against the URL that sent it.
	 */
	private function absolute(string $location, string $base): string
	{
		if (preg_match('#^https?://#i', $location)) {
			return $location;
		}

		$scheme = (string) parse_url($base, PHP_URL_SCHEME);
		$host   = (string) parse_url($base, PHP_URL_HOST);
		$port   = parse_url($base, PHP_URL_PORT);
		$origin = $scheme . '://' . $host . ($port ? ':' . $port : '');

		if (str_starts_with($location, '//')) {
			return $scheme . ':' . $location;
		}

		if (str_starts_with($location, '/')) {
			return $origin . $location;
		}

		$path = (string) parse_url($base, PHP_URL_PATH);

		return $origin . substr($path, 0, (int) strrpos($path, '/') + 1) . $location;
	}
}
