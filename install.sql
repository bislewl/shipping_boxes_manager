DROP TABLE IF EXISTS shipping_boxes_manager; 
CREATE TABLE IF NOT EXISTS shipping_boxes_manager (
  `box_id` int NOT NULL AUTO_INCREMENT,
  `title` varchar(50) NULL DEFAULT NULL,
  `length` float NOT NULL default '0',
  `width` float NOT NULL default '0',
  `height` float NOT NULL DEFAULT '0',
  `weight` float NOT NULL DEFAULT '0',
  `volume` float NOT NULL DEFAULT '0',
  `destination` varchar(32) NOT NULL DEFAULT 'both',
   PRIMARY KEY ( `box_id` )
);
ALTER TABLE products ADD nestable tinyint(1) NULL;
ALTER TABLE products ADD nestable_percentage tinyint(3) NULL;
ALTER TABLE products ADD nestable_group_code varchar(32) NULL; 

DROP TABLE IF EXISTS products_nesting_groups;
CREATE TABLE IF NOT EXISTS products_nesting_groups (
  `grouping_id` int NOT NULL AUTO_INCREMENT,
  `group_code` varchar(32) NULL DEFAULT NULL,
  `compatible_group_code` varchar(32) NULL DEFAULT NULL,
  `nesting_percentage` float(3) NULL DEFAULT NULL,
   PRIMARY KEY ( `grouping_id` )
);

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
(NULL, 'Version', 'SHIPPING_BOXES_MANAGER_VERSION', '2.1.0', 'Version Installed:', @configuration_group_id, 0, NOW(), NULL, NULL),
(NULL, 'Status', 'MODULE_SHIPPING_BOXES_MANAGER_STATUS', 'false', 'Use shipping boxes manager to calculate package weights/dimensions?', @configuration_group_id, 1, NOW(), NULL, 'zen_cfg_select_option(array(\'true\', \'false\'),'),
(NULL, 'Debug', 'MODULE_SHIPPING_BOXES_MANAGER_DEBUG', 'false', 'Enable debug mode output?', @configuration_group_id, 2, NOW(), NULL, 'zen_cfg_select_option(array(\'true\', \'false\'),');
                                     
#Register the configuration page for Admin Access Control
INSERT IGNORE INTO admin_pages (page_key, language_key, main_page, page_params, menu_key, display_on_menu, sort_order) VALUES ('configShippingBoxesManager', 'BOX_SHIPPING_BOXES_MANAGER_CONFIGURATION', 'FILENAME_CONFIGURATION',CONCAT('gID=',@configuration_group_id), 'configuration', 'Y', @configuration_group_id);

# Register the tools page for Admin Access Control
INSERT IGNORE INTO admin_pages (page_key, language_key, main_page, page_params, menu_key, display_on_menu, sort_order) VALUES ('toolsShippingBoxesManager', 'BOX_SHIPPING_BOXES_MANAGER', 'FILENAME_SHIPPING_BOXES_MANAGER', '', 'tools', 'Y', 31);

# Register the tools page for Admin Access Control
INSERT IGNORE INTO admin_pages (page_key, language_key, main_page, page_params, menu_key, display_on_menu, sort_order) VALUES ('catalogProductsNestingManager', 'BOX_PRODUCTS_NESTING_MANAGER', 'FILENAME_PRODUCTS_NESTIN_MANAGER', '', 'tools', 'Y', 31);

# Update to 2.2.0

ALTER TABLE products ADD nestable_group_code varchar(32) NULL; 
DROP TABLE IF EXISTS products_nesting_groups;
CREATE TABLE IF NOT EXISTS products_nesting_groups (
  `grouping_id` int NOT NULL AUTO_INCREMENT,
  `group_code` varchar(32) NULL DEFAULT NULL,
  `compatible_group_code` varchar(32) NULL DEFAULT NULL,
  `nesting_percentage` float(3) NULL DEFAULT NULL,
   PRIMARY KEY ( `grouping_id` )
);

# Register the tools page for Admin Access Control
INSERT IGNORE INTO admin_pages (page_key, language_key, main_page, page_params, menu_key, display_on_menu, sort_order) VALUES ('catalogProductsNestingManager', 'BOX_PRODUCTS_NESTING_MANAGER', 'FILENAME_PRODUCTS_NESTIN_MANAGER', '', 'tools', 'Y', 31);

UPDATE configuration SET configuration_value = '2.2.0' WHERE configuration_key = 'SHIPPING_BOXES_MANAGER_VERSION' LIMIT 1;