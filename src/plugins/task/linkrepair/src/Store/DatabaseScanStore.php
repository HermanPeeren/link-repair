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
use Joomla\Database\QueryInterface;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * ScanStore on `#__linkrepair_scans`.
 */
final class DatabaseScanStore implements ScanStore
{
	private const SCANS = '#__linkrepair_scans';
	private const LINKS = '#__linkrepair_links';

	public function __construct(private readonly DatabaseInterface $db)
	{
	}

	public function running(int $taskId): ?Scan
	{
		$status = Scan::RUNNING;

		$query = $this->select()
			->where($this->db->quoteName('task_id') . ' = :taskId')
			->where($this->db->quoteName('status') . ' = :status')
			->order($this->db->quoteName('id') . ' DESC')
			->bind(':taskId', $taskId, ParameterType::INTEGER)
			->bind(':status', $status);

		return $this->scan($this->db->setQuery($query, 0, 1)->loadObject());
	}

	public function start(int $taskId): Scan
	{
		$keep = $this->latestFinished()->id ?? 0;

		$this->prune($taskId, $keep);

		$status  = Scan::RUNNING;
		$repair  = Scan::REPAIR_IDLE;
		$started = gmdate('Y-m-d H:i:s');

		$query = $this->db->getQuery(true)
			->insert($this->db->quoteName(self::SCANS))
			->columns($this->db->quoteName(['task_id', 'status', 'repair_status', 'started']))
			->values(':taskId, :status, :repair, :started')
			->bind(':taskId', $taskId, ParameterType::INTEGER)
			->bind(':status', $status)
			->bind(':repair', $repair)
			->bind(':started', $started);

		$this->db->setQuery($query)->execute();

		return new Scan((int) $this->db->insertid(), $taskId);
	}

	public function save(Scan $scan): void
	{
		$id       = $scan->id;
		$status   = $scan->status;
		$cursor   = $scan->cursorId;
		$articles = $scan->articles;
		$links    = $scan->links;
		$repair   = $scan->repairStatus;
		$rcursor  = $scan->repairCursor;
		$finished = $scan->status === Scan::FINISHED ? gmdate('Y-m-d H:i:s') : null;

		$query = $this->db->getQuery(true)
			->update($this->db->quoteName(self::SCANS))
			->set($this->db->quoteName('status') . ' = :status')
			->set($this->db->quoteName('cursor_id') . ' = :cursor')
			->set($this->db->quoteName('articles') . ' = :articles')
			->set($this->db->quoteName('links') . ' = :links')
			->set($this->db->quoteName('repair_status') . ' = :repair')
			->set($this->db->quoteName('repair_cursor') . ' = :rcursor')
			->where($this->db->quoteName('id') . ' = :id')
			->bind(':status', $status)
			->bind(':cursor', $cursor, ParameterType::INTEGER)
			->bind(':articles', $articles, ParameterType::INTEGER)
			->bind(':links', $links, ParameterType::INTEGER)
			->bind(':repair', $repair)
			->bind(':rcursor', $rcursor, ParameterType::INTEGER)
			->bind(':id', $id, ParameterType::INTEGER);

		if ($finished !== null) {
			$query->set($this->db->quoteName('finished') . ' = COALESCE(' . $this->db->quoteName('finished') . ', :finished)')
				->bind(':finished', $finished);
		}

		$this->db->setQuery($query)->execute();
	}

	public function latestFinished(): ?Scan
	{
		$status = Scan::FINISHED;

		$query = $this->select()
			->where($this->db->quoteName('status') . ' = :status')
			->order($this->db->quoteName('id') . ' DESC')
			->bind(':status', $status);

		return $this->scan($this->db->setQuery($query, 0, 1)->loadObject());
	}

	/**
	 * Removes the finished scans other than $keep, and this task's unfinished ones;
	 * then the links that no longer belong to a scan. Another task's scan in
	 * progress is left alone.
	 */
	private function prune(int $taskId, int $keep): void
	{
		$finished = Scan::FINISHED;
		$running  = Scan::RUNNING;

		$scans = $this->db->getQuery(true)
			->delete($this->db->quoteName(self::SCANS))
			->where($this->db->quoteName('id') . ' <> :keep')
			->extendWhere('AND', [
				$this->db->quoteName('status') . ' = :finished',
				'(' . $this->db->quoteName('status') . ' = :running AND ' . $this->db->quoteName('task_id') . ' = :taskId)',
			], 'OR')
			->bind(':keep', $keep, ParameterType::INTEGER)
			->bind(':finished', $finished)
			->bind(':running', $running)
			->bind(':taskId', $taskId, ParameterType::INTEGER);

		$this->db->setQuery($scans)->execute();

		$existing = $this->db->getQuery(true)
			->select($this->db->quoteName('id'))
			->from($this->db->quoteName(self::SCANS));

		$links = $this->db->getQuery(true)
			->delete($this->db->quoteName(self::LINKS))
			->where($this->db->quoteName('scan_id') . ' NOT IN (' . $existing . ')');

		$this->db->setQuery($links)->execute();
	}

	private function select(): QueryInterface
	{
		return $this->db->getQuery(true)
			->select($this->db->quoteName(['id', 'task_id', 'status', 'cursor_id', 'articles', 'links', 'repair_status', 'repair_cursor']))
			->from($this->db->quoteName(self::SCANS));
	}

	private function scan(?\stdClass $row): ?Scan
	{
		if ($row === null) {
			return null;
		}

		return new Scan(
			(int) $row->id,
			(int) $row->task_id,
			(string) $row->status,
			(int) $row->cursor_id,
			(int) $row->articles,
			(int) $row->links,
			(string) $row->repair_status,
			(int) $row->repair_cursor
		);
	}
}
