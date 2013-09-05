<?php
	$products_nesting_groups_sql = $db->Execute("SELECT DISTINCT group_code as unique_group_code FROM " . TABLE_PRODUCTS_NESTING_GROUPS . "
																							 UNION
																							 SELECT DISTINCT compatible_group_code as unique_group_code FROM " . TABLE_PRODUCTS_NESTING_GROUPS . " as unique_group_code;");
	if ($products_nesting_groups_sql->RecordCount() > 0) {
		$products_nesting_groups = array(array('id' => 0, 'text' => 'None'));
		while (!$products_nesting_groups_sql->EOF) {
			$products_nesting_groups[] = array('id' => $products_nesting_groups_sql->fields['unique_group_code'], 'text' => $products_nesting_groups_sql->fields['unique_group_code']);
			$products_nesting_groups_sql->MoveNext();
		}
?>          
          <tr>
            <td colspan="2"><?php echo zen_draw_separator('pixel_trans.gif', '1', '10'); ?></td>
          </tr>          
          <tr bgcolor="#DDEACC">
            <td class="main"><?php echo TEXT_PRODUCTS_NESTING_GROUPS; ?></td>
            <td class="main"><?php echo zen_draw_separator('pixel_trans.gif', '24', '15') . '&nbsp;' . zen_draw_pull_down_menu('nestable_group_code', $products_nesting_groups, $pInfo->nestable_group_code); ?></td>
          </tr>
          <tr>
            <td colspan="2"><?php echo zen_draw_separator('pixel_trans.gif', '1', '10'); ?></td>
          </tr>
<?php } ?>