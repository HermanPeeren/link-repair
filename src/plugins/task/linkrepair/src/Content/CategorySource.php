<?php

/**
 * @package     LinkRepair
 *
 * @copyright   Copyright (C) 2026 Yepr, Herman Peeren. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

namespace Yepr\Plugin\Task\LinkRepair\Content;

use Joomla\CMS\MVC\Model\AdminModel;
use Joomla\Database\ParameterType;
use Joomla\Database\QueryInterface;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Categories of every component: their description, saved through com_categories'
 * CategoryModel.
 */
final class CategorySource extends TableSource
{
	/**
	 * The id of the root of the category tree, which is no category.
	 */
	private const ROOT = 1;

	public function kind(): string
	{
		return ContentItem::CATEGORY;
	}

	protected function table(): string
	{
		return '#__categories';
	}

	protected function stateColumn(): string
	{
		return 'published';
	}

	protected function fields(): array
	{
		return ['description'];
	}

	protected function restrict(QueryInterface $query): QueryInterface
	{
		$root = self::ROOT;

		return $query->where($this->db->quoteName('id') . ' <> :root')
			->bind(':root', $root, ParameterType::INTEGER);
	}

	public function save(ContentItem $item, array $fields, int $userId, string $note): void
	{
		$extension = $this->column($item->id, 'extension');

		$this->saver->save(
			'com_categories',
			'Category',
			[
				'id'           => $item->id,
				'extension'    => $extension,
				'description'  => $fields['description'] ?? $item->fields['description'],
				'version_note' => $note,
			],
			$userId,
			// The model takes the component the category belongs to from the request,
			// which a task does not have; its versions are filed under it.
			static function (AdminModel $model) use ($extension): void {
				$model->setState('category.extension', $extension);
				$model->typeAlias = $extension . '.category';
			}
		);
	}
}
