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
 * Where the site lives: its address, and the host names that count as its own.
 *
 * A task run from the command line cannot ask the web server for the site's
 * address, so it is a task parameter.
 */
final class SiteAddress
{
	/**
	 * @param   string        $scheme    'http' or 'https'.
	 * @param   string        $host      The main host name, lower case, with ":port" if not standard.
	 * @param   string        $basePath  The folder the site lives in: '' or '/folder', no trailing slash.
	 * @param   list<string>  $hosts     Every host name that counts as this site, lower case, the main one included.
	 */
	private function __construct(
		public readonly string $scheme,
		public readonly string $host,
		public readonly string $basePath,
		public readonly array $hosts
	) {
	}

	/**
	 * @param   string        $siteUrl     For example "https://guide.joomla.org" or "http://localhost/site".
	 * @param   list<string>  $otherHosts  More host names that are the same site ("www.example.org").
	 *
	 * @throws  \InvalidArgumentException  When the address is not an http(s) URL with a host.
	 */
	public static function fromUrl(string $siteUrl, array $otherHosts = []): self
	{
		$parts  = parse_url(trim($siteUrl));
		$scheme = strtolower((string) ($parts['scheme'] ?? ''));
		$host   = strtolower((string) ($parts['host'] ?? ''));

		if (!\in_array($scheme, ['http', 'https'], true) || $host === '') {
			throw new \InvalidArgumentException('The site address must be an http or https URL with a host name.');
		}

		if (isset($parts['port'])) {
			$host .= ':' . $parts['port'];
		}

		$basePath = rtrim((string) ($parts['path'] ?? ''), '/');

		if (str_ends_with($basePath, '/index.php')) {
			$basePath = substr($basePath, 0, -\strlen('/index.php'));
		}

		$hosts = [$host];

		foreach ($otherHosts as $other) {
			$other = strtolower(trim($other));

			if ($other !== '' && !\in_array($other, $hosts, true)) {
				$hosts[] = $other;
			}
		}

		return new self($scheme, $host, $basePath, $hosts);
	}

	public function isOwnHost(string $host): bool
	{
		return \in_array(strtolower($host), $this->hosts, true);
	}

	/**
	 * The absolute URL of a path below the site root ('' is the root itself).
	 */
	public function absolute(string $path): string
	{
		return $this->scheme . '://' . $this->host . $this->basePath . '/' . ltrim($path, '/');
	}
}
