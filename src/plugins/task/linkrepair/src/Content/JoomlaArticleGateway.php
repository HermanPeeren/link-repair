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
 * ArticleGateway on `#__content` for reading, and on com_content's ArticleModel for
 * saving.
 */
final class JoomlaArticleGateway implements ArticleGateway
{
	/**
	 * The state of a trashed article.
	 */
	private const TRASHED = -2;

	public function __construct(
		private readonly DatabaseInterface $db,
		private readonly ArticleModelFactory $models
	) {
	}

	public function page(int $afterId, int $limit): array
	{
		$query = $this->select()
			->where($this->db->quoteName('id') . ' > :afterId')
			->order($this->db->quoteName('id'))
			->bind(':afterId', $afterId, ParameterType::INTEGER);

		$articles = [];

		foreach ($this->db->setQuery($query, 0, max(1, $limit))->loadObjectList() as $row) {
			$articles[] = $this->article($row);
		}

		return $articles;
	}

	public function load(int $id): ?Article
	{
		$query = $this->select()
			->where($this->db->quoteName('id') . ' = :id')
			->bind(':id', $id, ParameterType::INTEGER);

		$row = $this->db->setQuery($query)->loadObject();

		return $row === null ? null : $this->article($row);
	}

	public function save(Article $article, string $introtext, string $fulltext, int $userId, string $note): void
	{
		$model = $this->models->create($userId);

		// The model returns false for a save that its own checks or a content plugin
		// refused, and throws on a database error. Joomla 6 deprecates asking a model
		// for the reason (getError), so a refusal is reported without one.
		try {
			$saved = $model->save([
				'id'           => $article->id,
				'catid'        => $article->categoryId,
				'introtext'    => $introtext,
				'fulltext'     => $fulltext,
				'version_note' => $note,
			]);
		} catch (\Throwable $e) {
			throw new \RuntimeException($e->getMessage(), 0, $e);
		}

		if (!$saved) {
			throw new \RuntimeException('the article model refused to save the article');
		}
	}

	private function select(): QueryInterface
	{
		$trashed = self::TRASHED;

		return $this->db->getQuery(true)
			->select($this->db->quoteName(['id', 'catid', 'title', 'introtext', 'fulltext', 'checked_out']))
			->from($this->db->quoteName('#__content'))
			->where($this->db->quoteName('state') . ' <> :trashed')
			->bind(':trashed', $trashed, ParameterType::INTEGER);
	}

	private function article(\stdClass $row): Article
	{
		return new Article(
			(int) $row->id,
			(int) $row->catid,
			(string) $row->title,
			(string) $row->introtext,
			(string) $row->fulltext,
			(int) $row->checked_out
		);
	}
}
