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
 */
interface LinkStore
{
	/**
	 * Stores the links of one article, all or none.
	 *
	 * @param   list<LinkRecord>  $records  Records with id 0.
	 */
	public function add(array $records): void;

	/**
	 * The next article after $afterArticleId with a link in the given state.
	 */
	public function nextArticle(int $scanId, string $state, int $afterArticleId): ?int;

	/**
	 * @return  list<LinkRecord>
	 */
	public function forArticle(int $scanId, int $articleId, string $state): array;

	public function mark(int $id, string $state, string $message): void;

	/**
	 * All links of a scan, by article, read a page at a time.
	 *
	 * @return  iterable<LinkRecord>
	 */
	public function all(int $scanId): iterable;

	/**
	 * @return  array<string, int>  Number of links by state.
	 */
	public function counts(int $scanId): array;
}
