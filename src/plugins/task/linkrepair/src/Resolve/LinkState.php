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
 * What became of a link: the first four come from a scan, the last three from a repair.
 */
final class LinkState
{
	/** A menu item was found; repair can rewrite the link. */
	public const REPAIRABLE = 'repairable';

	/** The link has a query string, which may mean more than the menu item alone. */
	public const QUERY = 'query';

	/** The page exists, but is no menu item (an article in a category blog, say). */
	public const UNMATCHED = 'unmatched';

	/** The link leads nowhere: an error status, no answer, or a redirect off the site. */
	public const BROKEN = 'broken';

	/** Rewritten into a menu item link. */
	public const REPAIRED = 'repaired';

	/** Left alone this time; the message says why (checked out, changed since the scan). */
	public const SKIPPED = 'skipped';

	/** Repair tried and did not succeed; the message says why. */
	public const FAILED = 'failed';
}
