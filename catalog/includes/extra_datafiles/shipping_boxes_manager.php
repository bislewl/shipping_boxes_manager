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
  define('TABLE_SHIPPING_BOXES_MANAGER', DB_PREFIX . 'shipping_boxes_manager');
  define('TABLE_PRODUCTS_NESTING_GROUPS', DB_PREFIX . 'products_nesting_groups');