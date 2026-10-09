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
 * An article, as far as its links are concerned.
 */
final class Article
{
	public const FIELDS = ['introtext', 'fulltext'];

	public function __construct(
		public readonly int $id,
		public readonly int $categoryId,
		public readonly string $title,
		public readonly string $introtext,
		public readonly string $fulltext,
		public readonly int $checkedOut
	) {
	}

	public function text(string $field): string
	{
		return $field === 'fulltext' ? $this->fulltext : $this->introtext;
	}

	/**
	 * A fingerprint of the text, to tell whether it changed between scan and repair.
	 */
	public function hash(): string
	{
		return sha1($this->introtext . "\0" . $this->fulltext);
	}
}
