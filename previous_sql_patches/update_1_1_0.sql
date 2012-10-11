ALTER TABLE shipping_boxes_manager ADD destination varchar(32) NOT NULL DEFAULT 'both';

SET @configuration_group_id=0; 
SELECT (@configuration_group_id:=configuration_group_id) 
FROM configuration_group 
WHERE configuration_group_title = 'Shipping Boxes Manager Configuration' 
LIMIT 1;
DELETE FROM configuration WHERE configuration_group_id = @configuration_group_id AND @configuration_group_id != 0;
DELETE FROM configuration_group WHERE configuration_group_id = @configuration_group_id AND @configuration_group_id != 0;

INSERT INTO configuration_group (configuration_group_id, configuration_group_title, configuration_group_description, sort_order, visible) VALUES (NULL, 'Shipping Boxes Manager Configuration', 'Set Shipping Boxes Manager Options', '1', '1');
SET @configuration_group_id=last_insert_id();
UPDATE configuration_group SET sort_order = @configuration_group_id WHERE configuration_group_id = @configuration_group_id;

INSERT INTO configuration (configuration_id, configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added, use_function, set_function) VALUES
(NULL, 'Version', 'SHIPPING_BOXES_MANAGER_VERSION', '1.1.0', 'Version Installed:', @configuration_group_id, 0, NOW(), NULL, NULL);

#Register the configuration page for Admin Access Control
INSERT IGNORE INTO admin_pages (page_key, language_key, main_page, page_params, menu_key, display_on_menu, sort_order) VALUES ('configShippingBoxesManager', 'BOX_SHIPPING_BOXES_MANAGER_CONFIGURATION', 'FILENAME_CONFIGURATION',CONCAT('gID=',@configuration_group_id), 'configuration', 'Y', @configuration_group_id);

# Register the tools page for Admin Access Control
INSERT IGNORE INTO admin_pages (page_key, language_key, main_page, page_params, menu_key, display_on_menu, sort_order) VALUES ('toolsShippingBoxesManager', 'BOX_SHIPPING_BOXES_MANAGER', 'FILENAME_SHIPPING_BOXES_MANAGER', '', 'tools', 'Y', 31);