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
 * Where following a link's redirects ended.
 *
 * Status 0 means there was no answer: a network error or a time-out, a redirect
 * to another site, or too many redirects. The message says which.
 */
final class RedirectResult
{
	public function __construct(
		public readonly int $status,
		public readonly string $finalUrl,
		public readonly int $redirects,
		public readonly string $message = ''
	) {
	}

	public function isPage(): bool
	{
		return $this->status >= 200 && $this->status < 300;
	}
}
