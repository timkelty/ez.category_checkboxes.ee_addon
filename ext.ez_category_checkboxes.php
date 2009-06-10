<?php

//--------------------------------------------
//   Category Checkboxes Extension
//   using the following hooks:
//   - publish_form_weblog_preferences
//   - show_full_control_panel_end
//   Build: 20080712
//   author: Elwin Zuiderveld (aka Cocoaholic)
//--------------------------------------------

if (! defined('EXT'))
{
    exit('Invalid file request');
}

/**
 * Checkbox replacement for the categories multi-select box
 *
 * @package			ExpressionEngine
 * @subpackage		Category Checkboxes Extension
 * @category		Extension
 * @author			Elwin Zuiderveld (aka Cocoaholic)
 * @link			http://elwinzuiderveld.nl/
 */
class Ez_category_checkboxes {
	
	var $dev_mode 		= 0; // 1
	var $table_width	= '45%'; // auto
	
	var $settings 		= array();
	var $name 			= 'Category Checkboxes';
	var $class_name 	= 'Ez_category_checkboxes';
	var $version 		= '1.1.5';
	var $description 	= 'Checkbox replacement for the categories multi-select box';
	var $settings_exist = 'y';
	var $docs_url 		= '';
	
	/**
	 * Constructor
	 *
	 * @access public
	 * @param array
	 * @return void
	 */
	function Ez_category_checkboxes($settings='')
	{
		$this->settings = $settings;
	}
	// END
	
	/**
	 * Settings
	 *
	 * @access public
	 * @return array
	 */
	function settings()
	{
		global $DB, $LANG;
		
		$settings = array();
		$settings['show_group_name'] = array('r', array('yes' => "yes", 'no' => "no"), 'yes');
		
		return $settings;
	}
	// END
	
	/**
	 * Activate Extension
	 *
	 * Installs the extension
	 *
	 * @access	public
	 * @return	void
	 */
	function activate_extension()
	{
		global $DB;
		
		$default_settings = serialize(array('show_group_name' => array('r', array('yes' => "yes", 'no' => "no"), 'yes')));
		
		$DB->query($DB->insert_string('exp_extensions',
				array(
				'extension_id'	=> '',
				'class'			=> $this->class_name,
				'method'		=> 'fetch_group_name',
				'hook'			=> 'publish_form_weblog_preferences',
				'settings'		=> $default_settings,
				'priority'		=> 10,
				'version'		=> $this->version
				)
			)
		);
		
		$DB->query($DB->insert_string('exp_extensions',
				array(
				'extension_id'	=> '',
				'class'			=> $this->class_name,
				'method'		=> 'create_category_table',
				'hook'			=> 'show_full_control_panel_end',
				'settings'		=> '',
				'priority'		=> 10,
				'version'		=> $this->version
				)
			)
		);
		
	}
	// END
	
	/**
	 * Update Extension
	 *
	 * Updates the extension
	 *
	 * @access	public
	 * @param	string
	 * @return	void
	 */
	function update_extension($current='')
	{
		global $DB;
		
		if ($current == '' || $current == $this->version)
		{
			return FALSE;
		}
		
		// custom version check because we're using the show_full_control_panel_end hook
		$query = $DB->query("SELECT version FROM exp_extensions WHERE class = '".$DB->escape_str($this->class_name)."'");
		if ($query->num_rows > 0)
		{
			if ($query->row['version'] < $this->version)
			{
				// update from 1.0 to 1.1
				if ($current < '1.1')
				{
					// lazy!
					$this->disable_extension();
					$this->activate_extension();
				}
				
			}
		}
		
		$data = array('version' => $this->version);
		$DB->query($DB->update_string('exp_extensions', $data, "class = '".$this->class_name."'"));
	}
	// END
	
	/**
	 * Disable Extension
	 *
	 * Uninstalls the extension
	 *
	 * @access	public
	 * @return	void
	 */
	function disable_extension()
	{
		global $DB;
		
		if ($this->dev_mode) $DB->query("DELETE FROM exp_extensions WHERE class = '".$DB->escape_str($this->class_name)."'");
	}
	// END
	
