<?php
$installer = $this;

$installer->startSetup();
$installer->run("
-- DROP TABLE IF EXISTS `multicarriershipping_tablerate`;
CREATE TABLE `multicarriershipping_tablerate`(
  `tablerate_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `zipcode_start` varchar(10) NOT NULL DEFAULT '',
  `zipcode_end` varchar(10) NOT NULL DEFAULT '',
  `weight_start` decimal(14,2) NOT NULL DEFAULT '0.00',
  `weight_end` decimal(14,2) NOT NULL DEFAULT '0.00',
  `price` decimal(14,2) NOT NULL DEFAULT '0.00',
  `additional_price` decimal(14,2) NOT NULL DEFAULT '0.00',
  PRIMARY KEY (`tablerate_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
");

$installer->endSetup(); 