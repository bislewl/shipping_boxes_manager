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
      $products = $order->products;
      //echo '<pre>';
      //print_r($products);
      //die('</pre>');
      $volume = $products_length = $total_length = $products_width = $total_width = $products_height = $total_height = $products_price = $weight = $new_total_weight = 0;
      $skipped = 0;
              
      if (is_array($products)) {
        foreach($products as $product) {
          if (substr($product['model'], 0, 4) == 'GIFT' || zen_get_products_virtual((int)$product['id'])) {
            $skipped++;
            continue;
          }
          $products_price += $product['final_price'] * $product['qty'];
          //weight OZ
          $productProperties = $db->Execute('SELECT products_weight, products_length, products_width, products_height, products_ready_to_ship FROM '.TABLE_PRODUCTS.' WHERE products_id='.(int)$product['id']);
          $products_weight = $productProperties->fields['products_weight'] * $product['qty'];
          $new_total_weight += $products_weight;
          // get the longest length and width
          if ($productProperties->fields['products_length'] > $total_length) $total_length = $productProperties->fields['products_length'];
          if ($productProperties->fields['products_width'] > $total_width) $total_width = $productProperties->fields['products_width'];
          // compound the height
          $total_height += $productProperties->fields['products_height'] * $product['qty'];
          $products_length = $productProperties->fields['products_length'];
          $products_width = $productProperties->fields['products_width'];
          $products_height = $productProperties->fields['products_height'];        
          if (is_array($product['attributes'])) {
            foreach ($product['attributes'] as $attribute) {
              $products_price += $attribute['price'] * $product['qty']; 
              $options_value = $attribute['value'];
              // try checking if weight exists for attribute outside of order
              $products_attributes_query = "SELECT * FROM " . TABLE_PRODUCTS_ATTRIBUTES . " pa
                                            LEFT JOIN " . TABLE_PRODUCTS_OPTIONS_VALUES . " pov ON (pov.products_options_values_id = pa.options_values_id) 
                                            WHERE pa.products_id = " . (int)$product['id'] . "
                                            AND pov.products_options_values_name = '" . addslashes($options_value) . "'
                                            LIMIT 1;"; 
              $products_attributes = $db->Execute($products_attributes_query);
              if ($products_attributes->fields['products_attributes_weight_prefix'] == '+') {                                                        
                $products_weight += $products_attributes->fields['products_attributes_weight'] * $product['qty'];
                $new_total_weight += $products_attributes->fields['products_attributes_weight'] * $product['qty'];
              } else if($products_attributes->fields['products_attributes_weight_prefix'] == '-') {
                $products_weight -= $products_attributes->fields['products_attributes_weight'] * $product['qty'];
                $new_total_weight -= $products_attributes->fields['products_attributes_weight'] * $product['qty'];
              }                  
              // adjust attributes
              if ($products_attributes->fields['products_attributes_length_prefix'] == '+') {
                $attribute_length = $products_attributes->fields['products_attributes_length'];
              } elseif ($products_attributes->fields['products_attributes_length_prefix'] == '-') {
                $attribute_length = (0 - $products_attributes->fields['products_attributes_length']);
              }
              if ($products_attributes->fields['products_attributes_width_prefix'] == '+') {
                $attribute_width = $products_attributes->fields['products_attributes_width'];
              } elseif ($products_attributes->fields['products_attributes_width_prefix'] == '-') {
                $attribute_width = (0 - $products_attributes->fields['products_attributes_width']);
              }
              if ($products_attributes->fields['products_attributes_height_prefix'] == '+') {
                $attribute_height = $products_attributes->fields['products_attributes_height'];
              } elseif ($products_attributes->fields['products_attributes_height_prefix'] == '-') {                                                                                     
                $attribute_height = (0 - $products_attributes->fields['products_attributes_height']);
              }            
              // get the longest length and width
              if ($attribute_length + $productProperties->fields['products_length'] > $total_length) $total_length = $productProperties->fields['products_length'] + $attribute_length;
              if ($attribute_width + $productProperties->fields['products_width'] > $total_width) $total_width = $productProperties->fields['products_width'] + $attribute_width;
              // compound the height
              $total_height += $attribute_height * $product['qty']; // already added the product's height
              $products_length += $attribute_length;
              $products_width += $attribute_width;
              $products_height += $attribute_height;
            }
          }
          if ($productProperties->fields['products_length'] <= 0 || $productProperties->fields['products_width'] <= 0 || $productProperties->fields['products_height'] <= 0) {
            // add weight to a default box as this cannot be packed using shipping boxes manager
            $packed_boxes['default']['weight'] += $products_weight;
            continue; 
          }        
          if ($productProperties->fields['products_ready_to_ship']) {
            for ($i=1; $i<=$product['qty']; $i++) {
              $packed_boxes[] = array('length' => $products_length / $product['qty'], 'width' => $products_width / $product['qty'], 'height' => $products_height / $product['qty'], 'weight' => $products_weight / $product['qty']);
            }
          } else {
            $volume = $total_length * $total_width * $total_height;
          }
        }
        
        // recalculate volume taking into consideration the possibility of multiple items per layer
        $new_products_height = 0;
        $products_by_dimension = array();
        foreach($products as $product) {
          if (substr($product['model'], 0, 4) == 'GIFT' || zen_get_products_virtual((int)$product['id'])) {
            continue;
          }
          $hproducts = $db->Execute('SELECT products_length, products_width, products_height, products_ready_to_ship, products_weight FROM '.TABLE_PRODUCTS.' WHERE products_id='.(int)$product['id']);
          if ($hproducts->fields['products_ready_to_ship']) {
            // skip this product
            continue;  
          }
          if ($hproducts->fields['products_length'] <= 0 || $hproducts->fields['products_width'] <= 0 || $hproducts->fields['products_height'] <= 0) {
            // skip this product, it's already packed
            continue;
          }
          $current_products_length = $hproducts->fields['products_length'];
          $current_products_width = $hproducts->fields['products_width'];
          $current_products_height = $hproducts->fields['products_height'];
          if (is_array($product['attributes'])) {
            foreach ($product['attributes'] as $attribute) {
              $products_price += $attribute['price'] * $product['qty']; 
              $options_value = $attribute['value'];
              
              $products_attributes_query = "SELECT * FROM " . TABLE_PRODUCTS_ATTRIBUTES . " pa
                                            LEFT JOIN " . TABLE_PRODUCTS_OPTIONS_VALUES . " pov ON (pov.products_options_values_id = pa.options_values_id) 
                                            WHERE pa.products_id = " . (int)$product['id'] . "
                                            AND pov.products_options_values_name = '" . addslashes($options_value) . "'
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
            }
          }
          $current_dimensions = $current_products_length . 'x' . $current_products_width . 'x' . $current_products_height;
          $match = false;
          for ($i=0; $i<count($products_by_dimension); $i++) {
            if ($current_dimensions == $products_by_dimension[$i]['dimensions']) {
              $match = true;
              $products_by_dimension[$i]['quantity'] += $product['qty'];
              break;  
            }
          }
          if (!$match) {
            $products_by_dimension[] = array('dimensions' => $current_dimensions, 'quantity' => $product['qty']);
          }
        }
        foreach ($products_by_dimension as $product_by_dimension) {
          $current_dimensions = explode('x', $product_by_dimension['dimensions']);
          $current_products_length = $current_dimensions[0];
          $current_products_width = $current_dimensions[1]; 
          $current_products_quantity = $product_by_dimension['quantity'];
          $current_products_height = $current_dimensions[2];
          $current_products_stacked_height = $current_products_height * $product_by_dimension['quantity'];
          if ($current_products_stacked_height > $current_products_length) {
            list($current_products_height, $current_products_length) = array($current_products_length, $current_products_stacked_height);
            if ((!$length_changed && $total_length == $current_products_height) or ($length_changed && $total_length < $current_products_height)) {
              $length_changed = true;
              $total_length = $current_products_length;
            }   
          }
          $box_destination = '';
          if ($order->delivery['country']['id'] == STORE_COUNTRY) {
            $box_destination == 'domestic';
          } else {
            $box_destination == 'international';
          }
          if ($box_destination == 'domestic') {
            $possible_boxes_query_where = "(destination = 'domestic' OR destination = 'both') ";
          } else {
            $possible_boxes_query_where = "(destination = 'international' OR destination = 'both') ";
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
          $num_per_layer = $product_by_dimension['quantity']; 
          // check how many times a product can be stacked hoirzontally within the max length of the box
          if ($current_products_length > $box_length) {
            $individual_products_length = $current_products_length / $product_by_dimension['quantity'];
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
          $new_products_height += $current_products_height * $layers / $product_by_dimension['quantity'];
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
        
        /*
        echo '<pre>';
        print_r($packed_boxes);
        echo '</pre>';
        */
      } 
    }   
  }
}
// eof