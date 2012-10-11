<?php
/**
 * @package admin
 * @copyright Copyright 2003-2006 Zen Cart Development Team
 * @copyright Portions Copyright 2003 osCommerce
 * @license http://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 * @version $Id: shipping_boxes_manager.php 2 2010-10-17 05:15:31Z numinix $
 */
if (!defined('IS_ADMIN_FLAG')) {
  die('Illegal Access');
}
  define('FILENAME_SHIPPING_BOXES_MANAGER', 'shipping_boxes_manager');
  define('TABLE_SHIPPING_BOXES_MANAGER', DB_PREFIX . 'shipping_boxes_manager');

define('BOX_SHIPPING_BOXES_MANAGER', 'Shipping Boxes Manager');
define('BOX_SHIPPING_BOXES_MANAGER_CONFIGURATION', 'Shipping Boxes Manager Configuration');