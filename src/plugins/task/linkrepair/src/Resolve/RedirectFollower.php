<?php

/**
 * @package     LinkRepair
 *
 * @copyright   Copyright (C) 2026 Yepr, Herman Peeren. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

namespace Yepr\Plugin\Task\LinkRepair\Resolve;

use Yepr\Plugin\Task\LinkRepair\Url\SiteAddress;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Asks the site where a URL ends up, following its redirects.
 */
interface RedirectFollower
{
	/**
	 * @param   string       $url   An absolute URL on one of the site's own hosts.
	 * @param   SiteAddress  $site  Which hosts the redirects may lead to.
	 */
	public function follow(string $url, SiteAddress $site): RedirectResult;
}
