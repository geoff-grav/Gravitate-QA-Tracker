<?php

/**
 * @package Gravitate Issue Tracker
 */
/*
Plugin Name: Gravitate Issue Tracker
Plugin URI: http://www.gravitatedesign.com
Description: This is Plugin allows you and your users to Track Website issues.
Version: 1.0.0
*/

// Make sure we don't expose any info if called directly
if ( !function_exists( 'add_action' ) ) {
	echo 'Gravitate Issue Tracker.';
	exit;
}

class GRAVITATE_ISSUE_TRACKER {

	private static $version = '1.0.0';
	private static $option_key = 'gravitate_issue_tracker_settings';

	static function activate()
	{
		// Set Default Data
		$settings = array();
		$settings['static_url'] = 'gissues';
		$settings['status'] = "RESOLVED : lightgreen\nPending : orange\nAddressed : #77bbff\nDiscussion : #ff3333\nFuture Request : #ff88ff";
		$settings['priorities'] = "URGENT : #ff3333\nHigh : orange\nNormal : lightgrey\nLow : #77bbff\nFuture : #ff88ff";
		$settings['departments'] = "Developer\nDesign\nAccount Manager\nDigital Marketer\nClient : white";

		update_option(self::$option_key, $settings);

	}

	static function init()
	{
		$settings = get_option(self::$option_key);

		if(!empty($settings['static_url']) && strpos($_SERVER['REQUEST_URI'], $settings['static_url']) !== false || !empty($_GET[$settings['static_url']]))
		{
			if(!is_user_logged_in())
			{
				echo 'You must be logged in';
				exit;
			}

			if(!empty($_POST['update_data']))
			{
				if(defined('DOING_AJAX'))
				{
					define('DOING_AJAX', true);
				}
				self::update_data($_POST['issue_id'], $_POST['issue_key'], $_POST['issue_value']);
				exit;
			}

			if(!empty($_POST['save_issue']))
			{
				if(defined('DOING_AJAX'))
				{
					define('DOING_AJAX', true);
				}
				self::save_issue();
				exit;
			}

			if(!empty($_POST['delete_issue']))
			{
				if(defined('DOING_AJAX'))
				{
					define('DOING_AJAX', true);
				}
				self::delete_issue();
				exit;
			}

			if(!empty($_GET['gissues_controls']))
			{
				self::controls();
				exit;
			}
			else if(!empty($_GET['view_issues']))
			{
				self::view_issues();
				exit;
			}
			else
			{
				self::tracker();
				exit;
			}
		}
	}

	private static function update_data($post_id, $key, $value)
	{
		$meta_value = get_post_meta( $post_id, 'gravitate_issue_data', true );

		if($meta_value[$key] == $value)
		{
			echo '--GRAVITATE_ISSUE_AJAX_SUCCESSFULLY--';
	    	exit;
		}

		// Update Issue
	    if(!empty($meta_value) && isset($meta_value[$key]))
	    {
	    	$meta_value[$key] = $value;

	    	$current_user = self::get_user_name();

	    	if($key == 'status' && $value == 1 && $current_user)
	    	{
	    		$meta_value['completed_by'] = $current_user;
	    	}

	    	if(update_post_meta($post_id, 'gravitate_issue_data', $meta_value))
	    	{
	    		echo '--GRAVITATE_ISSUE_AJAX_SUCCESSFULLY--';
	    		exit;
	    	}
	    }

	    echo 'Error';
		exit;
	}

	private static function add_comment($post_id, $comment=false)
	{
		$current_user = wp_get_current_user();
    	$current_user_name = self::get_user_name();

    	if($comment && $current_user && $current_user_name)
    	{
    		$data = array();
    		$data['user_id'] = $current_user->ID;
    		$data['user'] = $current_user_name;
    		$data['datetime'] = date('m-d-Y H:i:s');
    		$data['comment'] = $comment;

			if(add_post_meta($post_id, 'gravitate_issue_comment', $data))
			{
				echo '--GRAVITATE_ISSUE_AJAX_SUCCESSFULLY--';
				exit;
			}
		}

	    echo 'Error';
		exit;
	}

	private static function delete_issue()
	{
		// Delete Issue
	    if(!empty($_POST['delete_issue']))
	    {
	    	if($issue = get_post($_POST['delete_issue']))
	    	{
	    		if(delete_post_meta($issue->ID, 'gravitate_issue_data'))
	    		{
		    		if(wp_delete_post($issue->ID, true))
		    		{
		    			echo '--GRAVITATE_ISSUE_AJAX_SUCCESSFULLY--';
						exit;
		    		}
		    	}
	    	}
	    }

	    echo 'Error';
		exit;
	}

	private static function get_user_name()
	{
		$current_user = wp_get_current_user();

		if($current_user->user_login)
		{
			if($current_user->user_firstname && $current_user->user_lastname)
			{
				$current_user = $current_user->user_firstname.' '.$current_user->user_lastname;
			}
			else if($current_user->display_name)
			{
				$current_user = $current_user->display_name;
			}
			else
			{
				$current_user = $current_user->user_login;
			}

			return $current_user;
		}

		return false;
	}

	private static function save_issue()
	{
		$current_user = self::get_user_name();

		if($current_user)
		{
			// Create Image
		    if(!empty($_POST['screenshot_data']))
		    {
		    	$image_name = 'capture_'.rand(0,9).time().rand(0,9).'.png';
		    	$upload_dir = wp_upload_dir();

		    	if(!empty($upload_dir['path']))
		    	{
			        //file_put_contents(dirname(dirname(__FILE__)).'/capture_images/'.$image_name, base64_decode($_POST['screenshot_data']));
			        $data = base64_decode(substr($_POST['screenshot_data'], (strpos($_POST['screenshot_data'], ',')+1)));

			        $im = imagecreatefromstring($data);

			        if ($im !== false)
			        {
			            imagepng($im, $upload_dir['path'].'/'.$image_name);
			            imagedestroy($im);
			        }
			    }
		    }

		    // Save Data to Database
		    $args = array(
			  'post_status'           => 'draft',
			  'post_type'             => 'gravitate_issue',
			  'post_author'           => 1,
			);

			if($post_id = wp_insert_post( $args ))
			{
				$postdata = array();
				$postdata['description'] = esc_sql($_POST['description']);
				$postdata['status'] = esc_sql((!empty($_POST['status']) ? $_POST['status'] : 2));
				$postdata['priority'] = esc_sql($_POST['priority']);
				$postdata['department'] = esc_sql($_POST['department']);
				$postdata['created_by'] = esc_sql($current_user);
				$postdata['screenshot'] = $upload_dir['url'].'/'.$image_name;
				$postdata['url'] = esc_sql($_POST['url']);
				$postdata['browser'] = esc_sql($_POST['browser']);
				$postdata['os'] = esc_sql($_POST['os']);
				$postdata['screen_width'] = esc_sql($_POST['screen_width']);
				$postdata['device_width'] = esc_sql($_POST['device_width']);
				$postdata['ip'] = esc_sql(self::real_ip());
				$postdata['link'] = esc_sql($_POST['link']);


				if(update_post_meta($post_id, 'gravitate_issue_data', $postdata))
				{
					echo '--GRAVITATE_ISSUE_AJAX_SUCCESSFULLY--';
					exit;
				}
			}
		}

		echo 'Error';
		exit;

	}

