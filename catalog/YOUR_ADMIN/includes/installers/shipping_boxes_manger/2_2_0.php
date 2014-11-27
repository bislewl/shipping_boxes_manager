<?php
global $sniffer;
if (!$sniffer->field_exists(TABLE_PRODUCTS, 'nestable_group_code'))  $db->Execute("ALTER TABLE " . TABLE_PRODUCTS . "ADD nestable_group_code varchar(32) NULL;");

$db->Execute("CREATE TABLE IF NOT EXISTS ".TABLE_PRODUCTS_NESTING_GROUPS." (
  `grouping_id` int NOT NULL AUTO_INCREMENT,
  `group_code` varchar(32) NULL DEFAULT NULL,
  `compatible_group_code` varchar(32) NULL DEFAULT NULL,
  `nesting_percentage` float(3) NULL DEFAULT NULL,
   PRIMARY KEY ( `grouping_id` )
);");