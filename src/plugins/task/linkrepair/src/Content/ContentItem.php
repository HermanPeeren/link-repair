<?php

/**
 * @package     LinkRepair
 *
 * @copyright   Copyright (C) 2026 Yepr, Herman Peeren. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

namespace Yepr\Plugin\Task\LinkRepair\Content;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Something with HTML that can hold links: an article, a category or a custom module,
 * with its text fields by name.
 */
final class ContentItem
{
	public const ARTICLE  = 'article';
	public const CATEGORY = 'category';
	public const MODULE   = 'module';

	/**
	 * @param   string                 $kind        One of the constants above.
	 * @param   array<string, string>  $fields      The HTML fields, by column name, in a fixed order.
	 * @param   int                    $checkedOut  The user who has it open in an editor; 0 for nobody.
	 */
	public function __construct(
		public readonly string $kind,
		public readonly int $id,
		public readonly string $title,
		public readonly array $fields,
		public readonly int $checkedOut
	) {
	}

	/**
	 * A fingerprint of the text, to tell whether it changed between scan and repair.
	 */
	public function hash(): string
	{
		return sha1(implode("\0", $this->fields));
	}
}
