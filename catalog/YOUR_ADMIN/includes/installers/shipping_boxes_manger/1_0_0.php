<?php

// use $configuration_group_id where needed
// For Admin Pages
$zc150 = (PROJECT_VERSION_MAJOR > 1 || (PROJECT_VERSION_MAJOR == 1 && substr(PROJECT_VERSION_MINOR, 0, 3) >= 5));
if ($zc150) { // continue Zen Cart 1.5.0
    $admin_page = 'configShippingBoxesManager';
    // delete configuration menu
    $db->Execute("DELETE FROM " . TABLE_ADMIN_PAGES . " WHERE page_key = '" . $admin_page . "' LIMIT 1;");
    // add configuration menu
    if (!zen_page_key_exists($admin_page)) {
        if ((int) $configuration_group_id > 0) {
            zen_register_admin_page($admin_page, 'BOX_SHIPPING_BOXES_MANAGER_CONFIGURATION', 'FILENAME_CONFIGURATION', 'gID=' . $configuration_group_id, 'configuration', 'Y', $configuration_group_id);

            $messageStack->add('Enabled Shipping Boxes Manager Configuration Menu.', 'success');
        }
    }
    $admin_page = 'toolsShippingBoxesManager';
    // delete configuration menu
    $db->Execute("DELETE FROM " . TABLE_ADMIN_PAGES . " WHERE page_key = '" . $admin_page . "' LIMIT 1;");
    // add configuration menu
    if (!zen_page_key_exists($admin_page)) {
        if ((int) $configuration_group_id > 0) {
            zen_register_admin_page($admin_page, 'BOX_SHIPPING_BOXES_MANAGER', 'FILENAME_SHIPPING_BOXES_MANAGER', '', 'tools', 'Y', $configuration_group_id);

            $messageStack->add('Enabled Shipping Boxes Manager Tools Menu Item.', 'success');
        }
    }
    $admin_page = 'catalogProductsNestingManager';
    // delete configuration menu
    $db->Execute("DELETE FROM " . TABLE_ADMIN_PAGES . " WHERE page_key = '" . $admin_page . "' LIMIT 1;");
    // add configuration menu
    if (!zen_page_key_exists($admin_page)) {
        if ((int) $configuration_group_id > 0) {
            zen_register_admin_page($admin_page, 'BOX_PRODUCTS_NESTING_MANAGER', 'FILENAME_PRODUCTS_NESTIN_MANAGER', '', 'catalog', 'Y', $configuration_group_id);

            $messageStack->add('Enabled Shipping Boxes Manager Tools Menu Item.', 'success');
        }
    }
}

$db->Execute("CREATE TABLE IF NOT EXISTS " . TABLE_SHIPPING_BOXES_MANAGER . " ( 
  `box_id` int NOT NULL AUTO_INCREMENT,
  `length` float NOT NULL default '0',
  `width` float NOT NULL default '0',
  `height` float NOT NULL DEFAULT '0',
  `weight` float NOT NULL DEFAULT '0',
  `volume` float NOT NULL DEFAULT '0',
   PRIMARY KEY ( `box_id` )
);");
