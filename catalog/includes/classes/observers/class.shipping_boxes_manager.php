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
class shippingBoxesManagerObserver extends base 
{
  function shippingBoxesManagerObserver()
  {
    global $zco_notifier;
    $zco_notifier->attach($this, array('NOTIFY_SHIPPING_MODULE_CALCULATE_BOXES_AND_TARE'));
  }
  
  function update(&$class, $eventID, $paramsArray) {
    global $order, $db, $packed_boxes;
    if (MODULE_SHIPPING_BOXES_MANAGER_STATUS == 'true') {
      $packed_boxes = array('default' => array('weight' => 0));
      $products = $_SESSION['cart']->get_products();
      //echo '<pre>';
      //print_r($products);
      //die('</pre>');
      $total_volume = $volume = $products_length = $total_length = $products_width = $total_width = $products_height = $total_height = $products_price = $weight = $new_total_weight = 0;
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
      if (is_array($products)) {        
        $products_by_dimensions = array();                                                                                                      
        $packed_boxes = array();
        //$nestable = array();
        foreach($products as $product) {
          if (substr($product['model'], 0, 4) == 'GIFT' || zen_get_products_virtual((int)$product['id'])) {
            continue;
          }                                   
          $products_properties = $db->Execute('SELECT products_length, products_width, products_height, products_ready_to_ship, products_weight, nestable FROM '.TABLE_PRODUCTS.' WHERE products_id='.(int)$product['id']);
          if ($products_properties->fields['products_ready_to_ship']) {
            // skip this product
            for ($i=0; $i<=sizeof($product['quantity']); $i++) {
              $packed_boxes[] = array('length' => $products_properties->fields['products_length'], 'width' => $products_properties->fields['products_width'], 'height' => $products_properties->fields['products_height'], 'weight' => $product['weight'], 'remaining_volume' => 0);
              $new_total_weight += $product['weight'];
            } 
            continue;  
          }
          // check if product is nestable
          
          if ($products_properties->fields['products_length'] <= 0 || $products_properties->fields['products_width'] <= 0 || $products_properties->fields['products_height'] <= 0) {
            // pack this product into a default array   
            $packed_boxes['default'] = array('weight' => $products_properties->fields['products_weight'] * $product['quantity'], 'remaining_volume' => 0);
            $new_total_weight += ($products_properties->fields['products_weight'] * $product['quantity']);
            continue;
          }
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
                                            WHERE pa.products_id = " . (int)$product['id'] . "
                                            AND pov.products_options_values_id = " . (int)$options_values_id . "
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
          $current_volume = $current_products_length * $current_products_width * $current_products_height;
          // add current products volume to total volume
          $total_volume += $current_volume; 
          // build an array for each product in the cart that contains all of its physical attributes
          for ($i=0; $i<$product['quantity']; $i++) {
            $products_by_dimensions[] = array(
              'dimensions' => array(
                'length' => $current_products_length, 
                'width' => $current_products_width, 
                'height' => $current_products_height
              ), 
              'volume' => $current_volume, 
              'weight' => $current_products_weight,
              'nestable' => $products_properties->fields['nestable']  
              //'quantity' => $product['quantity'],
            );           
            $new_total_weight += $current_products_weight;                                                       
          }
        }
        //echo '<!-- ';
        /*
        echo '<pre>';
        print_r($products_by_dimensions); 
        echo '</pre>';
        */ 
        //echo ' -->';
        // loop through products til one larger nestable product is found
        $found_nestable = true; // default
        while($found_nestable) {
          $found_nestable = false; // set to false to avoid infinite loop                                                                                                                 
          foreach($products_by_dimensions as $key => $product_properties) {
          //for ($j=0; $j<sizeof($products_by_dimensions); $j++) {                                                                                    
            // first nestable product found
            if ($product_properties['nestable'] == 1) {
              // loop through products and find another nestable product
              foreach($products_by_dimensions as $key2 => $product_properties2) {                                                      
              //for ($i=0; $i<sizeof($products_by_dimensions); $i++) {
                // if product is nestable and not the current product we are on
                if ($product_properties2['nestable'] == 1 && $key != $key2) {
                  // check if product is larger
                  if ($product_properties2['volume'] >= $product_properties['volume'] && 
                    $product_properties2['dimensions']['length'] >= $product_properties['dimensions']['length'] && 
                    $product_properties2['dimensions']['width'] >= $product_properties['dimensions']['width'] && 
                    $product_properties2['dimensions']['height'] >= $product_properties['dimensions']['height']) 
                  {
                    // add weight of current product to the larger product
                    $products_by_dimensions[$key2]['weight'] += $products_by_dimensions[$key]['weight'];
                    // unset smaller product 
                    unset($products_by_dimensions[$key]);                                                                                                      
                    $found_nestable = true;
                    continue 2; 
                  }/* elseif ($product_properties['volume'] >= $product_properties2['volume'] && $product_properties['dimensions']['length'] >= $product_properties2['dimensions']['length'] && $product_properties['dimensions']['width'] >= $product_properties2['dimensions']['width'] && $product_properties['dimensions']['height'] >= $product_properties2['dimensions']['height']) {
                    // check the opposite direction
                    // add weight of current product to the larger product
                    $products_by_dimensions[$key]['weight'] += $product_properties2['weight'];
                    // unset smaller product 
                    unset($products_by_dimensions[$key2]);
                    $found_nestable = true; 
                  }*/
                }
              }
            } 
          }
        }
        //echo '<!-- ';
        /*        
        echo '<pre>';
        print_r($products_by_dimensions); 
        echo '</pre>';
        */
        //echo ' -->';      
        // sort the array by volume, largest to smallest product     
        $products_by_volume = $this->array_msort($products_by_dimensions, array('volume' => array(SORT_DESC)));
        $total_remaining_volume = $total_volume;
        foreach ($products_by_volume as $current_product_key => $products_properties) {         
          $current_products_length = $products_properties['dimensions']['length'];
          $current_products_width = $products_properties['dimensions']['width']; 
          $current_products_height = $products_properties['dimensions']['height'];
          $current_products_volume = $products_properties['volume'];
          $current_products_weight = $products_properties['weight'];
          //$current_products_quantity = $products_properties['quantity'];
          
          if (sizeof($packed_boxes) > 0) {
            // sort it by remaining volume
            $packed_boxes = $this->array_msort($packed_boxes, array('remaining_volume' => array(SORT_DESC)));
            $found_box = false;
            foreach ($packed_boxes as $current_box_key => $box_properties) {
              if ($box_properties['remaining_volume'] >= $current_products_volume) {
                $packed_boxes[$current_box_key]['remaining_volume'] = $box_properties['remaining_volume'] - $current_products_volume;
                $total_remaining_volume -= $current_products_volume;
                $found_box = true;
                break;
              }
            }
          }
          if ($found_box == false) {
            // get the smallest box that will fit all the products
            $box = $db->Execute("SELECT length, width, height, volume FROM " . TABLE_SHIPPING_BOXES_MANAGER . "
                                 WHERE length >= '" . $current_products_length . "'
                                 AND width >= '" . $current_products_width . "'
                                 AND height >= '" . $current_products_height . "'
                                 AND volume >= '" . $total_remaining_volume . "'
                                 AND " . $possible_boxes_query_where . "
                                 ORDER BY volume ASC
                                 LIMIT 1;");
            if ($box->RecordCount() > 0) {          
              $remaining_volume = $box->fields['volume'] - $current_products_volume;
              $packed_boxes[] = array('length' => $box->fields['length'], 'width' => $box->fields['width'], 'height' => $box->fields['height'], 'weight' => $box->fields['weight'] + $current_products_weight, 'remaining_volume' => $remaining_volume);
            } else {
              $packed_boxes[] = array('length' => $current_products_length, 'width' => $box->fields['width'], 'height' => $boxes->fields['height'], 'weight' => $box->fields['weight'] + $current_products_weight, 'remaining_volume' => 0);
            }
            $new_total_weight += $box->fields['weight']; 
            $total_remaining_volume -= $current_products_volume;
          }
        }
          
        if (!$packed_boxes['default']['weight'] > 0) unset($packed_boxes['default']);
        //echo '<!-- ';
        /*
        echo '<pre>';
        print_r($packed_boxes);
        echo '</pre>';
        */
        //echo ' -->';
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
        foreach ($array as $k => $row) { $colarr[$col]['_'.$k] = strtolower($row[$col]); }
    }
    $params = array();
    foreach ($cols as $col => $order) {
        $params[] =& $colarr[$col];
        $params = array_merge($params, (array)$order);
    }
    call_user_func_array('array_multisort', $params);
    $ret = array();
    $keys = array();
    $first = true;
    foreach ($colarr as $col => $arr) {
        foreach ($arr as $k => $v) {
            if ($first) { $keys[$k] = substr($k,1); }
            $k = $keys[$k];
            if (!isset($ret[$k])) $ret[$k] = $array[$k];
            $ret[$k][$col] = $array[$k][$col];
        }
        $first = false;
    }
    return $ret;
  }  
}
// eof