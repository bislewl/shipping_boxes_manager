<?php
/**
 * @package Shipping Boxes Manger
 * @copyright Copyright 2003-2014 Zen Cart Development Team
 * @copyright Portions Copyright 2003 osCommerce
 * @license http://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 * @version $Id: $
 */
require('includes/application_top.php');
if (isset($_REQUEST['action'])) {
  switch($_REQUEST['action']) {
    case 'remove':
      if (isset($_GET['box_id'])) {
        $db->Execute("DELETE FROM " . TABLE_SHIPPING_BOXES_MANAGER . " WHERE box_id = " . (int)$_GET['box_id'] . " LIMIT 1;");
      }
      // redirect the page
      zen_redirect(zen_href_link(FILENAME_SHIPPING_BOXES_MANAGER));
      break;
    case 'add':
      if (isset($_POST['length']) && isset($_POST['width']) && isset($_POST['height']) && isset($_POST['weight'])) {
        $sql_data_array = array(
          'title' => $_POST['title'],
          'length' => $_POST['length'],
          'width' => $_POST['width'],
          'height' => $_POST['height'],
          'weight' => $_POST['weight'],
          'destination' => $_POST['destination'],
          'volume' => $_POST['length'] * $_POST['width'] * $_POST['height']
        );
        if (isset($_POST['box_id'])) {
          // edited box
          $method = 'update';
          $where = 'box_id=' . $_POST['box_id'];
        } else {
          // new box
          $method = 'insert';
          $where = '';
        }
        zen_db_perform(TABLE_SHIPPING_BOXES_MANAGER, $sql_data_array, $method, $where);        
      }
      // redirect the page
      zen_redirect(zen_href_link(FILENAME_SHIPPING_BOXES_MANAGER));
      break;
    case 'edit':
      if (isset($_GET['box_id'])) {
        $box_id = (int)$_GET['box_id'];
        $edit_box = $db->Execute("SELECT * FROM " . TABLE_SHIPPING_BOXES_MANAGER . " WHERE box_id = " . $box_id . " LIMIT 1;");
        $box_title = $edit_box->fields['title'];
        $box_length = $edit_box->fields['length'];
        $box_width = $edit_box->fields['width'];
        $box_height = $edit_box->fields['height'];
        $box_weight = $edit_box->fields['weight'];
        $box_destination = $edit_box->fields['destination'];  
      }
      break;
  } 
}
$destinations = array(
  array('id' => 'both', 'text' => 'both'),
  array('id' => 'domestic', 'text' => 'domestic'),
  array('id' => 'international', 'text' => 'international')
);
// get existing boxes
$boxes_query = "SELECT * FROM " . TABLE_SHIPPING_BOXES_MANAGER . " ORDER BY box_id ASC;";
$boxes = $db->Execute($boxes_query);
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1" />
<title><?php echo TITLE; ?></title>
<link rel="stylesheet" type="text/css" href="includes/stylesheet.css">
<link rel="stylesheet" type="text/css" href="includes/cssjsmenuhover.css" media="all" id="hoverJS">
<script language="javascript" src="includes/menu.js"></script>
<script language="javascript" src="includes/general.js"></script>
<script type="text/javascript">
<!--
function init() {
  cssjsmenu('navbar');
  if (document.getElementById)
  {
    var kill = document.getElementById('hoverJS');
    kill.disabled = true;
  }
}
// -->
</script>
<style type="text/css">
  label{display:block;width:100px;float:left;}
  .buttonRow{padding:5px 0;}
  .forward{float:right;}
  #addBox{width: 275px;}
  table#existingBoxes { margin-left: 0px; border-collapse:collapse; border:1px solid #036; font-size: small; width: 100%; }
  table#existingBoxes th { background-color:#036; border-bottom:1px double #fff; color: #fff; text-align:center; padding:8px; }
  table#existingBoxes td { border:1px solid #036; vertical-align:top; padding:5px 10px; }
</style>
</head>

<body onLoad="init()">
<?php require(DIR_WS_INCLUDES . 'header.php'); ?>
<h1><?php echo HEADING_TITLE; ?></h1>
<?php if ($boxes->RecordCount() > 0) { ?>
<h2>Existing Boxes</h2>
<table id="existingBoxes">
  <tr>
    <th>Box ID</th>
    <th>Title</th>
    <th>Length (in)</th>
    <th>Width (in)</th>
    <th>Height (in)</th>
    <th>Weight (lbs)</th>
    <th>Volume (in<sup>3</sup>)</th>
    <th>Destination</th>
    <th>Action</th>
  </tr>
<?php 
  while (!$boxes->EOF) {
    echo '<tr>';
    echo '<td>' . $boxes->fields['box_id'] . '</td>';
    echo '<td>' . $boxes->fields['title'] . '</td>';
    echo '<td>' . $boxes->fields['length'] . '</td>';
    echo '<td>' . $boxes->fields['width'] . '</td>';
    echo '<td>' . $boxes->fields['height'] . '</td>';
    echo '<td>' . $boxes->fields['weight'] . '</td>';
    echo '<td>' . $boxes->fields['volume'] . '</td>';
    echo '<td>' . $boxes->fields['destination'] . '</td>';
    echo '<td><a href="' . zen_href_link(FILENAME_SHIPPING_BOXES_MANAGER, 'action=edit&box_id=' . $boxes->fields['box_id']) . '">Edit</a> / <a href="' . zen_href_link(FILENAME_SHIPPING_BOXES_MANAGER, 'action=remove&box_id=' . $boxes->fields['box_id']) . '">Remove</a></td>';
    echo '</tr>';
    $boxes->MoveNext();
  }
?>
</table>
<?php } ?>
<div id="addBox">
  <h2>Add/Edit Box</h2>
  <?php echo zen_draw_form('shipping_boxes_manager', FILENAME_SHIPPING_BOXES_MANAGER, 'action=add', 'post'); ?>
    <label for="length">Title</label><?php echo zen_draw_input_field('title', $box_title, 'size="30" id="title"'); ?><br />
    <label for="length">Length (in)</label><?php echo zen_draw_input_field('length', $box_length, 'size="30" id="length"'); ?><br />
    <label for="width">Width (in)</label><?php echo zen_draw_input_field('width', $box_width, 'size="30" id="width"'); ?><br />
    <label for="height">Height (in)</label><?php echo zen_draw_input_field('height', $box_height, 'size="30" id="height"'); ?><br />
    <label for="weight">Weight (lbs)</label><?php echo zen_draw_input_field('weight', $box_weight, 'size="30" id="weight"'); ?>
    <label for="destination">Destination</label><?php echo zen_draw_pull_down_menu('destination', $destinations, $box_destination); ?>
    <?php if ($box_id) echo zen_draw_hidden_field('box_id', $box_id); ?>
    <div class="buttonRow forward"><?php echo zen_image_submit('button_submit.gif', 'submit');?></div>   
  </form>
</div>
</body>
</html>
<?php require(DIR_WS_INCLUDES . 'application_bottom.php'); ?>