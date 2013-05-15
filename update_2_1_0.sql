ALTER TABLE products ADD nestable tinyint(1) NULL;

UPDATE configuration SET configuration_value = '2.1.0' WHERE configuration_key = 'SHIPPING_BOXES_MANAGER_VERSION' LIMIT 1;