	/**
	 * Fetch Group Name
	 *
	 * Fetches the category group name(s)
	 *
	 * @access	public
	 * @param	array
	 * @return	array
	 */
	function fetch_group_name($row)
	{
		global $IN, $EXT, $SESS;
		
		if ($IN->GBL('C') == 'publish' && ($IN->GBL('M') == '' ||  $IN->GBL('M') == 'entry_form' || $IN->GBL('M') == 'new_entry') || $IN->GBL('C') == 'edit' && ($IN->GBL('M') == 'edit_entry' || $IN->GBL('M') == 'new_entry'))
		{
			// This variable will return whatever the last extension returned to this hook
			if ($EXT->last_call !== FALSE)
			{
				$row = $EXT->last_call;
			}
			
			$cat_group = $row['cat_group'];
			
			if ($cat_group != '')
			{
				$SESS->cache['ez_category_checkboxes']['cat_group'] = $cat_group;
			}
			
		}
		
		return $row;
	}
	
	/**
	 * Create Category Table
	 *
	 * Creates the extension's html and javascript code
	 *
	 * @access	public
	 * @param	string
	 * @return	string
	 */
	function create_category_table($out='')
	{
		global $IN, $DB, $EXT, $SESS;
		
		// This variable will return whatever the last extension returned to this hook
		if ($EXT->last_call !== FALSE)
		{
			$out = $EXT->last_call;
		}
		
		if ($IN->GBL('C') == 'publish' && ($IN->GBL('M') == '' ||  $IN->GBL('M') == 'entry_form' || $IN->GBL('M') == 'new_entry') || $IN->GBL('C') == 'edit' && ($IN->GBL('M') == 'edit_entry' || $IN->GBL('M') == 'new_entry'))
		{
			// get group names from session cache
			if (isset($SESS->cache['ez_category_checkboxes']['cat_group']))
			{
				$show_group_name = $this->settings['show_group_name'];
				
				$js_array = '';
				$first_group = '';
				
				if ($show_group_name != 'no')
				{
					// get group_id from session cache
					$cat_group = $SESS->cache['ez_category_checkboxes']['cat_group'];
					
					$real_cat_groups = '';
					
					// make sure we only fetch non-empty groups
					$sql = $DB->query("SELECT DISTINCT group_id FROM exp_categories WHERE group_id IN ('".str_replace('|', "','", $DB->escape_str($cat_group))."') ORDER BY group_id ASC");
					
					if ($sql->num_rows > 0)
					{
						foreach ($sql->result as $r)
						{
							$real_cat_groups .= '|'.$r['group_id'];
						}
					}
					
					$group_names = array();
					
					$query = $DB->query("SELECT group_id, group_name FROM exp_category_groups WHERE group_id IN ('".str_replace('|', "','", $DB->escape_str($real_cat_groups))."') ORDER BY group_id ASC");
					
					if ($query->num_rows > 0)
					{
						foreach ($query->result as $r)
						{
							$group_names[] = '"'.$r['group_name'].'"';
						}
					}
					
					$first_group = array_shift($group_names);
					$first_group = str_replace('"', '', $first_group);
					$js_array = implode(',', $group_names);
					
				}
				
				$table = <<<TABLE
<!-- Code added by the Category Checkboxes Extension -->
<div id="ez_checkbox_div" style="width:{$this->table_width};margin:8px 0 7px 0;">
<table cellspacing="0" cellpadding="0" border="0" width="100%">
	<tr>
		<td class="tableHeadingAlt">{$first_group}&nbsp;</td>
	</tr>
</table>
<table id="ez_checkbox_table" cellspacing="0" cellpadding="0" border="0" width="100%" class="tableBorder">
	<tr id="ez_dummy_row">
		<td>&nbsp;</td>
	</tr>
</table>
</div>
<!-- END Category Checkboxes Extension code -->

<div id='categorytree'
TABLE;
				// replace select box with table
				$out = str_replace("<div id='categorytree'", $table, $out);
				$out = preg_replace_callback('!<option(.*)</option>!', create_function('$matches','return str_replace("&nbsp;","@nbsp@", $matches[0]);'), $out);
				$out = str_replace("document.getElementById('categorytree').innerHTML = str;", "get_selected();\nstr = str.replace(/&nbsp;/g, '@nbsp@');\ndocument.getElementById('categorytree').innerHTML = str;\nez_create_table('y');", $out);
				
				$path = PATH_CP_IMG;
				
				$js = <<<JS
<!-- Code added by the Category Checkboxes Extension -->
<script type="text/javascript">
//<![CDATA[
function ez_create_table(update_list)
{
	// hide multi-select box
	// CHANGED: Fusionary: check for existence of #categorytree first
	if (!document.getElementById || !document.getElementById('categorytree')) return;
	document.getElementById('categorytree').style.display='none';
	
	var cat_table = document.getElementById('ez_checkbox_table');
	var cat_table_body = cat_table.getElementsByTagName("tbody")[0];
	cat_table.removeChild(cat_table_body);
	
	var cat_table_body = document.createElement('tbody');
	cat_table.appendChild(cat_table_body);
	
	var categorytree = document.getElementsByName('category[]');
	var category_list = categorytree[0].options;
	
	var counter = category_list.length;
	
	// re-select categories that were initially selected
	if (update_list == 'y')
	{
		var selected_count = selected_options.length;
		for (i=0; i< selected_count; i++)
		{
			if (selected_options[i] != null)
			{
				for (n=0; n< counter; n++)
				{
					var opt = category_list[n];
					if (opt.value == selected_options[i])
					{
						opt.selected = 'selected';
					}
				}
			}
		}
	}
	
	var group_names = new Array($js_array);
	
	for (i=0; i< counter; i++)
	{
		var option = category_list[i];
		
		//create row
		var newRow = document.createElement('tr');
		
		// add table cell
		var cell = document.createElement('td');
		cell.setAttribute('style','padding: 2px 6px; line-height: 1.7em;');
		
		var checked = '';
		if (option.selected)
		{
			checked = ' checked="checked"';
		}
		
		var inner_data = option.innerHTML;
		
		// this is a crazy workaround for Safari's "innerHTML converts nbsp to spaces" bug
		// details: http://bugs.webkit.org/show_bug.cgi?id=11947
		inner_data = inner_data.replace(/@nbsp@@nbsp@@nbsp@@nbsp@/g, '@ez_img@');
		inner_data = inner_data.replace(/@ez_img@@nbsp@@nbsp@/g, '<img src="{$path}cat_marker.gif" border="0" width="18" height="14" alt="" title="" style="margin:1px 0 -1px 20px;" />&nbsp;&nbsp;');
		inner_data = inner_data.replace(/@ez_img@/g, '<img src="{$path}clear.gif" border="0" width="44" height="14" alt="" title="" />');
		
		var cell_html = '';
		if (inner_data == '-------')
		{
			if (group_names.length > 0) cell_html = group_names.shift();
			cell_html += '&nbsp;';
			cell.setAttribute('class','tableHeadingAlt');
		}
		else
		{
			cell_html = '<label style="width:100%;height:100%;display:block;"><input type="checkbox"'+checked+' id="ez_checkbox_'+option.value+'" onclick="ez_update_categories(this.id,this.checked);" />&nbsp;&nbsp;'+inner_data+'</label>';
		}
		
		cell.innerHTML = cell_html;
		newRow.appendChild(cell);
		cat_table_body.appendChild(newRow);
		cat_table.appendChild(cat_table_body);
	}
	
	// re-color table rows
	ez_zebraTable();
}
// END

// global array
var selected_options = new Array();

function get_selected()
{
	var categorytree = document.getElementsByName('category[]');
	var category_list = categorytree[0].options;
	
	var counter = category_list.length;
	for (i=0; i< counter; i++)
	{
		var option = category_list[i];
		
		if (option.selected)
		{
			// add value to array
			selected_options[i] = option.value;
		}
	}
}
// END

function ez_zebraTable()
{
	var table = document.getElementById('ez_checkbox_table');
	newClass = 'tableCellOne';
	
	var counter = table.rows.length;
	for (i=0; i< counter; i++)
	{
		row = table.rows[i];
		
		if (row.firstChild.className != 'tableHeadingAlt')
		{
			if (newClass != 'tableCellOne')
			{
				newClass = 'tableCellOne';
			}
			else
			{
				newClass = 'tableCellTwo';
			}
			
			// stripe rows
			var child_count = row.childNodes.length;
			for (n=0; n< child_count; n++)
			{
				row.childNodes[n].className=newClass;
			}
		}
	}
}
// END

function ez_update_categories(checkbox_id,checked)
{
	var categorytree = document.getElementsByName('category[]');
	var category_list = categorytree[0].options;
	var option_id = checkbox_id.replace('ez_checkbox_', '');
	
	var counter = category_list.length;
	for (i=0; i< counter; i++)
	{
		var option = category_list[i];
		if (option.value == option_id) option.selected=checked;
	}
}
// END

ez_create_table();

//]]>
</script>
<!-- END Category Checkboxes Extension code -->
JS;
				// add javascript just before the body closing tag
				$out = str_replace('</body>', $js.'</body>', $out);
			}
		}
		
		return $out;
	}
	// END
	
}
// END Category Checkboxes Class
?>