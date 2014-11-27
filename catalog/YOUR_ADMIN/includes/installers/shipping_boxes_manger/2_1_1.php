<?php
global $sniffer;
if (!$sniffer->field_exists(TABLE_PRODUCTS, 'nestable_percentage'))  $db->Execute("ALTER TABLE " . TABLE_PRODUCTS . "ADD nestable_percentage float(12) NULL;");