# Uncommenting the two lines below will result in permanent data loss:
#DROP TABLE IF EXISTS shipping_boxes_manager;
#DROP TABLE IF EXISTS products_nesting_groups;

SELECT (@configuration_group_id:=configuration_group_id) 
FROM configuration_group 
WHERE configuration_group_title = 'Shipping Boxes Manager Configuration' 
LIMIT 1;
DELETE FROM configuration WHERE configuration_group_id = @configuration_group_id AND @configuration_group_id != 0;
DELETE FROM configuration_group WHERE configuration_group_id = @configuration_group_id AND @configuration_group_id != 0;

#Zen Cart v1.5.0+ only Below! Skip if using an older version!
DELETE FROM admin_pages WHERE page_key = 'toolsShippingBoxesManager' LIMIT 1;
DELETE FROM admin_pages WHERE page_key = 'configShippingBoxesManager' LIMIT 1;
DELETE FROM admin_pages WHERE page_key = 'catalogProductsNestingManager' LIMIT 1;