	static function admin_menu()
	{
		add_submenu_page( 'options-general.php', 'Gravitate Issues', 'Gravitate Issues', 'manage_options', 'gravitate_issue_tracker', array( __CLASS__, 'settings' ));
	}

	static function enqueue_scripts()
	{
		//wp_enqueue_style( 'plugins', $library_location . 'css/gravitate-issue-tracker.css');
	    wp_enqueue_script( 'js_plugins', plugins_url( 'js/html2canvas_0.5.0.js', __FILE__ ));
	}

	static function settings()
	{
		if(!empty($_GET['page']) && $_GET['page'] == 'gravitate_issue_tracker')
		{

			$settings = array();

			$fields = array();
			$fields['static_url'] = array('type' => 'text', 'label' => 'Static URL', 'value' => 'gissues', 'description' => 'Users must be logged in or coming from the Allowed IPs to access this.');
			$fields['ips'] = array('type' => 'text', 'label' => 'Allowed IPs', 'description' => 'IPs that are allowed access without Logging into WordPress.  Separate with commas. ');

			$fields['status'] = array('type' => 'textarea', 'label' => 'Status List', 'value' => "RESOLVED\nPending\nAddressed\nDiscussion\nFuture Request", 'description' => 'One Per Line.  Place them in order of what you want the Sort Order to be.<br> Separate colors with : &nbsp; &nbsp; Colors can be Hex code.');
			$fields['priorities'] = array('type' => 'textarea', 'label' => 'Priority List', 'value' => "URGENT\nHigh\nNormal\nLow\nFuture", 'description' => 'One Per Line.  Place them in order of what you want the Sort Order to be.<br> Separate colors with : &nbsp; &nbsp; Colors can be Hex code.');
			$fields['departments'] = array('type' => 'textarea', 'label' => 'Department List', 'value' => "Developer\nDesign\nAccount Manager\nDigital Marketer\nClient", 'description' => 'One Per Line.  Place them in order of what you want the Sort Order to be.<br> Separate colors with : &nbsp; &nbsp; Colors can be Hex code.');

			// $error = 'The Settings have been locked.  Please see your Web Developer.  This is most likely intensional as the don\'t want you to mess with the settings :)';

			if(!empty($error))
			{
				?>
					<div class="wrap">
					<h2>Gravitate Issue Tracker</h2>
					<h4 style="margin: 6px 0;">Version <?php echo self::$version;?></h4>
					<?php if($error){?><div class="error"><p><?php echo $error; ?></p></div><?php } ?>
					</div>
				<?php
			}
			else
			{
				if(!empty($_POST['save_settings']) && !empty($_POST['settings']))
				{
					// $error = 'There was an error saving the Settings (Cannot access disk). Please try again.';
					$_POST['settings']['updated_at'] = time();
					if(update_option( self::$option_key, $_POST['settings'] ))
					{
						$success = 'Settings Saved Successfully';
					}
				}

				$settings = get_option(self::$option_key);

				if(!empty($settings))
				{
					foreach ($settings as $key => $value)
					{
						if(isset($fields[$key]))
						{
							$fields[$key]['value'] = $value;
						}
					}
				}

				?>
					<div class="wrap">
						<h2>Gravitate Issue Tracker</h2>
						<h4 style="margin: 6px 0;">Version <?php echo self::$version;?></h4>

						<?php if(!empty($success)){?><div class="updated"><p><?php echo $success; ?></p></div><?php } ?>
						<?php if(!empty($error)){?><div class="error"><p><?php echo $error; ?></p></div><?php } ?>
						<br>

						<form method="post">
							<input type="hidden" name="save_settings" value="1">
							<table class="form-table">
							<tr>
								<th><label>Non-Logged in Users Access</label></th>
								<td><a href="">sdfasdfsadfasfd</a></td>
							</tr>
							<?php
							foreach($fields as $meta_key => $field)
							{
								?>
								<tr>
									<th><label for="<?php echo $meta_key;?>"><?php echo $field['label'];?></label></th>
									<td>
									<?php

									if($field['type'] == 'text')
									{
										?><input type="text" name="settings[<?php echo $meta_key;?>]" id="<?php echo $meta_key;?>"<?php echo (isset($field['maxlength']) ? ' maxlength="'.$field['maxlength'].'"' : '');?> value="<?php echo esc_attr( (isset($field['value']) ? $field['value'] : '') );?>" class="regular-text" /><br /><?php
									}
									else if($field['type'] == 'textarea')
									{
										?><textarea rows="6" cols="38" name="settings[<?php echo $meta_key;?>]" id="<?php echo $meta_key;?>"><?php echo esc_attr( (isset($field['value']) ? $field['value'] : '') );?></textarea><br /><?php
									}
									else if($field['type'] == 'select')
									{
										?>
										<select name="settings[<?php echo $meta_key;?>]" id="<?php echo $meta_key;?>">
										<?php
										foreach($field['options'] as $option_value => $option_label){
											$real_value = ($option_value !== $option_label && !is_numeric($option_value) ? $option_value : $option_label);
											?>
											<option<?php echo ($real_value !== $option_label ? ' value="'.$real_value.'"' : '');?> <?php selected( ($real_value !== $option_label ? $real_value : $option_label), esc_attr( (isset($field['value']) ? $field['value'] : '') ));?>><?php echo $option_label;?></option>
											<?php
										} ?>
										</select>
										<?php
									}
									if(isset($field['description'])){ ?><span class="description"><?php echo $field['description'];?></span><?php } ?>
									</td>
								</tr>
								<?php
							}
							?>
							</table>
							<p><input type="submit" value="Save Settings" class="button button-primary" id="submit" name="submit"></p>
						</form>

				    </div>
				<?php
			}
		}
	}

	static function real_ip()
	{
	    if (!empty($_SERVER['HTTP_CLIENT_IP']))
	    {
	        $clientIP = $_SERVER['HTTP_CLIENT_IP'];
	    }
	    elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR']))
	    {
	        $clientIP = $_SERVER['HTTP_X_FORWARDED_FOR'];
	    }
	    elseif (!empty($_SERVER['HTTP_X_REAL_IP']))
	    {
	        $clientIP = $_SERVER['HTTP_X_REAL_IP'];
	    }
	    else
	    {
	        $clientIP = $_SERVER['REMOTE_ADDR'];
	    }
	    return $clientIP;
	}

