<?php

/**
 * @package     LinkRepair
 *
 * @copyright   Copyright (C) 2026 Yepr, Herman Peeren. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

namespace Yepr\Plugin\Task\LinkRepair\Content;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * One kind of content: reads its items a page at a time, and saves changed text the
 * way its own editor does.
 */
interface ContentSource
{
	/**
	 * The kind of content: one of the ContentItem constants.
	 */
	public function kind(): string;

	/**
	 * Items with an id above $afterId, by id; trashed items left out.
	 *
	 * @return  list<ContentItem>
	 */
	public function page(int $afterId, int $limit): array;

	public function load(int $id): ?ContentItem;

	/**
	 * Saves new text for an item through its component's model, so that the change has
	 * a version in the item's history.
	 *
	 * @param   array<string, string>  $fields  The new HTML fields, by column name.
	 * @param   int                    $userId  The user the change is recorded for; 0 for none.
	 * @param   string                 $note    The version note.
	 *
	 * @throws  \RuntimeException  When the item could not be saved.
	 */
	public function save(ContentItem $item, array $fields, int $userId, string $note): void;
}
