CREATE TABLE IF NOT EXISTS `#__linkrepair_scans` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `task_id` int unsigned NOT NULL DEFAULT 0,
  `status` varchar(20) NOT NULL DEFAULT 'running',
  `cursor_type` varchar(20) NOT NULL DEFAULT '',
  `cursor_id` int unsigned NOT NULL DEFAULT 0,
  `items` int unsigned NOT NULL DEFAULT 0,
  `links` int unsigned NOT NULL DEFAULT 0,
  `repair_status` varchar(20) NOT NULL DEFAULT 'idle',
  `repair_type` varchar(20) NOT NULL DEFAULT '',
  `repair_cursor` int unsigned NOT NULL DEFAULT 0,
  `started` datetime NOT NULL,
  `finished` datetime NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_task_status` (`task_id`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__linkrepair_links` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `scan_id` int unsigned NOT NULL,
  `item_type` varchar(20) NOT NULL,
  `item_id` int unsigned NOT NULL,
  `item_title` varchar(255) NOT NULL DEFAULT '',
  `page_url` varchar(2048) NOT NULL DEFAULT '',
  `field` varchar(20) NOT NULL,
  `link_text` varchar(1024) NOT NULL DEFAULT '',
  `href` text NOT NULL,
  `state` varchar(20) NOT NULL,
  `final_url` text NOT NULL,
  `menu_id` int unsigned NOT NULL DEFAULT 0,
  `menu_title` varchar(255) NOT NULL DEFAULT '',
  `new_href` varchar(2048) NOT NULL DEFAULT '',
  `message` varchar(1024) NOT NULL DEFAULT '',
  `item_hash` char(40) NOT NULL DEFAULT '',
  `modified` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_scan_state_item` (`scan_id`, `state`, `item_type`, `item_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;
