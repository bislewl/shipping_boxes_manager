<?php

global $sniffer;
if (!$sniffer->table_exists(TABLE_SHIPPING_BOXES_MANAGER)) {
    $db->Execute("CREATE TABLE IF NOT EXISTS " . TABLE_SHIPPING_BOXES_MANAGER . " ( 
                `box_id` int NOT NULL AUTO_INCREMENT,
                `title` varchar(50) NULL DEFAULT NULL
                `outer_width` float NOT NULL default '0',
                `outer_length` float NOT NULL default '0',
                `outer_depth` float NOT NULL DEFAULT '0',
                `empty_weight` float NOT NULL default '0',
                `inner_width` float NOT NULL default '0',
                `inner_length` float NOT NULL DEFAULT '0',
                `inner_depth` float NOT NULL default '0',
                `max_weight` float NOT NULL default '0',
                `destination` varchar(32) NOT NULL DEFAULT 'both',
                 PRIMARY KEY ( `box_id` )
              );");
}


if ($sniffer->field_exists(TABLE_SHIPPING_BOXES_MANAGER, 'volume')) 
        $db->Execute("ALTER TABLE " . TABLE_SHIPPING_BOXES_MANAGER . " DROP COLUMN volume;");
if ($sniffer->field_exists(TABLE_SHIPPING_BOXES_MANAGER, 'width') && !$sniffer->field_exists(TABLE_SHIPPING_BOXES_MANAGER, 'outer_width'))  
        $db->Execute("ALTER TABLE " . TABLE_SHIPPING_BOXES_MANAGER . " RENAME COLUMN width to outer_width;");
if ($sniffer->field_exists(TABLE_SHIPPING_BOXES_MANAGER, 'height') && !$sniffer->field_exists(TABLE_SHIPPING_BOXES_MANAGER, 'outer_depth'))  
        $db->Execute("ALTER TABLE " . TABLE_SHIPPING_BOXES_MANAGER . " RENAME COLUMN height to outer_depth;");
if ($sniffer->field_exists(TABLE_SHIPPING_BOXES_MANAGER, 'length') && !$sniffer->field_exists(TABLE_SHIPPING_BOXES_MANAGER, 'outer_length'))  
        $db->Execute("ALTER TABLE " . TABLE_SHIPPING_BOXES_MANAGER . " RENAME COLUMN length to outer_length;");
if ($sniffer->field_exists(TABLE_SHIPPING_BOXES_MANAGER, 'weight') && !$sniffer->field_exists(TABLE_SHIPPING_BOXES_MANAGER, 'empty_weight'))  
        $db->Execute("ALTER TABLE " . TABLE_SHIPPING_BOXES_MANAGER . " RENAME COLUMN weight to empty_weight;");

if (!$sniffer->field_exists(TABLE_SHIPPING_BOXES_MANAGER, 'box_id'))  $db->Execute("ALTER TABLE " . TABLE_SHIPPING_BOXES_MANAGER . " ADD box_id int NOT NULL AUTO_INCREMENT;");
if (!$sniffer->field_exists(TABLE_SHIPPING_BOXES_MANAGER, 'title'))  $db->Execute("ALTER TABLE " . TABLE_SHIPPING_BOXES_MANAGER . " ADD title varchar(50) NULL DEFAULT NULL;");
if (!$sniffer->field_exists(TABLE_SHIPPING_BOXES_MANAGER, 'outer_width'))  $db->Execute("ALTER TABLE " . TABLE_SHIPPING_BOXES_MANAGER . " ADD outer_width float NOT NULL default '0';");
if (!$sniffer->field_exists(TABLE_SHIPPING_BOXES_MANAGER, 'outer_length'))  $db->Execute("ALTER TABLE " . TABLE_SHIPPING_BOXES_MANAGER . " ADD outer_length float NOT NULL default '0';");
if (!$sniffer->field_exists(TABLE_SHIPPING_BOXES_MANAGER, 'outer_depth'))  $db->Execute("ALTER TABLE " . TABLE_SHIPPING_BOXES_MANAGER . " ADD outer_depth float NOT NULL default '0';");
if (!$sniffer->field_exists(TABLE_SHIPPING_BOXES_MANAGER, 'empty_weight'))  $db->Execute("ALTER TABLE " . TABLE_SHIPPING_BOXES_MANAGER . " ADD empty_weight float NOT NULL default '0';");
if (!$sniffer->field_exists(TABLE_SHIPPING_BOXES_MANAGER, 'inner_width'))  $db->Execute("ALTER TABLE " . TABLE_SHIPPING_BOXES_MANAGER . " ADD inner_width float NOT NULL default '0';");
if (!$sniffer->field_exists(TABLE_SHIPPING_BOXES_MANAGER, 'inner_length'))  $db->Execute("ALTER TABLE " . TABLE_SHIPPING_BOXES_MANAGER . " ADD inner_length float NOT NULL default '0';");
if (!$sniffer->field_exists(TABLE_SHIPPING_BOXES_MANAGER, 'inner_depth'))  $db->Execute("ALTER TABLE " . TABLE_SHIPPING_BOXES_MANAGER . " ADD inner_depth float NOT NULL default '0';");
if (!$sniffer->field_exists(TABLE_SHIPPING_BOXES_MANAGER, 'max_weight'))  $db->Execute("ALTER TABLE " . TABLE_SHIPPING_BOXES_MANAGER . " ADD max_weight float NOT NULL default '0';");
if (!$sniffer->field_exists(TABLE_SHIPPING_BOXES_MANAGER, 'destination'))  $db->Execute("ALTER TABLE " . TABLE_SHIPPING_BOXES_MANAGER . " ADD destination varchar(32) NOT NULL DEFAULT 'both';");

