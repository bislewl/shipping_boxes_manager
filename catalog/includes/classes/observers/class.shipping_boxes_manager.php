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
        foreach($products as $product) {
          if (substr($product['model'], 0, 4) == 'GIFT' || zen_get_products_virtual((int)$product['id'])) {
            continue;
          }                                   
          $products_properties = $db->Execute('SELECT products_length, products_width, products_height, products_ready_to_ship, products_weight FROM '.TABLE_PRODUCTS.' WHERE products_id='.(int)$product['id']);
          if ($products_properties->fields['products_ready_to_ship']) {
            // skip this product
            for ($i=0; $i<=sizeof($product['quantity']); $i++) {
              $packed_boxes[] = array('length' => $products_properties->fields['products_length'], 'width' => $products_properties->fields['products_width'], 'height' => $products_properties->fields['products_height'], 'weight' => $product['weight'], 'remaining_volume' => 0);
              $new_total_weight += $product['weight'];
            } 
            continue;  
          }
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
              // weight is already added to the product
              /*
              if ($products_attributes->fields['products_attributes_weight_prefix'] == '+') {
                $current_products_weight += $products_attributes->fields['products_attributes_weight'];
              } elseif ($products_attributes->fields['products_attributes_weight_prefix'] == '-') {
                $current_products_weight -= $products_attributes->fields['products_attributes_weight'];
              }
              */                            
            }
          }
          $current_volume = $current_products_length * $current_products_width * $current_products_height;
          $total_volume += $current_volume; 
          for ($i=0; $i<$product['quantity']; $i++) {
            $products_by_dimensions[] = array(
              'dimensions' => array(
                'length' => $current_products_length, 
                'width' => $current_products_width, 
                'height' => $current_products_height
              ), 
              'volume' => $current_volume, 
              'weight' => $current_products_weight, 
              'quantity' => $product['quantity']
            );
            $new_total_weight += $current_products_weight;                                                       
          }
        }      
        $products_by_volume = $this->array_msort($products_by_dimensions, array('volume' => array(SORT_DESC)));
        $total_remaining_volume = $total_volume;
        foreach ($products_by_volume as $current_product_key => $products_properties) {         
          $current_products_length = $products_properties['dimensions']['length'];
          $current_products_width = $products_properties['dimensions']['width']; 
          $current_products_height = $products_properties['dimensions']['height'];
          $current_products_volume = $products_properties['volume'];
          $current_products_weight = $products_properties['weight'];
          $current_products_quantity = $products_properties['quantity'];
          
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
        /*
        echo '<!-- <pre>';
        print_r($packed_boxes);
        echo '</pre> -->';
        */            
        if ($new_total_weight > 0) {
          $GLOBALS['shipping_num_boxes'] = sizeof($packed_boxes); 
          $GLOBALS['total_weight'] = $new_total_weight;
          $GLOBALS['shipping_weight'] = $GLOBALS['total_weight'] / $GLOBALS['shipping_num_boxes'];
          //echo '<!-- ' . $new_total_weight . ' = ' . $GLOBALS['shipping_weight'] . ' x ' . $GLOBALS['shipping_num_boxes'] . ' -->';
        }          
          // END NEW CODE
          
          /*
          $current_products_stacked_height = $current_products_height * $current_products_quantity;
          if ($current_products_stacked_height > $current_products_length) {
            list($current_products_height, $current_products_length) = array($current_products_length, $current_products_stacked_height);
            if ((!$length_changed && $total_length == $current_products_height) or ($length_changed && $total_length < $current_products_height)) {
              $length_changed = true;
              $total_length = $current_products_length;
            }   
          }
          // get the boxes dimensions that could fit these products if they were layered
          $possible_boxes_query = "SELECT length, width, height FROM " . TABLE_SHIPPING_BOXES_MANAGER . " 
                                   WHERE length >= '" . (float)$total_length . "'
                                   AND width >= '" . (float)$total_width . "'
                                   AND height >= '" . (float)$current_products_height . "'
                                   AND " . $possible_boxes_query_where . "
                                   ORDER BY volume ASC
                                   LIMIT 1;";
          $possible_boxes = $db->Execute($possible_boxes_query);
          if ($possible_boxes->RecordCount() > 0) {
            $box_length = $possible_boxes->fields['length'];
            $box_width = $possible_boxes->fields['width'];
            $current_products_quantity = 1; // 1 layer
          } else {
            $box_length = $total_length;
            $box_width = $total_width;
          }
          $num_per_layer = $products_properties['quantity']; 
          // check how many times a product can be stacked hoirzontally within the max length of the box
          if ($current_products_length > $box_length) {
            $individual_products_length = $current_products_length / $products_properties['quantity'];
            // how many times does the individual products length fit into the box length
            $num_per_layer = floor($box_length / $individual_products_length);
            $current_products_length = $num_per_layer * $individual_products_length;
            $current_products_quantity = $new_current_products_quantity = ceil($product_by_dimension['quantity'] / $num_per_layer);
          }
          $current_length_to_length = floor($box_length / $current_products_length); // floor(10 / 3) = 3
          $current_width_to_width = floor($box_width / $current_products_width); // floor (6 / 3) = 2
          $current_layer = $current_length_to_length * $current_width_to_width; // 5 products per layer
          $remaining_quantity = $current_products_quantity; // 4
          $layers = 0;
          while ($remaining_quantity > 0) {
            $layers++;
            if ($current_layer <= $remaining_quantity) { // 3 <= 4
              $remaining_quantity -= $current_layer;
            } else {
              $remaining_quantity = 0;                
            }
          }
          $new_products_height += $current_products_height * $layers / $products_properties['quantity'];
        }
        
        $total_height = $new_products_height;
        $volume = $total_length * $total_width * $total_height;
        $num_boxes = 0;
        while ($volume > 0) {
          // get the smallest volume that could hold the package
          $boxes = $db->Execute("SELECT weight, volume, length, width, height FROM " . TABLE_SHIPPING_BOXES_MANAGER . " 
                                 WHERE volume >= '" . $volume . "'
                                 AND length >= '" . $total_length . "'
                                 AND width >= '" . $total_width . "'
                                 AND height >= '" . $total_height . "'
                                 AND " . $possible_boxes_query_where . "
                                 ORDER BY volume ASC
                                 LIMIT 1;");
          if ($boxes->RecordCount() == 1) {
            // do nothing
          } else {
            // get the biggest box instead
            $boxes = $db->Execute("SELECT weight, volume, height, length, width FROM " . TABLE_SHIPPING_BOXES_MANAGER . " WHERE " . $possible_boxes_query_where . " ORDER BY volume DESC LIMIT 1;");
          }
          $new_total_weight += $boxes->fields['weight'];
          $volume -= $boxes->fields['volume']; // set to zero to break the loop
          $total_height -= $boxes->fields['height']; // subtract the height so that we can use smaller and smaller boxes
          $packed_boxes[] = array('length' => $boxes->fields['length'], 'width' => $boxes->fields['width'], 'height' => $boxes->fields['height'], 'weight' => $boxes->fields['weight']);
        }
        
        if (!$packed_boxes['default']['weight'] > 0) unset($packed_boxes['default']);
        if ($new_total_weight > 0) {
          $GLOBALS['shipping_num_boxes'] = sizeof($packed_boxes); 
          $GLOBALS['total_weight'] = $new_total_weight;
          $GLOBALS['shipping_weight'] = $GLOBALS['total_weight'] / $GLOBALS['shipping_num_boxes'];
        }
        */
        
        /*
        echo '<pre>';
        print_r($packed_boxes);
        echo '</pre>';
        */
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