<?php
if( isset($_POST['write-proj']) ){
	$_POST['action'] = $_GET['action'];
	$writeres = t8_pm_write_proj();
	if( isset( $writeres['updated'] ) ) $t8_pm_updated = $writeres['updated'];
	if( isset( $writeres['warning'] ) ) $t8_pm_warning = $writeres['warning'];
	$_GET['action'] = 'edit';
} 

/**
 * build user select list
 */
$current_user = wp_get_current_user();
global $wpdb, $pm_users;
$status_r = array( "Proposed", "Active", "Archived", "Trash" );
$user_color = $pm_users[$current_user->ID]['color'];

	/**
	 * LOAD Project Forms
	 */
	if( isset($_GET['action']) && $_GET['action'] != 'trash' && $_GET['action'] != 'delete' ){
		if($_GET['action'] === 'schedule'){

			include_once( plugin_dir_path(__FILE__).'projects/t8-projects-sched.php' );

		}elseif($_GET['action'] === 'view'){

			include_once( plugin_dir_path(__FILE__).'projects/t8-projects-view.php' );

		} else {
			// !!! these proj vars should follow the same architecture as a project array from the database
			$t8_pm_action = $_GET['action'];
			if($_GET['action'] === 'new' ){
				$t8_pm_proj_id 			= 
				$t8_pm_client_id 		= 
				$t8_pm_est_hours 		= 
				$t8_pm_status 			=
				$t8_pm_price 			=
				$t8_pm_proj_manager 	=
				$t8_pm_proj_features 	=
				$t8_pm_proj_mstones 	= 
				$t8_pm_hoursums 		= 0;
				$t8_pm_start_date 		= date('Y-m-d');
				$t8_pm_end_date 		= date("Y-m-d",strtotime("+1 week"));
				$t8_pm_proj_name 		= '';
			}else{
				$t8_pm_proj_id = esc_html($_GET['project']);
				$projs = t8_pm_get_projs( $t8_pm_proj_id );
				// need to sanitize and check for missing vars for new projs !!!
				$t8_pm_proj_name = ($_GET['action'] == 'duplicate' ? 'Project Title' : $projs[$t8_pm_proj_id]["name"]);
				$t8_pm_client_id = $projs[$t8_pm_proj_id]["cli_id"];
				$t8_pm_est_hours = $projs[$t8_pm_proj_id]["est_hours"];
				$t8_pm_status = $projs[$t8_pm_proj_id]["status"];
				$t8_pm_start_date = date('D, M j, Y', strtotime( $projs[$t8_pm_proj_id]["start_date"] ) );
				$t8_pm_end_date = date('D, M j, Y', strtotime( $projs[$t8_pm_proj_id]["end_date"] ) );
				$t8_pm_price = $projs[$t8_pm_proj_id]["price"];
				$t8_pm_proj_manager = $projs[$t8_pm_proj_id]["proj_manager"];
				$t8_pm_proj_features = $projs[$t8_pm_proj_id]['misc']['features'];
				$t8_pm_proj_mstones = $projs[$t8_pm_proj_id]['misc']['milestones'];
	
				$task_results = $wpdb->get_results("SELECT * FROM ".$wpdb->prefix . "pm_tasks WHERE proj_id = ".$t8_pm_proj_id ); // collect task with this project id
					if($task_results){
						foreach($task_results as $task){ // build array with id as key
							$t8_pm_p_tasks[$task->id] = array(
								'title' 	=> $task->task_title,
								'proj-id' 	=> $task->proj_id,
								'cli-id' 	=> $task->cli_id,
								'hours' 	=> $task->est_hours,
								'assign' 	=> $task->assign,
								'status' 	=> $task->status,
								'stage' 	=> $task->stage
							);
						}
					} else {
						$t8_pm_p_tasks = array();
					}
				$t8_pm_hoursums = array();
				if( isset( $t8_pm_p_tasks ) ) {
					foreach( $t8_pm_p_tasks as $task ){ 
						$t8_pm_hoursums[] = $task["hours"];
					}
				}
				$t8_pm_hoursums = array_sum($t8_pm_hoursums);
			}
			$task_status = array( "Current", "Submitted", "Completed");
			//krsort($t8_pm_p_tasks);
			if( isset( $t8_pm_p_tasks ) ) uasort( $t8_pm_p_tasks, "t8_pm_custom_sort" ); // !!! did this make it to the functions file?
			// include the project form
			include_once( plugin_dir_path(__FILE__).'projects/t8-projects-form.php' );
		}
	} else { // no GET or POST actions set
		if( isset($_GET['action']) && ( $_GET['action'] === 'trash' || $_GET['action'] === 'delete' ) ){
			if($_GET['action'] === 'trash'){
				// load the trashed projects
			}elseif($_GET['action'] === 'delete'){
				// trash projects, I think this will get handled elsewhere
			}
		}else{

			// include files for Projects List class
			if( ! class_exists( 'WP_List_Table' ) ) {
			    require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
			}
			include_once( plugin_dir_path(__FILE__).'projects/t8-projects-class-list-table.php' );

?>
	<div class="wrap t8-pm">
		<?php if(isset($t8_pm_warning)){ ?><div class="error"><?php echo $t8_pm_warning; ?></div><?php } ?>
		<?php if(isset($t8_pm_updated)){ ?><div class="updated"><?php echo $t8_pm_updated; ?></div><?php } ?>
		<?php 
			$t8_pm_projtable = new t8_pm_Project_Table();
			echo '<h2>Projects <a href="?page=' . $_REQUEST['page'] . '&action=new" class="add-new-h2">Add New</a></h2>'; 
			if ( function_exists('wp_nonce_field') ) wp_nonce_field('t8_pm_nonce','t8_pm_nonce');
			$t8_pm_projtable->views(); 
			$t8_pm_projtable->prepare_items(); 
			$t8_pm_projtable->display(); 
?>
	</div>
<?php 
		}
	} 
//eof