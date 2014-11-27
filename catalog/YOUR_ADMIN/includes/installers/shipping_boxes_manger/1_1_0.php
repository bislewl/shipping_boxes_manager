<?php
//add destination and status button

global $sniffer;
if (!$sniffer->field_exists(TABLE_SHIPPING_BOXES_MANAGER, 'destination'))  $db->Execute("ALTER TABLE " . TABLE_SHIPPING_BOXES_MANAGER . "ADD destination varchar(32) NOT NULL DEFAULT 'both';");

$db->Execute("INSERT INTO ".TABLE_CONFIGURATION." (configuration_id, configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added, use_function, set_function) VALUES
(NULL, 'Status', 'MODULE_SHIPPING_BOXES_MANAGER_STATUS', 'false', 'Use shipping boxes manager to calculate package weights/dimensions?', @configuration_group_id, 1, NOW(), NULL, 'zen_cfg_select_option(array(\'true\', \'false\'),');
");