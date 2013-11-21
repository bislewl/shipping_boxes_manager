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
      if (is_array($products)) {        
        $products_by_dimensions = array();                                                                                                      
        $packed_boxes = array();
        //$nestable = array();
        foreach($products as $product) {
          if (substr($product['model'], 0, 4) == 'GIFT' || zen_get_products_virtual((int)$product['id'])) {
            continue;
          }                                   
          $products_properties = $db->Execute('SELECT products_length, products_width, products_height, products_ready_to_ship, products_weight, nestable, nestable_percentage, nestable_group_code FROM '.TABLE_PRODUCTS.' WHERE products_id='.(int)$product['id']);
          if ($products_properties->fields['products_ready_to_ship']) {
          	//print_r($product);
            // skip this product
            for ($i=0; $i<sizeof($product['quantity']); $i++) {
              $packed_boxes[] = array('box ID' => 'ready to ship', 'length' => $products_properties->fields['products_length'], 'width' => $products_properties->fields['products_width'], 'height' => $products_properties->fields['products_height'], 'weight' => $product['weight'], 'remaining_volume' => 0);
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
          //$total_volume += $current_volume;
          // set the max length, width, and height (height will be recalculated if nesting is enabled lower down in the code)
          if ($current_products_length > $max_length) $max_length = $current_products_length;
          if ($current_products_width > $max_width) $max_width = $current_products_width; 
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
              'nestable' => $products_properties->fields['nestable'],
              'nestable_percentage' => $products_properties->fields['nestable_percentage'],
              'nestable_group_code' => $products_properties->fields['nestable_group_code']   
              //'quantity' => $product['quantity'],
            );           
            $new_total_weight += $current_products_weight;
            // stack the heights
          	$max_height += $current_products_height;                                                       
          }
        }
        //echo '<!-- ';
        /**/
        if (MODULE_SHIPPING_BOXES_MANAGER_DEBUG == 'true') {
	        echo '<p>Products (before nesting):</p>';
	        echo '<pre>';
	        print_r($products_by_dimensions); 
	        echo '</pre>';
				}
        /**/ 
        //echo ' -->';
        // loop through products til one larger nestable product is found
        //$products_by_dimensions2 = $products_by_dimensions;
        $found_nestable = true; // default
        while($found_nestable) {
          $found_nestable = false; // set to false to avoid infinite loop
          foreach($products_by_dimensions as $key => $product_properties) {
          //for ($j=0; $j<sizeof($products_by_dimensions); $j++) {                                                                                    
            // first nestable product found
            if ($products_by_dimensions[$key]['nestable'] == 1) {
              // loop through products and find another nestable product that this product can fit inside
              foreach($products_by_dimensions as $key2 => $product_properties2) {
              //for ($i=0; $i<sizeof($products_by_dimensions); $i++) {
                // if product is nestable and not the current product we are on
                if ($products_by_dimensions[$key2]['nestable'] == 1 && $key != $key2) {
                	// check that product 2 is nestable inside product 1
                	$products_nestable = false;
                	if ($products_by_dimensions[$key2]['nestable_group_code'] == '' && $products_by_dimensions[$key]['nestable_group_code'] == '') $products_nestable = true;
                	if ($products_by_dimensions[$key2]['nestable_group_code'] != '' && $products_by_dimensions[$key]['nestable_group_code'] != '') {
                		$minimum_nesting_percentage = 0;
                		$nesting_groups_1 = explode(',', $products_by_dimensions[$key2]['nestable_group_code']);
                		$nesting_groups_2 = explode(',', $products_by_dimensions[$key]['nestable_group_code']);
                		foreach($nesting_groups_1 as $nesting_group_1) {
                			foreach($nesting_groups_2 as $nesting_group_2) {
                				// check that products can nest
                				$products_nestable = $db->Execute("SELECT nesting_percentage FROM " . TABLE_PRODUCTS_NESTING_GROUPS . " WHERE group_code = '" . $nesting_group_1 . "' AND compatible_group_code = '" . $nesting_group_2 . "' LIMIT 1;");
												if ($products_nestable->RecordCount() > 0) {
													if ($minimum_nesting_percentage < $products_nestable->fields['nesting_percentage']) {
														$minimum_nesting_percentage = $products_nestable->fields['nesting_percentage'];
													}
													$products_nestable = true;
												} else {
													$products_nestable = false;
													break 2;
												}                				
											}
										}
										if ($products_nestable) {
											$products_by_dimensions[$key2]['nestable_percentage'] = $minimum_nesting_percentage;
										}
									}
									if ($products_nestable == false) {
										continue;
									}
                	/*
                	echo 'Comparing:<br /><pre>';
                	print_r($products_by_dimensions[$key]);
                	print_r($products_by_dimensions[$key2]);
                	echo '</pre>';
                	*/
                  // check if product is larger and moving forward keep the $products_by_dimensions[$key2] product as the larger product
                  if (/*$products_by_dimensions[$key]['volume'] >= $products_by_dimensions[$key2]['volume'] &&*/ 
                    $products_by_dimensions[$key]['dimensions']['length'] >= $products_by_dimensions[$key2]['dimensions']['length'] && 
                    $products_by_dimensions[$key]['dimensions']['width'] >= $products_by_dimensions[$key2]['dimensions']['width']/* &&
                    $products_by_dimensions[$key]['dimensions']['height'] >= $products_by_dimensions[$key2]['dimensions']['height']*/) 
                  {
                  	$new_nested_height = 0;
                  	// if products are the same size and the nestable percentage is 100
                  	if ($products_by_dimensions[$key2]['nestable_percentage'] == 100) { //|| (($products_by_dimensions[$key2]['dimensions']['length'] > $products_by_dimensions[$key]['dimensions']['length'] && $products_by_dimensions[$key2]['dimensions']['width'] > $products_by_dimensions[$key]['dimensions']['width']) && $products_by_dimensions[$key2]['dimensions']['height'] > ($products_by_dimensions[$key]['nestable_percentage'] != '' ? $products_by_dimensions[$key]['nestable_percentage'] / 100 * $products_by_dimensions[$key]['dimensions']['height'] : $products_by_dimensions[$key]['dimensions']['height']))) {
	                    // keep the height set to the larger product
	                    //echo $products_by_dimensions[$key2]['dimensions']['height'] . ' ' . $products_by_dimensions[$key]['dimensions']['height'] . '<br />';
	                    //if ($products_by_dimensions[$key]['dimensions']['height'] >= $products_by_dimensions[$key2]['dimensions']['height']) {
                    		$new_nested_height = $products_by_dimensions[$key]['dimensions']['height'];
											//} else {
												//$new_nested_height = $products_by_dimensions[$key2]['dimensions']['height'];
											//}
											//echo $new_nested_height . '<br />';										
										} elseif (/*$products_by_dimensions[$key]['dimensions']['height'] >= $products_by_dimensions[$key2]['dimensions']['height'] && */$products_by_dimensions[$key2]['nestable_percentage'] < 100) {
											// products are the same size but nest partially
	                    // set the height to the taller of the two products
	                    $nestable_percentage = 1 - $products_by_dimensions[$key2]['nestable_percentage'] / 100;
                    	$new_nested_height = $products_by_dimensions[$key2]['dimensions']['height'] + ($nestable_percentage * $products_by_dimensions[$key]['dimensions']['height']);
                    	//echo $new_nested_height . '<br />';
                    	$new_nested_volume = $products_by_dimensions[$key2]['dimensions']['length'] * $products_by_dimensions[$key2]['dimensions']['width'] * $new_nested_height;  
										} else {
											continue;
										}
										
										// check that a large enough box exists for the nested product
										$available_boxes = $db->Execute("SELECT box_id FROM " . TABLE_SHIPPING_BOXES_MANAGER . "
																                     WHERE length >= '" . $products_by_dimensions[$key2]['dimensions']['length'] . "'
																                     AND width >= '" . $products_by_dimensions[$key2]['dimensions']['width'] . "'
																                     AND height >= '" . $new_nested_height . "'
																                     AND volume >= '" . $new_nested_volume . "'
																                     AND " . $possible_boxes_query_where . "
																                     ORDER BY volume ASC
																                     LIMIT 1;"); 
										// large box exists
										if ($available_boxes->RecordCount() > 0) {
											$old_height = $products_by_dimensions[$key]['dimensions']['height'];
	                    // set the new height
	                    $products_by_dimensions[$key]['dimensions']['height'] = $new_nested_height;
	                    // set the new volume
	                    $products_by_dimensions[$key]['volume'] = $new_nested_volume;
	                    // add weight of current product to the larger product
	                    $products_by_dimensions[$key]['weight'] += $products_by_dimensions[$key2]['weight'];
	                    // subtract the height of the smaller product from the max height and subtract the old height of the currently nested product, then add the new nested height
	                    $max_height = ($max_height - $old_height - $products_by_dimensions[$key2]['dimensions']['height']) + $new_nested_height;;
	                    //echo 'set 1: ' . $key . ': old height: ' . $old_height . ' new height: ' . $new_nested_height . ' max height: ' . $max_height . '<br />'; 	                    
	                    // unset smaller product
	                    //echo 'unset: ' . $key2 . '<br />';
											//echo '<pre>';
											//print_r($products_by_dimensions[$key]);
											//echo '</pre>'; 	                     
	                    unset($products_by_dimensions[$key2]);
	                    $found_nestable = true;
	                    	                    
	                    break;
										} else {
											// large box does not exist
											// stop future nesting of this product
											$products_by_dimensions[$key]['nestable'] = 0; 											
											//continue;
										}
                  } elseif (/*$products_by_dimensions[$key2]['volume'] >= $products_by_dimensions[$key]['volume'] &&*/ 
                    $products_by_dimensions[$key2]['dimensions']['length'] >= $products_by_dimensions[$key]['dimensions']['length'] && 
                    $products_by_dimensions[$key2]['dimensions']['width'] >= $products_by_dimensions[$key]['dimensions']['width']/* &&
                    $products_by_dimensions[$key2]['dimensions']['height'] >= $products_by_dimensions[$key]['dimensions']['height']*/) 
                  {
                  	$new_nested_height = 0;
                  	// if products are the same size and the nestable percentage is 100
                  	if ($products_by_dimensions[$key2]['nestable_percentage'] == 100) { //|| (($products_by_dimensions[$key2]['dimensions']['length'] > $products_by_dimensions[$key]['dimensions']['length'] && $products_by_dimensions[$key2]['dimensions']['width'] > $products_by_dimensions[$key]['dimensions']['width']) && $products_by_dimensions[$key2]['dimensions']['height'] > ($products_by_dimensions[$key]['nestable_percentage'] != '' ? $products_by_dimensions[$key]['nestable_percentage'] / 100 * $products_by_dimensions[$key]['dimensions']['height'] : $products_by_dimensions[$key]['dimensions']['height']))) {
	                    // keep the height set to the larger product
	                    //echo $products_by_dimensions[$key2]['dimensions']['height'] . ' ' . $products_by_dimensions[$key]['dimensions']['height'] . '<br />';
	                    //if ($products_by_dimensions[$key]['dimensions']['height'] >= $products_by_dimensions[$key2]['dimensions']['height']) {
                    		$new_nested_height = $products_by_dimensions[$key2]['dimensions']['height'];
											//} else {
												//$new_nested_height = $products_by_dimensions[$key2]['dimensions']['height'];
											//}
											//echo $new_nested_height . '<br />';										
										} elseif (/*$products_by_dimensions[$key]['dimensions']['height'] >= $products_by_dimensions[$key2]['dimensions']['height'] && */$products_by_dimensions[$key2]['nestable_percentage'] < 100) {
											// products are the same size but nest partially
	                    // set the height to the taller of the two products
	                    $nestable_percentage = 1 - $products_by_dimensions[$key]['nestable_percentage'] / 100;
                    	$new_nested_height = $products_by_dimensions[$key]['dimensions']['height'] + ($nestable_percentage * $products_by_dimensions[$key2]['dimensions']['height']);
                    	//echo $new_nested_height . '<br />';
                    	$new_nested_volume = $products_by_dimensions[$key]['dimensions']['length'] * $products_by_dimensions[$key]['dimensions']['width'] * $new_nested_height;  
										} else {
											continue;
										}
										
										// check that a large enough box exists for the nested product
										$available_boxes = $db->Execute("SELECT box_id FROM " . TABLE_SHIPPING_BOXES_MANAGER . "
																                     WHERE length >= '" . $products_by_dimensions[$key]['dimensions']['length'] . "'
																                     AND width >= '" . $products_by_dimensions[$key]['dimensions']['width'] . "'
																                     AND height >= '" . $new_nested_height . "'
																                     AND volume >= '" . $new_nested_volume . "'
																                     AND " . $possible_boxes_query_where . "
																                     ORDER BY volume ASC
																                     LIMIT 1;"); 
										// large box exists
										if ($available_boxes->RecordCount() > 0) {
											$old_height = $products_by_dimensions[$key2]['dimensions']['height'];
	                    // set the new height
	                    $products_by_dimensions[$key2]['dimensions']['height'] = $new_nested_height;
	                    // set the new volume
	                    $products_by_dimensions[$key2]['volume'] = $new_nested_volume;
	                    // add weight of current product to the larger product
	                    $products_by_dimensions[$key2]['weight'] += $products_by_dimensions[$key]['weight'];
	                    // subtract the height of the smaller product from the max height and subtract the old height of the currently nested product, then add the new nested height
	                    $max_height = ($max_height - $old_height - $products_by_dimensions[$key]['dimensions']['height']) + $new_nested_height;;
	                    //echo 'set 2: ' . $key . ': old height: ' . $old_height . ' new height: ' . $new_nested_height . ' max height: ' . $max_height . '<br />'; 	                    
	                    // unset smaller product
	                    //echo 'unset: ' . $key2 . '<br />';
	                    // check to see if the nesting group will need to be updated
	                    if ($products_by_dimensions[$key]['nestable_group_code'] != $products_by_dimensions[$key2]['nestable_group_code']) {
	                    	$products_by_dimensions[$key2]['nestable_group_code'] .= ',' . $products_by_dimensions[$key]['nestable_group_code'];
											}
	                    // create a new nestable group 
	                    unset($products_by_dimensions[$key]);
	                    $found_nestable = true;
	                    	                    
	                    break;
										} else {
											// large box does not exist
											// stop future nesting of this product
											$products_by_dimensions[$key2]['nestable'] = 0; 											
											//continue;
										} 
                  }
                }
				        /*
				        if (MODULE_SHIPPING_BOXES_MANAGER_DEBUG == 'true') {
					        echo '<p>Products (after nesting):</p>';        
					        echo '<pre>';
					        print_r($products_by_dimensions); 
					        echo '</pre>';
								}
								*/                
              }
            } 
          }
        }
        //echo '<!-- ';
        /**/
        if (MODULE_SHIPPING_BOXES_MANAGER_DEBUG == 'true') {
	        echo '<p>Products (after nesting):</p>';        
	        echo '<pre>';
	        print_r($products_by_dimensions); 
	        echo '</pre>';
				}
				
				// calculate total volume
				foreach ($products_by_dimensions as $product_by_dimensions) {
					$total_volume += $product_by_dimensions['volume'];
				}
				
        //echo 'Total Volume: ' . $total_volume . '<br />';
        /**/
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
              if (($box_properties['remaining_volume'] >= $current_products_volume) && ($box_properties['length'] >= $current_products_length) && ($box_properties['width'] >= $current_products_width) && ($box_properties['height'] >= $current_products_height)) {
                $packed_boxes[$current_box_key]['remaining_volume'] = $box_properties['remaining_volume'] - $current_products_volume;
                $packed_boxes[$current_box_key]['weight'] += $current_products_weight;
                $total_remaining_volume -= $current_products_volume;
                $found_box = true;
                break;
              }
            }
          }
          if ($found_box == false) {
            // get the smallest box that will fit all the remaining products
            $box = $db->Execute("SELECT box_id, length, width, height, volume FROM " . TABLE_SHIPPING_BOXES_MANAGER . "
                                 WHERE length >= '" . $max_length . "'
                                 AND width >= '" . $max_width . "'
                                 AND height >= '" . $max_height . "'
                                 AND volume >= '" . $total_remaining_volume . "'
                                 AND " . $possible_boxes_query_where . "
                                 ORDER BY volume ASC
                                 LIMIT 1;");
            if ($box->RecordCount() > 0) {
              $remaining_volume = $box->fields['volume'] - $current_products_volume;
              $packed_boxes[] = array('box ID' => $box->fields['box_id'], 'length' => $box->fields['length'], 'width' => $box->fields['width'], 'height' => $box->fields['height'], 'weight' => $box->fields['weight'] + $current_products_weight, 'remaining_volume' => $remaining_volume);
	            // add the weight of the box to the products
	            $new_total_weight += $box->fields['weight'];             
            } else {
	            // get the largest box that will fit the current product (since our largest box couldn't fit all of the products, we need to 
	            $box = $db->Execute("SELECT box_id, length, width, height, volume FROM " . TABLE_SHIPPING_BOXES_MANAGER . "
	                                 WHERE length >= '" . $current_products_length . "'
	                                 AND width >= '" . $current_products_width . "'
	                                 AND height >= '" . $current_products_height . "'
	                                 AND volume >= '" . $current_products_volume . "'
	                                 AND " . $possible_boxes_query_where . "
	                                 ORDER BY volume DESC
	                                 LIMIT 1;");
							if ($box->RecordCount() > 0) {
	              $remaining_volume = $box->fields['volume'] - $current_products_volume;
	              $packed_boxes[] = array('box ID' => $box->fields['box_id'], 'length' => $box->fields['length'], 'width' => $box->fields['width'], 'height' => $box->fields['height'], 'weight' => $box->fields['weight'] + $current_products_weight, 'remaining_volume' => $remaining_volume);
	              // add the weight of the box to the products
	              $new_total_weight += $box->fields['weight']; 
							} else {
								// pack the product by itself           	
	              $packed_boxes[] = array('box ID' => 'no box', 'length' => $current_products_length, 'width' => $current_products_width, 'height' => $current_products_height, 'weight' => $current_products_weight, 'remaining_volume' => 0);
							}
            }
            $total_remaining_volume -= $current_products_volume;
          }
        }
          
        if (!$packed_boxes['default']['weight'] > 0) unset($packed_boxes['default']);
        //echo '<!-- ';
        /**/
        if (MODULE_SHIPPING_BOXES_MANAGER_DEBUG == 'true') {
	        echo '<p>Packages</p>';
	        echo '<pre>';
	        print_r($packed_boxes);
	        echo '</pre>';
				}
        /**/
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