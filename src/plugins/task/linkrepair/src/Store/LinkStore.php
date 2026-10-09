<?php

/**
 * @package     LinkRepair
 *
 * @copyright   Copyright (C) 2026 Yepr, Herman Peeren. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

namespace Yepr\Plugin\Task\LinkRepair\Store;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Keeps the links a scan found.
 *
 * Items are ordered by kind name, then id: the order the scan reads them in.
 */
interface LinkStore
{
	/**
	 * Stores the links of one item, all or none.
	 *
	 * @param   list<LinkRecord>  $records  Records with id 0.
	 */
	public function add(array $records): void;

	/**
	 * The next item after ($afterKind, $afterId) with a link in the given state.
	 *
	 * @return  array{0: string, 1: int}|null  Its kind and id.
	 */
	public function nextItem(int $scanId, string $state, string $afterKind, int $afterId): ?array;

	/**
	 * @return  list<LinkRecord>
	 */
	public function forItem(int $scanId, string $kind, int $itemId, string $state): array;

	public function mark(int $id, string $state, string $message): void;

	/**
	 * All links of a scan, read a page at a time.
	 *
	 * @return  iterable<LinkRecord>
	 */
	public function all(int $scanId): iterable;

	/**
	 * @return  array<string, int>  Number of links by state.
	 */
	public function counts(int $scanId): array;
}
