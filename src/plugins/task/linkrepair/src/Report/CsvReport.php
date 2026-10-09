<?php

/**
 * @package     LinkRepair
 *
 * @copyright   Copyright (C) 2026 Yepr, Herman Peeren. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

namespace Yepr\Plugin\Task\LinkRepair\Report;

use Yepr\Plugin\Task\LinkRepair\Store\LinkStore;
use Yepr\Plugin\Task\LinkRepair\Store\Scan;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Writes a scan's links, with their current state, to a CSV file in the site's log
 * folder: `linkrepair-scan-<id>.csv`. Written again after every repair run, so the
 * file always shows how things stand.
 */
final class CsvReport
{
	private const HEADER = [
		'Type', 'Id', 'Title', 'Page URL', 'Field', 'Link text', 'Link in the text',
		'State', 'Menu item', 'Menu item id', 'New link', 'Where the link ends up', 'Message',
	];

	public function __construct(private readonly LinkStore $links, private readonly string $folder)
	{
	}

	/**
	 * @return  string  The file written.
	 *
	 * @throws  \RuntimeException  When the file cannot be written.
	 */
	public function write(Scan $scan): string
	{
		$path   = rtrim($this->folder, '/\\') . '/linkrepair-scan-' . $scan->id . '.csv';
		$handle = @fopen($path, 'w');

		if ($handle === false) {
			throw new \RuntimeException('Cannot write the report to ' . $path);
		}

		try {
			// A byte order mark, so that spreadsheet programs read the file as UTF-8.
			fwrite($handle, "\xEF\xBB\xBF");
			fputcsv($handle, self::HEADER, ',', '"', '');

			foreach ($this->links->all($scan->id) as $link) {
				fputcsv($handle, [
					$link->itemKind,
					$link->itemId,
					$link->itemTitle,
					$link->pageUrl,
					$link->field,
					$link->linkText,
					$link->href,
					$link->state,
					$link->menuTitle,
					$link->menuId ?: '',
					$link->newHref,
					$link->finalUrl,
					$link->message,
				], ',', '"', '');
			}
		} finally {
			fclose($handle);
		}

		return $path;
	}
}
