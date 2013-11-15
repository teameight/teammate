<?php
global $wpdb; //set ur globals

$t8_pm_colors = array(
	'FF5300',
	'FFA400',
	'DC0055',
	'00AB6F',
	'620CAC',
	'06799F',
	'FFBF00',
	'B1F100',
	'14D100',
	'FFEF00',
	'C10087'
);

$user_query = new WP_User_Query( array( 'orderby' => 'display_name') );
if ( ! empty( $user_query->results ) ) {
	$i = 0;
	foreach ( $user_query->results as $user ) {
		$pm_users[$user->ID] = array( // build users array with id, name and slug
			"uname" => $user->display_name,
			"uslug" => $user->user_nicename,
			"color" => $t8_pm_colors[$i],
			"caps" => $user->t34m8_wp_capabilities
		);
		$i++;
		if( $i > count($t8_pm_colors) ) $i = 0;
	}
	// !!! need to move this to a plugin option
	$pm_users['all'] = array( // build users array with id, name and slug
		"uname" => 'Everyone',
		"uslug" => 'everyone',
		"color" => '888888',
		"caps" => ''
	);
} else { }

/*
*
* Functions
*
*/
if( ! class_exists( 'WP_List_Table' ) ) {
    require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

// Projects includes - !!! should these be conditional, only for projects pages? - prolly

include_once( plugin_dir_path(__FILE__).'projects/t8-projects-class-list-table.php' );

include_once( plugin_dir_path(__FILE__).'reports/t8-time-class-list-table.php' );

/*
* Write Projects
*
* handles writing new, duplicated and edits projects to the database
* also fires the schedule calculation
*/

function t8_pm_write_proj() {
	global $wpdb;

	/*
	*  Validate and sanitize data
	*/

	$write = ( $_POST['action'] == 'edit' ? 'edit' : 'new' );
	$cli_id = intval( $_POST['t8_pm_client_id'] );
	$mstones = $tasks = $newtasks = $deletetasks = array();
	if( is_array( $_POST['mstone'] ) ) {
		foreach( $_POST['mstone'] as $mstage => $mstn ) {
			$mstones[$mstage]['name'] = sanitize_text_field( $mstn['name'] );
			$mstones[$mstage]['deadline'] = 
				( $mstn['deadline'] && $mstn['deadline'] != '' 
				? date( 'Y-m-d', strtotime( $mstn['deadline'] ) )
				: date( 'Y-m-d', strtotime( $_POST['t8_pm_proj_end'] ) )
			);
			$mstones[$mstage]['hours'] = ( is_numeric( $mstn['hours'] ) ? $mstn['hours'] : intval( $mstn['hours'] ) );
		}
	}
	$hoursums = array();
	if( is_array( $_POST['task'] ) ) {
		foreach( $_POST['task'] as $tid => $task ) {
			$tasks[$tid]['task_title'] = sanitize_text_field( $task['title'] );
			$tasks[$tid]['stage'] = intval( $task['stage'] );
			$tasks[$tid]['assign'] = ( $task['assign'] == 'all' ? 'all' : intval( $task['assign'] ) );
			$tasks[$tid]['due'] = 
				( $mstones[$task['stage']]['deadline'] 
				? $mstones[$task['stage']]['deadline'] 
				: date( 'Y-m-d', strtotime( $_POST['t8_pm_proj_end'] ) ) 
			);
			$hoursums[] = $tasks[$tid]['est_hours'] = ( is_numeric( $task['hours'] ) ? $task['hours'] : intval( $task['hours'] ) );
		}
	}
	if( isset( $_POST['newtask'] ) ) {
		foreach( $_POST['newtask'] as $tid => $task ) {
			$newtasks[$tid]['task_title'] = sanitize_text_field( $task['title'] );
			$newtasks[$tid]['stage'] = intval( $task['stage'] );
			$newtasks[$tid]['assign'] =  ( $task['assign'] == 'all' ? 'all' : intval( $task['assign'] ) );
			$newtasks[$tid]['due'] = 
				( $mstones[$task['stage']]['deadline'] 
				? $mstones[$task['stage']]['deadline'] 
				: date( 'Y-m-d', strtotime( $_POST['t8_pm_proj_end'] ) ) 
			);
			$hoursums[] = $newtasks[$tid]['est_hours'] = ( is_numeric( $task['hours'] ) ? $task['hours'] : intval( $task['hours'] ) );
		}
	}
	if( !empty( $_POST['deletetask'] ) ) {
		foreach( $_POST['deletetask'] as $tid ) {
			$deletetasks[] = intval( $tid );
		}
	}
	$hoursums = array_sum( $hoursums );
	$proj_budget = ( is_numeric( $_POST['t8_pm_proj_budget'] ) ? $_POST['t8_pm_proj_budget'] : intval( $_POST['t8_pm_proj_budget'] ) );	
	$t8_pm_proj = array( 
		'name' => sanitize_text_field( $_POST['t8_pm_proj_name'] ),
		'cli_id' => $cli_id,
		'status' => intval( $_POST['t8_pm_proj_status'] ),
		'start_date' => date( 'Y-m-d', strtotime( $_POST['t8_pm_proj_start'] ) ),
		'end_date' => date( 'Y-m-d', strtotime( $_POST['t8_pm_proj_end'] ) ),
		'est_hours' => $hoursums,
		'price' => $proj_budget,
		'proj_manager' => intval( $_POST['t8_pm_proj_manager'] ),
		'misc' => serialize( array( 'features' => sanitize_text_field( $_POST['t8_pm_proj_features'] ) , 'milestones' => $mstones ) )
	);
	$project_table = $wpdb->prefix . "pm_projects";
	$task_table = $wpdb->prefix . "pm_tasks";
	$return['updated'] = '';
	/*
	*  Write data to wpdb
	*/
	if( $write == 'edit' ) { // edit existing proj
		$t8_pm_proj_id = ( isset( $_POST['t8_pm_proj_id'] ) ? intval( $_POST['t8_pm_proj_id'] ) : false ); // or ''
		$resultsproj = $wpdb->update( 
			$project_table,
			$t8_pm_proj,
			array( 'id' => $t8_pm_proj_id )
		);
		if( $resultsproj ) $return['updated'] .= "<p>" . $t8_pm_proj['name'] . " has been updated.</p>";
		if( isset( $t8_pm_proj_id ) ) {
			if( !empty( $tasks ) ){
				$tsk_count = 0;
				foreach( $tasks as $tid => $task ){ //build each task and write to pm_tasks
					$resultstasks = 0;
					$task['proj_id'] = $t8_pm_proj_id;
					$task['cli_id'] = $cli_id;
// echo '<pre>'; print_r($task); echo '</pre>';
					$resultstasks = $wpdb->update( $task_table, $task, array( 'id' => $tid ) );
					if( $wpdb->insert_id ) $tsk_count++;
				}
				if( $resultstasks ) $return['updated'] .= "<p>" . $tsk_count . " task(s) have been updated.</p>";
			}
			if( !empty( $newtasks ) ){
				$tsk_count = 0;
				foreach( $newtasks as $tid => $task ){ //build each task and write to pm_tasks
					$wpdb->insert_id = 0;
					$task['proj_id'] = $t8_pm_proj_id;
					$task['cli_id'] = $cli_id;
					$wpdb->insert( $task_table, $task );
					if( $wpdb->insert_id ) $tsk_count++;
				}
				if( $tsk_count ) $return['updated'] .= "<p>" . $tsk_count . " task(s) have been created.</p>";
			}
		}
		if( !empty( $deletetasks ) ){ 
			foreach( $deletetasks as $tid ){
				$wpdb->query(
					$wpdb->prepare( "DELETE FROM $task_table WHERE id = $tid AND proj_id = $t8_pm_proj_id" )
				);
				if( $wpdb->insert_id ) $return['updated'] .= "<p>" . $task['task_title'] . " has been created.</p>";
			}
		}
	} else { // write new proj
		$wpdb->insert( $project_table, $t8_pm_proj ); // write to pm_projects
		$t8_pm_proj_id = $wpdb->insert_id;		
		if( isset( $t8_pm_proj_id ) && !empty( $tasks ) ){
			$return['updated'] = "<p>" . $t8_pm_proj['name'] . " has been created.</p>";
			$_GET['project'] = $t8_pm_proj_id; // reset the proj_id global for edit screen to load this project instead of dupped or blank one
			$tsk_count = 0;
			foreach( $tasks as $tid => $task ){ //build each task and write to pm_tasks
				$wpdb->insert_id = 0;
				$task['cli_id'] = $cli_id;
				$task['proj_id'] = $t8_pm_proj_id;
				$wpdb->insert( $task_table, $task );
				if( $wpdb->insert_id ) $tsk_count++;
			}
			if( $tsk_count ) $return['updated'] .= "<p>" . $tsk_count . " task(s) have been created.</p>";
		}
	}
	if($t8_pm_proj_id) t8_pm_schedule( $t8_pm_proj_id, $t8_pm_proj['start_date'], $t8_pm_proj['end_date'] );
	// !!! need to define and return warnings
	if( empty( $return ) ) $return['warning'] = "Nothing Saved!";
	return $return;
} // end function t8_pm_write_proj( $proj )

/**
 * Create readout for task status select.
 *
 * @param  string $tid task id. 
 * @param  array $task array including proj-man (user_id), status, stage, and assign of task. 
 * @return  echo matched checkbox. 
*/
function t8_pm_task_statuses($tid = 0, $task = array() ) {
	global $current_user;
	$current_user = wp_get_current_user();

	if(!isset($task['proj-man'])) $task['proj-man'] = 0;

	$inreview_cbox = '<span>In Review</span> <input type="checkbox" name="review[]" checked class="t8-pm-task-status" value="'.$tid.'" />';
	$submit_cbox = '<span>Submit for Review</span> <input type="checkbox" name="review[]" class="t8-pm-task-status" value="'.$tid.'" />';
	$complete_cbox = ' <input type="checkbox" name="complete[]" class="t8-pm-task-status" value="'.$tid.'" />';
	$uncomplete_cbox = ' <input type="checkbox" name="complete[]" checked class="t8-pm-task-status" value="'.$tid.'" />';
	if( !isset( $task['stage'] ) ){ $task['stage'] = 0; }
	if( $task['stage'] == '0' ) { 
	    echo 'Ongoing';	
	}elseif( $task['status'] == '0' ) { 
	    if( $task['proj-man'] == $current_user->ID ) { 
	        echo '<span>Mark as Complete</span>' . $complete_cbox;
	    }elseif( $task['assign']  == $current_user->ID ) { 
	        echo $submit_cbox;
	    }else{
	        echo 'Incomplete';	
	    } 
	}elseif( $task['status'] == '1' ) { 
	    echo ( $task['proj-man'] == $current_user->ID ? '<span>Approve as Complete</span>' . $complete_cbox : $inreview_cbox );
	}elseif( $task['status'] == '2' ) { 
	    echo '<span>Completed</span>';
	    if( $task['proj-man'] == $current_user->ID || $task['assign']  == $current_user->ID ) echo $uncomplete_cbox;
	}else{ echo 'status: ' . $task['status']; }
} // end func t8_pm_task_statuses


/**
 * Create options for assign select.
 *
 * @param  string $selected_user user id to show as selected. 
 * @param  bool $exclude_everyone exclude the 'Everyone' user. 
 * @return  string $return list of options to populate a select. 
*/
function t8_pm_assign_select($selected_user = 0, $exclude_everyone = false ) {
	global $pm_users;
	$return = '';
	if( isset($pm_users) ){ 
		$users = $pm_users;
		if($exclude_everyone) unset($users['all']);
        foreach( $users as $user_id => $user ){ 
			$selected = ( $user_id == $selected_user ? ' selected="selected"' : '' );
			$return .= '<option value="'.$user_id.'"'.$selected.'>'.$user["uname"].'</option>'; 
        } 
    }
    return $return;
}

/**
 * Milestone and task form table.
 *
 * Generates a form table of tasks grouped by milestone.
 *
 * @param  array $mstones array of milestone and task data. 
 *    $mstones = array(
 *      $mid => array(
 *          'name' => '',
 *          'deadline' => '',
 *          'hours' => '',
 *          'tasks' => array(
 *              $tid => array(
 *                  'title' => '',
 *                  'assign' => '',
 *                  'hours' => '',
 *                  'status' => '',
 *              )
 *          )  
 *      )
 *    );
 *
 */
function t8_pm_mstone_form_table( $mstone = array() ){
	global $pm_users;
    //should validate array here or is it already done !!! ?
    if(!empty($mstone)){
        foreach ($mstone as $mid => $mArray) {

?>
<table class="wp-list-table widefat t8-pm-mstone t8-pm-form" cellspacing="0" data-mid="<?php echo $mid; ?>">
    <thead>
        <tr>
            <th class="m-title">
<?php 
	if($mid == 0){ 
?>
				General Tasks
                <input type="hidden" name="mstone[<?php echo $mid; ?>][name]" value="<?php echo $mid; ?>" />
<?php
	}else{
?>
				<input placeholder="Milestone" type="text" name="mstone[<?php echo $mid; ?>][name]" value="<?php echo $mArray['name']; ?>" required="required" />
<?php
	}
?>
            </th>
            <th><input type="text" placeholder="deadline" class="datepicker mstone-deadline" value="<?php echo $mArray['deadline']; ?>" name="mstone[<?php echo $mid; ?>][deadline]" /></th>
            <th class="mstone-hours"><input type="hidden" size="4" name="mstone[<?php echo $mid; ?>][hours]" value="<?php echo $mArray['hours']; ?>" /> <span><?php echo $mArray['hours']; ?></span> hrs</th>
            <th></th>
        </tr>
    </thead>

    <tfoot>
        <tr>
            <th>Task Name</th>
            <th>Assign</th>
            <th class="num">Est Hrs</th>
            <th><button class="add-task button" type="button">Add Task</button></th>
        </tr>
    </tfoot>

    <tbody class="the-list">
<?php

if(!empty( $mArray['tasks'] ) ) {
    $ti = 0;
    foreach($mArray['tasks'] as $tid => $task){
        $ti++;
?>
        <tr data-tid="<?php echo $tid; ?>" class="user-<?php echo $pm_users[$task['assign']]['uslug']; ?> task<?php echo ($ti%2 ? ' alternate' : '' ); ?>" valign="top">
            <td class="task-title" >
                <input placeholder="Task" type="text" name="task[<?php echo $tid; ?>][title]" value="<?php echo $task['title']; ?>" required="required" />
            </td>
            <td class="task-assign">
                <input type="hidden" name="task[<?php echo $tid; ?>][stage]" value="<?php echo $mid; ?>" />
                <select name="task[<?php echo $tid; ?>][assign]">
					<?php echo t8_pm_assign_select($task['assign']); ?>
                </select>
            </td>
            <td class="task-hours num">
                <input type="text" name="task[<?php echo $tid; ?>][hours]" value="<?php echo $task['hours']; ?>" /> hrs
            </td>
            <td class="task-actions">
                <a class="delete-task">Delete</a>
            </td>
        </tr>
        <?php
    } //end foreach $task_item
} else {
    // no tasks ???
}// end if tasks

?>
    </tbody>
</table>
<?php

        } // end foreach $mstone
    }

} // end func t8_pm_mstone_form_table

/**
 * Milestone and task view table.
 *
 * Generates a display table of tasks grouped by milestone.
 *
 * @param  array $mstones array of milestone and task data. 
 * @param array $args {
 *     An array of arguments. Optional.
 *		$mid => array(
 *          'name' => '',
 *          'deadline' => '',
 *          'hours' => '',
 *          'tasks' => array(
 *              $tid => array(
 *                  'title' => '',
 *                  'assign' => '',
 *                  'hours' => '',
 *                  'status' => '',
 *              )
 *          )  
 *      ) *
 * }
 *
 */
function t8_pm_mstone_view_table( $mstone = array() ){
	global $pm_users;
    //should validate array here or is it already done !!! ?
    if(!empty($mstone)){
        foreach ($mstone as $mid => $mArray) {

?>
<table class="wp-list-table widefat t8-pm-mstone t8-pm-view" cellspacing="0" data-mid="<?php echo $mid; ?>">
    <thead>
        <tr>
            <th class="m-title">
				<?php  echo ($mid == 0 ? 'General Tasks' : $mArray['name'] ); ?>
            </th>
            <th><?php echo $mArray['deadline']; ?></th>
            <th class="mstone-hours"><span><?php echo $mArray['hours']; ?></span> hrs</th>
            <th></th>
        </tr>
    </thead>

    <tfoot>
        <tr>
            <th>Task Name</th>
            <th>Assign</th>
            <th class="num">Est Hrs</th>
            <th></th>
        </tr>
    </tfoot>

    <tbody class="the-list">
<?php

if(!empty( $mArray['tasks'] ) ) {
    $ti = 0;
    foreach($mArray['tasks'] as $tid => $task){
        $ti++;
?>
        <tr data-tid="<?php echo $tid; ?>" class="user-<?php echo $pm_users[$task['assign']]['uslug']; ?> task<?php echo ($ti%2 ? ' alternate' : '' ); ?>" valign="top">
            <td class="task-title" ><?php echo $task['title']; ?></td>
            <td class="task-assign"><?php echo $pm_users[$task['assign']]['uname']; ?></td>
            <td class="task-hours num"><?php echo $task['hours']; ?> hrs</td>
            <td class="task-status">
				<?php t8_pm_task_statuses( $tid, $task ); ?>
			</td>
        </tr>
        <?php
    } //end foreach $task_item
} else {
    // no tasks ???
}// end if tasks

?>
    </tbody>
</table>
<?php

        } // end foreach $mstone
    }

} // end func t8_pm_mstone_form_table


/**
 * Create a dashboard task.
 *
 * Generates task markup for the dasboard.
 *
 * @param  array $taskR array of task data. 
 * @param array $args {
 *     An array of arguments. Optional.
 *		$tid => array(
 *          'cli-id' => '',
 *          'proj-id' => '',
 *          'stage' => '',
 *          'cli-name' => '',  
 *          'proj-name' => '',  
 *          'title' => '',  
 *          'days-left' => '',  
 *          'mstone-name' => '',  
 *          'hours' => ''
 *      )
 * }
 * @param  string $highlight_task id of task currently being tracked. 
 *
 */
function t8_pm_dtask( $taskR = array(), $highlight_task = 0 ){
	global $pm_users;
    //should validate array here or is it already done !!! ?
    if(!empty($taskR)){
        foreach ($taskR as $tid => $task) {
?>
<div class="dtask<?php echo ( $highlight_task == $tid ? ' punching' : ''); ?>" data-proj-id="<?php echo $task['proj-id']; ?>" data-stage="<?php echo $task['stage']; ?>" data-id="<?php echo $tid; ?>" data-cli="<?php echo $task['cli-id']; ?>" data-hours="<?php echo $task['hours']; ?>">
    <h3><span class="cli-span"><?php echo $task['cli-name']; ?></span>::<span class="proj-span"><?php truncate_string($task['proj-name']); ?></span>::
    <span class="rdts"><?php echo $task['hours']; ?> h est.</span></h3>
    <p>
        <span class="task-title"><?php echo $task['title']; ?></span>
        <span class="rdts"><?php echo $task['days-left']; ?></span>
    </p>
    <div class="x dact">X</div>
    <div class="send2pc dact">O</div>
    <div class="extras">
        <span>-</span>
        <div class="task-status rdts"><?php t8_pm_task_statuses( $tid = 0, $task ) ?></div>
    </div>
</div>
<?php
		}
	}
}

function truncate_string($string, $length = 20){
	echo (strlen($string) < $length ? $string : substr($string,0,$length).'...');
}
function t8_pm_custom_sort($a, $b) {
	return $a["stage"] > $b["stage"];
}


/*
*
* Don't delete this but pattern the Settings API stuff here !!!
*

// Register and define the settings
add_action('admin_init', 't8_pm_admin_init');
function t8_pm_admin_init(){
	register_setting(
		't8_pm_notes_group',        // settings page
		't8_pm_notes_group'//,        // option name
		//'wp_kses'  	// validation callback, good for wp_editor fields, except it tends to remove a lot of formatting we like, such as H3's and bullets
	);
	$notes_label = 'Notes / Help<br><span>Check here for hints on using this plugin. Also, share your notes in the Dev and Design sections.</span>';
// MGMT SECTIONS
	$t8_pm_settings_sections = array(
		't8-teammate/t8-teammate.php_mgmt' => array( // all sections in MGMT page
			'depts',
			'ptypes',
			'receive',
			'clients',
		),
		't8-teammate/t8-teammate.php_projects' => array( // all sections in MGMT page
			'proposals',
			'current',
			'closed',
			'trashed',
			'inactive',
			'createnew',
		),
		't8-teammate/t8-teammate.php' => array( // all sections in MGMT page
			'dashboard'
		),
		't8-teammate/t8-teammate.php_pclock' => array( // all sections in punchclock page
			'pclock'
		)
	);
	foreach( $t8_pm_settings_sections as $t8_pm_set_page => $t8_pm_set_subpages ){
		foreach( $t8_pm_set_subpages as $set_section ){
			add_settings_section( 
				't8_pm_notes_section_'.$set_section, 		// id
				'Notes', 				// title
				't8_pm_notes_desc', 	// callback
				$t8_pm_set_page	// page
			);
			add_settings_field(  // Project Types field
				't8_pm_notes_field_'.$set_section,    // id
				$notes_label,           // setting title
				't8_pm_notes_input',    // display callback
				$t8_pm_set_page,   // settings page
				't8_pm_notes_section_'.$set_section,         // settings section
				array('page_option' => $set_section )     // settings section
			);
		}
	}

}
*/
//end wp settings API stuff

/*
* Display Project schedule
* 
* Displays the project schedule chart, complete with project stats at top
*/
function t8_pm_display_schedule( $proj_id ) {
	global $wpdb, $pm_users;
	date_default_timezone_set("America/New_York"); // !!! need to check for user's timezone

	$project = $wpdb->get_row( "SELECT * FROM ".$wpdb->prefix . "pm_projects WHERE id = ".$proj_id ); // collect Project
	if($project){
		// first check to see if sched is up to date
		$pm_schedule = json_decode( $project->schedule, true );
		$yesterday = date("Y-m-d", time() - 60 * 60 * 24);
		$yestercheck = 0;
		if (isset($pm_schedule[$yesterday])) {
			foreach ($pm_schedule[$yesterday] as $assign => $assR) {
				if( $assR['hours'] ) $yestercheck = 1;
			}
		}
		if( $yestercheck || empty($pm_schedule) ) {
			$resched = t8_pm_schedule( $proj_id, $project->start_date, $project->end_date );
			$pm_schedule = $resched['pm_schedule'];
		} 

		$proj_hoursRaw = $project->est_hours;
		$stgHrs = array();
		//Get Time with this Project id
		$punched_results = $wpdb->get_results( "SELECT * FROM ".$wpdb->prefix . "pm_time WHERE proj_id = ".$proj_id ); // collect Project
		// Build arrays for chart
		$pm_time = array(); 
		$punched_hourstot = $i = 0;
		if($punched_results){ foreach($punched_results as $punched){ // build array with id as key
			$dt = new DateTime($punched->start_time);
			$date = $dt->format('Y-m-d');
			if( !isset($pm_time[$punched->user_id][$date]['hours']) ) $pm_time[$punched->user_id][$date]['hours'] = 0;
			$pm_time[$punched->user_id][$date]['hours'] += $punched->hours;
			if(!$punched->task_id ) $punched->task_id = 't'.$i;
			$pm_time[$punched->user_id][$date]['task_times'][$punched->task_id] = $punched->hours;
			$punched_hourstot += $punched->hours;
			if( !isset($punchedtasks[$punched->task_id]['hours']) ) $punchedtasks[$punched->task_id]['hours'] = 0;
			$punchedtasks[$punched->task_id]['hours'] += $punched->hours;
			$i++;
		}}
		ksort($pm_time); // sort these so dates are in order for chart
		// Build arrays for chart
		$dly_cpcty = array(); 
		$proj_hourstot = 0;
		if( !empty( $pm_schedule ) ){ foreach( $pm_schedule as $date => $dateR ){
			foreach ($dateR as $assign => $sched_day) {
			//	$date = strtotime( $sched->date );
			//	$pm_schedule[$sched->assign][$date]['hours'] = $sched->hours;
			//	$pm_schedule[$sched->assign][$date]['task_times'] = json_decode( $sched->task_times );
				if( !isset($dly_cpcty[$date]) ) $dly_cpcty[$date] = 0;
				$dly_cpcty[$date] += $sched_day['hours'];
				$proj_hourstot += $sched_day['hours'];
			}
		}}
		ksort($dly_cpcty); ksort($pm_schedule); // sort these so dates are in order for chart
		$wdays_left = count($dly_cpcty);
		?>
		<div class="chart" id="chart1">
            <table class="wp-list-table widefat t8-pm-barchart" id="data-table1" border="1" cellpadding="10" cellspacing="0"
            summary="hours estimated per day">
               <caption>Project Schedule</caption>
               <thead>
                  <tr>
                     <td>&nbsp;</td>
            <?php $nowtime = time(); $prevDay = 0;
					foreach($dly_cpcty as $day => $capacityleft){	
						$dstring = strtotime( $day );
						if($day != '') {
							if( date("j", $dstring ) == '1') {
								$displayDate = date("D, j M  y", $dstring);
							} else {
								$displayDate = date("D j", $dstring);
							}
							$addClass = '';
							if( ( $dstring + ( 24 * 3600 ) ) < $nowtime ) { $addClass = 'past '; $wdays_left--; }
							if( $dstring < $nowtime && $nowtime < ( $dstring + ( 24 * 3600 ) ) ) { $addClass = 'today '; $displayDate .= ' TODAY'; $wdays_left--; }
							if( $prevDay < $nowtime && $nowtime < $dstring ) { $addClass = 'nowtime '; }
							echo '<th scope="col" class="'. $addClass .'">'. $displayDate .'</th>';
							$prevDay = $dstring;
						}
					 } ?>
                 </tr>
               </thead>
               <tbody>
				 	<?php 
				 	$pm_schedule2 = array(); // !!! this is a mess, need to reorder the array for the old markup, need to reconcept markup to fit array, date then assign
				 	foreach($pm_schedule as $date => $dateR){
						foreach($dateR as $assign => $schedR){
							$pm_schedule2[$assign][$date] = $schedR;
				 		}
				 	} 
//echo '<pre>'; print_r($pm_time); echo '</pre>';
				 	foreach($pm_schedule2 as $assign => $dateR){ 
						if($assign != ''){ ?>
					<tr>
                    	<th scope="row" class="user-<?php echo $pm_users[$assign]["uslug"]; ?>"><?php echo $pm_users[$assign]["uname"]; ?></th>
                   		<?php foreach($dly_cpcty as $day => $capacityleft){ ?>
                  			<td><?php	
							$hoursR = 0;
							if( isset( $pm_time[$assign][$day]["hours"] ) ){
								$hoursR = $pm_time[$assign][$day]["hours"];
							} else {
								if( isset( $dateR[$day]["hours"] ) ) $hoursR = $dateR[$day]["hours"];
							}
							if($hoursR) echo round(100*$hoursR)/100;
							?></td>
                            <?php
						}
					}?>
                  </tr>
				<?php } ?>
               </tbody>
            </table>
       </div>		
        <p><?php echo $proj_hoursRaw; ?> estimated hours (<?php echo $proj_hourstot; ?> est task hours) | <?php echo $punched_hourstot; ?> punched hours</p>
		<p><?php echo date("D, M j  Y", strtotime($project->start_date) ) .' - '.date("D, M j  Y", strtotime($project->end_date) ); ?></p>
        <p><?php echo $wdays_left; ?> of <?php echo count( $dly_cpcty ); ?> Workdays left</p>
     <?php
	}else{ ?>
    <p>Couldn't find Project <?php echo $proj_id; ?> or </p>
	<?php } 
} //end function t8_pm_display_schedule( $proj_id ) 
/*
* Schedule Project 
* 
* Updates schedule AFTER edits to pm_ proj, tasks, or time
*/
function t8_pm_schedule( $proj_id, $t8_pm_proj_start = 0, $t8_pm_proj_end = 0 ) {
	global $wpdb;
	date_default_timezone_set("America/New_York");
	
	// Set Variables
	if( !$t8_pm_proj_start || !$t8_pm_proj_end ) { // get project dates if not set in function call
		$project = $wpdb->get_row("SELECT * FROM ".$wpdb->prefix . "pm_projects WHERE id = ".$proj_id); // collect Project
		if($project){
			$t8_pm_proj_start = $project->start_date;
			$t8_pm_proj_end = $project->end_date;
		}else{
			// no project exists !!! need to shut this down and throw warning
		}
	}
	//if proj has already started, move remaining schedule start to today
	if(strtotime($t8_pm_proj_start) < time() )  $nowday = date('Y-m-d', time());
	$est_hours = $stgHrs = $punched_hours = array();
	
	// collect time entries by proj
	$punched_results = $wpdb->get_results("SELECT task_id, hours FROM ".$wpdb->prefix . "pm_time WHERE proj_id = ".$proj_id); 
	if( $punched_results ) { 
		foreach($punched_results as $punched){
			if( !isset($punched_hours[$punched->task_id]) ) $punched_hours[$punched->task_id] = 0;
			$punched_hours[$punched->task_id] += $punched->hours;
		}
	}else{ //no punched time
	}
	
	// collect tasks by proj
	$task_results = $wpdb->get_results("SELECT * FROM ".$wpdb->prefix . "pm_tasks WHERE proj_id = ".$proj_id); 
	if( $task_results ) { 
		foreach($task_results as $task){
			if($task->status < 2){
				if( !isset($punched_hours[$task->id]) ) $punched_hours[$task->id] = 0;
				if( $punched_hours[$task->id] > $task->est_hours ) { 
					$est_hours[$punched->task_id] = 0;
				} else {
					$est_hours[$task->id] = $task->est_hours;
				}
				
				$tasks[$task->stage][$task->id] = array(
					"est_hours" => $task->est_hours,
					"cnt_hours" => $task->est_hours,
					"assign" => $task->assign,
					"status" => $task->status // !!! if this is completed, need to remove it from schedule
				);
				if( !isset($stgHrs[$task->stage]) ) $stgHrs[$task->stage] = 0;
				$stgHrs[$task->stage] += $task->est_hours;
			}
		}
	}else{ //no tasks !!! throw an error or work with empty array?
	}
// echo '<pre>'; print_r( $tasks ); echo '</pre>';
	
	//Create Schedule Array based on adjusted times
	$proj_hours = array_sum($est_hours)*1.10; // add 10% to project est_hours. 
	$dly_cpcty = t8_pm_getWorkDays($t8_pm_proj_start, $t8_pm_proj_end, $proj_hours); // calculate number of work days between project start and end, build array
	$workDays = count($dly_cpcty); // count number of work days in cal
	$proj_hoursRaw = array_sum($est_hours); // project est_hours. 

	$dly_hrs = $proj_hours/$workDays; // divide hours by days for target scheduling per day
	$schedule = array();
		ksort($stgHrs); ksort($tasks);
		if($tasks){foreach($tasks as $stage => $stgtasks){
			if($stage == 0){ // the ongoing stage. distrubute time across entire timeline
				foreach($stgtasks as $task_id => $task){
					$taskHours = $task["est_hours"]; //to subtract from as assigning
					$hours = array( 
						"assign" 	=> $task["assign"],
						"remainder" => $taskHours,
						"perday" 	=> $taskHours/$workDays
					);
					foreach($dly_cpcty as $date => $cap_in_day){
						if($cap_in_day >= $hours["perday"]){ // room left in day after we sched this, go ahead and sched full remainder of this task
							if($hours["perday"]>0){
								$assign = $hours["assign"];									
								if( !isset($pm_schedule[$date][$assign]["hours"]) ) $pm_schedule[$date][$assign]["hours"] = 0;
								$pm_schedule[$date][$assign]["hours"] += $hours["perday"]; // build the schedule
								$pm_schedule[$date][$assign]["task_times"][$task_id] = round(1000*$hours["perday"])/1000; // build the schedule
								$hours["remainder"] -= $hours["perday"];
								$dly_cpcty["$date"] -= $hours["perday"];
							}
						}else{ // no room for this full task remainder, schedule the portion
							$assign = $hours["assign"];
							if( !isset($pm_schedule[$date][$assign]["hours"]) ) $pm_schedule[$date][$assign]["hours"] = 0;
							$pm_schedule[$date][$assign]["hours"]+=$cap_in_day; // build the schedule
							$pm_schedule[$date][$assign]["task_times"][$task_id] = round(1000*$cap_in_day)/1000; // build the schedule
							$hours["remainder"]-=$cap_in_day;
							$dly_cpcty["$date"]-=0;
						}
					} 
				}//end each task
			}else{ //end if stage == 0
			// We are already iterating through stages and have calculated and assigned stage 0 (even disbursment thru project). Now we iterate thru days and then thru the tasks in the current stage for every day. Remember, we will end the task and day loop after each stage and then cycle back thru days for the next stage.
				$stgHrsLft = $stgHrs[$stage]; // tally for overall stage hours
				foreach($dly_cpcty as $date => $cap_in_day){ // iterate days
					$stgHrsToday = 0;
					if($cap_in_day > 0){ // capacity is not yet full
						foreach($stgtasks as $task_id => $task){ // already iterating days, now iterate tasks
							if($stgtasks[$task_id]["cnt_hours"] > 0){ // this stage still has time to assign
								$assign = $task["assign"];
								$schdTime = $cap_in_day*ceil(1000*$task["est_hours"]/$stgHrs[$stage])/1000; // Get the amount of time to schedule today. Overall task hours / stage hours * the day's capacity.
								if($schdTime > $stgtasks[$task_id]["cnt_hours"]) $schdTime = $stgtasks[$task_id]["cnt_hours"]; // if time to schedule is more than time left, set to time left
								if( !isset($pm_schedule[$date][$assign]["hours"]) ) $pm_schedule[$date][$assign]["hours"] = 0;
								$pm_schedule[$date][$assign]["hours"]+=$schdTime; // build the schedule
								$pm_schedule[$date][$assign]["task_times"][$task_id] = round(1000*$schdTime)/1000; // build the schedule
								$stgtasks[$task_id]["cnt_hours"] -= $schdTime; // update this task time left tally
								$stgHrsLft -= $schdTime; // update this stage time left tally
								$stgHrsToday += $schdTime; // add time scheduled from stage time
								
							}
						}//end foreach tasks
						if($stgHrsToday >= $cap_in_day){ $cap_in_day = $dly_cpcty["$date"] = 0; 
						}else{ $cap_in_day = $dly_cpcty["$date"] = $cap_in_day - $stgHrsToday;} // We're at the end of the stage. Update the capacity calendar to deduct all the time assigned in this stage on this day. This should block out whole days, before the next stage starts, except the last day, which will leave a remainder.
					}
				} //end foreach $dly_cpcty
			}// end else stage == 0
		}}//end if/foreach $tasks
	$sched_array = json_encode( $pm_schedule );
	$resultsSched = $wpdb->update( $wpdb->prefix . 'pm_projects', array( 'schedule' => $sched_array ), array('id' => $proj_id) );
	
	$return['pm_schedule'] = $pm_schedule;
	$return['dly_cpcty'] = $dly_cpcty;
	return $return;
	
} // end func t8_pm_schedule

// !!! are we using this?
function t8_pm_get_client($task_id) {
	global $wpdb;
	
	if(isset($task_id)) {
		$cli_id = $wpdb->get_results("SELECT cli_id FROM " . $wpdb->prefix . "pm_tasks WHERE id = " . $task_id . "");
		$cli_id = $cli_id[0]->cli_id;
		$cli_name = $wpdb->get_results("SELECT name FROM " . $wpdb->prefix . "pm_cli WHERE id = " . $cli_id . "");
		return $cli_name[0]->name;
	}
	else {
		return false;
	}
}

/*
* Display Capacity Clendar
* 
* Displays the Capacity calendar
*/
// !!! really need to fix this
function t8_pm_cap_calendar( $pm_users ) {
	global $wpdb;
	date_default_timezone_set("America/New_York");
		$stgHrs= array ();
		//Get Time with this Project id
		$punched_results = $wpdb->get_results("SELECT * FROM ".$wpdb->prefix . "pm_time WHERE DATE(start_time) > DATE_SUB(CURDATE(),INTERVAL 7 DAY)" ); // . strtotime('-1 week') collect punched time
		// Build arrays for chart
		$pm_time = $proj_ids = $pm_schedule = $cli_ids = array(); 
		$dly_cpcty = array(); 
		$punched_hourstot = $i = 0;
		if($punched_results){ foreach($punched_results as $punched){ // build array with id as key
			$date = date('Y-m-d', strtotime( $punched->start_time ) ); //  finds just day of timestamp. !!! - eventually these should change to the mySQL date format like the other tables?
			if( !isset($pm_time[$punched->user_id][$date]['hours']) ) $pm_time[$punched->user_id][$date][$punched->proj_id]['hours'] = 0;
			$pm_time[$punched->user_id][$date][$punched->proj_id]['hours'] += $punched->hours;
			if(!$punched->task_id ) $punched->task_id = 't'.$i;
			$pm_time[$punched->user_id][$date][$punched->proj_id]['task_times'][$punched->task_id] = $punched->hours;
			$punched_hourstot += $punched->hours;
			if( !isset($punchedtasks[$punched->task_id]['hours']) ) $punchedtasks[$punched->task_id]['hours'] = 0;
			$punchedtasks[$punched->task_id]['hours'] += $punched->hours;
			if( !isset($dly_cpcty[$date]) ) $dly_cpcty[$date] = 0;
			$dly_cpcty[$date] += $punched->hours;
			$proj_ids[] = $punched->proj_id;
			$i++;
		}}
		ksort($pm_time); // sort these so dates are in order for chart

		//Get Schedule with this Project id
		// Build arrays for chart
		$proj_results = $wpdb->get_results(
			"SELECT * FROM ".$wpdb->prefix . "pm_projects 
			WHERE start_date <= CURDATE() 
			AND status = '1'"  
		); // collect Project
		$cli_results = 0;
		if($proj_results){ foreach($proj_results as $proj){ // build array with id as key
			$t8_pm_projs[$proj->id]['name'] = $proj->name;
			$t8_pm_projs[$proj->id]['cli'] = $proj->cli_id;
			$t8_pm_projs[$proj->id]['sched'] = json_decode( $proj->schedule, true );
			$cli_ids[] = $proj->cli_id;
		}}
		if (!empty($cli_ids)) $cli_results = $wpdb->get_results("SELECT * FROM ".$wpdb->prefix . "pm_cli WHERE id IN (" . implode(',', $cli_ids ).")" ); // collect Project
		if($cli_results){ foreach($cli_results as $cli){ // build array with id as key
			$t8_pm_clis[$cli->id] = $cli->name;
		}}
		$proj_hourstot = 0;
		if( isset( $t8_pm_projs ) ){ foreach($t8_pm_projs as $proj_id => $projR ){ // build array with id as key
			if( !empty($projR['sched']) ){
				foreach ($projR['sched'] as $date => $dateR){
					foreach($dateR as $assign => $schedR){
						$pm_schedule[$assign][$date][$proj_id] = $schedR;
						if( !isset($dly_cpcty[$date]) ) $dly_cpcty[$date] = 0;
						$dly_cpcty[$date] += $schedR['hours'];
						$proj_hourstot += $schedR['hours'];
			 		}
			 	}
			}
		}}
		ksort($dly_cpcty); // sort these so dates are in order for chart
		// go through and trim empty days at back and front
		$hrsyet = 0;
		$dly_cpcty2 = array();
		foreach ($dly_cpcty as $date => $hours) {
			if ( !$hours && !$hrsyet ) {
			} else {
				$hrsyet = 1;
				$dly_cpcty2[$date]=$hours;
			}
		}
		$wdays_left = count($dly_cpcty); // !!! ths may not work here
		$dly_cpcty = $dly_cpcty2;
		?>
		<div class="chart" id="capchart1">
            <table class="wp-list-table widefat t8-pm-barchart" id="data-table1" border="1" cellpadding="10" cellspacing="0"
            summary="hours estimated per day">
               <caption>Project Schedule</caption>
               <thead>
                  <tr>
                     <td>&nbsp;</td>
            <?php $nowtime = time(); $prevDay = 0;
            ksort($dly_cpcty);
//echo '<pre>'; print_r($pm_schedule); echo '</pre>';
					foreach($dly_cpcty as $day => $capacityleft){	
						if($day != '') {
							$dtime = strtotime( $day );
							if(date("j", $dtime )=='1') {
								$displayDate = date("D, j M  y", $dtime);
							} else {
								$displayDate = date("D j", $dtime);
							}
							$addClass = '';
							if( ( $dtime + ( 24 * 3600 ) ) < $nowtime ) { $addClass = 'past '; $wdays_left--; }
							if( $dtime < $nowtime && $nowtime < ( $dtime + ( 24 * 3600 ) ) ) { $addClass = 'today '; $displayDate .= ' TODAY'; $wdays_left--; }
							if( $prevDay < $nowtime && $nowtime < $dtime ) { $addClass = 'nowtime '; }
							echo '<th scope="col" class="'. $addClass .'">'. $displayDate .'</th>';
							$prevDay = $dtime;
						}
					 } ?>
                 </tr>
               </thead>
               <tbody>
				 	<?php 
				 	foreach($pm_schedule as $assign => $dateR){ 
						if($assign != ''){ ?>
					<tr>
                    	<th scope="row" class="user-<?php echo $pm_users[$assign]["uslug"]; ?>"><?php echo $pm_users[$assign]["uname"]; ?></th>
                   		<?php foreach($dly_cpcty as $day => $capacityleft){ ?>
                  			<?php	
							$divText = $hours = '';
							if (isset($dateR[$day])) {
								foreach($dateR[$day] as $proj_id => $projR) {
									//echo '<pre>'; print_r($projR); echo '</pre>';
									$hours += $projR["hours"];
									$divText .= '<div class="user-'. $pm_users[$assign]["uslug"] . '">
										'. $t8_pm_clis[$t8_pm_projs[$proj_id]['cli']] .'::'. $t8_pm_projs[$proj_id]['name'] .'
										<strong>'.round(100*$projR["hours"])/100 . '</strong>
									</div>';
								}
							}
							//$dtime = strtotime( $day );
							if( isset( $pm_time[$assign][$day] ) ){
								foreach($pm_time[$assign][$day] as $proj_id => $projR) {
									$hours += $projR["hours"];
									$divText .=  '<div class="punched user-'. $pm_users[$assign]["uslug"] . '">
										'. $t8_pm_clis[$t8_pm_projs[$proj_id]['cli']] .'::'. $t8_pm_projs[$proj_id]['name'] .'
										<strong>'.round(100*$projR["hours"])/100 . '</strong>
									</div>';
								}
							}
							?><td data-total="<?php echo $hours; ?>"><?php echo ($divText != '' ? $divText : '<div>0</div>' ); ?></td>
                            <?php
						}
					}?>
                  </tr>
				<?php } ?>
               </tbody>
            </table>
       </div>		
     
	<?php  
} //end function t8_pm_display_schedule( $pm_users ) 

/**
 * Client Project Task selects.
 *
 * Generates a group of selects for clients, projects, and tasks.
 *
 * @param  bool $getcli Whether the client list needs to be populated.
 * @param  string $cli Optional. Client id.
 * @param  string $proj Optional. Project id.
 * @param  string $task Optional. Task id.
 * @return array Client, project, and/or task option lists to populate selects.
 */
function t8_pm_cli_proj_task_selects( $getcli = 0, $cli = 0, $proj = 0, $task = 0 ){
	global $wpdb;

	if( $getcli ){
		// build cli select
		$cats = t8_pm_get_catsarray(); // get cli array for cli dropdown
		$selcli = 0;
		$addclilist = '<optgroup label="Open Clients"><option>Choose a Client...</option>';
		$opencli = $othercli = array();
		if(!empty($cats[0])){
			foreach ( $cats[0] as $acat_id => $acat_name ) { // open clients first (status = 0 is open client)
				$sel = '';
				if( $cli ) $sel = ( $cli == $acat_id ? ' selected="selected"' : '' );
				if( $sel != '' ) $return['selcli'] = 1;
	            $addclilist .= "<option$sel value=\"$acat_id\">$acat_name</option>";
			}
		}	
		$addclilist .= '</optgroup><optgroup label="Other Clients">';
		if(!empty($cats[1])){
			foreach ( $cats[1] as $acat_id => $acat_name ) { // open clients first (status = 0 is open client)
				$sel = '';
				if( $cli ) $sel = ( $cli == $acat_id ? ' selected="selected"' : '' );
				if( $sel != '' ) $return['selcli'] = 1;
	            $addclilist .= "<option$sel value=\"$acat_id\">$acat_name</option>";
			}
		}
		$addclilist .= '</optgroup>';
		$return['cli'] = $addclilist;
	}

	// build proj select
	if( $cli ){
		$addprojlist = 0;
		$query = "SELECT id, name FROM " . $wpdb->prefix . "pm_projects WHERE status < 3 AND cli_id = " . $cli;
		$results = $wpdb->get_results( $query );
		if($results){
			$addprojlist = "<option>Project...</option>";
			foreach($results as $result){
				$sel = '';
				if( $proj ) $sel = ( $proj == $result->id ? ' selected="selected"' : '' );
				$addprojlist .= '<option'.$sel.' value="'.$result->id.'">'.$result->name.'</option>';
			}
		}
		$return['proj'] = $addprojlist;
	}
	if( $proj ) {
		$addtasklist = 0;
		//get proj for milestone titles
		$table_name = $wpdb->prefix . 'pm_projects';
		$query = "SELECT misc FROM ".$table_name." WHERE id = ".$proj;
		$results = $wpdb->get_results( $query );
		if($results){
			$mstones1 = unserialize( $results[0]->misc );
			$mstones = $mstones1['milestones'];
		}
		// now get tasks for this project
		$table_name = $wpdb->prefix . 'pm_tasks';
		$query = "SELECT id, task_title, stage, est_hours FROM ".$table_name." WHERE proj_id = ".$proj;
		$results1 = $wpdb->get_results( $query );
		if( $results1 ) {
			$addtasklist = '<option>Task...</option>';
			if( $mstones ) { 
				foreach($mstones as $mkey => $msname) { // loop through mstones
					$mstone2[] = $msname['name'];
					if($msname['name'] == '0') $msname['name'] = 'Ongoing';
					$addtasklist .= '<optgroup label="' . $msname['name'] . '">';
					foreach($results1 as $result){ // loop through tasks
						if( $task == $result->id && isset( $punchin ) ) {
							$tem = ($result->est_hours*60)%60;
							$punchin['est_thours'] = floor($result->est_hours) . 'h '. round($tem) . 'm'; 
						}
						if( $result->stage == $mkey ) { // pass this task if not in this mstone
							$sel = '';
							if( $task ) $sel = ( $task == $result->id ? ' selected="selected"' : '' );
							$addtasklist .= '<option'.$sel.' value="'.$result->id.'">'.$result->task_title.'</option>';
						}
					}
				}
			}
			$return['task'] = $addtasklist;
		}
	}
	return $return;
}
function t8_pm_totals ($projquery){
	global $wpdb; //set ur globals

	$proj_results = $wpdb->get_results("SELECT * FROM ".$wpdb->prefix . 'pm_projects WHERE '.$projquery ); // collect Projects based on Query
	if($proj_results){ foreach($proj_results as $project){ // build array with id as key
		$t8_pm_tots_hours[] = $project->est_hours;
		$t8_pm_tots_price[] = $project->price;
	}}
	$totals = array(
		'projects' => count( $proj_results ),			
		'est_hours' => array_sum( $t8_pm_tots_hours ),
		'price' => array_sum( $t8_pm_tots_price )
	);
	return $totals;
} // end function t8_pm_totals
/**
 * Build array of business days between two dates. Skips the holidays.
 */
function t8_pm_getWorkDays($startDate,$endDate, $proj_hours){
    // do strtotime calculations just once
    $day_stamp = strtotime($startDate);
    $end_stamp = strtotime($endDate);

    $dly_cpcty=array();
	while($day_stamp <= $end_stamp){
		if(date("N", $day_stamp) <= 5){ // we got a weekday, let's add it to the array
			$dly_cpcty[] = date( 'Y-m-d', $day_stamp );
			$day_stamp+=86400; //move forward a day			
		}else{ //weekend, pass
			$day_stamp+=86400; //move forward a day
		}
	}

	$holidays=array();
	$thisyear = 	date("Y");
	$holidays[]= 	date( 'Y-m-d', mktime(0, 0, 0, 1, 1, $thisyear) ); // New Year's Day Jan 1st
	$holidays[]= 	date( 'Y-m-d', mktime(0, 0, 0, 1, 1, $thisyear+1) );
	$holidays[]= 	date( 'Y-m-d', strtotime("3 Mondays", mktime(0, 0, 0, 1, 1, $thisyear)) ); // Martin Luther King, Jr 3rd Monday of Jan
	$holidays[]= 	date( 'Y-m-d', strtotime("3 Mondays", mktime(0, 0, 0, 1, 1, $thisyear+1)) );
	$holidays[]= 	date( 'Y-m-d', strtotime("3 Mondays", mktime(0, 0, 0, 2, 1, $thisyear)) ); // Washington 3rd Monday of Feb
	$holidays[]= 	date( 'Y-m-d', strtotime("3 Mondays", mktime(0, 0, 0, 2, 1, $thisyear+1)) );
	$holidays[]= 	date( 'Y-m-d', strtotime("last Monday of May".$thisyear) ); // Memorial last Monday of May
	$holidays[]= 	date( 'Y-m-d', strtotime("last Monday of May".$thisyear+1) );
	$holidays[]= 	date( 'Y-m-d', mktime(0, 0, 0, 7, 4, $thisyear) ); // Independence July 4
	$holidays[]= 	date( 'Y-m-d', mktime(0, 0, 0, 7, 4, $thisyear+1) );
	$holidays[]= 	date( 'Y-m-d', strtotime("first Monday of September".$thisyear) ); // Labor 1st Monday of Sept
	$holidays[]= 	date( 'Y-m-d', strtotime("first Monday of September".$thisyear+1) );
	$holidays[]= 	date( 'Y-m-d', strtotime("2 Mondays", mktime(0, 0, 0, 10, 1, $thisyear)) ); // Columbus 2nd Monday of Oct
	$holidays[]= 	date( 'Y-m-d', strtotime("2 Mondays", mktime(0, 0, 0, 10, 1, $thisyear+1)) );
	$holidays[]= 	date( 'Y-m-d', mktime(0, 0, 0, 11, 11, $thisyear) ); // Veteran November 11
	$holidays[]= 	date( 'Y-m-d', mktime(0, 0, 0, 11, 11, $thisyear+1) );
	$holidays[]= 	date( 'Y-m-d', strtotime("4 Thursdays", mktime(0, 0, 0, 11, 1, $thisyear)) ); // Thanksgiving 4th Thursday of Nov
	$holidays[]= 	date( 'Y-m-d', strtotime("4 Thursdays", mktime(0, 0, 0, 11, 1, $thisyear+1)) );
	$holidays[]= 	date( 'Y-m-d', mktime(0, 0, 0, 12, 25, $thisyear) ); // Xmas Dec 25
	$holidays[]= 	date( 'Y-m-d', mktime(0, 0, 0, 12, 25, $thisyear+1) );
	
    //We subtract the holidays
    foreach($holidays as $holiday){
        //If the holiday doesn't fall in weekend
        $holitime = strtotime( $holiday );
        if( isset($dly_cpcty[0]) ){
        	if ( strtotime( $dly_cpcty[0] ) <= $holitime && $holitime <= strtotime( end($dly_cpcty) ) && date("N",$holitime) != 6 && date("N",$holitime) != 7)
           		$dly_cpcty =  array_diff($dly_cpcty, array($holiday));
   		}
    }
	$dly_cpcty = array_flip($dly_cpcty);
	// run through dly_cpcty and count for days after yesterday
	$yester = strtotime("yesterday");
	$i = 0;
	foreach($dly_cpcty as $date => $cap){
		if( strtotime( $date ) > $yester ) $i++;
	}
	$dly_hrs = $proj_hours/$i;
	foreach($dly_cpcty as $date => $cap){
		$dly_cpcty[$date] = ( strtotime( $date ) > $yester ? $dly_hrs : 0 );
	}
    return $dly_cpcty;/**/
} // end function getWorkDays
/*Example:
$holidays=array("2008-12-25","2008-12-26","2009-01-01");
echo getWorkDays("2008-12-22","2009-01-02",$holidays)
// => will return 7 */

/**
 * PUNCHCLOCK Functions
 */
/* Get task build cat array */
function t8_pm_get_catsarray() {
   	global $wpdb;
	$return = array();

   	$table_name = $wpdb->prefix . 'pm_cli';
	$sql = "SELECT id, name, status FROM ".$table_name;
	$results = $wpdb->get_results( $sql );
	if($results){
		foreach($results as $result){
			$return[$result->status][$result->id]=$result->name;	
		}
		if(!empty($return) ) asort( $return );		
	}
	return $return;
}
/* Build dropdowns for punch clocks */
// used for dashboard and reports
function t8_pm_pc_drops() {
   	global $wpdb;
		if (! wp_verify_nonce( $_POST['nonce'], 't8_pm_nonce') ){
			die ('Busted!');
		}
			$addprojlist = $addtasklist = '';
		if($_POST['cli']){
			$newclient = intval($_POST['cli']);
			$table_name = $wpdb->prefix . 'pm_projects';
			$query = "SELECT id, name FROM ".$table_name." WHERE status < 3 AND cli_id = ".$newclient; // status = 2 AND 
			$results = $wpdb->get_results( $query );
			$addprojlist = '<option>Project...</option>';
			if($results){
				$return['projsshow'] = 1;
				foreach($results as $result){
					$sel = ( $_POST['proj'] == $result->id ? ' selected="selected"' : '' );
					$addprojlist .= '<option'. $sel .' value="'.$result->id.'">'.$result->name.'</option>';
				}
			}
		}
		if($_POST['proj']){
			$newproj = intval($_POST['proj']);
			$newtask = intval($_POST['task']);
			//get proj for milestone titles
			$table_name = $wpdb->prefix . 'pm_projects';
			$query = "SELECT misc FROM ".$table_name." WHERE id = ".$newproj;
			$results = $wpdb->get_results( $query );
			if($results){
				$mstones1 = unserialize( $results[0]->misc );
				$mstones = $mstones1['milestones'];
			}
			// now get tasks for this project
			$table_name = $wpdb->prefix . 'pm_tasks';
			$query = "SELECT * FROM ".$table_name." WHERE proj_id = ".$newproj;
			$results1 = $wpdb->get_results( $query );
			$addtasklist = '<option>Task...</option>';
			$pTimes = array(
				'est_time' => 0,
				'punched' => 0
			);
			if( $results1 ) {
				$return['tasksshow'] = 1;
				foreach($results1 as $rtask){
					if($rtask->id == $newtask ){ //
						// get task overall estimated time
						$pTimes['est_time'] = $rtask->est_hours; //$pTimes['est_time'] = $task_res[$tid]->est_time;
						// get all punched time to this task
						$punched_res = $wpdb->get_results('SELECT hours FROM ' . $wpdb->prefix . 'pm_time WHERE task_id = ' . $newtask . '');
						if($punched_res){
							foreach( $punched_res as $punched ){
								$pTimes['punched'] += $punched->hours;
							}
						}
					}
				}
				if( $mstones ) { 
					foreach($mstones as $mkey => $msname) { // loop through mstones
						$mstone2[] = $msname['name'];
						if($msname['name'] == '0') $msname['name'] = 'Ongoing';
						$addtasklist .= '<optgroup label="' . $msname['name'] . '">';
						foreach($results1 as $result){ // loop through tasks
							if( $result->stage == $mkey ) { // pass this task if not in this mstone
								$sel = ( $_POST['task'] == $result->id ? ' selected="selected"' : '' );
								$addtasklist .= '<option'. $sel .' value="'.$result->id.'">'.$result->task_title.'</option>';
							}
						}
					}
				}
			}
		}
		if ( $addtasklist || $addprojlist ) {
			$return['tasks'] = $addtasklist;
			$return['mstones'] = $mstone2;
			$return['projs'] = $addprojlist;
			$return['pTimes'] = $pTimes;
			$return['newtask'] = $newtask;
			echo json_encode($return);
			die();
		} else {
			$return['tasks'] = '<option>Task...</option>';
			$return['projs'] = '<option>Project...</option>';
			echo json_encode($return);
			die();
		}
}


/**
 * AJAX Functions
 */
add_action('wp_ajax_t8_pm_tasksubmit', 't8_pm_tasksubmit');
add_action('wp_ajax_t8_pm_find_cli_projs', 't8_pm_find_cli_projs');
add_action('wp_ajax_t8_pm_cli_status', 't8_pm_cli_status');
add_action('wp_ajax_t8_pm_find_proj_tasks', 't8_pm_find_proj_tasks');
add_action('wp_ajax_t8_pm_find_dash_cli_tasks', 't8_pm_find_dash_cli_tasks');
add_action('wp_ajax_t8_pm_find_dash_proj_tasks', 't8_pm_find_dash_proj_tasks');
add_action('wp_ajax_t8_pm_show_stage', 't8_pm_today_task_flyout');
add_action('wp_ajax_nopriv_t8_pm_show_stage', 't8_pm_today_task_flyout');
add_action('wp_ajax_t8_pm_add_orphan_assign', 't8_pm_add_orphan_assign');
add_action('wp_ajax_nopriv_t8_pm_add_orphan_assign', 't8_pm_add_orphan_assign');
add_action('wp_ajax_t8_pm_update_todays_tasks', 't8_pm_update_todays_tasks');
add_action('wp_ajax_nopriv_t8_pm_update_todays_tasks', 't8_pm_update_todays_tasks');
add_action('wp_ajax_t8_pm_pc_drops', 't8_pm_pc_drops');
add_action('wp_ajax_t8_pm_pc_punchin', 't8_pm_pc_punchin');
add_action('wp_ajax_t8_pm_pc_punchout', 't8_pm_pc_punchout');
add_action('wp_ajax_t8_pm_projtotrash', 't8_pm_projtotrash');
add_action('wp_ajax_t8_pm_update_time_entry', 't8_pm_update_time_entry');
add_action('wp_ajax_t8_pm_del_time_entry', 't8_pm_del_time_entry');
/**
 * Projects
 */
// move proj to trash
function t8_pm_projtotrash() {
   	global $wpdb;
		if (! wp_verify_nonce($_POST['nonce'], 't8_pm_nonce') ){
			die('Bad Nonce');
		}
		//$punchin = get_user_meta($userdata->ID, 'dayplanner', true);
		$proj_id = intval( $_POST['proj_id'] );
		
		$resultsproj = $wpdb->update( $wpdb->prefix . 'pm_projects', array( 'status' => 3 ), array('id' => $proj_id) );
		
		if( $resultsproj ) {
			$sched_results = $wpdb->get_results("DELETE FROM ".$wpdb->prefix . "pm_schedule WHERE proj_id = ".$proj_id  ); 
			$return = '<p>Project moved to trash</p>';
			if( $sched_results ){
				$return .= '<p>Project deleted from schedule</p>';
			}
		} else {
			$return = '<p>Project could not be moved to trash</p>';
		}
		echo $return;
		die();
	}	
/* PUNCH OUT */
// DASHBOARD PUNCHCLOCK

/* PUNCH IN */
function t8_pm_pc_punchin() {
   	global $wpdb, $userdata, $current_user;
		if (! wp_verify_nonce($_POST['nonce'], 't8_pm_nonce') ){
			die('Bad Nonce');
		}
		//$punchin = get_user_meta($userdata->ID, 'dayplanner', true);
		$tid = intval( $_POST['task'] );
		if($_POST['start_time']){ //only if cli is loaded, skip for just task data
			$desc = $_POST['desc'];
			$desc = wp_kses_stripslashes($desc);
			$punchin = array(
				'start_time' => intval( $_POST['start_time'] ),
				'cli' => intval( $_POST['cli'] ),		  
				'proj' => intval( $_POST['proj'] ),		  
				'task' => $tid,
				'description' => $desc
			);
			update_user_meta( $userdata->ID, 'punchin', $punchin );
		}elseif(!$tid){ //
			update_user_meta( $userdata->ID, 'punchin', '' );
		}
		$pTimes = array(
			'est_time' => 0,
			'punched' => 0
		);
		if($tid){ //
			// get task overall estimated time
			$task_res = $wpdb->get_results('SELECT est_hours FROM ' . $wpdb->prefix . 'pm_tasks WHERE id = ' . $tid . '');
			if($task_res) $pTimes['est_time'] = $task_res[0]->est_hours; //$pTimes['est_time'] = $task_res[$tid]->est_time;
			// get all punched time to this task
			$punched_res = $wpdb->get_results('SELECT hours FROM ' . $wpdb->prefix . 'pm_time WHERE task_id = ' . $tid . '');
			if($punched_res){
				foreach( $punched_res as $punched ){
					$pTimes['punched'] += $punched->hours;
				}
			}
		}
		echo json_encode($pTimes);
		die();
	}	
/* PUNCH OUT */
function t8_pm_pc_punchout() {
   	global $wpdb, $userdata, $current_user;
	if (! wp_verify_nonce($_POST['nonce'], 't8_pm_nonce') ){
		die('Bad Nonce');
	}
	// !!! what about assignments that are already in pm_time???
	
	//$punchin = get_user_meta($userdata->ID, 'dayplanner', true);
	$start_time = intval( $_POST['start_time'] );
	$end_time = intval( $_POST['end_time'] );
	$table_name = $wpdb->prefix . 'pm_time';
	$cli = intval( $_POST['cli'] );
	$proj = intval( $_POST['proj'] );
	$task = intval( $_POST['task'] );

	$cliname = sanitize_text_field( $_POST['cliname'] );
	$projname = sanitize_text_field( $_POST['projname'] );
	$taskname = sanitize_text_field( $_POST['taskname'] );

	$hours = round( ( $end_time - $start_time )/60/60, 2 );
	$assign = $userdata->ID;
	$desc = sanitize_text_field($_POST['desc']);
	date_default_timezone_set("America/New_York");
	$startNtime = date("g:i a", $start_time);
	$endNtime = date("g:i a", $end_time);

	$results = $wpdb->insert( $table_name, array( 
			'task_id' => $task, 
			'user_id' => $assign, 
			'hours' => $hours, 
			'description' => $desc, 
			'end_time' => date("Y-m-d H:i:s", $end_time), 
			'start_time' => date("Y-m-d H:i:s", $start_time ), 
			'cli_id' => $cli, 
			'proj_id' => $proj
		) 
	);
	if ( $results ) {
		update_user_meta( $userdata->ID, 'punchin', 0 );
		
		$ptask = '<div class="dtask" data-proj-id="'. $proj .'" data-id="'. $task .'" data-cli="'. $cli .'" data-hours="'. $hours .'">
					<h3><span class="cli-span">'. $cliname .'</span>::<span class="proj-span">'. $projname .'</span>::
					<span class="rdts">'. $startNtime .' - '. $endNtime .'</span></h3>
				<p>
					<span class="task-title">'. $taskname .'</span>
					<span class="rdts">'. $hours .' hrs</span>
				</p>
				<div class="send2pc dact">O</div>
			</div>';		
		echo json_encode($ptask);
	}
	die();
}	
/* Edit Time Entry */
function t8_pm_update_time_entry() {
   	global $wpdb;
	if (! wp_verify_nonce($_POST['nonce'], 't8_pm_nonce') ){
		die('Bad Nonce');
	}
	$cli = intval( $_POST['cli'] );
	$proj = intval( $_POST['proj'] );
	$task = intval( $_POST['task'] );
	$assign = intval( $_POST['assign'] );
	$notes = sanitize_text_field($_POST['notes']);
	$date = date("Y-m-d ", strtotime( $_POST['date'] ) );
	$start_time = intval( $_POST['start'] );
	$hours = floatval( $_POST['hours'] );
	$end_time = $start_time + ( $hours * 60 * 60 );
	$time_id = intval( $_POST['time_id'] );

	$table_name = $wpdb->prefix . 'pm_time';

	$entry_R = array( 
			'task_id' => $task, 
			'user_id' => $assign, 
			'hours' => $hours, 
			'description' => $notes, 
			'end_time' => $date . date('H:i:s', $end_time), 
			'start_time' => $date . date('H:i:s', $start_time ), // !!! need to consider timezones and storing and reading out times and chances for breaking these times
			'cli_id' => $cli, 
			'proj_id' => $proj
		);
	if( $time_id ){
		$results = $wpdb->update( $table_name, $entry_R, array('id' => $time_id )  );
		if ( $results ) {
			$return['message'] = '<p>Time entry was updated</p>';
		}		
	}else{
		$results = $wpdb->insert( $table_name, $entry_R );
		if ( $results ) {
			$return['message'] = '<p>Time entry was created</p>';
			$return['tid'] = $wpdb->insert_id;
		}		
	}
	if ( !$results ) $return["warning"] = 'Time entry was not updated!';
	echo json_encode($return);
	die();
}	
/* Delete Time Entry */
function t8_pm_del_time_entry() {
   	global $wpdb;
	if (! wp_verify_nonce($_POST['nonce'], 't8_pm_nonce') ){
		die('Bad Nonce');
	}
	$time_id = intval( $_POST['time_id'] );

	$table_name = $wpdb->prefix . 'pm_time';

	if( $time_id ){
		$wpdb->delete( $table_name, array( 'id' => $time_id ) );
		$return['message'] = '<p>Time entry was deleted</p>';
	}
	if ( !$results ) $return["warning"] = 'Time entry was not deleted!';
	echo json_encode($return);
	die();
}	
// !!! Gotta add Nonces to all these
/* Add task list item to Punchclock 
function t8_pm_punchclock_add() {
   	global $wpdb, $userdata, $t8_pm_punchclock_option, $current_user;
		get_currentuserinfo();
		$punch_users = array();
		$order = 'user_nicename';
		$user_ids = $wpdb->get_col("SELECT ID FROM $wpdb->users ORDER BY $order");
		foreach($user_ids as $user_id) {
			if($user_id != "1"){
				$user = get_userdata($user_id);
				$punch_users[$user_id] = array(
					"uname" => $user->display_name,
					"uslug" => $user->user_nicename
				);
				$color = get_user_meta( $user_id, "color", true);
				if($color!=''){$ltcolor = t8_pm_punchclock_hexLighter($color,30);}else{$color=$ltcolor='fff';}
				$punch_users[$user_id]["color"] = $color;
				$punch_users[$user_id]["ltcolor"] = $ltcolor;
			}
		}
		echo "sumpin'";
		if (! wp_verify_nonce($_POST['nonce'], 't8_pm_nonce') ){
			die();
		}
		$table_name = $wpdb->prefix . 'pm_time';
		if($_POST['start_time'] != ''){
				$start_time = strtotime($_POST['start_time']);
		}
		$cli = intval( $_POST['cli'] );
		$proj = intval( $_POST['proj'] );
		$task = intval( $_POST['task'] );
		$cli_name = esc_html( $_POST['cli_name'] );
		$proj_name = esc_html( $_POST['proj_name'] );
		$task_name = esc_html( $_POST['task_name'] );
		if( is_numeric( $_POST['hours'] ) ) $hours = $_POST['hours'];
		$assign = intval( $_POST['assign'] );
		$notes = $_POST['notes'];
		$end_time = $start_time + ( $hours * 60 * 60 );

	$results = $wpdb->insert( $table_name, array( 
			'task_id' => $task, 
			'user_id' => $assign, 
			'hours' => $hours, 
			'notes' => $notes, 
			'end_time' => date("Y-m-d H:i:s", $end_time), 
			'start_time' => date("Y-m-d H:i:s", $start_time ), 
			'cli_id' => $cli, 
			'proj_id' => $proj
		) 
	);
		if ( $results ) {
		
		$notes = wp_kses_stripslashes($notes);
		
		
			$itemid = $wpdb->insert_id;
			
			$edit = '';
			$edit .= '<a class="edit">Edit</a>';
			$edit .= ' | <a class="delete">Delete</a>';
			$additem .= '<td class="wc_item_date">'.esc_html( $_POST['start_time'] ).'</td>';
			$additem .= '<td class="wc_item_person" style="background-color:#'.$punch_users[$assign]["ltcolor"].';">'.$punch_users[$assign]["uname"].'</td>';
			$additem .= '<td class="wc_item_clijob">'.$cli_name.'::'.$proj_name.'::'.$task_name.'</td>';
			$additem .= '<td class="wc_item_time">'.$hours.'</td>';
			$additem .= '<td class="item_text">'.$notes.'</span></td>';
			$additem .= '<td class="itemedits"></a>'.$edit.'</td>';
			$additem .= '</tr>';
			$return['success'] = $additem;
		} else {
			$return['success'] ='nope';
		}
			echo json_encode($return);
			die();
	}	
*/


/* Handle Submit Task Checkboxes */
function t8_pm_tasksubmit() {
	global $wpdb; // this is how you get access to the database

	$taskid = intval($_POST['taskid']);
	$level = intval($_POST['level']);
	$t8_pm_proj_id = intval($_POST['proj_id']);
	$status = esc_html($_POST['checked']);
	if( $status == 'incomplete' ) $status = '0';
	$task_array = array(
		'status' => $status,
	);
	$resultstasks = $wpdb->update( $wpdb->prefix . 'pm_tasks', $task_array, array('id' => $taskid) );

	if ( $resultstasks ) {
		if($status == '1'){
			$message = __('In Review', 't8-pm');
		}elseif($status == '2'){
			$message = __('Completed', 't8-pm');
		}else{
			if($level == '1'){
				$message = __('Submit for Review', 't8-pm');
			}else{
				$message = __('Mark as Complete', 't8-pm');
			}
		}
		t8_pm_schedule( $t8_pm_proj_id );
	} else {
		$message = __('There was a problem submitting the task.', 't8-pm');
	}
	echo $message;
	die(); // this is required to return a proper result
}
/* Handle Notes Saving by task */
function t8_pm_notessave() {
	global $wpdb; // this is how you get access to the database

	$taskid = intval($_POST['taskid']);
	$notes = esc_html($_POST['notes']);
	$task_array = array(
		'notes' => $notes,
	);
	$resultstasks = $wpdb->update( $wpdb->prefix . 'pm_tasks', $task_array, array('id' => $taskid) );
	if ( $resultstasks ) {
		$message = __('Task notes were saved', 't8-pm');
	} else {
		$message = __('There was a problem saving the notes.', 't8-pm');
	}
	echo $message;
	die(); // this is required to return a proper result
}
/* Handle Notes Saving by task */
function t8_pm_cli_status() {
	global $wpdb; // this is how you get access to the database

	$cliid = intval($_POST['cliid']);
	$status = intval($_POST['status']);
	$task_array = array(
		'status' => $status,
	);
	$results = $wpdb->update( $wpdb->prefix . 'pm_cli', $task_array, array('id' => $cliid) );
	if ( $results ) {
		$message = __('Client was moved', 't8-pm');
		echo $message;
	}
	die(); // this is required to return a proper result
}

function t8_pm_today_task_flyout() {
	global $wpdb, $current_user;
	$wpdb->show_errors();
	if (! wp_verify_nonce($_POST['nonce'], 't8_pm_nonce') ){
		echo 'no noncense';
		die();
	}
	
	$proj = intval($_POST['proj']);
	$cliid = intval($_POST['cliid']);
	$ttid = intval($_POST['tid']);
	$stage = intval($_POST['stage']);
	$user = intval($_POST['user']);
	
	$proj_results = $wpdb->get_results('SELECT * FROM ' . $wpdb->prefix . 'pm_projects WHERE id = ' . $proj . '');
	$cli_name = $wpdb->get_results('SELECT name FROM ' . $wpdb->prefix . 'pm_cli WHERE id = ' . $proj_results[0]->cli_id . '');
	$tasks = $wpdb->get_results('SELECT * FROM ' . $wpdb->prefix . 'pm_tasks WHERE proj_id = ' . $proj . '');
	
	$t8_pm_proj_misc = unserialize($proj_results[0]->misc);
	$t8_pm_proj_mstones = $t8_pm_proj_misc['milestones']; // need this for milestone title

	if( isset( $tasks ) && !empty( $tasks ) ) { 
		$t8_pm_p_milestones = array(); // get ready to build the milestone array, tasks grouped by mstone
		foreach($tasks as $task) {
			if( $task->stage == 0 ) { 
				$mstone_title = 'General Tasks';
			} else {
				if( !empty( $t8_pm_proj_mstones ) ) $mstone_title = $t8_pm_proj_mstones[$task->stage]['name']; 
				else $mstone_title = $task->stage;
			} 
			$t8_pm_p_milestones[$task->stage]['tasks'][$task->id] = $task;
			$t8_pm_p_milestones[$task->stage]['mstone'] = $mstone_title;
			$t8_pm_p_milestones[$task->stage]['mstone'] = $mstone_title;
		}
		echo '<h3 class="th"><span>' . $cli_name[0]->name . '::' . $proj_results[0]->name . '<span>::' .$t8_pm_p_milestones[$stage]['mstone'] . '</h3>';
		?>
        <p><?php
        if( !empty( $t8_pm_proj_mstones ) ) $mstone_due = $t8_pm_proj_mstones[$stage]['deadline']; 
        else $mstone_due = $proj_results->end_date;
        echo 'Due: '.$mstone_due;
        ?><span class="rdts"><?php 
            $daysleft = ceil( (strtotime($mstone_due) - time())/(60*60*24) );
            echo $daysleft; 
        ?> days left</span></p>
		<p>Other Tasks In This Milestone</p>
		<?php
		echo '<div class="flyout-tasks sort">';
		//setup Milestone title
		foreach ($t8_pm_p_milestones[$stage]['tasks'] as $tid => $task) {
			$name = get_userdata($task->assign);
			if( $ttid != $tid ){
			?>
			<div class="dtask otask" data-proj-id="<?php echo $proj; ?>" data-stage="<?php echo $task->stage; ?>" data-id="<?php echo $task->id; ?>" data-type="task">
				<h3><span class="cli-span"><?php echo $cli_name[0]->name; ?></span>::<span class="proj-span"><?php echo $proj_results[0]->name; ?></span>::
				<span class="rdts"><?php 
					$task_due = ($task->due != '' ? $task->due : $proj_results->end_date);
					$daysleft = ceil( (strtotime($task_due) - time())/(60*60*24) );
					echo $daysleft; 
					?> days</span></h3>
				<p><?php echo $task->task_title; ?><span class="rdts"><?php echo $task->est_hours; ?> h est.</span>
				</p>
                <div class="x dact">X</div>
                <div class="send2pc dact">O</div>
                <div class="extras">
                    <span class="assign"><?php echo $name->display_name; ?></span>
                	<span class="mstone"><?php echo $mstone_title; ?></span>
                    <div class="task-status rdts">
						<?php 
                        $t8_pm_inreview_cbox = '<span>In Review</span> <input type="checkbox" name="review[]" checked class="t8-pm-task-status" value="'.$tid.'" />';
                        $t8_pm_submit_cbox = '<span>Submit for Review</span> <input type="checkbox" name="review[]" class="t8-pm-task-status" value="'.$tid.'" />';
                        $t8_pm_complete_cbox = ' <input type="checkbox" name="complete[]" class="t8-pm-task-status" value="'.$tid.'" />';
                        $t8_pm_uncomplete_cbox = ' <input type="checkbox" name="complete[]" checked class="t8-pm-task-status" value="'.$tid.'" />';
                        if( $task->stage == '0' ) { 
                            echo 'Ongoing';	
                        }elseif( $task->status == '0' ) { 
                            if( $task->proj-man == $current_user->ID ) { 
                                echo '<span>Mark as Complete</span>' . $t8_pm_complete_cbox;
                            }elseif( $task->assign  == $current_user->ID ) { 
                                echo $t8_pm_submit_cbox;
                            }else{
                                echo 'Incomplete';	
                            } 
                        }elseif( $task->status == '1' ) { 
                            if( $task->proj-man == $current_user->ID ) { 
                                echo '<span>Approve as Complete</span>' . $t8_pm_complete_cbox;
                            }else{
                                echo $t8_pm_inreview_cbox;	
                            }
                        }elseif( $task->status == '2' ) { 
                            if( $task->proj-man == $current_user->ID || $task->assign  == $current_user->ID ) { 
                                echo $t8_pm_uncomplete_cbox;
                            }
                            echo '<span>Completed</span>';
                        }else{ echo 'status: ' . $task->status; }
                    ?>
                    </div>
                </div>
			</div>            
		<?php 
			}
		}
	}/**/
	// echo '<pre>'; print_r($t8_pm_p_milestones); echo'</pre>'; 
    die();
}
/* Build proj and cli lists on cli change */
function t8_pm_find_dash_cli_tasks() {
   	global $wpdb, $userdata;
	$wpdb->show_errors();
		if (! wp_verify_nonce($_POST['nonce'], 't8_pm_nonce') ){
			die();
		}
		$newclient = intval($_POST['cli']);

		if($newclient!=''){ // if client field is not empty
			$table_name = $wpdb->prefix . 'pm_projects';
			$query = "SELECT id, name FROM ".$table_name." WHERE cli_id = ".$newclient;
			$results = $wpdb->get_results( $query );
			$addprojlist = '';
			$first_proj = 0;
			if($results){
				foreach($results as $result){
					$addprojlist .= '<option value="'.$result->id.'">'.$result->name.'</option>';
					if( !$first_proj ) $first_proj = $result->id;
				}
			}
			if( $first_proj ){
				$table_name = $wpdb->prefix . 'pm_tasks';
				$query = "SELECT id, task_title FROM ".$table_name." WHERE proj_id = ".$first_proj;
				$results1 = $wpdb->get_results( $query );
				$addtasklist = '';
				if($results1){
					foreach($results1 as $result){
						$addtasklist .= '<option'.$sel.' value="'.$result->id.'">'.$result->task_title.'</option>';
					}
				}
			}
		}
		if ( $results ) {
			$return['projs'] = $addprojlist;
			echo json_encode($return);
		}
		else echo $newclient;

		die();

}
/* Build proj and task lists on cli change */
function t8_pm_find_dash_proj_tasks() {
   	global $wpdb, $userdata;
	$wpdb->show_errors();
		if (! wp_verify_nonce($_POST['nonce'], 't8_pm_nonce') ){
			die();
		}
		$newproj = intval($_POST['proj']);

		if($newproj!=''){ // if client field is not empty
			$table_name = $wpdb->prefix . 'pm_tasks';
			$query = "SELECT id, task_title FROM ".$table_name." WHERE proj_id = ".$newproj;
			$results = $wpdb->get_results( $query );
			$addtasklist = 'huh';
			if($results){
				foreach($results as $result){
					$addtasklist .= '<option value="'.$result->id.'">'.$result->task_title.'</option>';
				}
			}

		}
		if ( $results ) {
			$return['tasks'] = $addtasklist;
			echo json_encode($return);
		}else{
			echo $newproj;
		}
		die();

}

/* Update Time table with orphan assigns and add their data to the dtask div */
function t8_pm_add_orphan_assign() {
   	global $wpdb;
	$wpdb->show_errors();
		if (! wp_verify_nonce($_POST['nonce'], 't8_pm_nonce') ){
			die();
		}
		//Client list
		$cli_results = $wpdb->get_results("SELECT id, name FROM ".$wpdb->prefix . 'pm_cli' ); // collect Client names and id
		if($cli_results){ foreach($cli_results as $client){ // build array with id as key
			$clients[$client->id]["name"] = $client->name;
		}}
		//Project Name list
		$proj_name_results = $wpdb->get_results("SELECT id, name FROM ".$wpdb->prefix . 'pm_projects' ); // collect Project names and id
		if($proj_name_results){ foreach($proj_name_results as $proj){
			$projnames[$proj->id]['name'] = $proj->name;
		}}
		
		$cli = (isset($_POST['cli']) ? intval($_POST['cli']) : '');
		$proj = (isset($_POST['proj']) ? intval($_POST['proj']) : '');
		$task = (isset($_POST['task']) ? intval($_POST['task']) : '');
		$desc = $_POST['desc'];
		$oass = intval($_POST['oass']);
		$date = date("Y-m-d H:i:s", strtotime($_POST['date']) );
		
		$time_array = array(
			'cli_id'	=> $cli,
			'proj_id'	=> $proj,
			'task_id'	=> $task,
			'notes'		=> $desc,
			'user_id'	=> $oass,
			'end_time'	=> $date
		);
		$format_array = array(
			'%d',
			'%d',
			'%d',
			'%s',
			'%d',
			'%d'
		);
		
		$timeresults = $wpdb->insert( $wpdb->prefix . 'pm_time', $time_array, $format_array );
		
		if($timeresults) {
			$return['id'] = $wpdb->insert_id;
			$return['cli_id'] = $time_array['cli_id'];
			$return['proj_id'] = $time_array['proj_id'];
			$return['task_id'] = $time_array['task_id'];
			$return['notes'] = $time_array['notes'];
			$return['user_id'] = $time_array['user_id'];
			$return['end_time'] = $time_array['end_time'];
			$return['cli_name'] = $clients[$time_array['cli_id']]['name'];
			$return['proj_name'] = $projnames[$time_array['proj_id']]['name'];
			echo json_encode($return);			
		} else {
			$message = 'There was a problem creating the assignment';
			echo $message;
		}
		die();
}

// Save Today's Tasks to usermeta
function t8_pm_update_todays_tasks() {
   	global $wpdb, $userdata;
	$wpdb->show_errors();
		if (! wp_verify_nonce($_POST['nonce'], 't8_pm_nonce') ){
			die();
		}
	//Client list
	$cli_results = $wpdb->get_results("SELECT * FROM ".$wpdb->prefix . 'pm_cli' ); // collect Client names and id
	if($cli_results){ foreach($cli_results as $client){ // build array with id as key
		$clients[$client->id]["name"] = $client->name;
	}}
	//Project Name list
	$proj_name_results = $wpdb->get_results("SELECT id, name FROM ".$wpdb->prefix . 'pm_projects' ); // collect Project names and id
	if($proj_name_results){ foreach($proj_name_results as $proj){
		$projnames[$proj->id]['name'] = $proj->name;
	}}
	
	$year = intval($_POST['year']);
	$day = intval($_POST['day']);
	$tasklist = $_POST['tasklist'];
	$assignlist = array_filter($_POST['assignlist']);
	
	$current = get_user_meta($userdata->ID, 'dayplanner', true);
	if( !is_array($current) ) $current = array();
	// NEED TO COMPARE THE SPECIFIC DAY, AND ONLY CHANGE THAT DAY, NOT OVERWRITE THE WHOLE PLANNER
	if( empty( $tasklist ) && empty( $assignlist ) ){
		unset(	$current[$year][$day] );						
	}else{
		if( !empty( $tasklist ) ) $current[$year][$day]['task'] = $tasklist;
		if( !empty( $assignlist ) ) $current[$year][$day]['assign'] = $assignlist;		
	}

	update_user_meta( $userdata->ID, 'dayplanner', $current );

	// so check and make sure the stored value matches $new_value
	if ( get_user_meta($userdata->ID,  'dayplanner', true ) != $current ) {
		print_r($current); 
		wp_die('no way jose ');
	}else{
		print_r($current);
	}

	die();
}
	//come back to this. need to create an array of the tasks/assigns with ids and types, stored under a datestamp

function t8_pm_install () {
	global $wpdb;
	global $t8_pm_db_version;
	$t8_pm_db_version = "1.0";

	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

	$table_name = $wpdb->prefix . "pm_tasks"; 
	$sql2 = "CREATE TABLE " . $table_name . " (
		id mediumint(9) NOT NULL AUTO_INCREMENT,
		task_title VARCHAR(55) NOT NULL,
		cli_id mediumint(9) NOT NULL,
		proj_id mediumint(9) NOT NULL,
		assign text NOT NULL,
		stage tinyint(4) DEFAULT '0' NOT NULL,
		est_hours decimal(10,2) NOT NULL,
		status tinyint(4) DEFAULT '0' NOT NULL,
		due date NULL,
		UNIQUE KEY id (id)
	);";
// !!! task_title and task_desc should change to name and description
	dbDelta( $sql2 );

	$table_name = $wpdb->prefix . "pm_time"; 

	$sql = "CREATE TABLE " .$table_name. " (
		id mediumint(9) NOT NULL AUTO_INCREMENT,
		cli_id mediumint(9) NOT NULL,
		proj_id mediumint(9) NOT NULL,
		task_id mediumint(9) NOT NULL,
		user_id mediumint(9) NOT NULL,
		start_time datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
		end_time datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
		hours decimal(10,2) NOT NULL,
		description longtext NOT NULL,
		UNIQUE KEY id (id)
	);";
	dbDelta( $sql );

	$table_name = $wpdb->prefix . "pm_projects"; 
	$sql3 = "CREATE TABLE " .$table_name. " (
		id mediumint(9) NOT NULL AUTO_INCREMENT,
		name longtext NOT NULL,
		cli_id mediumint(9) NOT NULL,
		est_hours decimal(10,2) NOT NULL,
		status tinyint(4) DEFAULT '0' NOT NULL,
		start_date date NULL,
		end_date date NULL,
		proj_manager mediumint(9) NOT NULL,
		price bigint(20) NOT NULL,
		misc longtext NOT NULL,
		schedule longtext NOT NULL,
		UNIQUE KEY id (id)
	);";
	dbDelta( $sql3 );
	/*

	*/

	$table_name = $wpdb->prefix . "pm_cli"; 
	$sql4 = "CREATE TABLE " .$table_name. " (
		id mediumint(9) NOT NULL AUTO_INCREMENT,
		name longtext NOT NULL,
		status tinyint(4) DEFAULT '0' NOT NULL,
		UNIQUE KEY id (id)
	);";
	dbDelta( $sql4 );


	add_option( "t8_pm_db_version", $t8_pm_db_version );

}