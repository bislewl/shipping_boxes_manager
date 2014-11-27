SET @configuration_group_id=0; 
SELECT (@configuration_group_id:=configuration_group_id) 
FROM configuration_group 
WHERE configuration_group_title = 'Shipping Boxes Manager Configuration' 
LIMIT 1;

INSERT IGNORE INTO configuration (configuration_id, configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added, use_function, set_function) VALUES
(NULL, 'Debug', 'MODULE_SHIPPING_BOXES_MANAGER_DEBUG', 'false', 'Enable debug mode output?', @configuration_group_id, 2, NOW(), NULL, 'zen_cfg_select_option(array(\'true\', \'false\'),');

UPDATE configuration SET configuration_value = '2.1.2' WHERE configuration_key = 'SHIPPING_BOXES_MANAGER_VERSION' LIMIT 1;