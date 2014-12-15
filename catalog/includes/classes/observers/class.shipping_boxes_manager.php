<?php

//
// +----------------------------------------------------------------------+
// |zen-cart Open Source E-commerce                                       |
// +----------------------------------------------------------------------+
// | Copyright (c) 2007-2008 Numinix Technology http://www.numinix.com    |
// |                                                                      |
// | Portions Copyright (c) 2003-2006 Zen Cart Development Team           |
// | http://www.zen-cart.com/index.php                                    |
// |                                                                      |
// | Portions Copyright (c) 2003 osCommerce                               |
// +----------------------------------------------------------------------+
// | This source file is subject to version 2.0 of the GPL license,       |
// | that is bundled with this package in the file LICENSE, and is        |
// | available through the world-wide-web at the following url:           |
// | http://www.zen-cart.com/license/2_0.txt.                             |
// | If you did not receive a copy of the zen-cart license and are unable |
// | to obtain it through the world-wide-web, please send a note to       |
// | license@zen-cart.com so we can mail you a copy immediately.          |
// +----------------------------------------------------------------------+
//  $Id: class.shipping_boxes_manager.php 85 2010-04-20 00:49:04Z numinix $
//
/**
 * Observer class used to redirect to the FEC page
 *
 */
class shippingBoxesManagerObserver extends base {

    function shippingBoxesManagerObserver() {
        global $zco_notifier;
        $zco_notifier->attach($this, array('NOTIFY_SHIPPING_MODULE_CALCULATE_BOXES_AND_TARE'));
    }

