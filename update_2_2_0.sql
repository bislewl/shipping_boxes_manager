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