<?php

/**
 * @package     LinkRepair
 *
 * @copyright   Copyright (C) 2026 Yepr, Herman Peeren. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

namespace Yepr\Plugin\Task\LinkRepair\Content;

use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Joomla\Database\QueryInterface;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Reading for a ContentSource whose items are rows of one table: a page by id, or one
 * row. The subclass says which table, which HTML columns and which rows.
 */
abstract class TableSource implements ContentSource
{
	/**
	 * The state of a trashed item, in every core table.
	 */
	protected const TRASHED = -2;

	public function __construct(
		protected readonly DatabaseInterface $db,
		protected readonly AdminModelSaver $saver
	) {
	}

	/**
	 * The table, for example "#__content".
	 */
	abstract protected function table(): string;

	/**
	 * The column that holds the item's state (published, trashed).
	 */
	abstract protected function stateColumn(): string;

	/**
	 * The HTML columns, in a fixed order.
	 *
	 * @return  list<string>
	 */
	abstract protected function fields(): array;

	/**
	 * Narrows the query to the rows of this kind (custom modules only, say).
	 */
	protected function restrict(QueryInterface $query): QueryInterface
	{
		return $query;
	}

	public function page(int $afterId, int $limit): array
	{
		$query = $this->select()
			->where($this->db->quoteName('id') . ' > :afterId')
			->order($this->db->quoteName('id'))
			->bind(':afterId', $afterId, ParameterType::INTEGER);

		$items = [];

		foreach ($this->db->setQuery($query, 0, max(1, $limit))->loadObjectList() as $row) {
			$items[] = $this->item($row);
		}

		return $items;
	}

	public function load(int $id): ?ContentItem
	{
		$query = $this->select()
			->where($this->db->quoteName('id') . ' = :id')
			->bind(':id', $id, ParameterType::INTEGER);

		$row = $this->db->setQuery($query)->loadObject();

		return $row === null ? null : $this->item($row);
	}

	/**
	 * One column of one row, by id: what a save needs besides the text.
	 */
	protected function column(int $id, string $column): string
	{
		$query = $this->db->getQuery(true)
			->select($this->db->quoteName($column))
			->from($this->db->quoteName($this->table()))
			->where($this->db->quoteName('id') . ' = :id')
			->bind(':id', $id, ParameterType::INTEGER);

		return (string) $this->db->setQuery($query)->loadResult();
	}

	private function select(): QueryInterface
	{
		$trashed = self::TRASHED;

		$query = $this->db->getQuery(true)
			->select($this->db->quoteName(array_merge(['id', 'title', 'checked_out'], $this->fields())))
			->from($this->db->quoteName($this->table()))
			->where($this->db->quoteName($this->stateColumn()) . ' <> :trashed')
			->bind(':trashed', $trashed, ParameterType::INTEGER);

		return $this->restrict($query);
	}

	private function item(\stdClass $row): ContentItem
	{
		$fields = [];

		foreach ($this->fields() as $field) {
			$fields[$field] = (string) $row->{$field};
		}

		return new ContentItem($this->kind(), (int) $row->id, (string) $row->title, $fields, (int) $row->checked_out);
	}
}
