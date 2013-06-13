ALTER TABLE products ADD nestable_percentage float(12) NULL;

UPDATE configuration SET configuration_value = '2.1.1' WHERE configuration_key = 'SHIPPING_BOXES_MANAGER_VERSION' LIMIT 1;