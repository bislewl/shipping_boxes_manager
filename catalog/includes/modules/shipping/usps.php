<?php
/**
 * USPS Module for Zen Cart v1.3.x thru v1.6
 * USPS RateV4 Intl RateV2 - January 27, 2013 Updates to: March 27, 2013 Version C
 *
 * @package shippingMethod
 * @copyright Copyright 2003-2013 Zen Cart Development Team
 * @copyright Portions Copyright 2003 osCommerce
 * @copyright Portions adapted from 2012 osCbyJetta
 * @license http://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 * @version $Id: usps.php 2013-03-27 ajeh $
 */

/**
 * USPS Shipping Module class
 *
 */
class usps extends base {
  /**
   * Declare shipping module alias code
   *
   * @var string
   */
  var $code;
  /**
   * Shipping module display name
   *
   * @var string
   */
  var $title;
  /**
   * Shipping module display description
   *
   * @var string
   */
  var $description;
  /**
   * Shipping module icon filename/path
   *
   * @var string
   */
  var $icon;
  /**
   * Shipping module status
   *
   * @var boolean
   */
  var $enabled;
  /**
   * Shipping module list of supported countries
   *
   * @var array
   */
  var $countries;
  /**
   *  use USPS translations for US shops
   *  @var string
   */
   var $usps_countries;
  /**
   * USPS certain methods don't qualify if declared value is greater than $400
   * @var array
   */
   var $types_to_skip_over_certain_value;
  /**
   * use for debug log of what is sent to usps
   * @var string
   */
   var $request_display;
  /**
   * Constructor
   *
   * @return object
   */
  function __construct() {
    global $db, $template;

    $this->code = 'usps';
    $this->title = MODULE_SHIPPING_USPS_TEXT_TITLE;
    if (MODULE_SHIPPING_USPS_STATUS == 'True' && MODULE_SHIPPING_USPS_DEBUG_MODE != 'Off' && IS_ADMIN_FLAG) {
      $this->title .=  '<span class="alert"> (Debug is ON: ' . MODULE_SHIPPING_USPS_DEBUG_MODE . ')</span>';
    }
    if (MODULE_SHIPPING_USPS_STATUS == 'True' && MODULE_SHIPPING_USPS_SERVER != 'production' && IS_ADMIN_FLAG) {
      $this->title .=  '<span class="alert"> (USPS Server set to: ' . MODULE_SHIPPING_USPS_SERVER . ')</span>';
    }
    $this->description = MODULE_SHIPPING_USPS_TEXT_DESCRIPTION;
    $this->sort_order = MODULE_SHIPPING_USPS_SORT_ORDER;
    $this->tax_class = MODULE_SHIPPING_USPS_TAX_CLASS;

// fix here -- was this ever actually used?
    $this->tax_basis = MODULE_SHIPPING_USPS_TAX_BASIS;

    $this->update_status();

    // check if all keys are in configuration table and correct version
    if (MODULE_SHIPPING_USPS_STATUS == 'True') {
      if (IS_ADMIN_FLAG) {
        $this->enabled = TRUE;
        $chk_keys = $this->keys();
        $chk_sql = $db->Execute("select * from " . TABLE_CONFIGURATION . " where configuration_key like 'MODULE\_SHIPPING\_USPS\_%' ");
        if ((MODULE_SHIPPING_USPS_VERSION != '2013-03-27') || (sizeof($chk_keys) != $chk_sql->RecordCount())) {
          $this->title .= '<span class="alert">' . ' - Missing Keys or Out of date you should reinstall!' . '</span>';
          $this->enabled = FALSE;
        }
        if ($this->enabled) {
          // insert checks here to give warnings if some of the configured selections don't make sense (such as no boxes checked)
          // And in those cases, set $this->enabled to FALSE so that the amber warning symbol appears. Consider also adding more BRIEF error text to the $this->title.

          // verify checked boxes
          $usps_shipping_methods_domestic_cnt = 0;
          $usps_shipping_methods_international_cnt = 0;
          foreach(explode(', ', MODULE_SHIPPING_USPS_TYPES) as $request_type)
          {
            if(is_numeric($request_type) || (preg_match('#International#' , $request_type) || preg_match('#GXG#' , $request_type)) ) continue;
            $usps_shipping_methods_domestic_cnt += 1;
          }
          foreach(explode(', ', MODULE_SHIPPING_USPS_TYPES) as $request_type)
          {
            if(is_numeric($request_type) || !(preg_match('#International#' , $request_type) || preg_match('#GXG#' , $request_type)) ) continue;
            $usps_shipping_methods_international_cnt += 1;
          }
          if (($usps_shipping_methods_domestic_cnt + $usps_shipping_methods_international_cnt) < 1) {
            $this->title .= '<span class="alert">' . ' - Nothing has been selected for Quotes.' . '</span>';
          }


          //
        }
      }
    }

    if (SHIPPING_ORIGIN_COUNTRY != '223') {
      $this->title .= '<span class="alert">' . ' - USPS can only ship from USA. But your store is configured with another origin! See Admin->Configuration->Shipping/Packaging.' . '</span>';
    }

    if (isset($template)) {
      $this->icon = $template->get_template_dir('shipping_usps.gif', DIR_WS_TEMPLATE, $current_page_base,'images/icons'). '/' . 'shipping_usps.gif';
    }

    // prepare list of countries which USPS ships to
    $this->countries = $this->country_list();

    // use USPS translations for US shops (USPS treats certain regions as "US States" instead of as different "countries", so we translate here)
    $this->usps_countries = $this->usps_translation();

    // certain methods don't qualify if declared value is greater than $400
    $this->types_to_skip_over_certain_value = array();
    $this->types_to_skip_over_certain_value['Priority MailRM International Flat Rate Envelope**'] = 400; // skip value > $400 Priority Mail International Flat Rate Envelope
    $this->types_to_skip_over_certain_value['Priority MailRM International Small Flat Rate Envelope**'] = 400; // skip value > $400 Priority Mail International Small Flat Rate Envelope
    $this->types_to_skip_over_certain_value['Priority MailRM International Small Flat Rate Box**'] = 400; // skip value > $400 Priority Mail International Small Flat Rate Box
    $this->types_to_skip_over_certain_value['Priority MailRM International Legal Flat Rate Envelope**'] = 400; // skip value > $400 Priority Mail International Legal Flat Rate Envelope
    $this->types_to_skip_over_certain_value['Priority MailRM International Padded Flat Rate Envelope**'] = 400; // skip value > $400 Priority Mail International Padded Flat Rate Envelope
    $this->types_to_skip_over_certain_value['Priority MailRM International Gift Card Flat Rate Envelope**'] = 400; // skip value > $400 Priority Mail International Gift Card Flat Rate Envelope
    $this->types_to_skip_over_certain_value['Priority MailRM International Window Flat Rate Envelope**'] = 400; // skip value > $400 Priority Mail International Window Flat Rate Envelope
    $this->types_to_skip_over_certain_value['First-Class MailRM International Letter**'] = 400; // skip value > $400 First-Class Mail International Letter
    $this->types_to_skip_over_certain_value['First-Class MailRM International Large Envelope**'] = 400; // skip value > $400 First-Class Mail International Large Envelope
    $this->types_to_skip_over_certain_value['First-Class Package International ServiceTM**'] = 400; // skip value > $400 First-Class Package International Service
  }

  /**
   * check whether this module should be enabled or disabled based on zone assignments and any other rules
   */
  function update_status() {
    global $order, $db;
    if (IS_ADMIN_FLAG == TRUE) return;

    // disable when entire cart is free shipping
    if (zen_get_shipping_enabled($this->code)) {
      $this->enabled = ((MODULE_SHIPPING_USPS_STATUS == 'True') ? true : false);
    }

    if ($this->enabled == true) {
      if ((int)MODULE_SHIPPING_USPS_ZONE > 0) {
        $check_flag = false;
        $check = $db->Execute("select zone_id from " . TABLE_ZONES_TO_GEO_ZONES . " where geo_zone_id = '" . MODULE_SHIPPING_USPS_ZONE . "' and zone_country_id = '" . $order->delivery['country']['id'] . "' order by zone_id");
        while (!$check->EOF) {
          if ($check->fields['zone_id'] < 1) {
            $check_flag = true;
            break;
          } elseif ($check->fields['zone_id'] == $order->delivery['zone_id']) {
            $check_flag = true;
            break;
          }
          $check->MoveNext();
        }

        if ($check_flag == false) {
          $this->enabled = false;
        }
      }

      global $template, $current_page_base;
      // CUSTOMIZED CONDITIONS GO HERE
      // Optionally add additional code here to change $this->enabled to false based on whatever custom rules you require.
      // -----


      // -----
      // eof: optional additional code
    }
  }

