<?php

/**
 * @package     LinkRepair
 *
 * @copyright   Copyright (C) 2026 Yepr, Herman Peeren. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

namespace Yepr\Plugin\Task\LinkRepair\Store;

use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * LinkStore on `#__linkrepair_links`.
 */
final class DatabaseLinkStore implements LinkStore
{
	private const TABLE = '#__linkrepair_links';

	private const COLUMNS = [
		'id', 'scan_id', 'article_id', 'article_title', 'page_url', 'field', 'link_text', 'href',
		'state', 'final_url', 'menu_id', 'menu_title', 'new_href', 'message', 'article_hash',
	];

	/**
	 * Rows read at a time by all().
	 */
	private const PAGE = 500;

	public function __construct(private readonly DatabaseInterface $db)
	{
	}

	public function add(array $records): void
	{
		if ($records === []) {
			return;
		}

		$this->db->transactionStart();

		try {
			foreach ($records as $record) {
				$this->insert($record);
			}

			$this->db->transactionCommit();
		} catch (\Throwable $e) {
			$this->db->transactionRollback();

			throw $e;
		}
	}

	public function nextArticle(int $scanId, string $state, int $afterArticleId): ?int
	{
		$query = $this->db->getQuery(true)
			->select('MIN(' . $this->db->quoteName('article_id') . ')')
			->from($this->db->quoteName(self::TABLE))
			->where($this->db->quoteName('scan_id') . ' = :scanId')
			->where($this->db->quoteName('state') . ' = :state')
			->where($this->db->quoteName('article_id') . ' > :after')
			->bind(':scanId', $scanId, ParameterType::INTEGER)
			->bind(':state', $state)
			->bind(':after', $afterArticleId, ParameterType::INTEGER);

		$id = $this->db->setQuery($query)->loadResult();

		return $id === null ? null : (int) $id;
	}

	public function forArticle(int $scanId, int $articleId, string $state): array
	{
		$query = $this->db->getQuery(true)
			->select($this->db->quoteName(self::COLUMNS))
			->from($this->db->quoteName(self::TABLE))
			->where($this->db->quoteName('scan_id') . ' = :scanId')
			->where($this->db->quoteName('article_id') . ' = :articleId')
			->where($this->db->quoteName('state') . ' = :state')
			->order($this->db->quoteName('id'))
			->bind(':scanId', $scanId, ParameterType::INTEGER)
			->bind(':articleId', $articleId, ParameterType::INTEGER)
			->bind(':state', $state);

		return array_values(array_map($this->record(...), $this->db->setQuery($query)->loadObjectList()));
	}

	public function mark(int $id, string $state, string $message): void
	{
		$message  = mb_substr($message, 0, 1024);
		$modified = gmdate('Y-m-d H:i:s');

		$query = $this->db->getQuery(true)
			->update($this->db->quoteName(self::TABLE))
			->set($this->db->quoteName('state') . ' = :state')
			->set($this->db->quoteName('message') . ' = :message')
			->set($this->db->quoteName('modified') . ' = :modified')
			->where($this->db->quoteName('id') . ' = :id')
			->bind(':state', $state)
			->bind(':message', $message)
			->bind(':modified', $modified)
			->bind(':id', $id, ParameterType::INTEGER);

		$this->db->setQuery($query)->execute();
	}

	public function all(int $scanId): iterable
	{
		$afterId = 0;

		do {
			$query = $this->db->getQuery(true)
				->select($this->db->quoteName(self::COLUMNS))
				->from($this->db->quoteName(self::TABLE))
				->where($this->db->quoteName('scan_id') . ' = :scanId')
				->where($this->db->quoteName('id') . ' > :afterId')
				->order($this->db->quoteName('id'))
				->bind(':scanId', $scanId, ParameterType::INTEGER)
				->bind(':afterId', $afterId, ParameterType::INTEGER);

			$rows = $this->db->setQuery($query, 0, self::PAGE)->loadObjectList();

			foreach ($rows as $row) {
				$record  = $this->record($row);
				$afterId = $record->id;

				yield $record;
			}
		} while (\count($rows) === self::PAGE);
	}

	public function counts(int $scanId): array
	{
		$query = $this->db->getQuery(true)
			->select([$this->db->quoteName('state'), 'COUNT(*) AS ' . $this->db->quoteName('n')])
			->from($this->db->quoteName(self::TABLE))
			->where($this->db->quoteName('scan_id') . ' = :scanId')
			->group($this->db->quoteName('state'))
			->bind(':scanId', $scanId, ParameterType::INTEGER);

		$counts = [];

		foreach ($this->db->setQuery($query)->loadObjectList() as $row) {
			$counts[(string) $row->state] = (int) $row->n;
		}

		return $counts;
	}

	private function insert(LinkRecord $record): void
	{
		$values = [
			'scan_id'       => [$record->scanId, ParameterType::INTEGER],
			'article_id'    => [$record->articleId, ParameterType::INTEGER],
			'article_title' => [mb_substr($record->articleTitle, 0, 255), ParameterType::STRING],
			'page_url'      => [mb_substr($record->pageUrl, 0, 2048), ParameterType::STRING],
			'field'         => [$record->field, ParameterType::STRING],
			'link_text'     => [mb_substr($record->linkText, 0, 1024), ParameterType::STRING],
			'href'          => [$record->href, ParameterType::STRING],
			'state'         => [$record->state, ParameterType::STRING],
			'final_url'     => [$record->finalUrl, ParameterType::STRING],
			'menu_id'       => [$record->menuId, ParameterType::INTEGER],
			'menu_title'    => [mb_substr($record->menuTitle, 0, 255), ParameterType::STRING],
			'new_href'      => [mb_substr($record->newHref, 0, 2048), ParameterType::STRING],
			'message'       => [mb_substr($record->message, 0, 1024), ParameterType::STRING],
			'article_hash'  => [$record->articleHash, ParameterType::STRING],
			'modified'      => [gmdate('Y-m-d H:i:s'), ParameterType::STRING],
		];

		$query = $this->db->getQuery(true)
			->insert($this->db->quoteName(self::TABLE))
			->columns($this->db->quoteName(array_keys($values)))
			->values(implode(', ', array_map(static fn (string $column): string => ':' . $column, array_keys($values))));

		foreach ($values as $column => [$value, $type]) {
			$query->bind(':' . $column, $values[$column][0], $type);
		}

		$this->db->setQuery($query)->execute();
	}

	private function record(\stdClass $row): LinkRecord
	{
		return new LinkRecord(
			(int) $row->id,
			(int) $row->scan_id,
			(int) $row->article_id,
			(string) $row->article_title,
			(string) $row->page_url,
			(string) $row->field,
			(string) $row->link_text,
			(string) $row->href,
			(string) $row->state,
			(string) $row->final_url,
			(int) $row->menu_id,
			(string) $row->menu_title,
			(string) $row->new_href,
			(string) $row->message,
			(string) $row->article_hash
		);
	}
}
