<?php

	/**
	 * List Columns properties in tables
	 *
	 * $Id: colproperties.php
	 */

	// Include application functions
	include_once('./libraries/lib.inc.php');

	$action = (isset($_REQUEST['action'])) ? $_REQUEST['action'] : '';
	if (isset($_REQUEST['table']))
		$tableName =& $_REQUEST['table'];
	elseif (isset($_REQUEST['view']))
		$tableName =& $_REQUEST['view'];
	else 
		die($lang['strnotableprovided']);

	/**
	 * Displays a screen where they can alter a column
	 */
	function doProperties($msg = '') {
		global $data, $misc, $_reload_browser;
		global $lang;

		if (!isset($_REQUEST['stage'])) $_REQUEST['stage'] = 1;

		switch ($_REQUEST['stage']) {
			case 1:
				$misc->printTrail('column');
				$misc->printTitle($lang['stralter'], 'pg.column.alter'); 
				$misc->printMsg($msg);

				echo "<script src=\"tables.js\" type=\"text/javascript\"></script>";
				echo "<form action=\"colproperties.php\" method=\"post\">\n";

				// Output table header
				echo "<table>\n";
				echo "<tr><th class=\"data required\">{$lang['strname']}</th>\n";
				if ($data->hasAlterColumnType()) {
					echo "<th class=\"data required\" colspan=\"2\">{$lang['strtype']}</th>\n";
					echo "<th class=\"data\">{$lang['strlength']}</th>\n";
				}
				else {
					echo "<th class=\"data required\">{$lang['strtype']}</th>\n";
				}
				echo "<th class=\"data\">{$lang['strnotnull']}</th>\n<th class=\"data\">{$lang['strdefault']}</th>\n<th class=\"data\">{$lang['strcomment']}</th></tr>\n";

				$column = $data->getTableAttributes($_REQUEST['table'], $_REQUEST['column']);
				$column->fields['attnotnull'] = $data->phpBool($column->fields['attnotnull']);

				// Upon first drawing the screen, load the existing column information
				// from the database.
				if (!isset($_REQUEST['default'])) {
					$_REQUEST['field'] = $column->fields['attname'];
					$_REQUEST['type'] = $column->fields['base_type'];
					// Check to see if its' an array type...
					// XXX: HACKY
					if (substr($column->fields['base_type'], strlen($column->fields['base_type']) - 2) == '[]') {
						$_REQUEST['type'] = substr($column->fields['base_type'], 0, strlen($column->fields['base_type']) - 2);
						$_REQUEST['array'] = '[]';
					}
					else {
						$_REQUEST['type'] = $column->fields['base_type'];
						$_REQUEST['array'] = '';
					}
					// To figure out the length, look in the brackets :(
					// XXX: HACKY
					if ($column->fields['type'] != $column->fields['base_type'] && ereg('\\(([0-9, ]*)\\)', $column->fields['type'], $bits)) {
						$_REQUEST['length'] = $bits[1];
					}
					else
						$_REQUEST['length'] = '';
					$_REQUEST['default'] = $_REQUEST['olddefault'] = $column->fields['adsrc'];
					if ($column->fields['attnotnull']) $_REQUEST['notnull'] = 'YES';
					$_REQUEST['comment'] = $column->fields['comment'];
				}				

				// Column name
				echo "<tr><td><input name=\"field\" size=\"16\" maxlength=\"{$data->_maxNameLen}\" value=\"",
					htmlspecialchars($_REQUEST['field']), "\" /></td>\n";
					
				// Column type
				$escaped_predef_types = array(); // the JS escaped array elements
				if ($data->hasAlterColumnType()) {
					// Fetch all available types
					$types = $data->getTypes(true, false, true);
					$types_for_js = array();
					
					echo "<td><select name=\"type\" id=\"type\" onchange=\"checkLengths(document.getElementById('type').value,'');\">\n";				
					// Output any "magic" types.  This came in with Alter Column Type so we don't need to check that
					foreach ($data->extraTypes as $v) {
						$types_for_js[] = strtolower($v);
						echo "\t<option value=\"", htmlspecialchars($v), "\"",
						($v == $_REQUEST['type']) ? ' selected="selected"' : '', ">",
							$misc->printVal($v), "</option>\n";
					}
					while (!$types->EOF) {
						$typname = $types->fields['typname'];
						$types_for_js[] = $typname;
						echo "\t<option value=\"", htmlspecialchars($typname), "\"", ($typname == $_REQUEST['type']) ? ' selected="selected"' : '', ">",
							$misc->printVal($typname), "</option>\n";
						$types->moveNext();
					}
					echo "</select></td>\n";
					
					// Output array type selector
					echo "<td><select name=\"array\">\n";
					echo "\t<option value=\"\"", ($_REQUEST['array'] == '') ? ' selected="selected"' : '', "></option>\n";
					echo "\t<option value=\"[]\"", ($_REQUEST['array'] == '[]') ? ' selected="selected"' : '', ">[ ]</option>\n";
					echo "</select></td>\n";
					$predefined_size_types = array_intersect($data->predefined_size_types, $types_for_js);
					foreach($predefined_size_types as $value) {
						$escaped_predef_types[] = "'{$value}'";
					}
	
					echo "<td><input name=\"length\" id=\"lengths\" size=\"8\" value=\"",
						htmlspecialchars($_REQUEST['length']), "\" /></td>\n";
				} else {
					// Otherwise draw the read-only type name
					echo "<td>", $misc->printVal($data->formatType($column->fields['type'], $column->fields['atttypmod'])), "</td>\n";
				}
				
				echo "<td><input type=\"checkbox\" name=\"notnull\"", (isset($_REQUEST['notnull'])) ? ' checked="checked"' : '', " /></td>\n";
				echo "<td><input name=\"default\" size=\"20\" value=\"", 
					htmlspecialchars($_REQUEST['default']), "\" /></td>\n";
				echo "<td><input name=\"comment\" size=\"40\" value=\"", 
					htmlspecialchars($_REQUEST['comment']), "\" /></td></tr>\n";
				echo "</table>\n";
				echo "<p><input type=\"hidden\" name=\"action\" value=\"properties\" />\n";
				echo "<input type=\"hidden\" name=\"stage\" value=\"2\" />\n";
				echo $misc->form;
				echo "<input type=\"hidden\" name=\"table\" value=\"", htmlspecialchars($_REQUEST['table']), "\" />\n";
				echo "<input type=\"hidden\" name=\"column\" value=\"", htmlspecialchars($_REQUEST['column']), "\" />\n";
				echo "<input type=\"hidden\" name=\"olddefault\" value=\"", htmlspecialchars($_REQUEST['olddefault']), "\" />\n";
				if ($column->fields['attnotnull']) echo "<input type=\"hidden\" name=\"oldnotnull\" value=\"on\" />\n";
				echo "<input type=\"hidden\" name=\"oldtype\" value=\"", htmlspecialchars($data->formatType($column->fields['type'], $column->fields['atttypmod'])), "\" />\n";
				// Add hidden variables to suppress error notices if we don't support altering column type
				if (!$data->hasAlterColumnType()) {
					echo "<input type=\"hidden\" name=\"type\" value=\"", htmlspecialchars($_REQUEST['type']), "\" />\n";				
					echo "<input type=\"hidden\" name=\"length\" value=\"", htmlspecialchars($_REQUEST['length']), "\" />\n";				
					echo "<input type=\"hidden\" name=\"array\" value=\"", htmlspecialchars($_REQUEST['array']), "\" />\n";				
				}
				echo "<input type=\"submit\" value=\"{$lang['stralter']}\" />\n";
				echo "<input type=\"submit\" name=\"cancel\" value=\"{$lang['strcancel']}\" /></p>\n";
				echo "</form>\n";
				echo "<script type=\"text/javascript\">predefined_lengths = new Array(". implode(",",$escaped_predef_types) .");checkLengths(document.getElementById('type').value,'');</script>\n";		
				break;
			case 2:
				// Check inputs
				if (trim($_REQUEST['field']) == '') {
					$_REQUEST['stage'] = 1;
					doProperties($lang['strcolneedsname']);
					return;
				}
				if (!isset($_REQUEST['length'])) $_REQUEST['length'] = '';
				$status = $data->alterColumn($_REQUEST['table'], $_REQUEST['column'], $_REQUEST['field'], 
							     isset($_REQUEST['notnull']), isset($_REQUEST['oldnotnull']), 
							     $_REQUEST['default'], $_REQUEST['olddefault'],
							     $_REQUEST['type'], $_REQUEST['length'], $_REQUEST['array'], $_REQUEST['oldtype'],
							     $_REQUEST['comment']);
				if ($status == 0) {
					if ($_REQUEST['column'] != $_REQUEST['field']) {
						$_REQUEST['column'] = $_REQUEST['field'];
						$_reload_browser = true;
					}
					doDefault($lang['strcolumnaltered']);
				}
				else {
					$_REQUEST['stage'] = 1;
					doProperties($lang['strcolumnalteredbad']);
					return;
				}
				break;
			default:
				echo "<p>{$lang['strinvalidparam']}</p>\n";
		}
	}

	/**
	 * Show default list of columns in the table
	 */
	function doDefault($msg = '', $isTable = true) {
		global $data, $conf, $misc, $tableName;
		global $lang;

		function attPre(&$rowdata) {
			global $data;
			$rowdata->fields['+type'] = $data->formatType($rowdata->fields['type'], $rowdata->fields['atttypmod']);
		}
		
		if (empty($_REQUEST['column']))
			$msg.= "<br/>{$lang['strnoobjects']}";
		
			$misc->printTrail('column');
			$misc->printTitle($lang['strcolprop']);
			#$misc->printTabs('column','properties');
			$misc->printMsg($msg);

		if (! empty($_REQUEST['column'])) {
			// Get table
			$tdata = $data->getTable($tableName);
			// Get columns
			$attrs = $data->getTableAttributes($tableName, $_REQUEST['column']);

			// Show comment if any
			if ($attrs->fields['comment'] !== null)
				echo "<p class=\"comment\">", $misc->printVal($attrs->fields['comment']), "</p>\n";

			$column = array(
				'column' => array(
					'title' => $lang['strcolumn'],
					'field' => field('attname'),
				),
				'type' => array(
					'title' => $lang['strtype'],
					'field' => field('+type'),
				)
			);
		
			if ($isTable) {
				$column['notnull'] = array(
					'title' => $lang['strnotnull'],
					'field' => field('attnotnull'),
					'type'  => 'bool',
					'params'=> array('true' => 'NOT NULL', 'false' => '')
				);
				$column['default'] = array(
					'title' => $lang['strdefault'],
					'field' => field('adsrc'),
				);
			}

			$actions=array();
			$misc->printTable($attrs, $column, $actions, null, 'attPre');

			echo "<br />\n";

			echo "<ul class=\"navlink\">\n";
			if ($isTable) {
				$return_url = urlencode("colproperties.php?{$misc->href}&amp;table=$tableName&amp;column={$_REQUEST['column']}");

				/* Browse link */
				echo "\t<li><a href=\"display.php?{$misc->href}&amp;subject=column&amp;column=",
					urlencode($_REQUEST['column']), "&amp;return_url={$return_url}&amp;return_desc=", urlencode($lang['strback']), "&amp;query=", 
					urlencode("SELECT \"{$_REQUEST['column']}\", count(*) AS \"count\" FROM \"$tableName\" GROUP BY \"{$_REQUEST['column']}\" ORDER BY \"{$_REQUEST['column']}\"") , "\">{$lang['strbrowse']}</a></li>\n";

				/* Edit link */
				echo "\t<li><a href=\"colproperties.php?action=properties&amp;{$misc->href}&amp;table=", urlencode($_REQUEST['table']),
				    "&amp;column=", urlencode($_REQUEST['column']) . "\">{$lang['stralter']}</a></li>\n";
				
				/* Delete link */
				if ($data->hasDropColumn())
					echo "\t<li><a href=\"tblproperties.php?action=confirm_drop&amp;{$misc->href}&amp;table=", urlencode($_REQUEST['table']),
						"&amp;column=" . urlencode($_REQUEST['column']) . "\">{$lang['strdrop']}</a></li>\n";
			} else {
				$return_url = urlencode("colproperties.php?{$misc->href}&amp;view=$tableName&amp;column={$_REQUEST['column']}");
				/* Browse link */
				echo "\t<li><a href=\"display.php?{$misc->href}&amp;subject=column&amp;column=",
					urlencode($_REQUEST['column']), "&amp;return_url={$return_url}&amp;return_desc=", urlencode($lang['strback']), "&amp;query=",
					urlencode("SELECT \"{$_REQUEST['column']}\", count(*) AS \"count\" FROM \"$tableName\" GROUP BY \"{$_REQUEST['column']}\" ORDER BY \"{$_REQUEST['column']}\"") , "\">{$lang['strbrowse']}</a></li>\n";
			}

			echo "</ul>\n";
		}
	}

	$misc->printHeader($lang['strtables'] . ' - ' . $tableName);
	$misc->printBody();

	if (isset($_REQUEST['view']))
		doDefault(null, false);
	else
		switch ($action) {
			case 'properties':
				if (isset($_POST['cancel'])) doDefault();
				else doProperties();
				break;
			default:
				doDefault();
				break;
		}
	
	$misc->printFooter();

?>