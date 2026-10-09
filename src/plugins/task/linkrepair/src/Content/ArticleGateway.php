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
 * Reads articles a page at a time, and saves changed text.
 */
interface ArticleGateway
{
	/**
	 * Articles with an id above $afterId, by id; trashed articles left out.
	 *
	 * @return  list<Article>
	 */
	public function page(int $afterId, int $limit): array;

	public function load(int $id): ?Article;

	/**
	 * Saves new text for an article the way the article editor does, so that the
	 * change is in the article's version history.
	 *
	 * @param   int     $userId  The user the change is recorded for; 0 for none.
	 * @param   string  $note    The version note.
	 *
	 * @throws  \RuntimeException  When the article could not be saved.
	 */
	public function save(Article $article, string $introtext, string $fulltext, int $userId, string $note): void;
}
