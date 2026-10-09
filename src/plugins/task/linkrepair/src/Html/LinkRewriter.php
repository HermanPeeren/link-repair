<?php

/**
 * @package     LinkRepair
 *
 * @copyright   Copyright (C) 2026 Yepr, Herman Peeren. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

namespace Yepr\Plugin\Task\LinkRepair\Html;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Replaces the value of one href in the `<a>` tags of a piece of HTML.
 *
 * Only the attribute value changes. The tag's other attributes, the quotes around
 * the value, the link text and everything outside the tags stay byte for byte as
 * they were.
 */
final class LinkRewriter
{
	/**
	 * @param   string  $html     The HTML.
	 * @param   string  $oldHref  The link to replace, decoded as LinkExtractor returns it.
	 * @param   string  $newHref  The new link, not yet HTML-encoded.
	 *
	 * @return  array{0: string, 1: int}  The new HTML, and how many links were replaced.
	 */
	public function rewrite(string $html, string $oldHref, string $newHref): array
	{
		$count   = 0;
		$encoded = htmlspecialchars($newHref, ENT_QUOTES | ENT_HTML5, 'UTF-8');

		$result = preg_replace_callback(
			'#<a\b[^>]*>#i',
			static function (array $tag) use ($oldHref, $encoded, &$count): string {
				$href = LinkExtractor::hrefOf($tag[0]);

				if ($href === null || $href !== $oldHref) {
					return $tag[0];
				}

				$count++;

				return (string) preg_replace_callback(
					LinkExtractor::HREF,
					static function (array $attribute) use ($encoded): string {
						$quote = ($attribute[3] ?? '') !== '' && ($attribute[2] ?? '') === '' ? "'" : '"';

						return $attribute[1] . $quote . $encoded . $quote;
					},
					$tag[0],
					1
				);
			},
			$html
		);

		return [$result ?? $html, $result === null ? 0 : $count];
	}
}
