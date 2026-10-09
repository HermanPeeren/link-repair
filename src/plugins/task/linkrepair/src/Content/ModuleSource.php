<?php

/**
 * @package     LinkRepair
 *
 * @copyright   Copyright (C) 2026 Yepr, Herman Peeren. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

namespace Yepr\Plugin\Task\LinkRepair\Content;

use Joomla\Database\ParameterType;
use Joomla\Database\QueryInterface;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Custom modules on the site: their content, saved through com_modules' ModuleModel.
 *
 * That model deletes a module's menu assignments on every save and writes them again
 * from what the form sent. So the save sends them along, in the form's own shape: the
 * mode (0 all pages, 1 only these, -1 all except these, '-' none) and the menu item ids
 * as positive numbers. (The model's getItem() returns them signed, as stored, and
 * sending those back would turn "all except" into "only".)
 */
final class ModuleSource extends TableSource
{
	private const MODULE   = 'mod_custom';
	private const CLIENT   = 0;
	private const NO_PAGES = '-';

	public function kind(): string
	{
		return ContentItem::MODULE;
	}

	protected function table(): string
	{
		return '#__modules';
	}

	protected function stateColumn(): string
	{
		return 'published';
	}

	protected function fields(): array
	{
		return ['content'];
	}

	protected function restrict(QueryInterface $query): QueryInterface
	{
		$module = self::MODULE;
		$client = self::CLIENT;

		return $query->where($this->db->quoteName('module') . ' = :module')
			->where($this->db->quoteName('client_id') . ' = :client')
			->bind(':module', $module)
			->bind(':client', $client, ParameterType::INTEGER);
	}

	public function save(ContentItem $item, array $fields, int $userId, string $note): void
	{
		[$assignment, $assigned] = $this->assignment($item->id);

		$this->saver->save('com_modules', 'Module', [
			'id'           => $item->id,
			'content'      => $fields['content'] ?? $item->fields['content'],
			'assignment'   => $assignment,
			'assigned'     => $assigned,
			'version_note' => $note,
		], $userId);
	}

	/**
	 * The module's menu assignment as the module form sends it.
	 *
	 * @return  array{0: int|string, 1: list<int>}  The mode, and the menu item ids.
	 */
	private function assignment(int $moduleId): array
	{
		$query = $this->db->getQuery(true)
			->select($this->db->quoteName('menuid'))
			->from($this->db->quoteName('#__modules_menu'))
			->where($this->db->quoteName('moduleid') . ' = :moduleId')
			->bind(':moduleId', $moduleId, ParameterType::INTEGER);

		$menuIds = array_map('intval', $this->db->setQuery($query)->loadColumn());

		if ($menuIds === []) {
			return [self::NO_PAGES, []];
		}

		$mode = $menuIds[0] <=> 0;

		return [$mode, $mode === 0 ? [] : array_values(array_map('abs', $menuIds))];
	}
}
