<?php

/**
 * @package     LinkRepair
 *
 * @copyright   Copyright (C) 2026 Yepr, Herman Peeren. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\User\UserFactoryInterface;
use Joomla\Database\DatabaseInterface;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Joomla\Http\HttpFactory;
use Yepr\Plugin\Task\LinkRepair\Content\ArticleGateway;
use Yepr\Plugin\Task\LinkRepair\Content\ArticleModelFactory;
use Yepr\Plugin\Task\LinkRepair\Content\JoomlaArticleGateway;
use Yepr\Plugin\Task\LinkRepair\Extension\LinkRepair;
use Yepr\Plugin\Task\LinkRepair\Html\LinkExtractor;
use Yepr\Plugin\Task\LinkRepair\Html\LinkRewriter;
use Yepr\Plugin\Task\LinkRepair\Report\CsvReport;
use Yepr\Plugin\Task\LinkRepair\Resolve\DatabaseMenuIndex;
use Yepr\Plugin\Task\LinkRepair\Resolve\HttpRedirectFollower;
use Yepr\Plugin\Task\LinkRepair\Resolve\MenuIndex;
use Yepr\Plugin\Task\LinkRepair\Resolve\RedirectFollower;
use Yepr\Plugin\Task\LinkRepair\Run\Clock;
use Yepr\Plugin\Task\LinkRepair\Run\RoutineFactory;
use Yepr\Plugin\Task\LinkRepair\Run\SystemClock;
use Yepr\Plugin\Task\LinkRepair\Store\DatabaseLinkStore;
use Yepr\Plugin\Task\LinkRepair\Store\DatabaseScanStore;
use Yepr\Plugin\Task\LinkRepair\Store\LinkStore;
use Yepr\Plugin\Task\LinkRepair\Store\ScanStore;
use Yepr\Plugin\Task\LinkRepair\Url\InternalLinkFilter;

/**
 * The composition root: the only place the plugin's services are put together.
 *
 * Joomla hands each extension a child container, so what is registered here is the
 * plugin's own and does not leak into the site's container. Every service is shared
 * and built on first use, and the plugin itself is a lazy proxy (on PHP 8.4 and
 * later): a page that runs no task builds none of it.
 */
return new class () implements ServiceProviderInterface {
	public function register(Container $container): void
	{
		$container->share(
			MenuIndex::class,
			static fn (Container $container): MenuIndex => new DatabaseMenuIndex($container->get(DatabaseInterface::class))
		);

		// Redirects are followed by hand, hop by hop, so the client must not follow them itself.
		$container->share(
			RedirectFollower::class,
			static fn (): RedirectFollower => new HttpRedirectFollower((new HttpFactory())->getHttp(['follow_location' => false]))
		);

		$container->share(
			ScanStore::class,
			static fn (Container $container): ScanStore => new DatabaseScanStore($container->get(DatabaseInterface::class))
		);

		$container->share(
			LinkStore::class,
			static fn (Container $container): LinkStore => new DatabaseLinkStore($container->get(DatabaseInterface::class))
		);

		$container->share(
			ArticleModelFactory::class,
			static fn (Container $container): ArticleModelFactory => new ArticleModelFactory(
				Factory::getApplication(),
				$container->get(UserFactoryInterface::class)
			)
		);

		$container->share(
			ArticleGateway::class,
			static fn (Container $container): ArticleGateway => new JoomlaArticleGateway(
				$container->get(DatabaseInterface::class),
				$container->get(ArticleModelFactory::class)
			)
		);

		$container->share(
			CsvReport::class,
			static fn (Container $container): CsvReport => new CsvReport(
				$container->get(LinkStore::class),
				(string) Factory::getApplication()->get('log_path', JPATH_ADMINISTRATOR . '/logs')
			)
		);

		$container->share(LinkExtractor::class, static fn (): LinkExtractor => new LinkExtractor());
		$container->share(LinkRewriter::class, static fn (): LinkRewriter => new LinkRewriter());
		$container->share(InternalLinkFilter::class, static fn (): InternalLinkFilter => new InternalLinkFilter());
		$container->share(Clock::class, static fn (): Clock => new SystemClock());

		$container->share(
			RoutineFactory::class,
			static fn (Container $container): RoutineFactory => new RoutineFactory(
				$container->get(ArticleGateway::class),
				$container->get(ScanStore::class),
				$container->get(LinkStore::class),
				$container->get(MenuIndex::class),
				$container->get(RedirectFollower::class),
				$container->get(LinkExtractor::class),
				$container->get(LinkRewriter::class),
				$container->get(InternalLinkFilter::class),
				$container->get(CsvReport::class),
				$container->get(Clock::class)
			)
		);

		$container->set(
			PluginInterface::class,
			$container->lazy(LinkRepair::class, function (Container $container) {
				$plugin = new LinkRepair(
					(array) PluginHelper::getPlugin('task', 'linkrepair'),
					$container->get(RoutineFactory::class)
				);
				$plugin->setApplication(Factory::getApplication());

				return $plugin;
			})
		);
	}
};
