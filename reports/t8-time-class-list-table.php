<?php

/*
* Time List Table class.
* Extends the WP core class: WP_List_Table
*
* @subpackage List_Table
* @subpackage List_Table
* @ http://core.trac.wordpress.org/browser/tags/3.4/wp-admin/includes/class-wp-list-table.php
*
*/

class t8_pm_Time_Table extends WP_List_Table {

	function __construct(){
		global $status, $page, $pm_users;
		add_action( 'admin_head', array( &$this, 'admin_header' ) );

		parent::__construct( array(
			'singular'  => __( 'entry', 't8_pm_timetable' ),     //singular name of the listed records
			'plural'    => __( 'entries', 't8_pm_timetable' ),   //plural name of the listed records
			'ajax'      => true        //does this table support ajax?
		) );

		$this->pm_users = $pm_users;

    }

	function admin_header() {
		$page = ( isset($_REQUEST['page'] ) ) ? esc_attr( $_REQUEST['page'] ) : false;
	//	if( 't8-teammate%2Ft8-teammate.php_projects2' != $page ) // !!! this is wrong, need to figure out how to check for submenu page
	//	return; 
		
		echo '<style type="text/css">';
//		echo '.wp-list-table .column-id { width: 5%; }';
		echo '.wp-list-table .column-title { width: 45%; }';
		echo '</style>';
	}
	function time_data(){
		global $wpdb, $current_user;
		$current_user = wp_get_current_user();

		// If no status, default to current
		/*
		Status: 
			1 - PROPOSALS/INACTIVE - on the books, but not yet accepted, or on hold
			2 - CURRENT - All active projects
			3 - ARCHIVED - For completed projects, though may not be completely paid yet.
			4 - TRASH - Proposals and projects that need to be deleted. There will be a function to empty trash, permanently deleting these.
		*/
		// If no status, default to 2 for current
		$start = ( !empty( $_GET['start'] ) ? date( 'Y-m-d', strtotime( $_GET['start'] ) ) : date('Y-m-01') );
		$end = ( !empty( $_GET['end'] ) ? date( 'Y-m-d', strtotime( $_GET['end'] ) ) : date('Y-m-d') );
		// If no sort, default to date
		$orderby = ( !empty( $_GET['orderby'] ) ) ? $_GET['orderby'] : 'start_time'; // !!! need to sanitaze this
		if( $orderby == 'assign' ) $orderby = 'user_id'; // !!! this should be changed on the database to assign
		// If no user, default to none
		$user = ( !empty( $_GET['user'] ) && is_numeric( $_GET['user'] ) ) ? "AND user_id = '" . intval( $_GET['user'] ) . "' " : '';

		$cli_ids = $time_r = $proj_ids = $task_ids = array();
		$time_results = $wpdb->get_results("SELECT * FROM ".$wpdb->prefix . "pm_time WHERE DATE(start_time) BETWEEN '" . $start . "' AND '" . $end . "' " . $user . "ORDER BY $orderby DESC" ); // collect Time based on Query	
		if($time_results){ 

			$submit = sprintf('<a class="savetime" title="Save these edits" href="?page=%s&action=%s">Save</a>', $_REQUEST['page'], 'savetime' );
			$cancel = sprintf('<a class="cancel" title="Cancel editing" href="?page=%s&action=%s">Cancel</a>', $_REQUEST['page'], 'delete'  );
			$cpt_sels = t8_pm_cli_proj_task_selects( 1 );
			$assign = '<select class="assign">';
            $assign .= t8_pm_assign_select( $current_user->ID, true );
            $assign .= '</select>';

			// build the first row as a blank edit form
			$time_r['edit'] = array(
				"ID" => 'edit',
				"cli" => '<select class="clisel">' . $cpt_sels['cli'] . '</select>',
				"proj" => '<select class="projsel" disabled="disabled"><option>Project...</option></select>',
				"task" => '<select class="tasksel" disabled="disabled"><option>Task...</option></select>', 
				"task_id" 	=> 0,
				"cli_id" 	=> 0,
				"proj_id"	=> 0,
				"user_id" 	=> 0,
				"assign" => $assign,
				"hours" => '<input required type="text" name="hours" class="hours" value="">',
				"notes" => '<input required type="text" name="notes" class="notes" placeholder="notes" value="">',
				"date" => '<input required type="text" name="date" class="date smDtPicker" value="'. date('Y/m/d') .'">',
				"actions" => "$submit | $cancel",
				"class" => 'editrow time-new'
			);

			foreach( $time_results as $time ){ // build array with id as key

				$disp_hours = '<span class="start" data-start="' . strtotime( $time->start_time ) .'">';
				$disp_hours .= $time->hours;
				$disp_hours .= '</span>';
				$edit = sprintf('<a class="edittime" data-time="%s" title="Edit this time entry" href="?page=%s&action=%s&time=%s">Edit</a>',$time->id,$_REQUEST['page'],'edit',$time->id );
				$delete = sprintf('<a class="deltime" data-time="%s" title="Delete this time entry" href="?page=%s&action=%s&time=%s">Delete</a>',$time->id,$_REQUEST['page'],'delete',$time->id  );

				$time_r[$time->id] = array(
					// vars for specific later use
					"ID" 		=> $time->id,
					"task_id" 	=> $time->task_id,
					"cli_id" 	=> $time->cli_id,
					"proj_id"	=> $time->proj_id,
					"user_id" 	=> $time->user_id,
					"class" 	=> ( $this->pm_users ? $this->pm_users[$time->user_id]['uslug'] : $time->user_id ),
					"start" 	=> $time->start_time,
					"end" 		=> $time->end_time,
					// for table columns
					"cli" 	=> $time->cli_id,
					"proj" 	=> $time->proj_id,
					"task" 	=> $time->task_id, 
					"assign" => ($this->pm_users ? $this->pm_users[$time->user_id]['uname'] : $time->user_id ),
					"hours" => $disp_hours,
					"notes" => $time->description,
					"date" => date('Y/m/d', strtotime($time->start_time) ),
					"actions" => "$edit | $delete"
				);

				$cli_ids[] = $time->cli_id;
				$proj_ids[] = $time->proj_id;
				$task_ids[] = $time->task_id; 

			}
		}
		// used Client ID list from proj query to get client names and write into title td
		if( !empty($cli_ids) ){
			$cli_results = $wpdb->get_results("SELECT id, name FROM ".$wpdb->prefix . "pm_cli WHERE id IN(" . implode(',', $cli_ids).")" ); 
			if($cli_results){
				$cli_names = array();
				foreach($cli_results as $cli){
					$cli_names[$cli->id] = $cli->name;
				}
				foreach($time_r as $time_id => $time ){
					if( $time_id != 'edit' ) $time_r[$time_id]['cli'] = $cli_names[$time['cli_id']].'::';
				}
			}
		}
		// used proj IDs from proj query to get names and write into title td
		if( !empty($proj_ids) ){
			$proj_results = $wpdb->get_results("SELECT id, name FROM ".$wpdb->prefix . "pm_projects WHERE id IN(" . implode(',', $proj_ids).")" ); 
			if($proj_results){
				$proj_names = array();
				foreach($proj_results as $proj){
					$proj_names[$proj->id] = $proj->name;
				}
				foreach($time_r as $time_id => $time ){
					if( $time_id != 'edit' ) $time_r[$time_id]['proj'] = $proj_names[$time['proj_id']].'::';
				}
			}
		}
		// used task IDs from task query to get names and write into title td
		if( !empty($task_ids) ){
			$task_results = $wpdb->get_results("SELECT id, task_title FROM ".$wpdb->prefix . "pm_tasks WHERE id IN(" . implode(',', $task_ids).")" ); 
			if($task_results){
				$task_names = array();
				foreach($task_results as $task){
					$task_names[$task->id] = $task->task_title;
				}
				foreach($time_r as $time_id => $time ){
					if( $time_id != 'edit' ) $time_r[$time_id]['task'] = $task_names[$time['task_id']];
				}
			}
		}
		
		return $time_r;
	}

