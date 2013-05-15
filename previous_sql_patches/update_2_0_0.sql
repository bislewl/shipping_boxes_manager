ALTER TABLE shipping_boxes_manager ADD title varchar(32) NULL;

UPDATE configuration SET configuration_value = '2.0.0' WHERE configuration_key = 'SHIPPING_BOXES_MANAGER_VERSION' LIMIT 1;