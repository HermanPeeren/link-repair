<?php

/**
 * @package     LinkRepair
 *
 * @copyright   Copyright (C) 2026 Yepr, Herman Peeren. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

namespace Yepr\Plugin\Task\LinkRepair\Extension;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Component\Scheduler\Administrator\Event\ExecuteTaskEvent;
use Joomla\Component\Scheduler\Administrator\Task\Status as TaskStatus;
use Joomla\Component\Scheduler\Administrator\Traits\TaskPluginTrait;
use Joomla\Event\SubscriberInterface;
use Yepr\Plugin\Task\LinkRepair\Run\MissingConfiguration;
use Yepr\Plugin\Task\LinkRepair\Run\RoutineFactory;
use Yepr\Plugin\Task\LinkRepair\Run\RunOutcome;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Offers two task types to the Task Scheduler: "Link repair: scan" and
 * "Link repair: repair".
 *
 * The work is in Scanner and Repairer. This class hands the task's parameters to the
 * injected factory, runs what it gets back and turns the outcome into an exit code:
 * OK when done, WILL_RESUME when the time budget ran out with work left, which makes
 * the scheduler run the task again straight away.
 */
final class LinkRepair extends CMSPlugin implements SubscriberInterface
{
	use TaskPluginTrait;

	/**
	 * @var array<string, array<string, string>>
	 */
	protected const TASKS_MAP = [
		'linkrepair.scan' => [
			'langConstPrefix' => 'PLG_TASK_LINKREPAIR_SCAN',
			'form'            => 'scan',
			'method'          => 'scan',
		],
		'linkrepair.repair' => [
			'langConstPrefix' => 'PLG_TASK_LINKREPAIR_REPAIR',
			'form'            => 'repair',
			'method'          => 'repair',
		],
	];

	/**
	 * @var boolean
	 */
	protected $autoloadLanguage = true;

	/**
	 * @param   array<string, mixed>  $config  The plugin's row, from PluginHelper::getPlugin().
	 */
	public function __construct(array $config, private readonly RoutineFactory $routines)
	{
		parent::__construct($config);
	}

	/**
	 * @return  array<string, string>
	 */
	public static function getSubscribedEvents(): array
	{
		return [
			'onTaskOptionsList'    => 'advertiseRoutines',
			'onExecuteTask'        => 'standardRoutineHandler',
			'onContentPrepareForm' => 'enhanceTaskItemForm',
		];
	}

	/**
	 * The scan routine.
	 *
	 * @return  integer  A TaskStatus exit code.
	 */
	protected function scan(ExecuteTaskEvent $event): int
	{
		try {
			$scanner = $this->routines->scanner($this->params($event), $this->logger());
		} catch (MissingConfiguration $e) {
			$this->logTask(\sprintf(Text::_('PLG_TASK_LINKREPAIR_LOG_NOT_CONFIGURED'), $e->getMessage()), 'error');

			return TaskStatus::KNOCKOUT;
		}

		return $this->outcome(fn (): RunOutcome => $scanner->run($event->getTaskId()));
	}

	/**
	 * The repair routine.
	 *
	 * @return  integer  A TaskStatus exit code.
	 */
	protected function repair(ExecuteTaskEvent $event): int
	{
		$repairer = $this->routines->repairer($this->params($event), $this->logger());

		return $this->outcome(fn (): RunOutcome => $repairer->run());
	}

	/**
	 * @param   callable(): RunOutcome  $run
	 */
	private function outcome(callable $run): int
	{
		try {
			return $run()->finished ? TaskStatus::OK : TaskStatus::WILL_RESUME;
		} catch (\RuntimeException $e) {
			$this->logTask(\sprintf(Text::_('PLG_TASK_LINKREPAIR_LOG_FAILED'), $e->getMessage()), 'error');

			return TaskStatus::KNOCKOUT;
		}
	}

	private function params(ExecuteTaskEvent $event): object
	{
		$params = $event->getArgument('params');

		return \is_object($params) ? $params : (object) [];
	}

	/**
	 * @return  callable(string, string): void
	 */
	private function logger(): callable
	{
		return function (string $message, string $priority): void {
			$this->logTask($message, $priority);
		};
	}
}