	static function view_issues()
	{

		$settings = get_option(self::$option_key);

		?>
		<!doctype html>
		<html lang="en-US">
		<head>
		<meta charset="utf-8">
		<title>Issues</title>
		<link rel="stylesheet" href="<?php echo plugins_url( 'css/gravitate-issue-tracker.css', __FILE__ );?>" type="text/css"/>
		<link rel="stylesheet" href="<?php echo plugins_url( 'slickgrid/slick.grid.css', __FILE__ );?>" type="text/css"/>
		<link href="//maxcdn.bootstrapcdn.com/font-awesome/4.2.0/css/font-awesome.min.css" rel="stylesheet">
		<script src="<?php echo plugins_url( 'slickgrid/lib/jquery-1.7.min.js', __FILE__ );?>"></script>
		<script src="<?php echo plugins_url( 'slickgrid/lib/jquery-ui-1.8.16.custom.min.js', __FILE__ );?>"></script>
		<!-- <script type='text/javascript' src='//code.jquery.com/jquery-2.0.3.min.js'></script> -->
		<script src="<?php echo plugins_url( 'slickgrid/lib/jquery.event.drag-2.2.js', __FILE__ );?>"></script>

		<script src="<?php echo plugins_url( 'slickgrid/slick.core.js', __FILE__ );?>"></script>
		<script src="<?php echo plugins_url( 'slickgrid/slick.grid.js', __FILE__ );?>"></script>
		<script src="<?php echo plugins_url( 'slickgrid/slick.editors.js?v=01', __FILE__ );?>"></script>

		</head>
		<body>
		<div id="view_header"><button id="cancel_views" onclick="">< Back</button> <input placeholder="Filter..." id="search" type="text"></div>
		<div id="container"></div>
		<script>

		var _original_grid_data = [];

		jQuery(document).ready(function() {

			$('button#cancel_views').on('click', function(){
		        parent.closeIssue();
		        window.open('?gissues_controls=1', '_self');
		    });

		    $('#search').on('keyup', function()
		    {

		    	if(!$(this).val())
		    	{
		    		grid.setData(_original_grid_data);
		    		grid.render();
		    		add_grid_listeners();
		    	}
		    	else
		    	{
		    		var gdata = [];
			    	var cols = grid.getColumns();
			    	var d, c;

			    	var newData = [];
			    	var unique = []

			    	for(d in _original_grid_data)
			    	{
			    		for(c in cols)
				    	{
				    		if(_original_grid_data[d][cols[c].id].length > 0)
				    		{
					    		if(_original_grid_data[d][cols[c].id].toLowerCase().indexOf($(this).val().toLowerCase()) > 0)
					    		{
					    			if(!unique[d])
					    			{
					    				newData.push(_original_grid_data[d]);
					    				unique[d] = 1;
					    			}
					    		}
					    	}
				    	}
			    	}

					grid.setData(newData);
					grid.render();
					add_grid_listeners();
				}

		    });

		});

		function add_grid_listeners()
	    {
	    	$('select').each(function(){
	    		$(this).css('color', $(this).find(":selected").attr('data-color'));
	    	});

		    $('.update_data').on('change', function(){

		    	$(this).css('color', $(this).find(":selected").attr('data-color'));

		    	$('body').addClass('loading');
		    	var html = $(this).parent().parent().html().split('selected="selected"').join('');
		    	html = html.split('value="'+$(this).val()+'"').join('value="'+$(this).val()+'" selected="selected"');

		    	var data = grid.getData();
                var active = grid.getActiveCell();
                var cols = grid.getColumns();

		        $.post( '<?php echo $_SERVER['REQUEST_URI'];?>', {
	                update_data: true,
	                issue_id: data[active.row].id.split('<p>').join('').split('</p>').join(''),
	                issue_key: $(this).attr('id'),
	                issue_value: $(this).val(),
	            },
	            function(response)
	            {
	                //alert(response);
	                if(response && response.indexOf('GRAVITATE_ISSUE_AJAX_SUCCESSFULLY') > 0)
	                {
	                    //

	                    data[active.row][cols[active.cell].id] = html.split(" selected='selected'").join('');
	                    grid.setData(data);
	                    grid.render();

	                    add_grid_listeners();

	                    $('body').removeClass('loading');
	                }
	                else
	                {
	                	$('body').removeClass('loading');

	                    // Error
	                    alert('There was an error Saving the Issue. Please try again or contact your Account Manager.');
	                }
	            });
		    });
		}

		function delete_issue(issue_id)
		{
			$.post( '<?php echo $_SERVER['REQUEST_URI'];?>', {
                delete_issue: issue_id,
            },
            function(response)
            {
                //alert(response);
                if(response && response.indexOf('GRAVITATE_ISSUE_AJAX_SUCCESSFULLY') > 0)
                {
                    //
                    var data = grid.getData();
					data.splice(grid.getActiveCell().row, 1);
					_original_grid_data.splice(grid.getActiveCell().row, 1);
					grid.setData(data);
					grid.render();
					add_grid_listeners();
                }
                else
                {
                    // Error
                    alert('There was an error Saving the Issue. Please try again or contact your Account Manager.');
                }
            });
		}

		function HTMLFormatter(row, cell, value, columnDef, dataContext) {
		        return value;
		}

		function sorterNumeric(a, b) {
		    var x = (isNaN(a[sortcol]) || a[sortcol] === "" || a[sortcol] === null) ? -99e+10 : parseFloat(a[sortcol]);
		    var y = (isNaN(b[sortcol]) || b[sortcol] === "" || b[sortcol] === null) ? -99e+10 : parseFloat(b[sortcol]);
		    return sortdir * (x === y ? 0 : (x > y ? 1 : -1));
		}

		function sorterStringCompare(a, b) {
		    var x = a[sortcol], y = b[sortcol];
		    return sortdir * (x === y ? 0 : (x > y ? 1 : -1));
		}

		  var grid,
		      data = [],
		      columns = [
		          { id: "id", name: "#", field: "id", width: 30, sortable: true, sorter: sorterNumeric },
		          { id: "status", name: "Status", field: "status", width: 150, sortable: true, sorter: sorterStringCompare },
		          { id: "department", name: "Department", field: "department", width: 120, sortable: true, sorter: sorterStringCompare },
		          { id: "priority", name: "Priority", field: "priority", width: 120, sortable: true, sorter: sorterStringCompare },
		          { id: "created_by", name: "Created By", field: "created_by", width: 110, sortable: true, sorter: sorterStringCompare },
		          { id: "description", name: "Description", field: "description", width: ($(window).width()-660), sortable: true, sorter: sorterStringCompare, editor: Slick.Editors.LongText },
		          //{ id: "url", name: "URL", field: "url", width: 60, sortable: true, sorter: sorterStringCompare },
		          //{ id: "screenshot", name: "Screenshot", field: "screenshot", width: 120, sortable: true, sorter: sorterStringCompare }
		          { id: "info", name: "Info", field: "info", width: 130, sortable: true, sorter: sorterStringCompare }
		      ],
		      options = {
		        enableCellNavigation: true,
		        enableColumnReorder: true,
		        multiColumnSort: true,
		        //forceFitColumns: true,
		        syncColumnCellResize: true,
		        rowHeight: 40,
		        defaultFormatter: HTMLFormatter,
		        editable: true,
		      };

		    <?php

		    $selects = array('status', 'priorities', 'departments');

		    foreach ($selects as $select)
		    {
		    	if(!empty($settings[$select]))
			    {
			    	$items = $settings[$select];
			    	$settings[$select] = array();
				    foreach (explode("\n", str_replace("\r", '', $items)) as $key => $value)
				    {
				    	$color = trim(strpos($value, ':') ? substr($value, (strpos($value, ':')+1)) : '');
				    	$value = trim(strpos($value, ':') ? substr($value, 0, strpos($value, ':')) : $value);
				    	$settings[$select][] = array('value' => $value, 'color' => $color);
				    }
				}
		    }

		    $issues = get_posts(array('post_type' => 'gravitate_issue', 'post_status' => 'draft'));

		    if($issues)
		    {
		        $num = 0;
		        foreach($issues as $issue)
		        {
		        	$data = get_post_meta( $issue->ID, 'gravitate_issue_data', 1);
		            $description = str_replace('"','', $data['description']);
		            ?>
		            var inner_start = '<p>';
		            data[<?php echo $num;?>] = {
		                id: inner_start+"<?php echo $issue->ID;?></p>",
		                //status: inner_start+"<?php echo $data['status'];?></p>",
		                status: inner_start+"<select id=\"status\" class=\"update_data\"><?php if(!empty($settings['status'])){foreach($settings['status'] as $k => $v){?><option data-order=\"<?php echo $k;?>\" data-color=\"<?php echo $v['color'];?>\" <?php selected($data['status'], sanitize_title($v['value']));?> value=\"<?php echo sanitize_title($v['value']);?>\"><?php echo $v['value'];?></option><?php }} ?></select></p>",
		                department: inner_start+"<select id=\"department\" class=\"update_data\"><?php if(!empty($settings['departments'])){foreach($settings['departments'] as $k => $v){?><option data-order=\"<?php echo $k;?>\" data-color=\"<?php echo $v['color'];?>\" <?php selected($data['department'], sanitize_title($v['value']));?> value=\"<?php echo sanitize_title($v['value']);?>\"><?php echo $v['value'];?></option><?php }} ?></select></p>",
		                priority: inner_start+"<select id=\"priority\" class=\"update_data\"><?php if(!empty($settings['priorities'])){foreach($settings['priorities'] as $k => $v){?><option data-order=\"<?php echo $k;?>\" data-color=\"<?php echo $v['color'];?>\" <?php selected($data['priority'], sanitize_title($v['value']));?> value=\"<?php echo sanitize_title($v['value']);?>\"><?php echo $v['value'];?></option><?php }} ?></select></p>",
		                created_by: inner_start+"<?php echo ucwords($data['created_by']);?></p>",
		                description: inner_start+'<?php echo strip_tags(str_replace(array("\n","\r"), "", $description));?></p>',
		                //url: inner_start+"<a target=\"windowMain\" href=\"<?php echo $data['url'];?>\">url</a></p>",
		                //screenshot: inner_start+"<a target=\"windowMain\" href=\"<?php echo $data['screenshot'];?>\">Screenshot</a></p>"
		                info: inner_start+'<a class="btn" target="windowMain" title="<?php echo $data['url'];?>" href="<?php echo site_url().$data['url'];?>"><i class="fa fa-link"></i></a><a class="btn" target="windowMain" href="<?php echo $data['screenshot'];?>"><i class="fa fa-photo"></i></a><a class="btn" href="#"><i class="fa fa-comment"></i></a><a class="btn" onclick=\'alert(\"URL: <?php echo $data['url'];?>\\n\\nBrowser: <?php echo $data['browser'];?>\\n\\nOS: <?php echo $data['os'];?>\\n\\n\\nBrowser Width: <?php echo $data['screen_width'];?>\\n\\nDevice Width: <?php echo $data['device_width'];?>\\n\\nIP: <?php echo $data['ip'];?>\\n\\n\\nDate Time: <?php echo date('M jS - g:ia', strtotime($issue->post_date));?>\");\'><i class="fa fa-info-circle"></i></a><a class="btn" target="windowMain" title="Delete" onclick="if(confirm(\'You are about to Delete this Issue.\\n\\nClick OK to continue.\')){delete_issue(<?php echo $issue->ID;?>);};"><i class="fa fa-close"></i></a></p>'
		            };

		            _original_grid_data[<?php echo $num;?>] = {
		                id: inner_start+"<?php echo $issue->ID;?></p>",
		                status: inner_start+"<select id=\"status\" class=\"update_data\"><?php if(!empty($settings['status'])){foreach($settings['status'] as $k => $v){?><option data-order=\"<?php echo $k;?>\" data-color=\"<?php echo $v['color'];?>\" <?php selected($data['status'], sanitize_title($v['value']));?> value=\"<?php echo sanitize_title($v['value']);?>\"><?php echo $v['value'];?></option><?php }} ?></select></p>",
		                department: inner_start+"<select id=\"department\" class=\"update_data\"><?php if(!empty($settings['departments'])){foreach($settings['departments'] as $k => $v){?><option data-order=\"<?php echo $k;?>\" data-color=\"<?php echo $v['color'];?>\" <?php selected($data['department'], sanitize_title($v['value']));?> value=\"<?php echo sanitize_title($v['value']);?>\"><?php echo $v['value'];?></option><?php }} ?></select></p>",
		                priority: inner_start+"<select id=\"priority\" class=\"update_data\"><?php if(!empty($settings['priorities'])){foreach($settings['priorities'] as $k => $v){?><option data-order=\"<?php echo $k;?>\" data-color=\"<?php echo $v['color'];?>\" <?php selected($data['priority'], sanitize_title($v['value']));?> value=\"<?php echo sanitize_title($v['value']);?>\"><?php echo $v['value'];?></option><?php }} ?></select></p>",
		                created_by: inner_start+"<?php echo ucwords($data['created_by']);?></p>",
		                description: inner_start+'<?php echo strip_tags(str_replace(array("\n","\r"), "", $description));?></p>',
		                info: inner_start+'<a class="btn" target="windowMain" title="<?php echo $data['url'];?>" href="<?php echo site_url().$data['url'];?>"><i class="fa fa-link"></i></a><a class="btn" target="windowMain" href="<?php echo $data['screenshot'];?>"><i class="fa fa-photo"></i></a><a class="btn" href="#"><i class="fa fa-comment"></i></a><a class="btn" onclick=\'alert(\"URL: <?php echo $data['url'];?>\\n\\nBrowser: <?php echo $data['browser'];?>\\n\\nOS: <?php echo $data['os'];?>\\n\\n\\nBrowser Width: <?php echo $data['screen_width'];?>\\n\\nDevice Width: <?php echo $data['device_width'];?>\\n\\nIP: <?php echo $data['ip'];?>\\n\\n\\nDate Time: <?php echo date('M jS - g:ia', strtotime($issue->post_date));?>\");\'><i class="fa fa-info-circle"></i></a><a class="btn" target="windowMain" title="Delete" onclick="if(confirm(\'You are about to Delete this Issue.\\n\\nClick OK to continue.\')){delete_issue(<?php echo $issue->ID;?>);};"><i class="fa fa-close"></i></a></p>'
		            };

		            <?php
		            $num++;
		        }
		    }
		    ?>

		  	grid = new Slick.Grid("#container", data, columns, options);



			// grid.onSort.subscribe(function (e, args) {
			//   currentSortCol = args.sortCol;
			//   isAsc = args.sortAsc;
			//   grid.invalidateAllRows();
			//   grid.render();
			// });

			grid.onCellChange.subscribe(function (e,args) {

				console.log(args);

                var cols = grid.getColumns();

                if(cols[args.cell].id == 'description')
                {
                	$('body').addClass('loading');

	                $.post( '<?php echo $_SERVER['REQUEST_URI'];?>', {
		                update_data: true,
		                issue_id: args.item.id.split('<p>').join('').split('</p>').join(''),
		                issue_key: 'description',
		                issue_value: args.item.description.split('<p>').join('').split('</p>').join(''),
		            },
		            function(response)
		            {
		                if(response && response.indexOf('GRAVITATE_ISSUE_AJAX_SUCCESSFULLY') > 0)
		                {
		                    //
		                    $('body').removeClass('loading');
		                }
		                else
		                {
		                	$('body').removeClass('loading');

		                    // Error
		                    alert('1111111There was an error Saving the Issue. Please try again or contact your Account Manager.');
		                }
		                add_grid_listeners();
		            });
				}
             });

			grid.onColumnsReordered.subscribe(function(e, args) {
				add_grid_listeners();
			});

			grid.onSort.subscribe(function (e, args) {
			  var cols = args.sortCols;

			  args.grid.getData().sort(function (dataRow1, dataRow2) {
			  for (var i = 0, l = cols.length; i < l; i++) {
			      sortdir = cols[i].sortAsc ? 1 : -1;
			      sortcol = cols[i].sortCol.field;

			      var result = cols[i].sortCol.sorter(dataRow1, dataRow2); // sorter property from column definition comes in play here
			      if (result != 0) {
			        return result;
			      }
			    }
			    return 0;
			  });
			  args.grid.invalidateAllRows();
			  args.grid.render();
			  add_grid_listeners();
			});

			add_grid_listeners();


		  //   grid.onSort.subscribe(function(e, args) {
		  //   sortdir = args.sortAsc ? 1 : -1;
		  //   sortcol = args.sortCol.field;

		  //   data_view.sort(args.sortCol.sorter, sortdir);
		  //   args.grid.invalidateAllRows();
		  //   args.grid.render();
		  // });

		</script>
		</body>
		</html>
		<?php
	}

