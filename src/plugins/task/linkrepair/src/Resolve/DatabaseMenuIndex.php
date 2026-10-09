<?php

/**
 * @package     LinkRepair
 *
 * @copyright   Copyright (C) 2026 Yepr, Herman Peeren. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

namespace Yepr\Plugin\Task\LinkRepair\Resolve;

use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * MenuIndex on `#__menu` and `#__languages`, read once on first use.
 *
 * Joomla keeps the full route of every menu item in `#__menu.path`, so finding the
 * menu item behind a URL is a lookup, not a routing exercise. Separators and
 * headings have no page and are left out.
 */
final class DatabaseMenuIndex implements MenuIndex
{
	/**
	 * @var  array<string, list<MenuItem>>|null  Menu items by path.
	 */
	private ?array $byPath = null;

	/**
	 * @var  array<string, MenuItem>  Home menu items by language.
	 */
	private array $homes = [];

	/**
	 * @var  array<int, string>  Paths of menu items that show one article, by article id.
	 */
	private array $articlePaths = [];

	/**
	 * @var  array<string, string>|null  Language codes by URL prefix.
	 */
	private ?array $prefixes = null;

	public function __construct(private readonly DatabaseInterface $db)
	{
	}

	public function find(string $path, ?string $language = null): ?MenuItem
	{
		$this->load();

		return $this->pick($this->byPath[$path] ?? [], $language);
	}

	public function home(?string $language = null): ?MenuItem
	{
		$this->load();

		return $this->homes[$language ?? '*'] ?? $this->homes['*'] ?? (reset($this->homes) ?: null);
	}

	public function languageForPrefix(string $prefix): ?string
	{
		if ($this->prefixes === null) {
			$published = 1;

			$query = $this->db->getQuery(true)
				->select($this->db->quoteName(['sef', 'lang_code']))
				->from($this->db->quoteName('#__languages'))
				->where($this->db->quoteName('published') . ' = :published')
				->bind(':published', $published, ParameterType::INTEGER);

			$this->prefixes = [];

			foreach ($this->db->setQuery($query)->loadObjectList() as $row) {
				$this->prefixes[(string) $row->sef] = (string) $row->lang_code;
			}
		}

		return $this->prefixes[$prefix] ?? null;
	}

	public function pathForArticle(int $articleId): ?string
	{
		$this->load();

		return $this->articlePaths[$articleId] ?? null;
	}

	private function load(): void
	{
		if ($this->byPath !== null) {
			return;
		}

		$clientId  = 0;
		$published = 1;
		$noPage    = ['separator', 'heading'];

		$query = $this->db->getQuery(true)
			->select($this->db->quoteName(['id', 'title', 'path', 'language', 'home', 'link', 'type']))
			->from($this->db->quoteName('#__menu'))
			->where($this->db->quoteName('client_id') . ' = :clientId')
			->where($this->db->quoteName('published') . ' = :published')
			->whereNotIn($this->db->quoteName('type'), $noPage, ParameterType::STRING)
			->order($this->db->quoteName('id'))
			->bind(':clientId', $clientId, ParameterType::INTEGER)
			->bind(':published', $published, ParameterType::INTEGER);

		$this->byPath = [];

		foreach ($this->db->setQuery($query)->loadObjectList() as $row) {
			$item = new MenuItem((int) $row->id, (string) $row->title, (string) $row->path, (string) $row->language);

			$this->byPath[$item->path][] = $item;

			if ((int) $row->home === 1) {
				$this->homes[$item->language] = $item;
			}

			if (
				(string) $row->type === 'component'
				&& preg_match('#^index\.php\?option=com_content&view=article&id=(\d+)$#', (string) $row->link, $match)
				&& !isset($this->articlePaths[(int) $match[1]])
			) {
				$this->articlePaths[(int) $match[1]] = $item->path;
			}
		}
	}

	/**
	 * @param   list<MenuItem>  $items
	 */
	private function pick(array $items, ?string $language): ?MenuItem
	{
		if ($items === []) {
			return null;
		}

		foreach ([$language, '*'] as $wanted) {
			foreach ($items as $item) {
				if ($wanted !== null && $item->language === $wanted) {
					return $item;
				}
			}
		}

		return $items[0];
	}
}
