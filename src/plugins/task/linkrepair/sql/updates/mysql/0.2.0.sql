-- 0.2.0: categories and custom modules are scanned too, so a link belongs to an item
-- of some kind, not always to an article; and the cursors say which kind they are in.
ALTER TABLE `#__linkrepair_links` CHANGE `article_id` `item_id` int unsigned NOT NULL;
ALTER TABLE `#__linkrepair_links` CHANGE `article_title` `item_title` varchar(255) NOT NULL DEFAULT '';
ALTER TABLE `#__linkrepair_links` CHANGE `article_hash` `item_hash` char(40) NOT NULL DEFAULT '';
ALTER TABLE `#__linkrepair_links` ADD COLUMN `item_type` varchar(20) NOT NULL DEFAULT 'article' AFTER `scan_id`;
ALTER TABLE `#__linkrepair_links` DROP INDEX `idx_scan_state_article`;
ALTER TABLE `#__linkrepair_links` ADD INDEX `idx_scan_state_item` (`scan_id`, `state`, `item_type`, `item_id`);
ALTER TABLE `#__linkrepair_scans` CHANGE `articles` `items` int unsigned NOT NULL DEFAULT 0;
ALTER TABLE `#__linkrepair_scans` ADD COLUMN `cursor_type` varchar(20) NOT NULL DEFAULT 'article' AFTER `status`;
ALTER TABLE `#__linkrepair_scans` ADD COLUMN `repair_type` varchar(20) NOT NULL DEFAULT 'article' AFTER `repair_status`;