	static function controls()
	{
		$settings = get_option(self::$option_key);

		?>
		<!doctype html>
		<html lang="en-US">
		<head>
		<meta charset="utf-8">
		<title>Issues</title>
		<link rel='stylesheet' href='<?php echo plugins_url( 'css/gravitate-issue-tracker.css', __FILE__ );?>' type='text/css' media='all' />
		<script type='text/javascript' src='//code.jquery.com/jquery-2.0.3.min.js'></script>
		<script type='text/javascript'>

		navigator.sayswho= (function(){
		    var ua= navigator.userAgent, tem,
		    M= ua.match(/(opera|chrome|safari|firefox|msie|trident(?=\/))\/?\s*(\d+)/i) || [];
		    if(/trident/i.test(M[1])){
		        tem=  /\brv[ :]+(\d+)/g.exec(ua) || [];
		        return 'IE '+(tem[1] || '');
		    }
		    if(M[1]=== 'Chrome'){
		        tem= ua.match(/\bOPR\/(\d+)/)
		        if(tem!= null) return 'Opera '+tem[1];
		    }
		    M= M[2]? [M[1], M[2]]: [navigator.appName, navigator.appVersion, '-?'];
		    if((tem= ua.match(/version\/(\d+)/i))!= null) M.splice(1, 1, tem[1]);
		    return M.join(' ');
		})();

		(function (window) {
		    {
		        var unknown = '-';

		        // screen
		        var screenSize = '';
		        if (screen.width) {
		            width = (screen.width) ? screen.width : '';
		            height = (screen.height) ? screen.height : '';
		            screenSize += '' + width + " x " + height;
		        }

		        //browser
		        var nVer = navigator.appVersion;
		        var nAgt = navigator.userAgent;
		        var browser = navigator.appName;
		        var version = '' + parseFloat(navigator.appVersion);
		        var majorVersion = parseInt(navigator.appVersion, 10);
		        var nameOffset, verOffset, ix;

		        // Opera
		        if ((verOffset = nAgt.indexOf('Opera')) != -1) {
		            browser = 'Opera';
		            version = nAgt.substring(verOffset + 6);
		            if ((verOffset = nAgt.indexOf('Version')) != -1) {
		                version = nAgt.substring(verOffset + 8);
		            }
		        }
		        // MSIE
		        else if ((verOffset = nAgt.indexOf('MSIE')) != -1) {
		            browser = 'Microsoft Internet Explorer';
		            version = nAgt.substring(verOffset + 5);
		        }
		        // Chrome
		        else if ((verOffset = nAgt.indexOf('Chrome')) != -1) {
		            browser = 'Chrome';
		            version = nAgt.substring(verOffset + 7);
		        }
		        // Safari
		        else if ((verOffset = nAgt.indexOf('Safari')) != -1) {
		            browser = 'Safari';
		            version = nAgt.substring(verOffset + 7);
		            if ((verOffset = nAgt.indexOf('Version')) != -1) {
		                version = nAgt.substring(verOffset + 8);
		            }
		        }
		        // Firefox
		        else if ((verOffset = nAgt.indexOf('Firefox')) != -1) {
		            browser = 'Firefox';
		            version = nAgt.substring(verOffset + 8);
		        }
		        // MSIE 11+
		        else if (nAgt.indexOf('Trident/') != -1) {
		            browser = 'Microsoft Internet Explorer';
		            version = nAgt.substring(nAgt.indexOf('rv:') + 3);
		        }
		        // Other browsers
		        else if ((nameOffset = nAgt.lastIndexOf(' ') + 1) < (verOffset = nAgt.lastIndexOf('/'))) {
		            browser = nAgt.substring(nameOffset, verOffset);
		            version = nAgt.substring(verOffset + 1);
		            if (browser.toLowerCase() == browser.toUpperCase()) {
		                browser = navigator.appName;
		            }
		        }
		        // trim the version string
		        if ((ix = version.indexOf(';')) != -1) version = version.substring(0, ix);
		        if ((ix = version.indexOf(' ')) != -1) version = version.substring(0, ix);
		        if ((ix = version.indexOf(')')) != -1) version = version.substring(0, ix);

		        majorVersion = parseInt('' + version, 10);
		        if (isNaN(majorVersion)) {
		            version = '' + parseFloat(navigator.appVersion);
		            majorVersion = parseInt(navigator.appVersion, 10);
		        }

		        // mobile version
		        var mobile = /Mobile|mini|Fennec|Android|iP(ad|od|hone)/.test(nVer);

		        // cookie
		        var cookieEnabled = (navigator.cookieEnabled) ? true : false;

		        if (typeof navigator.cookieEnabled == 'undefined' && !cookieEnabled) {
		            document.cookie = 'testcookie';
		            cookieEnabled = (document.cookie.indexOf('testcookie') != -1) ? true : false;
		        }

		        // system
		        var os = unknown;
		        var clientStrings = [
		            {s:'Windows 3.11', r:/Win16/},
		            {s:'Windows 95', r:/(Windows 95|Win95|Windows_95)/},
		            {s:'Windows ME', r:/(Win 9x 4.90|Windows ME)/},
		            {s:'Windows 98', r:/(Windows 98|Win98)/},
		            {s:'Windows CE', r:/Windows CE/},
		            {s:'Windows 2000', r:/(Windows NT 5.0|Windows 2000)/},
		            {s:'Windows XP', r:/(Windows NT 5.1|Windows XP)/},
		            {s:'Windows Server 2003', r:/Windows NT 5.2/},
		            {s:'Windows Vista', r:/Windows NT 6.0/},
		            {s:'Windows 7', r:/(Windows 7|Windows NT 6.1)/},
		            {s:'Windows 8.1', r:/(Windows 8.1|Windows NT 6.3)/},
		            {s:'Windows 8', r:/(Windows 8|Windows NT 6.2)/},
		            {s:'Windows NT 4.0', r:/(Windows NT 4.0|WinNT4.0|WinNT|Windows NT)/},
		            {s:'Windows ME', r:/Windows ME/},
		            {s:'Android', r:/Android/},
		            {s:'Open BSD', r:/OpenBSD/},
		            {s:'Sun OS', r:/SunOS/},
		            {s:'Linux', r:/(Linux|X11)/},
		            {s:'iOS', r:/(iPhone|iPad|iPod)/},
		            {s:'Mac OS X', r:/Mac OS X/},
		            {s:'Mac OS', r:/(MacPPC|MacIntel|Mac_PowerPC|Macintosh)/},
		            {s:'QNX', r:/QNX/},
		            {s:'UNIX', r:/UNIX/},
		            {s:'BeOS', r:/BeOS/},
		            {s:'OS/2', r:/OS\/2/},
		            {s:'Search Bot', r:/(nuhk|Googlebot|Yammybot|Openbot|Slurp|MSNBot|Ask Jeeves\/Teoma|ia_archiver)/}
		        ];
		        for (var id in clientStrings) {
		            var cs = clientStrings[id];
		            if (cs.r.test(nAgt)) {
		                os = cs.s;
		                break;
		            }
		        }

		        var osVersion = unknown;

		        if (/Windows/.test(os)) {
		            osVersion = /Windows (.*)/.exec(os)[1];
		            os = 'Windows';
		        }

		        switch (os) {
		            case 'Mac OS X':
		                osVersion = /Mac OS X (10[\.\_\d]+)/.exec(nAgt)[1];
		                break;

		            case 'Android':
		                osVersion = /Android ([\.\_\d]+)/.exec(nAgt)[1];
		                break;

		            case 'iOS':
		                osVersion = /OS (\d+)_(\d+)_?(\d+)?/.exec(nVer);
		                osVersion = osVersion[1] + '.' + osVersion[2] + '.' + (osVersion[3] | 0);
		                break;
		        }

		        // flash (you'll need to include swfobject)
		        /* script src="//ajax.googleapis.com/ajax/libs/swfobject/2.2/swfobject.js" */
		        var flashVersion = 'no check';
		        if (typeof swfobject != 'undefined') {
		            var fv = swfobject.getFlashPlayerVersion();
		            if (fv.major > 0) {
		                flashVersion = fv.major + '.' + fv.minor + ' r' + fv.release;
		            }
		            else  {
		                flashVersion = unknown;
		            }
		        }
		    }

		    window.jscd = {
		        screen: screenSize,
		        browser: browser,
		        browserVersion: version,
		        mobile: mobile,
		        os: os,
		        osVersion: osVersion,
		        cookies: cookieEnabled,
		        flashVersion: flashVersion
		    };
		}(this));

		jQuery(document).ready(function() {

		    setTimeout(function(){
		    	jQuery('#input').val(parent.gravWindowMain.location.href);
				parent.document.onkeydown = KeyPress;
				document.onkeydown = KeyPress;

			}, 1000);

			$('button#capture').on('click', function(){
				captureScreenshot();
			});

			$('button#capture').hide();

			$('button#capture-status').on('click', function(){
				makeScreenshot();
			});

			$('button#issue').on('click', function(){
				parent.openIssue();
				$('#issue-container').hide();
				$('#controls').fadeIn();
				makeScreenshot();
				$('#controls textarea').focus();
			});

		    $('button#view_issues').on('click', function(){
		        parent.openViewIssues();
		        window.open('/<?php echo $settings['static_url'];?>?view_issues=true', '_self');
		    });

			$('button#sendCapture').on('click', function(){

		        if(!$('#description').val())
		        {
		            alert('You must provide a Description');
		        }
		        else if(!$('#created_by').val())
		        {
		            alert('You must provide your Initials');
		        }
		        else if(!$('#priority').val())
		        {
		            alert('You must provide a Priority');
		        }
		        else
		        {
		    		if(!storedLines.length)
		    		{
		    			alert("You need to create at least one arrow pointing to the location of the issue.\n\nYou can create an arrow by clicking and dragging within the red box.");
		    		}
		    		else
		    		{
		    			if(confirm("Click OK to submit the Issue.\n\nPlease make sure that your Description is easy to understand.")){
		                    saveScreenshotData();
		    			}
		    		}
		        }
			});

			$('button#cancelCapture').on('click', function(){
				parent.closeIssue();
				$('#controls').hide();
				$('#issue-container').fadeIn();
				closeScreenshot();
			});

			jQuery(window).resize(function() {

		         jQuery('#screenSize').html(jQuery(window).width());


		    }).resize(); // Trigger resize handlers.

		    jQuery('#screenSize').html(jQuery(window).width());


		});//ready

		function KeyPress(e) {
			var evtobj = window.event? event : e
			if(evtobj.keyCode == 90 && (evtobj.ctrlKey || evtobj.metaKey))
			{
				storedLines.pop();
				ctx.clearRect(0,0,maxx,maxy);
				redrawStoredLines(ctx);
			}
			if(evtobj.keyCode == 67 && (evtobj.ctrlKey || evtobj.metaKey))
			{
				makeScreenshot();
			}
		}
		function closeScreenshot()
		{
		    if(parent.gravWindowMain.document.getElementById('drawing'))
		    {
		        elem=parent.gravWindowMain.document.getElementById('drawing');
		        elem.parentNode.removeChild(elem);
		        storedLines = [];
		        $(parent.gravWindowMain.window).off('mousedown').off('mousemove').off('mouseup');
		        $('button#capture-status').html('Start Capture');
		        $('button#capture').hide();
		    }
		}
		function makeScreenshot()
		{
			if(parent.gravWindowMain.document.getElementById('drawing'))
			{
				elem=parent.gravWindowMain.document.getElementById('drawing');
				elem.parentNode.removeChild(elem);
				storedLines = [];
				$(parent.gravWindowMain.window).off('mousedown').off('mousemove').off('mouseup');
				$('button#capture-status').html('Start Capture');
				$('button#capture').hide();

			}
			else
			{
				canvas = parent.gravWindowMain.document.createElement('canvas');
		        canvas.id = 'drawing';
		        canvas.style.position = 'absolute';
		        canvas.style.top = $(parent.gravWindowMain).scrollTop()+'px';
		        canvas.style.left = '0';
		        canvas.style.bottom = '0';
		        canvas.style.right = '0';
		        //canvas.style.border = '6px solid red';
		        canvas.style.boxShadow = '0 0 0 6px red inset';
		        canvas.style.zIndex = '1000000000';
		        canvas.width = $(parent.gravWindowMain).width();
		        canvas.height = ($(parent.gravWindowMain).height()-6);
		        canvas.style.width = '100%';
		        //canvas.draggable = 'false';
		        //canvas.onclick = function(e){ alert(234); };

				parent.gravWindowMain.document.getElementsByTagName('body')[0].appendChild(canvas);

				//var attach_to = $.browser.msie ? '#drawing' : window;
				$obj = $(parent.gravWindowMain.window.document.getElementById('drawing'));
				ctx = canvas.getContext('2d');
				$(parent.gravWindowMain.window).mousedown(mDown).mousemove(mMove).mouseup(mDone);
				$('button#capture-status').html('Cancel Capture');
				$('button#capture').show();
			}
		}

		function saveScreenshotData()
		{
		    var div = document.createElement("DIV");
		    div.setAttribute('data-html2canvas-ignore', 'true');
		    div.id = 'captureLoadingDiv';
		    div.style.position = 'fixed';
		    div.style.top = '0';
		    div.style.bottom = '0';
		    div.style.left = '0';
		    div.style.right = '0';
		    div.style.background = "#333333 url('http://scripts.dev.gravitatedesign.com/loading.gif') no-repeat center center";
		    div.style.backgroundSize = '10%';
		    div.style.opacity = '0.7';
		    div.style.zIndex = '1000000000';
		    parent.gravWindowMain.document.body.appendChild(div);
		    //alert(1);


		    parent.gravWindowMain.html2canvas(parent.gravWindowMain.document.body, {
		        onrendered: function(_canvas) {
		            //alert(2);
		            var img_data = _canvas.toDataURL("image/png", 0.1);

		            if(img_data)
		            {
		                //alert(3);
		                $.post( '<?php echo $_SERVER['REQUEST_URI'];?>', {
		                    save_issue: true,
		                    status: 'pending',
		                    description: $('#description').val(),
		                    browser: navigator.sayswho,
		                    device_width: jscd.screen,
		                    screen_width: jQuery(window).width(),
		                    os: jscd.os +' '+ jscd.osVersion,
		                    ip: '<?php echo self::real_ip();?>',
		                    created_by: $('#created_by').val(),
		                    department: 'Developer',
		                    priority: $('#priority').val(),
		                    screenshot: '',
		                    screenshot_data: img_data,
		                    url: parent.gravWindowMain.location.pathname+parent.gravWindowMain.location.search,
		                    link: $('#link').val(),
		                },
		                function(response)
		                {
		                    //alert(response);
		                    if(response && response.indexOf('GRAVITATE_ISSUE_AJAX_SUCCESSFULLY') > 0)
		                    {
		                        parent.closeIssue();
		                        $('#controls').hide();
		                        $('#issue-container').fadeIn();
		                        closeScreenshot();
		                        parent.gravWindowMain.document.body.removeChild(parent.gravWindowMain.document.getElementById('captureLoadingDiv'));
		                    }
		                    else
		                    {
		                        // Error
		                        alert('There was an error Saving the Issue. Please try again or contact your Account Manager.');
		                    }
		                });
		            }
		        },
		        //type: 'view',
		        top: $(parent.gravWindowMain.window.document.getElementById('drawing')).offset().top,
		        height: ($(parent.gravWindowMain).height()+8)
		    });
		};


		function captureScreenshot()
		{
			parent.gravWindowMain.html2canvas(parent.gravWindowMain.document.body, {
		        onrendered: function(_canvas) {

		        	//canvas = _canvas;
		        	//canvas = document.getElementById('drawing');

					//document.getElementById('redraw').onclick = randomLines;
				    //randomLines();

		            //parent.gravWindowMain.document.body.appendChild(canvas);
		            var img = _canvas.toDataURL("image/png", 0);
		            //jQuery('textarea').val(img);
		            jQuery('#screenshots').append('<img src="'+img+'">');

		            makeScreenshot();

		        },
		        //type: 'view',
		        top: $(parent.gravWindowMain.window.document.getElementById('drawing')).offset().top,
				height: ($(parent.gravWindowMain).height()+8)
		    });
		};


		var canvas, ctx, storedLines = [];

		// Functions from blog tutorial
		function drawFilledPolygon(canvas,shape)
		{
			canvas.beginPath();
			canvas.moveTo(shape[0][0],shape[0][1]);

			for(p in shape)
				if (p > 0) canvas.lineTo(shape[p][0],shape[p][1]);
			canvas.lineTo(shape[0][0],shape[0][1]);
			canvas.fillStyle = "#ff0000";
			canvas.fill();
		};

		function translateShape(shape,x,y)
		{
			var rv = [];
			for(p in shape)
				rv.push([ shape[p][0] + x, shape[p][1] + y ]);
			return rv;
		};

		function rotateShape(shape,ang)
		{
			var rv = [];
			for(p in shape)
				rv.push(rotatePoint(ang,shape[p][0],shape[p][1]));
			return rv;
		};

		function rotatePoint(ang,x,y)
		{
			return [
				(x * Math.cos(ang)) - (y * Math.sin(ang)),
				(x * Math.sin(ang)) + (y * Math.cos(ang))
			];
		};

		function drawLineArrow(canvas,x1,y1,x2,y2)
		{
			canvas.beginPath();
			canvas.moveTo(x1,y1);
			canvas.lineTo(x2,y2);
			canvas.lineWidth = 6;
			canvas.strokeStyle = "#ff0000";
			canvas.stroke();
			canvas.fillStyle = "#ff0000";
			var ang = Math.atan2(y2-y1,x2-x1);
			drawFilledPolygon(canvas,translateShape(rotateShape(arrow_shape,ang),x2,y2));
		};

		function redrawLine(canvas,x1,y1,x2,y2)
		{
			canvas.clearRect(0,0,maxx,maxy);
			drawLineArrow(canvas,x1,y1,x2,y2);
			redrawStoredLines(canvas);
		};

		function redrawStoredLines(canvas)
		{
			if (storedLines.length == 0) {
		        return;
		    }

		    // redraw each stored line
		    for (var i = 0; i < storedLines.length; i++) {
		    	drawLineArrow(canvas,storedLines[i].x1,storedLines[i].y1,storedLines[i].x2,storedLines[i].y2);
		    }
		};

		// Event handlers
		function mDown(e)
		{
			$(parent.gravWindowMain.document.body).css('cursor','none');
			read_position();
			var p = get_offset(e);
			if ((p[0] < 0) || (p[1] < 0)) return;
			if ((p[0] > maxx) || (p[1] > maxy)) return;
			drawing = true;
			ox = p[0];
			oy = p[1];
			return nothing(e);
		};

		function mMove(e)
		{
			if (!!drawing)
			{
				var p = get_offset(e);
				// Constrain the line to the canvas...
				if (p[0] < 0) p[0] = 0;
				if (p[1] < 0) p[1] = 0;
				if (p[0] > maxx) p[0] = maxx;
				if (p[1] > maxy) p[1] = maxy;
				redrawLine(ctx,ox,oy,p[0],p[1]);
			}
			return nothing(e);
		};

		function mDone(e)
		{
			$(parent.gravWindowMain.document.body).css('cursor','auto');
			if (drawing) {
				var p = get_offset(e);
				$(parent.gravWindowMain.document.body).css('cursor','auto');
				debug_msg(['Draw Arrow',ox,oy,p[0],p[1]].toString());

				storedLines.push({
		            x1: ox,
		            y1: oy,
		            x2: p[0],
		            y2: p[1]
		        });

				drawing = false;
				return mMove(e);
			}
		};

		function nothing(e)
		{
			e.stopPropagation();
			e.preventDefault();
			return false;
		};

		function read_position()
		{
			var o = $obj.position();
			yoff = o.top;
			xoff = o.left;
		};
		function get_offset(e)
		{
			return [ e.pageX - xoff, e.pageY - yoff ];
		};

		function debug_msg(msg)
		{
			console.log(msg);
		};

		var arrow_shape = [
			[ -17, -12 ],
			[ -8, 0 ],
			[ -17, 12 ],
			[ 4, 0 ]
		];

		var debug_ctr = 0;
		var debug_clr = 12;
		var $obj;
		var maxx = $(window).width(), maxy = 2000;
		var xoff,yoff;
		var ox,oy;
		var drawing;

		</script>
		</head>
		<body>
		<div id="controls">
			<div class="left">
				Description *<br>
				<textarea id="description" required></textarea>
			</div>
		    <div class="left settings">
		        <label>Your Initials *</label>
		        <input type="text" id="created_by" name="created_by" required><br>
		        <label>Priority *</label>
		        <select id="priority" name="priority" required>
		            <option value="urgent">URGENT</option>
		            <option value="high">High</option>
		            <option value="" selected="selected">- Select -</option>
		            <option value="normal">Normal</option>
		            <option value="low">Low</option>
		            <option value="future_request">Future Request</option>
		        </select><br>
		        <label>(optional link)</label>
		        <input type="text" id="link" name="link" placeholder="http://">
		    </div>

			<!-- <div class="left">
				ScreenShots -
				<button id="capture-status">Start Capture</button> (ctrl+c) &nbsp;
				<button id="capture">Save Capture</button>
				<br>
				<div id="screenshots"></div>
			</div> -->

			<div id="right" class="right">
				<!-- <span id="url"></span><br>
				<script>document.write(navigator.sayswho);</script><br>
				<script>document.write(jscd.os +' '+ jscd.osVersion);</script><br>
				<span id="screenSize"></span> (<script>document.write(jscd.screen);</script>)<br>
				<?php echo $_SERVER['REMOTE_ADDR'];?> -->
				<br>
				<button id="cancelCapture">Cancel</button><br><button id="sendCapture">Submit</button>
			</div>
		</div>
		<div id="issue-container">
			<button id="issue">Submit Issue</button> &nbsp; &nbsp; <button id="view_issues">View Issues</button>
		</div>
		</body>
		</html>
		<?php
	}

