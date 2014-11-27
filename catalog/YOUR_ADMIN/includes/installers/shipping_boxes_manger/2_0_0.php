<?php

// Add title to boxes

if (!$sniffer->field_exists(TABLE_SHIPPING_BOXES_MANAGER, 'title'))  $db->Execute("ALTER TABLE " . TABLE_SHIPPING_BOXES_MANAGER . "ADD title varchar(50) NULL DEFAULT NULL;");
