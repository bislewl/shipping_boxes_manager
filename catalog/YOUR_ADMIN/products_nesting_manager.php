<?php require('includes/application_top.php'); ?>
<?php
	$product_nesting_groups = $db->Execute("SELECT * FROM " . TABLE_PRODUCTS_NESTING_GROUPS . " ORDER BY grouping_id ASC;");
	if (isset($_REQUEST['action'])) {
	  switch($_REQUEST['action']) {
	    case 'remove':
	      if (isset($_GET['box_id'])) {
	        $db->Execute("DELETE FROM " . TABLE_PRODUCTS_NESTING_GROUPS . " WHERE grouping_id = " . (int)$_GET['grouping_id'] . " LIMIT 1;");
	      }
	      // redirect the page
	      zen_redirect(zen_href_link(FILENAME_PRODUCTS_NESTING_MANAGER));
	      break;
	    case 'add':
	      if (isset($_POST['group_code']) && isset($_POST['compatible_group_code']) && isset($_POST['nesting_percentage'])) {
	        $sql_data_array = array(
	          'group_code' => $_POST['group_code'],
	          'compatible_group_code' => $_POST['compatible_group_code'],
	          'nesting_percentage' => $_POST['nesting_percentage']
	        );
	        if (isset($_POST['grouping_id'])) {
	          // edited box
	          $method = 'update';
	          $where = 'grouping_id=' . $_POST['grouping_id'];
	        } else {
	          // new box
	          $method = 'insert';
	          $where = '';
	        }
	        zen_db_perform(TABLE_PRODUCTS_NESTING_GROUPS, $sql_data_array, $method, $where);        
	      }
	      // redirect the page
	      zen_redirect(zen_href_link(FILENAME_PRODUCTS_NESTING_MANAGER));
	      break;
	    case 'edit':
	      if (isset($_GET['grouping_id'])) {
	        $grouping_id = (int)$_GET['grouping_id'];
	        $edit_group = $db->Execute("SELECT * FROM " . TABLE_PRODUCTS_NESTING_GROUPS . " WHERE grouping_id = " . $grouping_id . " LIMIT 1;");
	        $group_code = $edit_group->fields['group_code'];
	        $compatible_group_code = $edit_group->fields['compatible_group_code'];
	        $nesting_percentage = $edit_group->fields['nesting_percentage'];
	      }
	      break;
	    case 'view':
	    	if (isset($_GET['group_code'])) {
	    		$products = $db->Execute("SELECT * FROM " . TABLE_PRODUCTS . " WHERE nestable_group_code = '" . $_GET['group_code'] . "' ORDER BY products_id ASC;"); 
				}
				break;
	  } 
	}	
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
  label{display:block;width:200px;float:left;}
  .buttonRow{padding:5px 0;}
  .forward{float:right;}
  .clearBoth {clear: both;}
  #addBox{width: 375px;}
  table#existingGroups { margin-left: 0px; border-collapse:collapse; border:1px solid #036; font-size: small; width: 100%; }
  table#existingGroups th { background-color:#036; border-bottom:1px double #fff; color: #fff; text-align:center; padding:8px; }
  table#existingGroups td { border:1px solid #036; vertical-align:top; padding:5px 10px; }
</style>
</head>

<body onLoad="init()">
<?php require(DIR_WS_INCLUDES . 'header.php'); ?>
<h1><?php echo HEADING_TITLE; ?></h1>
<?php
	if (isset($_REQUEST['action']) && $_REQUEST['action'] == 'view' && is_object($products) && $products->RecordCount() > 0) {
		echo '<h2>Products in Group</h2>';
		while (!$products->EOF) {
			echo '<ul>';
			echo '	<li><a href="' . zen_href_link(FILENAME_CATEGORIES, 'action=new_product' . '&cPath=' . $products->fields['master_categories_id'] . '&pID=' . $products->fields['products_id'] . '&product_type=' . zen_get_products_type($products->fields['products_id'])) . '" target="_blank">' . zen_get_products_name($products->fields['products_id']) . '</a></li>';
			echo '</ul>';
			$products->MoveNext();
		}
		echo '<br />'; 
	} 
?>
<?php if ($product_nesting_groups->RecordCount() > 0) { ?>
<h2>Existing Groups</h2>
<table id="existingGroups">
  <tr>
    <th>Grouping ID</th>
    <th>Group Code</th>
    <th>Compatible Group Code</th>
    <th>Nesting Percentage</th>
    <th>Action</th>
  </tr>
<?php 
  while (!$product_nesting_groups->EOF) {
    echo '<tr>';
    echo '<td>' . $product_nesting_groups->fields['grouping_id'] . '</td>';
    echo '<td><a href="' . zen_href_link(FILENAME_PRODUCTS_NESTING_MANAGER, 'action=view&group_code=' . $product_nesting_groups->fields['group_code']) . '">' . $product_nesting_groups->fields['group_code'] . '</a></td>';
    echo '<td><a href="' . zen_href_link(FILENAME_PRODUCTS_NESTING_MANAGER, 'action=view&group_code=' . $product_nesting_groups->fields['compatible_group_code']) . '">' . $product_nesting_groups->fields['compatible_group_code'] . '</a></td>';
    echo '<td>' . $product_nesting_groups->fields['nesting_percentage'] . '</td>';
    echo '<td><a href="' . zen_href_link(FILENAME_PRODUCTS_NESTING_MANAGER, 'action=edit&grouping_id=' . $product_nesting_groups->fields['grouping_id']) . '">Edit</a> / <a href="' . zen_href_link(FILENAME_PRODUCTS_NESTING_MANAGER, 'action=remove&grouping_id=' . $product_nesting_groups->fields['grouping_id']) . '">Remove</a></td>';
    echo '</tr>';
    $product_nesting_groups->MoveNext();
  }
?>
</table>
<?php } ?>
<div id="addBox">
  <h2>Add/Edit Group</h2>
  <?php echo zen_draw_form('products_nesting_manager', FILENAME_PRODUCTS_NESTING_MANAGER, 'action=add', 'post'); ?>
    <label for="group_code">Group Code</label><?php echo zen_draw_input_field('group_code', $group_code, 'size="30" id="group_code"'); ?><br />
    <label for="compatible_group_code">Compatible Group Code</label><?php echo zen_draw_input_field('compatible_group_code', $compatible_group_code, 'size="30" id="compatible_group_code"'); ?><br />
    <label for="nesting_percentage">Nesting Percentage</label><?php echo zen_draw_input_field('nesting_percentage', $nesting_percentage, 'size="30" id="nesting_percentage"'); ?><br />
    <?php if ($grouping_id) echo zen_draw_hidden_field('grouping_id', $grouping_id); ?>
    <div class="buttonRow forward"><?php echo zen_image_submit('button_submit.gif', 'submit');?></div>   
  </form>
  <br class="clearBoth" />
  <p>Create a new grouping using the form above.  Items in the 'group code' will nest inside items in the 'compatible group code' at the nesting percentage.<br /><br />For example, group A nests inside group B at 80% means that 20% of the item from group A will be added to the height of the item in group B.</p>
</div>
</body>
</html>
<?php require(DIR_WS_INCLUDES . 'application_bottom.php'); ?>