	static function tracker()
	{
		?><!doctype html>
		<html lang="en-US">
		<head>
		<meta charset="utf-8">
		<title>Capture</title>
		<script type='text/javascript' src='//code.jquery.com/jquery-2.0.3.min.js'></script>
		<script type='text/javascript'>

		function frameLoaded()
		{
			jQuery(gravWindowControls.document.getElementById('url')).html(gravWindowMain.location.pathname+gravWindowMain.location.search);
			gravWindowMain.document.onkeydown = gravWindowControls.KeyPress;
		}

		function openIssue()
		{
			var pace = 3;
			var stop = 112;
			var current = 28;
		    $('frameset').attr('rows', 80 + ',*');
		}

		function openViewIssues()
		{
		    $('frameset').attr('rows', 220 + ',*');
		}

		function closeIssue()
		{
			var pace = 3;
			var stop = 112;
			var current = 28;
		    $('frameset').attr('rows', 28 + ',*');
		}

		var gravWindowControls;
		var gravWindowMain;

		jQuery(document).ready(function() {
			gravWindowControls = windowControls;
			gravWindowMain = windowMain;
		});

		</script>
		</head>
		<frameset rows="28,*" border="4">
		  <frame name="windowControls" src="?gissues_controls=1" scrolling="no" frameborder="6" bordercolor="#333333" />
		  <frame name="windowMain" src="<?php echo 'http://'.$_SERVER['HTTP_HOST'];?>" frameborder="0" onload="frameLoaded();" />
		</frameset>
		</html>
		<?php
	}
}

add_action( 'wp_ajax_save_issues', array('GRAVITATE_ISSUE_TRACKER', 'save_issues'));

register_activation_hook( __FILE__, array( 'GRAVITATE_ISSUE_TRACKER', 'activate' ));
add_action('admin_menu', array( 'GRAVITATE_ISSUE_TRACKER', 'admin_menu' ));
add_action('init', array( 'GRAVITATE_ISSUE_TRACKER', 'init' ));
add_action( 'wp_enqueue_scripts', array( 'GRAVITATE_ISSUE_TRACKER', 'enqueue_scripts' ));
add_action( 'admin_enqueue_scripts', array( 'GRAVITATE_ISSUE_TRACKER', 'enqueue_scripts' ));