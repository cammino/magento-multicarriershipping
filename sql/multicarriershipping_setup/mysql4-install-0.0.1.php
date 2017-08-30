<?php
$installer = $this;

$installer->startSetup();
$installer->run("
-- DROP TABLE IF EXISTS `multicarriershipping_tablerate`;
CREATE TABLE `multicarriershipping_tablerate`(
  `tablerate_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `zipcode_start` int(8) NOT NULL DEFAULT '0',
  `zipcode_end` int(8) NOT NULL DEFAULT '0',
  `weight_start` decimal(14,2) NOT NULL DEFAULT '0.00',
  `weight_end` decimal(14,2) NOT NULL DEFAULT '0.00',
  `price` decimal(14,2) NOT NULL DEFAULT '0.00',
  `additional_price` decimal(14,2) NOT NULL DEFAULT '0.00',
  `shipping_days` int(3) NOT NULL DEFAULT '0',
  PRIMARY KEY (`tablerate_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
");

$installer->endSetup(); 