    function update(&$class, $eventID, $paramsArray) {
        global $order, $db, $packed_boxes;
        if (MODULE_SHIPPING_BOXES_MANAGER_STATUS == 'true') {
            $packer = new Packer();
            $packed_boxes = array('default' => array('weight' => 0));
            $products = $_SESSION['cart']->get_products();
            //echo '<pre>';
            //print_r($products);
            //die('</pre>');                                                                                               
            $total_volume = $volume = $products_length = $max_length = $products_width = $max_width = $products_height = $max_height = $products_price = $weight = $new_total_weight = 0;
            $skipped = 0;
            $box_destination = '';
            if ($order->delivery['country']['id'] == STORE_COUNTRY) {
                $box_destination = 'domestic';
            } else {
                $box_destination = 'international';
            }
            if ($box_destination == 'domestic') {
                $possible_boxes_query_where = "(destination = 'domestic' OR destination = 'both') ";
            } else {
                $possible_boxes_query_where = "(destination = 'international' OR destination = 'both') ";
            }
            $all_boxes = $db->Execute("SELECT * FROM " . TABLE_SHIPPING_BOXES_MANAGER . " WHERE " . $possible_boxes_query_where);
            while (!$all_boxes->EOF) {
                if ($all_boxes->fields['inner_width'] == 0) {
                    $inner_width = $all_boxes->fields['outer_width'] * 0.9;
                } else {
                    $inner_width = $all_boxes->fields['outer_width'];
                }
                if ($all_boxes->fields['inner_length'] == 0) {
                    $inner_length = $all_boxes->fields['outer_length'] * 0.9;
                } else {
                    $inner_length = $all_boxes->fields['inner_length'];
                }
                if ($all_boxes->fields['inner_depth'] == 0) {
                    $inner_depth = $all_boxes->fields['outer_depth'] * 0.9;
                } else {
                    $inner_depth = $all_boxes->fields['inner_depth'];
                }
                if ($all_boxes->fields['max_weight'] == 0) {
                    $max_weight = 999999;
                } else {
                    $max_weight = $all_boxes->fields['max_weight'];
                }
                $box = new TestBox($all_boxes->fields['box_id'], $all_boxes->fields['outer_width'], $all_boxes->fields['outer_length'], $all_boxes->fields['outer_depth'], $all_boxes->fields['empty_weight'], $inner_width, $inner_length, $inner_depth, $max_weight);
                $packer->addBox($box);
                $all_boxes->MoveNext();
            }
            if (is_array($products)) {
                $products_by_dimensions = array();
                $packed_boxes = array();
                //$nestable = array();
                foreach ($products as $product) {
                    if (substr($product['model'], 0, 4) == 'GIFT' || zen_get_products_virtual((int) $product['id'])) {
                        continue;
                    }
                    $products_properties = $db->Execute('SELECT products_length, products_width, products_height, products_ready_to_ship, products_weight, nestable, nestable_percentage, nestable_group_code FROM ' . TABLE_PRODUCTS . ' WHERE products_id=' . (int) $product['id']);

                    //add or subtract from dimensions based on attributes
                    $current_products_length = $products_properties->fields['products_length'];
                    $current_products_width = $products_properties->fields['products_width'];
                    $current_products_height = $products_properties->fields['products_height'];
                    $current_products_weight = $products_properties->fields['products_weight'];

                    if (is_array($product['attributes'])) {
                        foreach ($product['attributes'] as $options_id => $options_values_id) {
                            //$products_price += $attribute['price'] * $product['quantity']; 
                            //$options_value = $attribute['value'];

                            $products_attributes_query = "SELECT * FROM " . TABLE_PRODUCTS_ATTRIBUTES . " pa
                                            LEFT JOIN " . TABLE_PRODUCTS_OPTIONS_VALUES . " pov ON (pov.products_options_values_id = pa.options_values_id) 
                                            WHERE pa.products_id = " . (int) $product['id'] . "
                                            AND pov.products_options_values_id = " . (int) $options_values_id . "
                                            LIMIT 1;";
                            $products_attributes = $db->Execute($products_attributes_query);

                            if ($products_attributes->fields['products_attributes_length_prefix'] == '+') {
                                $current_products_length += $products_attributes->fields['products_attributes_length'];
                            } elseif ($products_attributes->fields['products_attributes_length_prefix'] == '-') {
                                $current_products_length -= $products_attributes->fields['products_attributes_length'];
                            }
                            if ($products_attributes->fields['products_attributes_width_prefix'] == '+') {
                                $current_products_width += $products_attributes->fields['products_attributes_width'];
                            } elseif ($products_attributes->fields['products_attributes_width_prefix'] == '-') {
                                $current_products_width -= $products_attributes->fields['products_attributes_width'];
                            }
                            if ($products_attributes->fields['products_attributes_height_prefix'] == '+') {
                                $current_products_height += $products_attributes->fields['products_attributes_height'];
                            } elseif ($products_attributes->fields['products_attributes_height_prefix'] == '-') {
                                $current_products_height -= $products_attributes->fields['products_attributes_height'];
                            }
                            if ($products_attributes->fields['products_attributes_weight_prefix'] == '+') {
                                $current_products_weight += $products_attributes->fields['products_attributes_weight'];
                            } elseif ($products_attributes->fields['products_attributes_weight_prefix'] == '-') {
                                $current_products_weight -= $products_attributes->fields['products_attributes_weight'];
                            }
                        }
                    }


                    if ($products_properties->fields['products_ready_to_ship']) {
                        //print_r($product);
                        // skip this product
                        for ($i = 0; $i < sizeof($product['quantity']); $i++) {
                            $pre_packed_boxes[] = array('box ID' => 'ready to ship', 'length' => $current_products_length, 'width' => $current_products_width, 'height' => $current_products_height, 'weight' => $current_products_weight, 'remaining_volume' => 0);
                            $new_total_weight += $product['weight'];
                        }
                        continue;
                    }
                    
                    for ($i = 0; $i < $product['quantity']; $i++) {
                        $item = new TestItem($i,$current_products_width,$current_products_length,$current_products_height,$current_products_weight);
                        $packer->addItem($item);
                    }
                }
                $packed_boxes = $packer->pack();
                
                if (MODULE_SHIPPING_BOXES_MANAGER_DEBUG == 'true') {
                    echo '<p>Packages</p>';
                    echo '<pre>';
                    print_r($packed_boxes);
                    echo '</pre>';
                }
                
                if ($new_total_weight > 0) {
                    $GLOBALS['shipping_num_boxes'] = sizeof($packed_boxes);
                    $GLOBALS['total_weight'] = $new_total_weight;
                    $GLOBALS['shipping_weight'] = $GLOBALS['total_weight'] / $GLOBALS['shipping_num_boxes'];
                    //echo '<!-- ' . $new_total_weight . ' = ' . $GLOBALS['shipping_weight'] . ' x ' . $GLOBALS['shipping_num_boxes'] . ' -->';
                }
            }
        }
    }

    function array_msort($array, $cols) {
        $colarr = array();
        foreach ($cols as $col => $order) {
            $colarr[$col] = array();
            foreach ($array as $k => $row) {
                $colarr[$col]['_' . $k] = strtolower($row[$col]);
            }
        }
        $params = array();
        foreach ($cols as $col => $order) {
            $params[] = & $colarr[$col];
            $params = array_merge($params, (array) $order);
        }
        call_user_func_array('array_multisort', $params);
        $ret = array();
        $keys = array();
        $first = true;
        foreach ($colarr as $col => $arr) {
            foreach ($arr as $k => $v) {
                if ($first) {
                    $keys[$k] = substr($k, 1);
                }
                $k = $keys[$k];
                if (!isset($ret[$k]))
                    $ret[$k] = $array[$k];
                $ret[$k][$col] = $array[$k][$col];
            }
            $first = false;
        }
        return $ret;
    }

}

// eof