  /**
   * Prepare request for quotes and process obtained results
   *
   * @param string $method
   * @return array of quotation results
   */
    function quote($method = '')
    {
      global $order, $shipping_weight, $shipping_num_boxes, $currencies, $shipping;
      $iInfo = '';
      $methods = array();
      $usps_shipping_weight = ($shipping_weight < 0.0625 ? 0.0625 : $shipping_weight);
      
      // shipping boxes manager
      if (MODULE_SHIPPING_BOXES_MANAGER_STATUS == 'true') {
        global $packed_boxes, $total_weight;
        if (is_array($packed_boxes) && sizeof($packed_boxes) && $total_weight > 0) {
          //$shipping_num_boxes = sizeof($packed_boxes);
          //$shipping_weight = round(($total_weight / $shipping_num_boxes), 2); // use our number of packages rather than Zen Cart's calculation, package weight will still have to be an average since we don't know which products are in the box.
          $width = $height = $length = 0;
          foreach ($packed_boxes as $packed_box) {
            if ($packed_box['width'] > $width) $this->width = $packed_box['width'];
            if ($packed_box['height'] > $height) $this->height = $packed_box['height'];
            if ($packed_box['length'] > $length) $this->length = $packed_box['length'];
          }                                                                                                                                
          $usps_shipping_weight = $shipping_weight;
        }
      }      
      
      $this->pounds = (int)$usps_shipping_weight;
      // usps currently cannot handle more than 5 digits on international
      // change to 2 if International rates fail based on Tare Settings
      $this->ounces = ceil(round(16 * ($usps_shipping_weight - $this->pounds), MODULE_SHIPPING_USPS_DECIMALS));

      // Determine machinable or not
      // weight must be less than 35lbs and greater than 6 ounces or it is not machinable
      switch(true) {
        case ($shipping_pounds == 0 and $shipping_ounces < 6):
          // override admin choice too light
          $this->machinable = 'False';
          break;

        case ($usps_shipping_weight > 35):
          // override admin choice too heavy
          $this->machinable = 'False';
          break;

        default:
          // admin choice on what to use
          $this->machinable = MODULE_SHIPPING_USPS_MACHINABLE;
      }


      // request quotes
      $this->notify('NOTIFY_SHIPPING_USPS_BEFORE_GETQUOTE', $order, $usps_shipping_weight, $shipping_num_boxes);
      $this->request_display = '';
      $this->uspsQuote = $this->_getQuote();
      $this->notify('NOTIFY_SHIPPING_USPS_AFTER_GETQUOTE', $order, $usps_shipping_weight, $shipping_num_boxes);
      $uspsQuote = $this->uspsQuote;

      // were errors encountered?
      if ($uspsQuote === -1) {
        $this->quotes = array('module' => $this->title,
                              'error' => MODULE_SHIPPING_USPS_TEXT_SERVER_ERROR . (MODULE_SHIPPING_USPS_SERVER == 'test' ? MODULE_SHIPPING_USPS_TEXT_TEST_MODE_NOTICE : ''));
        return $this->quotes;
      }
      if (!is_array($uspsQuote)) {
        $this->quotes = array('module' => $this->title,
                              'error' => MODULE_SHIPPING_USPS_TEXT_ERROR . (MODULE_SHIPPING_USPS_SERVER == 'test' ? MODULE_SHIPPING_USPS_TEXT_TEST_MODE_NOTICE : ''));
        return $this->quotes;
      }
      if (isset($uspsQuote['Number']) && !isset($uspsQuote['error'])) $uspsQuote['error'] = $uspsQuote['Number'] . ' - ' . $uspsQuote['Description'];
      if (isset($uspsQuote['error'])) {
        if ($uspsQuote['Number'] == -2147219085) {
          $this->quotes = array('module' => $this->title,
                                'error' => 'NO OPTIONS INSTALLED: ' . $uspsQuote['error']);
        } else {
          $this->quotes = array('module' => $this->title,
                                'error' => $uspsQuote['error']);
        }
        return $this->quotes;
      }

      // if we got here, there were no errors, so proceed with evaluating the obtained quotes


      // Domestic/US destination:
      if ($this->usps_countries == 'US') {
        $dExtras = array();
        $dOptions = explode(', ', MODULE_SHIPPING_USPS_DMST_SERVICES); // domestic
        foreach ($dOptions as $key => $val) {
          if (strlen($dOptions[$key]) > 1) {
            if ($dOptions[$key+1] == 'C' || $dOptions[$key+1] == 'S' || $dOptions[$key+1] == 'Y') {
              $dExtras[$dOptions[$key]] = $dOptions[$key+1];
            }
//echo '$dOptions[$key]: ' . $dOptions[$key] . ' ' . $dOptions[$key+1] . '<br>';
          }
        }
      } else {
        $iExtras = array();
        $iOptions = explode(', ', MODULE_SHIPPING_USPS_INTL_SERVICES);
        foreach ($iOptions as $key => $val) {
          if(strlen($iOptions[$key]) > 1) {
            if ($iOptions[$key+1] == 'C' || $iOptions[$key+1] == 'S' || $iOptions[$key+1] == 'Y') {
              $iExtras[$iOptions[$key]] = $iOptions[$key+1];
//echo '$iOptions[$key]: ' . $iOptions[$key] . ' ' . $iOptions[$key+1] . '<br>';
            }
          }
        }

        if (MODULE_SHIPPING_USPS_REGULATIONS == 'True') {
          $iInfo = '<div id="iInfo">' . "\n" .
                   '  <div id="showInfo" class="ui-state-error" style="cursor:pointer; text-align:center;" onclick="$(\'#showInfo\').hide();$(\'#hideInfo, #Info\').show();">' . MODULE_SHIPPING_USPS_TEXT_INTL_SHOW . '</div>' . "\n" .
                   '  <div id="hideInfo" class="ui-state-error" style="cursor:pointer; text-align:center; display:none;" onclick="$(\'#hideInfo, #Info\').hide();$(\'#showInfo\').show();">' . MODULE_SHIPPING_USPS_TEXT_INTL_HIDE .'</div>' . "\n" .
                   '  <div id="Info" class="ui-state-highlight" style="display:none; padding:10px; max-height:200px; overflow:auto;">' . '<b>Prohibitions:</b><br />' . nl2br($uspsQuote['Package']['Prohibitions']) . '<br /><br /><b>Restrictions:</b><br />' . nl2br($uspsQuote['Package']['Restrictions']) . '<br /><br /><b>Observations:</b><br />' . nl2br($uspsQuote['Package']['Observations']) . '<br /><br /><b>CustomsForms:</b><br />' . nl2br($uspsQuote['Package']['CustomsForms']) . '<br /><br /><b>ExpressMail:</b><br />' . nl2br($uspsQuote['Package']['ExpressMail']) . '<br /><br /><b>AreasServed:</b><br />' . nl2br($uspsQuote['Package']['AreasServed']) . '<br /><br /><b>AdditionalRestrictions:</b><br />' . nl2br($uspsQuote['Package']['AdditionalRestrictions']) .'</div>' . "\n" .
                   '</div>';
        }
      }

      if (isset($uspsQuote['Package']['Postage']) && zen_not_null($uspsQuote['Package']['Postage'])) {
        $PackageSize = 1;
      } else {
        $PackageSize = ($this->usps_countries == 'US') ? sizeof($uspsQuote['Package']) : sizeof($uspsQuote['Package']['Service']);
      }

      for ($i=0; $i<$PackageSize; $i++)
      {
        if (isset($uspsQuote['Package'][$i]['Error']) && zen_not_null($uspsQuote['Package'][$i]['Error'])) continue;

        $Services = array();
        $hiddenServices = array();
        $hiddenCost = 0;
        $handling = 0;
        $types = explode(', ', MODULE_SHIPPING_USPS_TYPES);

        $Package = ($PackageSize == 1 ? $uspsQuote['Package']['Postage'] : ($this->usps_countries == 'US' ? $uspsQuote['Package'][$i]['Postage'] : $uspsQuote['Package']['Service'][$i]));

        if ($this->usps_countries == 'US') {
          if (zen_not_null($Package['SpecialServices']['SpecialService'])) {
//echo 'USPS DOMESTIC $Package[SpecialServices][SpecialService]:<pre>'; echo var_dump($Package['SpecialServices']['SpecialService']); echo '</pre><br>';
            foreach ($Package['SpecialServices']['SpecialService'] as $key => $val) {
              if (isset($dExtras[$val['ServiceName']]) && zen_not_null($dExtras[$val['ServiceName']]) && ((MODULE_SHIPPING_USPS_RATE_TYPE == 'Online' && $val['AvailableOnline'] == 'true') || (MODULE_SHIPPING_USPS_RATE_TYPE == 'Retail' && $val['Available'] == 'true'))) {
                $val['ServiceAdmin'] = $dExtras[$val['ServiceName']];
                $Services[] = $val;
//echo 'USPS Domestic $d $key: ' . $key . ' $val[ServiceAdmin]:' . $val['ServiceAdmin'] . ' $val: ' . $val . '<br>';
              }
            }
          }
          $cost = MODULE_SHIPPING_USPS_RATE_TYPE == 'Online' && zen_not_null($Package['CommercialRate']) ? $Package['CommercialRate'] : $Package['Rate'];
          $type = ($Package['MailService']);
        } else {
          // International
//echo 'USPS INTERNATIONAL $Package[ExtraServices][ExtraService]: <pre>'; echo print_r($Package['ExtraServices']['ExtraService'], true); echo '</pre><br>';
          foreach ($Package['ExtraServices']['ExtraService'] as $key => $val) {
//echo '$iExtras[$val[ServiceName]]: ' . $iExtras[$val['ServiceName']] . ' $val[ServiceName]: ' . $val['ServiceName'] . ' $val[OnlineAvailable]: ' . $val['OnlineAvailable'] . ' $val[Available]: ' . $val['Available'] . '<br>';
            if (isset($val['ServiceName']) && isset($iExtras[$val['ServiceName']]) && zen_not_null($iExtras[$val['ServiceName']]) && ((MODULE_SHIPPING_USPS_RATE_TYPE == 'Online' && $val['OnlineAvailable'] == 'True') || (MODULE_SHIPPING_USPS_RATE_TYPE == 'Retail' && $val['Available'] == 'True'))) {
              $val['ServiceAdmin'] = $iExtras[$val['ServiceName']];
              $Services[] = $val;
//echo 'USPS International $i $key: ' . $key . ' $val[ServiceAdmin]: ' . $val['ServiceAdmin'] . ' $val: ' . $val . '<br>';
            }
            $cost = MODULE_SHIPPING_USPS_RATE_TYPE == 'Online' && zen_not_null($Package['CommercialPostage']) ? $Package['CommercialPostage'] : $Package['Postage'];
            $type = ($Package['SvcDescription']);
          }
        }
        if ($cost == 0) continue;

        // Certain methods cannot ship if declared value is over $400, so we "continue" which skips the current $type and proceeds with the next one in the loop:
        if (isset($this->types_to_skip_over_certain_value[$type]) && $_SESSION['cart']->total > $this->types_to_skip_over_certain_value[$type]) {
//echo 'Skipping: ' . $type . ' because amount is over ' . $this->types_to_skip_over_certain_value[$type] . '<br>';
          continue;
        }


        // handle charges for additional services selected
        foreach ($types as $key => $val) {
          if (!is_numeric($val) && $val == $type) {
            $minweight = $types[$key+1];
            $maxweight = $types[$key+2];
            $handling = $types[$key+3];
          }
//echo 'USPS $val: ' . $val . ' $minweight: ' . $minweight . ' $maxweight: ' . $maxweight . '<br>';
        }
        foreach ($Services as $key => $val)
        {
//echo 'USPS DOES IT SHOW $Services[$key][ServiceAdmin]: ' . $Services[$key]['ServiceAdmin'] . '<br>';
          $sDisplay = $Services[$key]['ServiceAdmin'];
          if ($sDisplay == 'Y') $hiddenServices[] = array($Services[$key]['ServiceName'] => (MODULE_SHIPPING_USPS_RATE_TYPE == 'Online' ? $Services[$key]['PriceOnline'] : $Services[$key]['Price']));
        }
        // prepare costs associated with selected additional services
        foreach($hiddenServices as $key => $val) {
          foreach($hiddenServices[$key] as $key1 => $val1) {
            $hiddenCost += $val1;
//echo 'USPS Hidden Costs $key1: ' . $key1 . ' $val1: ' . $val1 . '<br>';
          }
        }


        // set module-specific handling fee
        if ($order->delivery['country']['id'] == SHIPPING_ORIGIN_COUNTRY || $this->usps_countries == 'US') {
          // domestic/national
          $usps_handling_fee = MODULE_SHIPPING_USPS_HANDLING;
        } else {
          // international
          $usps_handling_fee = MODULE_SHIPPING_USPS_HANDLING_INT;
        }

        // COST
        // clean out invalid characters
        $cost = preg_replace('/[^0-9.]/', '',  $cost);
//echo 'USPS $cost: ' . $cost . ' $handling: ' . $handling . ' $hiddenCost: ' . $hiddenCost . ' $shipping_num_boxes: ' . $shipping_num_boxes . ' $usps_handling_fee: ' . $usps_handling_fee . '<br>';
        // add handling for shipping method costs for extra services applied
        $cost = ($cost + $handling + $hiddenCost) * $shipping_num_boxes;
        // add handling fee per Box or per Order
        $cost += (MODULE_SHIPPING_USPS_HANDLING_METHOD == 'Box') ? $usps_handling_fee * $shipping_num_boxes : $usps_handling_fee;

        // set the output title display name back to correct format
        $title = str_replace(array('RM', 'TM', '**'), array('&reg;', '&trade;', ''), $type);

        // handle International transit-time results
        $getTransitTimeInt = (in_array('Display transit time', explode(', ', MODULE_SHIPPING_USPS_OPTIONS))) ? TRUE : FALSE;
        if ($getTransitTimeInt && ($this->transittime[$type] == '' && preg_match('#(International|USPS GXG)#' , $type))) {
          $time = $Package['SvcCommitments'];
          $time = preg_replace('/Weeks$/', MODULE_SHIPPING_USPS_TEXT_WEEKS, $time);
          $time = preg_replace('/Days$/', MODULE_SHIPPING_USPS_TEXT_DAYS, $time);
          $time = preg_replace('/Day$/', MODULE_SHIPPING_USPS_TEXT_DAY, $time);
          $this->transittime[$type] = $time == '' ? '' : ' (' . $time . ')';
        }

        // if the transit time feature is enabled, then the transittime variable will not be blank, so this adds it. If it's disabled, will be blank, so adding here will have no negative effect.
        $title .= $this->transittime[$type];

    //  $title .= '~' . $this->usps_countries;   // adds $this->usps_countries to title to test actual country

//echo 'USPS $type: ' . $type . ' $cost: ' . $cost . ' - $_SESSION[cart]->total: ' . $_SESSION['cart']->total . ' $handling: ' . $handling . ' $hiddenCost: ' . $hiddenCost . ' $usps_handling_fee: ' . $usps_handling_fee . ' $shipping_num_boxes: ' . $shipping_num_boxes . ' $title: ' . $title . '<br /><br />';
//echo 'USPS $type: ' . $type . ' $cost: ' . $cost . ' $title: ' . $title . '<br /><br />';
//echo 'USPS $type: ' . $type . ' $usps_shipping_weight: ' . $usps_shipping_weight . ' $minweight: ' . $minweight . ' $maxweight: ' . $maxweight . '<br>';
        if ((($method == '' && in_array($type, $types)) || $method == $type) && $usps_shipping_weight <= $maxweight && $usps_shipping_weight > $minweight) {
          $methods[] = array('id' => $type,
                             'title' => $title,
                             'cost' => $cost,
                            );
        }
      }

      if (sizeof($methods) == 0) return false;


      // sort results
      if (MODULE_SHIPPING_USPS_QUOTE_SORT != 'Unsorted') {
        if (sizeof($methods) > 1)
        {
          if (substr(MODULE_SHIPPING_USPS_QUOTE_SORT, 0, 5) == 'Price') {
            foreach($methods as $c=>$key)
            {
              $sort_cost[] = $key['cost'];
              $sort_id[] = $key['id'];
            }
            array_multisort($sort_cost, (MODULE_SHIPPING_USPS_QUOTE_SORT == 'Price-LowToHigh' ? SORT_ASC : SORT_DESC), $sort_id, SORT_ASC, $methods);
          } else {
            foreach($methods as $c=>$key)
            {
              $sort_key[] = $key['title'];
              $sort_id[] = $key['id'];
            }
            array_multisort($sort_key, (MODULE_SHIPPING_USPS_QUOTE_SORT == 'Alphabetical' ? SORT_ASC : SORT_DESC), $sort_id, SORT_ASC, $methods);
          }
        }
      }


      // Show box weight if enabled
      $show_box_weight = '';
      if (in_array('Display weight', explode(', ', MODULE_SHIPPING_USPS_OPTIONS))) {
        switch (SHIPPING_BOX_WEIGHT_DISPLAY) {
          case (0):
            $show_box_weight = '';
            break;
          case (1):
            $show_box_weight = ' (' . $shipping_num_boxes . ' ' . TEXT_SHIPPING_BOXES . ')';
            break;
          case (2):
            $show_box_weight = ' (' . number_format($usps_shipping_weight * $shipping_num_boxes,2) . TEXT_SHIPPING_WEIGHT . ')';
            break;
          default:
            $show_box_weight = ' (' . $shipping_num_boxes . ' ' . TEXT_SHIPPING_BOXES . ')  (' . $this->pounds . ' lbs, ' . $this->ounces . ' oz' . ')';
            break;
        }
      }

      $this->quotes = array('id' => $this->code,
                            'module' => $this->title . $show_box_weight,
                            'methods' => $methods,
                            'tax' => $this->tax_class > 0 ? zen_get_tax_rate($this->tax_class, $order->delivery['country']['id'], $order->delivery['zone_id']) : null,
                           );

      // add icon/message, if any
      if ($this->icon != '') $this->quotes['icon'] = zen_image($this->icon, $this->title);
      if ($iInfo != '') $this->quotes['icon'] .= '<br />' . $iInfo;

      return $this->quotes;
    }