	/**
	 * Generates content for a single row of the table
	 *
	 * @since 3.1.0
	 * @access protected
	 *
	 * @param object $item The current item
	 */
	function single_row( $item ) {
		static $row_class = '';
		$row_class = ( $row_class == '' ? 'alternate' : '' );

		if( $item['ID'] == 'edit' ) {
			echo '<tr class="' . ' ' . $item['class'] . '">';
		}else{
			echo '<tr data-time="'. $item['ID'] .'" id="time-'. $item['ID'] .'" class="' . $row_class . ' ' . $item['class'] . '">';
		}
//		echo '<pre>'; print_r($item); echo '</pre>';
		
		list( $columns, $hidden ) = $this->get_column_info();
        foreach ( $columns as $column_name => $column_display_name ) {
        	$class = "class=\"$column_name column-$column_name\"";
        	switch ( $column_name ) {
        		case 'cli': 
        			$cli_id = $item['cli_id'];
        			echo "<td $class data-cli=\"$cli_id\" >";
        			echo $this->column_default( $item, $column_name );
	                echo "</td>";
				break;
        		case 'proj': 
        			$proj_id = $item['proj_id'];
        			echo "<td $class data-proj=\"$proj_id\" >";
        			echo $this->column_default( $item, $column_name );
	                echo "</td>";
				break;
        		case 'task': 
        			$task_id = $item['task_id'];
        			echo "<td $class data-task=\"$task_id\" >";
        			echo $this->column_default( $item, $column_name );
	                echo "</td>";
				break;
        		case 'assign': 
        			$user_id = $item['user_id'];
        			echo "<td $class data-assign=\"$user_id\" >";
        			echo $this->column_default( $item, $column_name );
	                echo "</td>";
				break;
        		default: 
        			$task_id = $item['task_id'];
        			echo "<td $class >";
        			echo $this->column_default( $item, $column_name );
	                echo "</td>";
				break;
			}
		}
//		$this->single_row_columns( $item );
		echo '</tr>';
	}

