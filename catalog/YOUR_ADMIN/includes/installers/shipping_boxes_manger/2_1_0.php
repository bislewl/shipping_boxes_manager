<?php
global $sniffer;
if (!$sniffer->field_exists(TABLE_PRODUCTS, 'nestable'))  $db->Execute("ALTER TABLE " . TABLE_PRODUCTS . "ADD nestable tinyint(1) NULL;");