  /**
   * check status of module
   *
   * @return boolean
   */
  function check() {
    global $db;
    if (!isset($this->_check)) {
      $check_query = $db->Execute("select configuration_value from " . TABLE_CONFIGURATION . " where configuration_key = 'MODULE_SHIPPING_USPS_STATUS'");
      $this->_check = $check_query->RecordCount();
    }
    return $this->_check;
  }

  /**
   * Install this module
   */
  function install() {
    global $db;
    $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('USPS Version Date', 'MODULE_SHIPPING_USPS_VERSION', '2013-03-27', 'You have installed:', '6', '0', 'zen_cfg_select_option(array(\'2013-03-27\'), ', now())");
    $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Enable USPS Shipping', 'MODULE_SHIPPING_USPS_STATUS', 'True', 'Do you want to offer USPS shipping?', '6', '0', 'zen_cfg_select_option(array(\'True\', \'False\'), ', now())");
    $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, use_function, set_function, date_added) values ('Shipping Zone', 'MODULE_SHIPPING_USPS_ZONE', '0', 'If a zone is selected, only enable this shipping method for that zone.', '6', '0', 'zen_get_zone_class_title', 'zen_cfg_pull_down_zone_classes(', now())");
    $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Sort Order', 'MODULE_SHIPPING_USPS_SORT_ORDER', '0', 'Sort order of display.', '6', '0', now())");

    $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Enter the USPS Web Tools User ID', 'MODULE_SHIPPING_USPS_USERID', 'NONE', 'Enter the USPS USERID assigned to you for Rate Quotes/ShippingAPI.', '6', '0', now())");
    $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Which server to use', 'MODULE_SHIPPING_USPS_SERVER', 'production', 'An account at USPS is needed to use the Production server', '6', '0', 'zen_cfg_select_option(array(\'test\', \'production\'), ', now())");
    $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('All Packages are Machinable?', 'MODULE_SHIPPING_USPS_MACHINABLE', 'False', 'Are all products shipped machinable based on C700 Package Services 2.0 Nonmachinable PARCEL POST USPS Rules and Regulations?<br /><br /><strong>Note: Nonmachinable packages will usually result in a higher Parcel Post Rate Charge.<br /><br />Packages 35lbs or more, or less than 6 ounces (.375), will be overridden and set to False</strong>', '6', '0', 'zen_cfg_select_option(array(\'True\', \'False\'), ', now())");
    $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Quote Sort Order', 'MODULE_SHIPPING_USPS_QUOTE_SORT', 'Price-LowToHigh', 'Sorts the returned quotes using the service name Alphanumerically or by Price. Unsorted will give the order provided by USPS.', '6', '0', 'zen_cfg_select_option(array(\'Unsorted\',\'Alphabetical\', \'Price-LowToHigh\', \'Price-HighToLow\'), ', now())");
    $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Decimal Settings', 'MODULE_SHIPPING_USPS_DECIMALS', '3', 'Decimal Setting can be 1, 2 or 3. Sometimes International requires 2 decimals, based on Tare Rates or Product weights. Do you want to use 1, 2 or 3 decimals?', '6', '0', 'zen_cfg_select_option(array(\'1\', \'2\', \'3\'), ', now())");

    $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, use_function, set_function, date_added) values ('Tax Class', 'MODULE_SHIPPING_USPS_TAX_CLASS', '0', 'Use the following tax class on the shipping fee.', '6', '0', 'zen_get_tax_class_title', 'zen_cfg_pull_down_tax_classes(', now())");

// fix here: module needs code for tax basis
    $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Tax Basis', 'MODULE_SHIPPING_USPS_TAX_BASIS', 'Shipping', 'On what basis is Shipping Tax calculated. Options are<br />Shipping - Based on customers Shipping Address<br />Billing Based on customers Billing address<br />Store - Based on Store address if Billing/Shipping Zone equals Store zone', '6', '0', 'zen_cfg_select_option(array(\'Shipping\', \'Billing\', \'Store\'), ', now())");


    $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('USPS Options', 'MODULE_SHIPPING_USPS_OPTIONS', 'Display weight, Display transit time', 'Select from the following the USPS options.', '6', '16', 'zen_cfg_select_multioption(array(\'Display weight\', \'Display transit time\'), ',  now())");
    $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Debug Mode', 'MODULE_SHIPPING_USPS_DEBUG_MODE', 'Off', 'Would you like to enable debug mode?  A complete detailed log of USPS quote results may be emailed to the store owner, Log results or displayed to Screen.', '6', '0', 'zen_cfg_select_option(array(\'Off\', \'Email\', \'Logs\', \'Screen\'), ', now())");

    $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Handling Fee - US', 'MODULE_SHIPPING_USPS_HANDLING', '0', 'National Handling fee for this shipping method.', '6', '0', now())");
    $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Handling Fee - International', 'MODULE_SHIPPING_USPS_HANDLING_INT', '0', 'International Handling fee for this shipping method.', '6', '0', now())");
    $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Handling Per Order or Per Box', 'MODULE_SHIPPING_USPS_HANDLING_METHOD', 'Box', 'Do you want to charge Handling Fee Per Order or Per Box?', '6', '0', 'zen_cfg_select_option(array(\'Order\', \'Box\'), ', now())");

/*
Small Flat Rate Box 8-5/8" x 5-3/8" x 1-5/8"
Global Express Guaranteed - Min. length 9-1/2", height 5-1/2"
MODULE_SHIPPING_USPS_LENGTH 8.625
MODULE_SHIPPING_USPS_WIDTH  5.375
MODULE_SHIPPING_USPS_HEIGHT 1.625
*/
  $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('USPS minimum Length', 'MODULE_SHIPPING_USPS_LENGTH', '8.625', 'The Minimum Length, Width and Height are used to determine shipping methods available for International Shipping.<br />While dimensions are not supported at this time, the Minimums are sent to USPS for obtaining Rate Quotes.<br />In most cases, these Minimums should never have to be changed.<br /><br />Enter the Minimum Length - default 8.625', '6', '0', now())");
  $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('USPS minimum Width', 'MODULE_SHIPPING_USPS_WIDTH', '5.375', 'Enter the Minimum Width - default 5.375', '6', '0', now())");
  $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('USPS minimum Height', 'MODULE_SHIPPING_USPS_HEIGHT', '1.625', 'Enter the Minimum Height - default 1.625', '6', '0', now())");

    $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Shipping Methods (Domestic and International)',  'MODULE_SHIPPING_USPS_TYPES',  '0, .21875, 0.00, 0, .8125, 0.00, 0, .8125, 0.00, 0, 70, 0.00, 0, 70, 0.00, 0, 70, 0.00, 0, 70, 0.00, 0, 70, 0.00, 0, 70, 0.00, 0, 70, 0.00, 0, 70, 0.00, 0, 70, 0.00, 0, 15, 0.00, 0, 20, 0.00, 0, 25, 0.00, 0, 70, 0.00, 0, 70, 0.00, 0, 70, 0.00, 0, 70, 0.00, 0, .21875, 0.00, 0, 4, 0.00, 0, 4, 0.00, 0, 66, 0.00, 0, 4, 0.00, 0, 4, 0.00, 0, 20, 0.00, 0, 20, 0.00, 0, 66, 0.00, 0, 4, 0.00, 0, 20, 0.00, 0, 70, 0.00, 0, 70, 0.00', '<b><u>Checkbox:</u></b> Select the services to be offered<br /><b><u>Minimum Weight (lbs)</u></b>first input field<br /><b><u>Maximum Weight (lbs):</u></b>second input field<br /><br />USPS returns methods based on cart weights.  These settings will allow further control (particularly helpful for flat rate methods) but will not override USPS limits', '6', '0', 'zen_cfg_usps_services(array(\'First-Class MailRM Letter\', \'First-Class MailRM Large Envelope\', \'First-Class MailRM Parcel\', \'Media MailRM\', \'Standard PostRM\', \'Priority MailRM\', \'Priority MailRM Flat Rate Envelope\', \'Priority MailRM Legal Flat Rate Envelope\', \'Priority MailRM Padded Flat Rate Envelope\', \'Priority MailRM Small Flat Rate Box\', \'Priority MailRM Medium Flat Rate Box\', \'Priority MailRM Large Flat Rate Box\', \'Priority MailRM Regional Rate Box A\', \'Priority MailRM Regional Rate Box B\', \'Priority MailRM Regional Rate Box C\', \'Express MailRM\', \'Express MailRM Flat Rate Envelope\', \'Express MailRM Legal Flat Rate Envelope\', \'Express MailRM Flat Rate Boxes\', \'First-Class MailRM International Letter**\', \'First-Class MailRM International Large Envelope**\', \'First-Class Package International ServiceTM**\', \'Priority MailRM International\', \'Priority MailRM International Flat Rate Envelope**\', \'Priority MailRM International Small Flat Rate Box**\', \'Priority MailRM International Medium Flat Rate Box\', \'Priority MailRM International Large Flat Rate Box\', \'Express MailRM International\', \'Express MailRM International Flat Rate Envelope\', \'Express MailRM International Flat Rate Boxes\', \'USPS GXGTM Envelopes**\', \'Global Express GuaranteedRM (GXG)**\'), ', now())");
    $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Extra Services (Domestic)', 'MODULE_SHIPPING_USPS_DMST_SERVICES', 'Certified MailRM, N, Insurance, N, Adult Signature Restricted Delivery, N, Registered without Insurance, N, Registered MailTM, N, Collect on Delivery, N, Return Receipt for Merchandise, N, Return Receipt, N, Certificate of Mailing, N, Express Mail Insurance, N, Delivery ConfirmationTM, N, Signature ConfirmationTM, N', 'Included in postage rates.  Not shown to the customer.', '6', '0', 'zen_cfg_usps_extraservices(array(\'Certified MailRM\', \'Insurance\', \'Adult Signature Restricted Delivery\', \'Registered without Insurance\', \'Registered MailTM\', \'Collect on Delivery\', \'Return Receipt for Merchandise\', \'Return Receipt\', \'Certificate of Mailing\', \'Express Mail Insurance\', \'Delivery ConfirmationTM\', \'Signature ConfirmationTM\'), ', now())");
    $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Extra Services (International)', 'MODULE_SHIPPING_USPS_INTL_SERVICES', 'Registered Mail, N, Insurance, N, Return Receipt, N, Restricted Delivery, N, Pick-Up, N, Certificate of Mailing, N', 'Included in postage rates.  Not shown to the customer.', '6', '0', 'zen_cfg_usps_extraservices(array(\'Registered Mail\', \'Insurance\', \'Return Receipt\', \'Restricted Delivery\', \'Pick-Up\', \'Certificate of Mailing\'), ', now())");

// Special Services prices and availability will not be returned when Service = ALL or ONLINE
    $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Retail pricing or Online pricing?', 'MODULE_SHIPPING_USPS_RATE_TYPE', 'Online', 'Rates will be returned ONLY for methods available in this pricing type.  Applies to prices <u>and</u> add on services', '6', '0', 'zen_cfg_select_option(array(\'Retail\', \'Online\'), ', now())");
  }

  /**
   * For removing this module's settings
   */
  function remove() {
    global $db;
    $db->Execute("delete from " . TABLE_CONFIGURATION . " where configuration_key like 'MODULE\_SHIPPING\_USPS\_%' ");
  }

  /**
   * Build array of keys used for installing/managing this module
   *
   * @return array
   */
  function keys() {
    $keys_list = array('MODULE_SHIPPING_USPS_VERSION', 'MODULE_SHIPPING_USPS_STATUS', 'MODULE_SHIPPING_USPS_USERID', 'MODULE_SHIPPING_USPS_SERVER', 'MODULE_SHIPPING_USPS_QUOTE_SORT', 'MODULE_SHIPPING_USPS_HANDLING', 'MODULE_SHIPPING_USPS_HANDLING_INT', 'MODULE_SHIPPING_USPS_HANDLING_METHOD', 'MODULE_SHIPPING_USPS_DECIMALS', 'MODULE_SHIPPING_USPS_TAX_CLASS', 'MODULE_SHIPPING_USPS_TAX_BASIS', 'MODULE_SHIPPING_USPS_ZONE', 'MODULE_SHIPPING_USPS_SORT_ORDER', 'MODULE_SHIPPING_USPS_MACHINABLE', 'MODULE_SHIPPING_USPS_OPTIONS', 'MODULE_SHIPPING_USPS_LENGTH', 'MODULE_SHIPPING_USPS_WIDTH', 'MODULE_SHIPPING_USPS_HEIGHT', 'MODULE_SHIPPING_USPS_TYPES', 'MODULE_SHIPPING_USPS_DMST_SERVICES', 'MODULE_SHIPPING_USPS_INTL_SERVICES', 'MODULE_SHIPPING_USPS_RATE_TYPE');
    $keys_list[] = 'MODULE_SHIPPING_USPS_DEBUG_MODE';
    return $keys_list;
  }

  /**
   * Get actual quote from USPS
   *
   * @return array of results or boolean false if no results
   */
  function _getQuote() {
    global $order;
    if ((int)SHIPPING_ORIGIN_ZIP == 0) {
      // no quotes obtained no 5 digit zip code origin set
      return array('module' => $this->title,
                   'error' => MODULE_SHIPPING_USPS_TEXT_ERROR . (MODULE_SHIPPING_USPS_SERVER == 'test' ? MODULE_SHIPPING_USPS_TEXT_TEST_MODE_NOTICE : ''));
    }
    if (MODULE_SHIPPING_USPS_DEBUG_MODE == 'Logs') {
      $usps_instance_id = date('mdYGis');
      $usps_dir_logs = (defined('DIR_FS_LOGS') ? DIR_FS_LOGS : DIR_FS_SQL_CACHE);
      $logfilename = $usps_dir_logs . '/SHIP_usps_Debug_' . $usps_instance_id . '_' . str_replace(' ', '', $order->delivery['country']['iso_code_2']) . '_' . str_replace(' ', '', $order->delivery['postcode']) . '.log';
    }


// fix here -- do we still want to offer this?
    $getTransitTime = (in_array('Display transit time', explode(', ', MODULE_SHIPPING_USPS_OPTIONS))) ? TRUE : FALSE;
    $transreq = array();

    if (MODULE_SHIPPING_USPS_DEBUG_MODE != 'Off') {
      // display checked boxes
      $usps_shipping_methods_domestic = '';
      $usps_shipping_methods_international = '';
      $usps_shipping_methods_domestic .= 'USPS Country - $this->countries[$order->delivery[country][iso_code_2]]: ' . $this->countries[$order->delivery['country']['iso_code_2']] . ' $this->usps_countries: ' . $this->usps_countries . '<br />';
      if ($this->usps_countries == 'US') {
        $usps_shipping_methods_domestic .= '<br />USPS DOMESTIC CHECKED: ' . MODULE_SHIPPING_USPS_RATE_TYPE . '<br />';
        foreach(explode(', ', MODULE_SHIPPING_USPS_TYPES) as $request_type)
        {
          if(is_numeric($request_type) || (preg_match('#International#' , $request_type) || preg_match('#GXG#' , $request_type)) ) continue;
          $usps_shipping_methods_domestic .= $request_type . '<br />';
        }
      } else {
        $usps_shipping_methods_international .= '<br />USPS INTERNATIONAL CHECKED: ' . MODULE_SHIPPING_USPS_RATE_TYPE . '<br />';
        foreach(explode(', ', MODULE_SHIPPING_USPS_TYPES) as $request_type)
        {
          if(is_numeric($request_type) || !(preg_match('#International#' , $request_type) || preg_match('#GXG#' , $request_type)) ) continue;
          $usps_shipping_methods_international .= $request_type . '<br />';
        }
      }


// TURNED OFF WITH FALSE
      if (false && $_GET['main_page'] == 'popup_shipping_estimator' && MODULE_SHIPPING_USPS_DEBUG_MODE != 'Off') {
        echo '================================' . '<br />';
        echo $usps_shipping_methods_domestic;
        echo $usps_shipping_methods_international;
        echo '================================' . '<br />';
      }
    }

    // US Domestic destinations
    if ($order->delivery['country']['id'] == SHIPPING_ORIGIN_COUNTRY || $this->usps_countries == 'US') {
      $ZipDestination = substr(str_replace(' ', '', $order->delivery['postcode']), 0, 5);
      $request =  '<RateV4Request USERID="' . MODULE_SHIPPING_USPS_USERID . '">' . '<Revision>2</Revision>';
      $package_count = 0;
      foreach(explode(', ', MODULE_SHIPPING_USPS_TYPES) as $request_type)
      {
        if(is_numeric($request_type) || preg_match('#International#' , $request_type)) continue;
        $FirstClassMailType = '';
        $Container = 'VARIABLE';
        if (preg_match('#First\-Class#', $request_type))
        {
          if ($shipping_weight > 13/16) continue;
          else
          {

// First-Class MailRM Letter\', \'First-Class MailRM Large Envelope\', \'First-Class MailRM Parcel
            $service = 'First-Class Mail';
            if ($request_type == 'First-Class MailRM Letter') $FirstClassMailType = 'LETTER';
            elseif ($request_type == 'First-Class MailRM Large Envelope') $FirstClassMailType = 'FLAT';
            else $FirstClassMailType = 'PARCEL';
          }
        }
        elseif ($request_type == 'Media MailRM') $service = 'MEDIA';
        // In the following line, changed Parcel to Standard due to USPS service name change - 01/27/13 a.forever edit
        elseif ($request_type == 'Standard PostRM') $service = 'PARCEL';
        elseif (preg_match('#Priority MailRM#', $request_type))
        {
          $service = 'PRIORITY COMMERCIAL';
          if ($request_type == 'Priority MailRM Flat Rate Envelope') $Container = 'FLAT RATE ENVELOPE';
          elseif ($request_type == 'Priority MailRM Legal Flat Rate Envelope') $Container = 'LEGAL FLAT RATE ENVELOPE';
          elseif ($request_type == 'Priority MailRM Padded Flat Rate Envelope') $Container = 'PADDED FLAT RATE ENVELOPE';
          elseif ($request_type == 'Priority MailRM Small Flat Rate Box') $Container = 'SM FLAT RATE BOX';
          elseif ($request_type == 'Priority MailRM Medium Flat Rate Box') $Container = 'MD FLAT RATE BOX';
          elseif ($request_type == 'Priority MailRM Large Flat Rate Box') $Container = 'LG FLAT RATE BOX';
          elseif ($request_type == 'Priority MailRM Regional Rate Box A') $Container = 'REGIONALRATEBOXA';
          elseif ($request_type == 'Priority MailRM Regional Rate Box B') $Container = 'REGIONALRATEBOXB';
          elseif ($request_type == 'Priority MailRM Regional Rate Box C') $Container = 'REGIONALRATEBOXC';
        }
        elseif (preg_match('#Express MailRM#', $request_type))
        {
          $service = 'EXPRESS COMMERCIAL';
          if ($request_type == 'Express MailRM Flat Rate Envelope') $Container = 'FLAT RATE ENVELOPE';
          elseif ($request_type == 'Express MailRM Legal Flat Rate Envelope') $Container = 'LEGAL FLAT RATE ENVELOPE';
          elseif ($request_type == 'Express MailRM Flat Rate Boxes') $Container = 'FLAT RATE BOX';
        }
        else
        {
          continue;
        }
        $request .=  '<Package ID="' . $package_count . '">' .
                     '<Service>' . $service . '</Service>' .
                     ($FirstClassMailType != '' ? '<FirstClassMailType>' . $FirstClassMailType . '</FirstClassMailType>' : '') .
                     '<ZipOrigination>' . SHIPPING_ORIGIN_ZIP . '</ZipOrigination>' .
                     '<ZipDestination>' . $ZipDestination . '</ZipDestination>' .
                     '<Pounds>' . $this->pounds . '</Pounds>' .
                     '<Ounces>' . $this->ounces . '</Ounces>' .
                     '<Container>' . $Container . '</Container>' .
                     '<Size>REGULAR</Size>' .
'<Value>' . ($order->info['subtotal'] + $order->info['tax']) . '</Value>' .
                     '<Machinable>' . ($this->machinable == 'True' ? 'TRUE' : 'FALSE') . '</Machinable>' .
                     '</Package>';
        $package_count++;


        if($getTransitTime){
          $transitreq  = 'USERID="' . MODULE_SHIPPING_USPS_USERID . '">' . '<OriginZip>' . SHIPPING_ORIGIN_ZIP . '</OriginZip>' . '<DestinationZip>' . $ZipDestination . '</DestinationZip>';
          switch ($service) {
//             case 'EXPRESS COMMERCIAL':
//             case 'EXPRESS':  $transreq[$request_type] = 'API=ExpressMail&XML=' . urlencode( '<ExpressMailRequest ' . $transitreq . '</ExpressMailRequest>');
//             break;
            case 'PRIORITY COMMERCIAL':
            case 'PRIORITY': $transreq[$request_type] = 'API=PriorityMail&XML=' . urlencode( '<PriorityMailRequest ' . $transitreq . '</PriorityMailRequest>');
            break;
            case 'PARCEL':   $transreq[$request_type] = 'API=StandardB&XML=' . urlencode( '<StandardBRequest ' . $transitreq . '</StandardBRequest>');
            break;
            case 'First-Class Mail':$transreq[$request_type] = 'API=FirstClassMail&XML=' . urlencode( '<FirstClassMailRequest ' . $transitreq . '</FirstClassMailRequest>');
            break;
            case 'MEDIA':
            default:         $transreq[$request_type] = '';
            break;
          }
        }

      }
      $request .=  '</RateV4Request>';

      if (MODULE_SHIPPING_USPS_DEBUG_MODE != 'Off') {
        // prepare request for display
        $this->request_display = preg_replace(array('/<\//', '/></', '/>  </', '/</', '/>/', '/&gt;  &lt;/', '/&gt;&lt;/'), array('&lt;/', '&gt;&lt;', '&gt;  &lt;', '&lt;', '&gt;', '&gt;<br>  &lt;', '&gt;<br>&lt;'), htmlspecialchars_decode($request));

// TURNED OFF WITH FALSE
    if (false && $_GET['main_page'] == 'popup_shipping_estimator' && MODULE_SHIPPING_USPS_DEBUG_MODE != 'Off') {
        echo '<br />USPS DOMESTIC $request: <br />' . 'API=RateV4&XML=' . $this->request_display . '<br />';
    }
        // prepare request for debug log
        $this->request_display = $request;
      }

      $request =   'API=RateV4&XML=' . urlencode($request);

    } else {
      // INTERNATIONAL
//echo 'USPS SHIPPING_ORIGIN_ZIP: ' . SHIPPING_ORIGIN_ZIP . '<br />';
      $request =  '<IntlRateV2Request USERID="' . MODULE_SHIPPING_USPS_USERID . '">' .
                  '<Revision>2</Revision>' .
                  '<Package ID="0">' .
                  '<Pounds>' . $this->pounds . '</Pounds>' .
                  '<Ounces>' . $this->ounces . '</Ounces>' .
                  '<MailType>All</MailType>' .
                  '<GXG>' .
                  '  <POBoxFlag>N</POBoxFlag>' .
                  '  <GiftFlag>N</GiftFlag>' .
                  '</GXG>' .
                  '<ValueOfContents>' . ($order->info['subtotal'] + $order->info['tax']) . '</ValueOfContents>' .
                  '<Country>' . zen_get_country_name($order->delivery['country']['id']) . '</Country>' .
                  '<Container>RECTANGULAR</Container>' .
                  '<Size>REGULAR</Size>' .
// Small Flat Rate Box - 'maxLength'=>'8.625', 'maxWidth'=>'5.375','maxHeight'=>'1.625'
// Global Express Guaranteed - Minimum 'maxLength'=>'9.5', 'maxHeight'=>'5.5' Maximum - 'maxLength'=>'46', 'maxWidth'=>'35', 'maxHeight'=>'46' and max. length plus girth combined 108"
// NOTE: sizes for Small Flat Rate Box prevent Global Express Guaranteed
// NOTE: sizes for Global Express Guaranteed prevent Small Flat Rate Box
// Not set up:
// Video - 'maxLength'=>'9.25', 'maxWidth'=>'6.25','maxHeight'=>'2'
// DVD - 'maxLength'=>'7.5625', 'maxWidth'=>'5.4375','maxHeight'=>'.625'
// defaults
// MODULE_SHIPPING_USPS_LENGTH 8.625
// MODULE_SHIPPING_USPS_WIDTH  5.375
// MODULE_SHIPPING_USPS_HEIGHT 1.625
                  '<Width>' . $this->dimensions['width'] . '</Width>' .
                  '<Length>' . $this->dimensions['length'] . '</Length>' .
                  '<Height>' . $this->dimensions['height'] . '</Height>' .
                  '<Girth>0</Girth>' .
//'<Value>' . ($order->info['subtotal'] + $order->info['tax']) . '</Value>' .
//'<CommercialPlusFlag>N</CommercialPlusFlag>' .
                  '<OriginZip>' . SHIPPING_ORIGIN_ZIP . '</OriginZip>' .
                  // In the following line, changed N to Y to activate optional commercial base pricing for international services - 01/27/13 a.forever edit
                  '<CommercialFlag>Y</CommercialFlag>' .
                  '<ExtraServices>' .
                  '  <ExtraService>0</ExtraService>' .
                  '  <ExtraService>1</ExtraService>' .
                  '  <ExtraService>2</ExtraService>' .
                  '  <ExtraService>3</ExtraService>' .
                  '  <ExtraService>5</ExtraService>' .
                  '  <ExtraService>6</ExtraService>' .
                  '</ExtraServices>' .
                  '</Package>' .
                  '</IntlRateV2Request>';

      if ($getTransitTime) {
        $transreq[$request_type] = '';
      }

    if (MODULE_SHIPPING_USPS_DEBUG_MODE != 'Off') {
//      if (MODULE_SHIPPING_USPS_DEBUG_MODE != 'Off') {
        // prepare request for display
        $this->request_display = preg_replace(array('/<\//', '/></', '/>  </', '/</', '/>/', '/&gt;  &lt;/', '/&gt;&lt;/'), array('&lt;/', '&gt;&lt;', '&gt;  &lt;', '&lt;', '&gt;', '&gt;<br>  &lt;', '&gt;<br>&lt;'), htmlspecialchars_decode($request));

// TURNED OFF WITH FALSE
    if (false && $_GET['main_page'] == 'popup_shipping_estimator' && MODULE_SHIPPING_USPS_DEBUG_MODE != 'Off') {
        echo '<br />USPS INTERNATIONAL $request: <br />' . 'API=IntlRateV2&XML=' . $this->request_display . '<br />';
    }
        // prepare request for debug log
        $this->request_display = $request;
      }
      $request =   'API=IntlRateV2&XML=' . urlencode($request);
  }


// Prepare to make quote-request to USPS servers
    switch (MODULE_SHIPPING_USPS_SERVER) {
      case 'production':
// 01-02-2011
// 01-22-2012
      $usps_server = 'http://production.shippingapis.com';
      $api_dll = 'shippingapi.dll';
      break;
      case 'test':
      default:
// 01-27-2013
      $usps_server = 'http://stg-production.shippingapis.com';
      $api_dll = 'ShippingApi.dll';
      break;
    }

    $body = '';
// BOF CURL
    // Send quote request via CURL
    global $request_type;
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $usps_server . '/' . $api_dll);
    curl_setopt($ch, CURLOPT_REFERER, ($request_type == 'SSL' ? HTTPS_SERVER . DIR_WS_HTTPS_CATALOG : HTTP_SERVER . DIR_WS_CATALOG ));
    curl_setopt($ch, CURLOPT_FRESH_CONNECT, 1);
    curl_setopt($ch, CURLOPT_HEADER, 0);
    curl_setopt($ch, CURLOPT_VERBOSE, 0);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $request);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_SSLVERSION, 3);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, TRUE);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Zen Cart');
    if (CURL_PROXY_REQUIRED == 'True') {
      $this->proxy_tunnel_flag = (defined('CURL_PROXY_TUNNEL_FLAG') && strtoupper(CURL_PROXY_TUNNEL_FLAG) == 'FALSE') ? false : true;
      curl_setopt ($ch, CURLOPT_HTTPPROXYTUNNEL, $this->proxy_tunnel_flag);
      curl_setopt ($ch, CURLOPT_PROXYTYPE, CURLPROXY_HTTP);
      curl_setopt ($ch, CURLOPT_PROXY, CURL_PROXY_SERVER_DETAILS);
    }

    // submit request
    $body = curl_exec($ch);

    $this->commError = curl_error($ch);
    $this->commErrNo = curl_errno($ch);
    $this->commInfo = @curl_getinfo($ch);

    // SUBMIT ADDITIONAL REQUESTS FOR DELIVERY TIME ESTIMATES
    if ($getTransitTime && sizeof($transreq) ) {
      while (list($key, $value) = each($transreq)) {
        $transitResp[$key] = '';
        if ($value != '') {
          curl_setopt($ch, CURLOPT_POSTFIELDS, $value);
          $transitResp[$key] = curl_exec($ch);
        }
      }
      $this->parseTransitTimeResults($transitResp);
    }

    curl_close ($ch);

    // DEV ONLY - dump out the returned data for debugging
    if (MODULE_SHIPPING_USPS_DEBUG_MODE == 'Email') mail(STORE_OWNER_EMAIL_ADDRESS, 'Debug: USPS rate quote response', '(You can turn off this debug email by editing your USPS module settings in the admin area of your store.) ' . "\n\n" . $body, 'From: <' . EMAIL_FROM . '>');
    //      echo 'USPS METHODS: <pre>'; echo print_r($body); echo '</pre>';

    if (MODULE_SHIPPING_USPS_DEBUG_MODE == 'Screen') {
      echo ($this->commErrNo != 0 ? '<br />' . $this->commErrNo . ' ' . $this->commError : '') . '<br /><pre>' . $body . '</pre><br />';
    }
    if (MODULE_SHIPPING_USPS_DEBUG_MODE == 'Logs') {
      // skip debug log if no destination zipcode is set:   0==(int)SHIPPING_ORIGIN_ZIP
      $fp = @fopen($logfilename, 'a');
      if ($fp && $this->commErrNo != 0) {
        $usps_shipping_methods_domestic = str_replace("<br />", "\n", $usps_shipping_methods_domestic);
        $usps_shipping_methods_international = str_replace("<br />", "\n", $usps_shipping_methods_international);
        fwrite($fp, date('M d Y G:i:s') . ' -- ' . 'CommErr (should be 0): ' . $this->commErrNo . ' - ' . $this->commError . "\n\n" . $body . "\n\n" . $usps_shipping_methods_domestic . "\n\n" . $usps_shipping_methods_international . "\n\n" . 'SENT TO USPS:' . "\n\n");
        fclose($fp);
      }
    }
    //if communication error, return -1 because no quotes were found, and user doesn't need to see the actual error message (set DEBUG mode to get the messages logged instead)
    if ($this->commErrNo != 0) return -1;
// EOF CURL

// old non-CURL method
    if (FALSE) {
      if (!class_exists('httpClient', FALSE)) require_once(DIR_WS_CLASSES . 'http_client.php');
      $http = new httpClient();
      $http->timeout = 5;
      if ($http->Connect($usps_server, 80)) {
        $http->addHeader('Host', $usps_server);
        $http->addHeader('User-Agent', 'Zen Cart');
        $http->addHeader('Connection', 'Close');

        if ($http->Get('/' . $api_dll . '?' . $request)) $body = $http->getBody();
        if (MODULE_SHIPPING_USPS_DEBUG_MODE == 'Email') mail(STORE_OWNER_EMAIL_ADDRESS, 'Debug: USPS rate quote response', '(You can turn off this debug email by editing your USPS module settings in the admin area of your store.) ' . "\n\n" . $body, 'From: <' . EMAIL_FROM . '>');
  //      echo 'USPS METHODS: <pre>'; echo print_r($body); echo '</pre>';
        if ($transit && is_array($transreq) && ($order->delivery['country']['id'] == STORE_COUNTRY || $this->usps_countries == 'US') ) {
          while (list($key, $value) = each($transreq)) {
            if ($http->Get('/' . $api_dll . '?' . $value)) $transresp[$key] = $http->getBody();
          }
        }
        $http->Disconnect();
      } else {
        return -1;
      }
    } // false


// USPS debug to Logs
    if (MODULE_SHIPPING_USPS_DEBUG_MODE == 'Logs' || MODULE_SHIPPING_USPS_DEBUG_MODE == 'Screen') {
      $body_display = str_replace('&amp;lt;sup&amp;gt;&amp;amp;reg;&amp;lt;/sup&amp;gt;', '', $body);
      $body_display = str_replace('&amp;lt;sup&amp;gt;&amp;amp;trade;&amp;lt;/sup&amp;gt;', '', $body_display);

      $body_display = str_replace('<Service ID', (MODULE_SHIPPING_USPS_DEBUG_MODE == 'Logs' ? "\n\n" : '<br /><br />') . '<Service ID', $body_display);
      $body_display = str_replace('</Service>', '</Service>' . "\n\n", $body_display);
      $body_display = str_replace('<MaxDimensions>', "\n" . '<MaxDimensions>', $body_display);
      $body_display = str_replace('</MaxDimensions>', '</MaxDimensions>' . "\n", $body_display);

      $body_display = str_replace('<Package ID', (MODULE_SHIPPING_USPS_DEBUG_MODE == 'Logs' ? "\n\n" : '<br /><br />') . '<Package ID', $body_display);
      $body_display = str_replace('</Package>', '</Package>', $body_display);
      $body_display = str_replace('<Postage CLASSID', "\n" . '<Postage CLASSID', $body_display);
      $body_display = str_replace('</Postage>', '</Postage>' . "\n", $body_display);

      global $shipping_weight, $currencies;
      $body_display_header = '';
      $body_display_header .= "\n" . 'Server: ' . MODULE_SHIPPING_USPS_SERVER . "\n";
      $body_display_header .= 'Quote Request Rate Type: ' . MODULE_SHIPPING_USPS_RATE_TYPE . "\n";

      $body_display_header .= "\n" . 'Cart Weight:' . $_SESSION['cart']->weight . "\n";
      $body_display_header .= 'Tare Rates: Small/Medium: ' . SHIPPING_BOX_WEIGHT . ' Large: ' . SHIPPING_BOX_PADDING . "\n";
      $body_display_header .= 'Decimals: ' . MODULE_SHIPPING_USPS_DECIMALS . "\n";
      $body_display_header .= 'Total Weight: ' . $shipping_weight . ' Pounds: ' . $this->pounds . ' Ounces: ' . $this->ounces . "\n";
      $body_display_header .= 'Length: ' . MODULE_SHIPPING_USPS_LENGTH . ' Width: ' . MODULE_SHIPPING_USPS_WIDTH . ' Height: ' . MODULE_SHIPPING_USPS_HEIGHT . "\n";

      $body_display_header .= "\n" . 'ZipOrigination: ' . ((int)SHIPPING_ORIGIN_ZIP == 0 ? '***WARNING: NO STORE 5 DIGIT ZIP CODE SET' : SHIPPING_ORIGIN_ZIP) . "\n" . 'ZipDestination: ' . $order->delivery['postcode'] . (!empty($this->countries[$order->delivery['country']['iso_code_2']]) ? ' Country: ' . $this->countries[$order->delivery['country']['iso_code_2']] : '') . ($order->delivery['city'] != '' ? ' City: ' . $order->delivery['city'] : '') . ($order->delivery['state'] != '' ? ' State: ' . $order->delivery['state'] : '') . "\n";
      $body_display_header .= 'Order Total: ' . $currencies->format(($order->info['subtotal'] + $order->info['tax'])) . "\n";
      $body_display_header .= "\n" . 'RESPONSE FROM USPS: ' . "\n";

      if (MODULE_SHIPPING_USPS_DEBUG_MODE == 'Screen') {
        echo '<br />View Source:<br />' . "\n" . $body_display_header . "\n\n" . $body_display . '<br />';
      }
      if (MODULE_SHIPPING_USPS_DEBUG_MODE == 'Logs') {
        // skip debug log if no destination zipcode is set
          $fp = @fopen($logfilename, 'a');
          if ($fp) {
            $usps_shipping_methods_domestic = str_replace("<br />", "\n", $usps_shipping_methods_domestic);
            $usps_shipping_methods_international = str_replace("<br />", "\n", $usps_shipping_methods_international);
            $this->request_display = preg_replace(array('/></', '/>  </'), array('>' . "\n". '<', '>' . "\n" . ' <'), htmlspecialchars_decode($this->request_display));
            fwrite($fp, date('M d Y G:i:s') . ' -- ' . $body_display_header . "\n\n" . $body_display . "\n\n" . $usps_shipping_methods_domestic . "\n\n" . $usps_shipping_methods_international . "\n\n" . '==================================' . "\n\n" . 'Sent to USPS:' . "\n\n" . $this->request_display . "\n\n");
            fclose($fp);
          }
      }
    }
//      echo 'USPS METHODS: <pre>'; echo print_r($body); echo '</pre>';


    // strip reg and trade out 01-02-2011
//echo '<br /><br />USPS $body: ' . $body . '<br /><br />';
    $body = preg_replace(array('/\&lt;sup\&gt;\&amp;reg;\&lt;\/sup\&gt;/', '/\&lt;sup\&gt;\&amp;trade;\&lt;\/sup\&gt;/', '/\" /', '/\",/', '/\"<br>/', '/<br>/'), array('RM', 'TM', '&quot;,', '&quot; ', '&quot;<br>', 'BREAK'), htmlspecialchars_decode($body));
    return json_decode(json_encode(simplexml_load_string($body)),TRUE);
  }

  /**
   * Parse the domestic-services transit time results data
   * Note: International transit times are part of the rate-quote request call, so cannot be handled here
   * @param array $transresp
   */
  function parseTransitTimeResults($transresp) {
    foreach ($transresp as $service => $val) {
      $val = json_decode(json_encode(simplexml_load_string($val)),TRUE);
      switch (TRUE) {
        case (preg_match('#Express MailRM#', $service)):
          $time = $val['CommitmentTime'];
          if ($time == '' || $time == 'No Data') {
            $time = '1 - 2 ' . MODULE_SHIPPING_USPS_TEXT_DAYS;
          } else {
            $time = 'Tomorrow by ' . $time;
          }
          break;
        case (preg_match('#Priority MailRM#', $service)):
          $time = $val['Days'];
          if ($time == '' || $time == 'No Data') {
            $time = '2 - 3 ' . MODULE_SHIPPING_USPS_TEXT_DAYS;
          } elseif ($time == '1') {
            $time .= ' ' . MODULE_SHIPPING_USPS_TEXT_DAY;
          } else {
            $time .= ' ' . MODULE_SHIPPING_USPS_TEXT_DAYS;
          }
          break;
        case (preg_match('#Standard PostRM#', $service)):
          $time = $val['Days'];
          if ($time == '' || $time == 'No Data') {
            $time = '4 - 7 ' . MODULE_SHIPPING_USPS_TEXT_DAYS;
          } elseif ($time == '1') {
            $time .= ' ' . MODULE_SHIPPING_USPS_TEXT_DAY;
          } else {
            $time .= ' ' . MODULE_SHIPPING_USPS_TEXT_DAYS;
          }
          break;
        case (preg_match('#First\-Class#', $service)):
          $time = '2 - 5 ' . MODULE_SHIPPING_USPS_TEXT_DAYS;
          break;
        case (preg_match('#Media MailRM#', $service)):
        default:
          $time = '';
      }
      $this->transittime[$service] = $time == '' ? '' : ' (' . $time . ')';
    }
  }

  /**
   * USPS Country Code List
   * This list is used to compare the 2-letter ISO code against the order country ISO code, and provide the proper/expected
   * spelling of the country name to USPS in order to obtain a rate quote
   *
   * @return array
   */
  function country_list() {
    $list = array(
    'AF' => 'Afghanistan',
    'AL' => 'Albania',
    'AX' => 'Aland Island (Finland)',
    'DZ' => 'Algeria',
    'AD' => 'Andorra',
    'AO' => 'Angola',
    'AI' => 'Anguilla',
    'AG' => 'Antigua and Barbuda',
    'AR' => 'Argentina',
    'AM' => 'Armenia',
    'AW' => 'Aruba',
    'AU' => 'Australia',
    'AT' => 'Austria',
    'AZ' => 'Azerbaijan',
    'BS' => 'Bahamas',
    'BH' => 'Bahrain',
    'BD' => 'Bangladesh',
    'BB' => 'Barbados',
    'BY' => 'Belarus',
    'BE' => 'Belgium',
    'BZ' => 'Belize',
    'BJ' => 'Benin',
    'BM' => 'Bermuda',
    'BT' => 'Bhutan',
    'BO' => 'Bolivia',
    'BA' => 'Bosnia-Herzegovina',
    'BW' => 'Botswana',
    'BR' => 'Brazil',
    'VG' => 'British Virgin Islands',
    'BN' => 'Brunei Darussalam',
    'BG' => 'Bulgaria',
    'BF' => 'Burkina Faso',
    'MM' => 'Burma',
    'BI' => 'Burundi',
    'KH' => 'Cambodia',
    'CM' => 'Cameroon',
    'CA' => 'Canada',
    'CV' => 'Cape Verde',
    'KY' => 'Cayman Islands',
    'CF' => 'Central African Republic',
    'TD' => 'Chad',
    'CL' => 'Chile',
    'CN' => 'China',
    'CX' => 'Christmas Island (Australia)',
    'CC' => 'Cocos Island (Australia)',
    'CO' => 'Colombia',
    'KM' => 'Comoros',
    'CG' => 'Congo, Republic of the',
    'CD' => 'Congo, Democratic Republic of the',
    'CK' => 'Cook Islands (New Zealand)',
    'CR' => 'Costa Rica',
    'CI' => 'Cote d Ivoire (Ivory Coast)',
    'HR' => 'Croatia',
    'CU' => 'Cuba',
    'CY' => 'Cyprus',
    'CZ' => 'Czech Republic',
    'DK' => 'Denmark',
    'DJ' => 'Djibouti',
    'DM' => 'Dominica',
    'DO' => 'Dominican Republic',
    'EC' => 'Ecuador',
    'EG' => 'Egypt',
    'SV' => 'El Salvador',
    'GQ' => 'Equatorial Guinea',
    'ER' => 'Eritrea',
    'EE' => 'Estonia',
    'ET' => 'Ethiopia',
    'FK' => 'Falkland Islands',
    'FO' => 'Faroe Islands',
    'FJ' => 'Fiji',
    'FI' => 'Finland',
    'FR' => 'France',
    'GF' => 'French Guiana',
    'PF' => 'French Polynesia',
    'GA' => 'Gabon',
    'GM' => 'Gambia',
    'GE' => 'Georgia, Republic of',
    'DE' => 'Germany',
    'GH' => 'Ghana',
    'GI' => 'Gibraltar',
    'GB' => 'Great Britain and Northern Ireland',
    'GR' => 'Greece',
    'GL' => 'Greenland',
    'GD' => 'Grenada',
    'GP' => 'Guadeloupe',
    'GT' => 'Guatemala',
    'GN' => 'Guinea',
    'GW' => 'Guinea-Bissau',
    'GY' => 'Guyana',
    'HT' => 'Haiti',
    'HN' => 'Honduras',
    'HK' => 'Hong Kong',
    'HU' => 'Hungary',
    'IS' => 'Iceland',
    'IN' => 'India',
    'ID' => 'Indonesia',
    'IR' => 'Iran',
    'IQ' => 'Iraq',
    'IE' => 'Ireland',
    'IL' => 'Israel',
    'IT' => 'Italy',
    'JM' => 'Jamaica',
    'JP' => 'Japan',
    'JO' => 'Jordan',
    'KZ' => 'Kazakhstan',
    'KE' => 'Kenya',
    'KI' => 'Kiribati',
    'KW' => 'Kuwait',
    'KG' => 'Kyrgyzstan',
    'LA' => 'Laos',
    'LV' => 'Latvia',
    'LB' => 'Lebanon',
    'LS' => 'Lesotho',
    'LR' => 'Liberia',
    'LY' => 'Libya',
    'LI' => 'Liechtenstein',
    'LT' => 'Lithuania',
    'LU' => 'Luxembourg',
    'MO' => 'Macao',
    'MK' => 'Macedonia, Republic of',
    'MG' => 'Madagascar',
    'MW' => 'Malawi',
    'MY' => 'Malaysia',
    'MV' => 'Maldives',
    'ML' => 'Mali',
    'MT' => 'Malta',
    'MQ' => 'Martinique',
    'MR' => 'Mauritania',
    'MU' => 'Mauritius',
    'YT' => 'Mayotte (France)',
    'MX' => 'Mexico',
    'FM' => 'Micronesia, Federated States of',
    'MD' => 'Moldova',
    'MC' => 'Monaco (France)',
    'MN' => 'Mongolia',
    'MS' => 'Montserrat',
    'MA' => 'Morocco',
    'MZ' => 'Mozambique',
    'NA' => 'Namibia',
    'NR' => 'Nauru',
    'NP' => 'Nepal',
    'NL' => 'Netherlands',
    'AN' => 'Netherlands Antilles',
    'NC' => 'New Caledonia',
    'NZ' => 'New Zealand',
    'NI' => 'Nicaragua',
    'NE' => 'Niger',
    'NG' => 'Nigeria',
    'KP' => 'North Korea (Korea, Democratic People\'s Republic of)',
    'NO' => 'Norway',
    'OM' => 'Oman',
    'PK' => 'Pakistan',
    'PA' => 'Panama',
    'PG' => 'Papua New Guinea',
    'PY' => 'Paraguay',
    'PE' => 'Peru',
    'PH' => 'Philippines',
    'PN' => 'Pitcairn Island',
    'PL' => 'Poland',
    'PT' => 'Portugal',
    'QA' => 'Qatar',
    'RE' => 'Reunion',
    'RO' => 'Romania',
    'RU' => 'Russia',
    'RW' => 'Rwanda',
    'SH' => 'Saint Helena',
    'KN' => 'Saint Kitts (St. Christopher and Nevis)',
    'LC' => 'Saint Lucia',
    'PM' => 'Saint Pierre and Miquelon',
    'VC' => 'Saint Vincent and the Grenadines',
    'SM' => 'San Marino',
    'ST' => 'Sao Tome and Principe',
    'SA' => 'Saudi Arabia',
    'SN' => 'Senegal',
    'RS' => 'Serbia',
    'SC' => 'Seychelles',
    'SL' => 'Sierra Leone',
    'SG' => 'Singapore',
    'SK' => 'Slovak Republic',
    'SI' => 'Slovenia',
    'SB' => 'Solomon Islands',
    'SO' => 'Somalia',
    'ZA' => 'South Africa',
    'GS' => 'South Georgia (Falkland Islands)',
    'KR' => 'South Korea (Korea, Republic of)',
    'ES' => 'Spain',
    'LK' => 'Sri Lanka',
    'SD' => 'Sudan',
    'SR' => 'Suriname',
    'SZ' => 'Swaziland',
    'SE' => 'Sweden',
    'CH' => 'Switzerland',
    'SY' => 'Syrian Arab Republic',
    'TW' => 'Taiwan',
    'TJ' => 'Tajikistan',
    'TZ' => 'Tanzania',
    'TH' => 'Thailand',
    'TL' => 'East Timor (Indonesia)',
    'TG' => 'Togo',
    'TK' => 'Tokelau (Union) Group (Western Samoa)',
    'TO' => 'Tonga',
    'TT' => 'Trinidad and Tobago',
    'TN' => 'Tunisia',
    'TR' => 'Turkey',
    'TM' => 'Turkmenistan',
    'TC' => 'Turks and Caicos Islands',
    'TV' => 'Tuvalu',
    'UG' => 'Uganda',
    'UA' => 'Ukraine',
    'AE' => 'United Arab Emirates',
    'UY' => 'Uruguay',
    'UZ' => 'Uzbekistan',
    'VU' => 'Vanuatu',
    'VA' => 'Vatican City',
    'VE' => 'Venezuela',
    'VN' => 'Vietnam',
    'WF' => 'Wallis and Futuna Islands',
    'WS' => 'Western Samoa',
    'YE' => 'Yemen',
    'ZM' => 'Zambia',
    'ZW' => 'Zimbabwe',
    'PS' => 'Palestinian Territory', // usps does not ship
    'ME' => 'Montenegro',
    'GG' => 'Guernsey',
    'IM' => 'Isle of Man',
    'JE' => 'Jersey'
    );

    return $list;
  }

// translate for US Territories
  function usps_translation() {
    global $order;
    global $selected_country, $state_zone_id;
    if (SHIPPING_ORIGIN_COUNTRY == '223') {
      switch($order->delivery['country']['iso_code_2']) {
        case 'AS': // Samoa American
        case 'GU': // Guam
        case 'MP': // Northern Mariana Islands
        case 'PW': // Palau
        case 'PR': // Puerto Rico
        case 'VI': // Virgin Islands US
// which is right
        case 'FM': // Micronesia, Federated States of
          return 'US';
          break;
// stays as original country
//        case 'FM': // Micronesia, Federated States of
        default:
          return $order->delivery['country']['iso_code_2'];
          break;
      }
    } else {
      return $order->delivery['country']['iso_code_2'];
    }
  }
}


// admin display functions inspired by osCbyJetta
function zen_cfg_usps_services($select_array, $key_value, $key = '')
{
  $key_values = explode( ", ", $key_value);
  $name = (($key) ? 'configuration[' . $key . '][]' : 'configuration_value');
  $string = '<b><div style="width:20px;float:left;text-align:center;">&nbsp;</div><div style="width:60px;float:left;text-align:center;">Min</div><div style="width:60px;float:left;text-align:center;">Max</div><div style="float:left;"></div><div style="width:60px;float:right;text-align:center;">Handling</div></b><div style="clear:both;"></div>';
  $string_spacing = '<div><br /><br /><b>&nbsp;International Rates:</b><br /></div>' . $string;
  $string_spacing_international = 0;
  $string = '<div><br /><b>&nbsp;National Rates:</b><br /></div>' . $string;
  for ($i=0; $i<sizeof($select_array); $i++)
  {
//echo 'USPS $select_array[$i]: ' . $select_array[$i] . '<br />';
    if (preg_match("/international/i", $select_array[$i])) {
      $string_spacing_international ++;
    }
    if ($string_spacing_international == 1) {
      $string.= $string_spacing;
    }

    $string .= '<div id="' . $key . $i . '">';
    $string .= '<div style="width:20px;float:left;text-align:center;">' . zen_draw_checkbox_field($name, $select_array[$i], (in_array($select_array[$i], $key_values) ? 'CHECKED' : '')) . '</div>';
    if (in_array($select_array[$i], $key_values)) next($key_values);
    $string .= '<div style="width:60px;float:left;text-align:center;">' . zen_draw_input_field($name, current($key_values), 'size="5"') . '</div>';
    next($key_values);
    $string .= '<div style="width:60px;float:left;text-align:center;">' . zen_draw_input_field($name, current($key_values), 'size="5"') . '</div>';
    next($key_values);
    $string .= '<div style="float:left;">' . preg_replace(array('/RM/', '/TM/', '/International/', '/Envelope/', '/ Mail/', '/Large/', '/Medium/', '/Small/', '/First/', '/Legal/', '/Padded/', '/Flat Rate/', '/Regional Rate/', '/Express Guaranteed /'), array('', '', 'Intl', 'Env', '', 'Lg.', 'Md.', 'Sm.', '1st', 'Leg.', 'Pad.', 'F/R', 'R/R', 'Exp Guar'), $select_array[$i]) . '</div>';
    $string .= '<div style="width:60px;float:right;text-align:center;">$' . zen_draw_input_field($name, current($key_values), 'size="4"') . '</div>';
    next($key_values);
    $string .= '<div style="clear:both;"></div></div>';
  }
  return $string;
}
function zen_cfg_usps_extraservices($select_array, $key_value, $key = '')
{
  $key_values = explode( ", ", $key_value);
  $name = (($key) ? 'configuration[' . $key . '][]' : 'configuration_value');
  $string = '<b><div style="width:20px;float:left;text-align:center;">N</div><div style="width:20px;float:left;text-align:center;">Y</div></b><div style="clear:both;"></div>';
  for ($i=0; $i<sizeof($select_array); $i++)
  {
    $string .= zen_draw_hidden_field($name, $select_array[$i]);
    next($key_values);
    $string .= '<div id="' . $key . $i . '">';
    $string .= '<div style="width:20px;float:left;text-align:center;"><input type="checkbox" name="' . $name . '" value="N" ' . (current($key_values) == 'N' || current($key_values) == '' ? 'CHECKED' : '') . ' id="N-'.$key.$i.'" onClick="if(this.checked==1)document.getElementById(\'Y-'.$key.$i.'\').checked=false;else document.getElementById(\'Y-'.$key.$i.'\').checked=true;"></div>';
    $string .= '<div style="width:20px;float:left;text-align:center;"><input type="checkbox" name="' . $name . '" value="Y" ' . (current($key_values) == 'Y' ? 'CHECKED' : '') . ' id="Y-'.$key.$i.'" onClick="if(this.checked==1)document.getElementById(\'N-'.$key.$i.'\').checked=false;else document.getElementById(\'N-'.$key.$i.'\').checked=true;"></div>';
    next($key_values);
    $string .= preg_replace(array('/Signature/', '/without/', '/Merchandise/', '/TM/', '/RM/'), array('Sig', 'w/out', 'Merch.', '', ''), $select_array[$i]) . '<br />';
    $string .= '<div style="clear:both;"></div></div>';
  }
  return $string;
}
