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
 * Finds the links in a piece of article HTML.
 *
 * Regular expressions rather than a DOM parser on purpose: LinkRewriter has to
 * change these same links without touching anything else in the HTML, and a DOM
 * round trip re-formats a whole article. Both classes therefore read `<a>` tags
 * the same way, with the same patterns.
 */
final class LinkExtractor
{
	/**
	 * An opening `<a>` tag with its attributes, and the content up to `</a>`.
	 */
	public const ANCHOR = '#<a\b([^>]*)>(.*?)</a\s*>#is';

	/**
	 * The href attribute in a tag's attributes, double-, single- or unquoted.
	 */
	public const HREF = '#(\bhref\s*=\s*)(?:"([^"]*)"|\'([^\']*)\'|([^\s"\'>]+))#i';

	/**
	 * @return  list<HtmlLink>
	 */
	public function extract(string $html): array
	{
		if ($html === '' || stripos($html, '<a') === false) {
			return [];
		}

		if (!preg_match_all(self::ANCHOR, $html, $anchors, PREG_SET_ORDER)) {
			return [];
		}

		$links = [];

		foreach ($anchors as $anchor) {
			$href = self::hrefOf($anchor[1]);

			if ($href === null) {
				continue;
			}

			$links[] = new HtmlLink($href, $this->textOf($anchor[2]));
		}

		return $links;
	}

	/**
	 * The decoded href in a tag's attributes, or null when it has none.
	 */
	public static function hrefOf(string $attributes): ?string
	{
		if (!preg_match(self::HREF, $attributes, $match)) {
			return null;
		}

		$raw = ($match[2] ?? '') . ($match[3] ?? '') . ($match[4] ?? '');

		return trim(html_entity_decode($raw, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
	}

	/**
	 * What a reader sees as the link: its text, or the alt text of an image.
	 */
	private function textOf(string $content): string
	{
		$text = html_entity_decode(strip_tags($content), ENT_QUOTES | ENT_HTML5, 'UTF-8');
		$text = trim((string) preg_replace('/\s+/u', ' ', $text));

		if ($text !== '') {
			return $text;
		}

		if (preg_match('#<img\b[^>]*\balt\s*=\s*(?:"([^"]*)"|\'([^\']*)\')#i', $content, $alt)) {
			return '[image] ' . trim(html_entity_decode($alt[1] . ($alt[2] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
		}

		return stripos($content, '<img') !== false ? '[image]' : '';
	}
}
