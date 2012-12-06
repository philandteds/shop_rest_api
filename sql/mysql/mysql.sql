DROP TABLE IF EXISTS `ezorder_export_history`;
CREATE TABLE `ezorder_export_history` (
  `order_id` int(11) unsigned NOT NULL,
  `user_id` int(11) unsigned NOT NULL,
  `is_sent_lj` BIT(1) DEFAULT 0,
  `is_processed_lj` BIT(1) DEFAULT 0,
  `sent_to_lj_at` int(11) unsigned NOT NULL,
  PRIMARY KEY  (`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;