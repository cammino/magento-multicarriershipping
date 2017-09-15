<?php
$installer = $this;

$installer->startSetup();
$installer->run("
-- DROP TABLE IF EXISTS `multicarriershipping_tablerate_groups`;
CREATE TABLE `multicarriershipping_tablerate_groups` (
	`id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
");

$installer->run("
	ALTER TABLE multicarriershipping_tablerate
	ADD COLUMN `group` VARCHAR(100) NOT NULL AFTER `shipping_days`");

$installer->endSetup(); 