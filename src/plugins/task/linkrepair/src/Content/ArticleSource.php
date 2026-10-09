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
 * Articles: their intro text and full text, saved through com_content's ArticleModel.
 */
final class ArticleSource extends TableSource
{
	public function kind(): string
	{
		return ContentItem::ARTICLE;
	}

	protected function table(): string
	{
		return '#__content';
	}

	protected function stateColumn(): string
	{
		return 'state';
	}

	protected function fields(): array
	{
		return ['introtext', 'fulltext'];
	}

	public function save(ContentItem $item, array $fields, int $userId, string $note): void
	{
		// The model reads the category on every save, so it has to be sent along.
		$this->saver->save('com_content', 'Article', [
			'id'           => $item->id,
			'catid'        => (int) $this->column($item->id, 'catid'),
			'introtext'    => $fields['introtext'] ?? $item->fields['introtext'],
			'fulltext'     => $fields['fulltext'] ?? $item->fields['fulltext'],
			'version_note' => $note,
		], $userId);
	}
}