  function column_default( $item, $column_name ) {
    switch( $column_name ) { 
        case 'cli':
        case 'proj':
        case 'task':
        case 'assign':
        case 'hours':
        case 'notes':
        case 'date':
        case 'actions':
            return $item[ $column_name ];
        default:
            return print_r( $item, true ) ; //Show the whole array for troubleshooting purposes
    }
  }
	function get_sortable_columns() {
	  $sortable_columns = array(
		'cli'    	=> array('cli_id',false),
		'proj'   	=> array('proj_id',false),
		'task'     	=> array('task_id',false),
		'assign' 	=> array('user_id',false),
		'hours'     => array('hours',false),
		'date' 	 	=> array('start_time',false)
	  );
	  return $sortable_columns;
	}
	function get_columns(){
		$columns = array(
			'cli'    => __( 'Client', 't8_pm_timetable' ),
			'proj'      => __( 'Project', 't8_pm_timetable' ),
			'task'      => __( 'Task', 't8_pm_timetable' ),
			'assign'    => __( 'Assign', 't8_pm_timetable' ),
			'hours'      => __( 'Hours', 't8_pm_timetable' ),
			'notes'      => __( 'Notes', 't8_pm_timetable' ),
			'date' => __( 'Date', 't8_pm_timetable' ),
			'actions' => __( '', 't8_pm_timetable' )
		);
		 return $columns;
	}
	/*
	* Get an associative array ( id => link ) with the list
	 * of views available on this table.
	 *
	 * @since 3.1.0
	 * @access protected
	 *
	 * @return array
	 */
	function get_views(){
		$views = array();
		$current = ( !empty($_REQUEST['user']) ? $_REQUEST['user'] : 'all');
		if( !empty($pm_users) ){
			foreach ($this->pm_users as $uid => $user) {
				$add2url = add_query_arg('user', $uid);
				$class = ($current == $uid ? 'current' : $user['uslug'] );
				$views[$user['uslug']] = "<a href=\"{$add2url}\" class=\"{$class}\" >" . $user['uname'] . "</a>";
			}
		}
		return $views;
	}
	function prepare_items() {
		$columns  = $this->get_columns();
		$hidden   = array();
		$sortable = $this->get_sortable_columns();
		$this->_column_headers = array( $columns, $hidden, $sortable );
		$this->items = $this->time_data();
		//Retrieve $customvar for use in query to get items.
		$user = ( isset($_REQUEST['user']) ? $_REQUEST['user'] : 'active');
	}
} //class t8_pm_Time_Table
//eof