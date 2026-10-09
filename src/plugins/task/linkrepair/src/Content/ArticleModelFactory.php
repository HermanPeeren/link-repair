<?php

/**
 * @package     LinkRepair
 *
 * @copyright   Copyright (C) 2026 Yepr, Herman Peeren. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

namespace Yepr\Plugin\Task\LinkRepair\Content;

use Joomla\CMS\Application\CMSApplicationInterface;
use Joomla\CMS\MVC\Factory\MVCFactoryServiceInterface;
use Joomla\CMS\MVC\Model\AdminModel;
use Joomla\CMS\User\UserFactoryInterface;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Makes com_content's administrator ArticleModel, one per save.
 *
 * One per save, because a model keeps state (the item id, errors) from the last
 * save. Through com_content's own MVC factory, so the model is wired exactly as when
 * the article editor saves: content plugins, workflow, Smart Search and version
 * history all take part.
 */
final class ArticleModelFactory
{
	public function __construct(
		private readonly CMSApplicationInterface $app,
		private readonly UserFactoryInterface $users
	) {
	}

	/**
	 * @param   int  $userId  The user the model saves as; 0 for none.
	 *
	 * @throws  \RuntimeException  When com_content does not give an article model.
	 */
	public function create(int $userId): AdminModel
	{
		$component = $this->app->bootComponent('com_content');

		if (!$component instanceof MVCFactoryServiceInterface) {
			throw new \RuntimeException('com_content has no MVC factory.');
		}

		$model = $component->getMVCFactory()->createModel('Article', 'Administrator', ['ignore_request' => true]);

		if (!$model instanceof AdminModel) {
			throw new \RuntimeException('com_content has no article model.');
		}

		if ($userId > 0) {
			$user = $this->users->loadUserById($userId);

			if ((int) $user->id !== $userId) {
				throw new \RuntimeException(\sprintf('There is no user with id %d.', $userId));
			}

			$model->setCurrentUser($user);
		}

		return $model;
	}
}
