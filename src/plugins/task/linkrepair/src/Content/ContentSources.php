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
 * The kinds of content the plugin reads, in the order they are read: by kind name,
 * the same order the link store sorts them in.
 */
final class ContentSources
{
	/**
	 * @var  array<string, ContentSource>
	 */
	private array $sources = [];

	public function __construct(ContentSource ...$sources)
	{
		foreach ($sources as $source) {
			$this->sources[$source->kind()] = $source;
		}

		ksort($this->sources);
	}

	public function first(): ?string
	{
		return array_key_first($this->sources);
	}

	/**
	 * The kind read after $kind, or null after the last.
	 */
	public function after(string $kind): ?string
	{
		$kinds = array_keys($this->sources);
		$index = array_search($kind, $kinds, true);

		return $index === false ? null : ($kinds[$index + 1] ?? null);
	}

	/**
	 * @throws  \RuntimeException  For a kind there is no source for.
	 */
	public function get(string $kind): ContentSource
	{
		return $this->sources[$kind] ?? throw new \RuntimeException('No content source for ' . $kind);
	}
}
