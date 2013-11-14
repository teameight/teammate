<?php

/*
* Projects List Table class.
* Extends the WP core class: WP_List_Table
*
* @subpackage List_Table
* @subpackage List_Table
* @ http://core.trac.wordpress.org/browser/tags/3.4/wp-admin/includes/class-wp-list-table.php
*
*/

class t8_pm_Project_Table extends WP_List_Table {

	function __construct(){
		global $status, $page;
		add_action( 'admin_head', array( &$this, 'admin_header' ) );

		parent::__construct( array(
			'singular'  => __( 'project', 't8_pm_projtable' ),     //singular name of the listed records
			'plural'    => __( 'projects', 't8_pm_projtable' ),   //plural name of the listed records
			'ajax'      => false        //does this table support ajax?
		) );
		$this->nonce = wp_create_nonce( 't8-pm-nonce' );
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
	function project_data(){
		global $wpdb, $pm_users;
		// If no status, default to current
		/*
		Status: 
			0 - PROPOSALS/INACTIVE - on the books, but not yet accepted, or on hold
			1 - ACTIVE - All active projects
			2 - ARCHIVED - For completed projects, though may not be completely paid yet.
			3 - TRASH - Proposals and projects that need to be deleted. There will be a function to empty trash, permanently deleting these.
		*/
		// If no status, default to 1 for current
		$status = '1';
		if( isset( $_GET['proj_status'] ) ) $status = intval( $_GET['proj_status'] );
		// If no sort, default to title
		$orderby = 'end_date';
		if( isset( $_GET['orderby'] ) ) $orderby = $_GET['orderby'];
		// If no order, default to asc
		$order = 'ASC';
		if( isset($_GET['order'] ) ) $order = $_GET['order'];
		$cli_ids = $projects_r = $proj_ids = array();
		$proj_results = $wpdb->get_results("SELECT * FROM ".$wpdb->prefix . 'pm_projects WHERE status = '.$status.' ORDER BY '. $orderby .' '.$order ); // collect Projects based on Query	
		if($proj_results){ 
			foreach($proj_results as $project){ // build array with id as key
				// !!! need to remove uneeded items from array
				$projects_r[$project->id]["ID"] = $project->id;
				$projects_r[$project->id]["title"] = $project->name;
				$projects_r[$project->id]["cli_id"] = $project->cli_id;
				$projects_r[$project->id]["hours"] = $t8_pm_tots_hours[] = $project->est_hours;
				$projects_r[$project->id]["status"] = $project->status;
				$projects_r[$project->id]["start_date"] = date('D M jS, Y', strtotime($project->start_date) );
				$projects_r[$project->id]["end_date"] = date('D M jS, Y', strtotime($project->end_date ) );
				$projects_r[$project->id]["price"] = $t8_pm_tots_price[] = '$'.$project->price;
				$projects_r[$project->id]["proj_manager"] = ($pm_users ? $pm_users[$project->proj_manager]['uname'] : $project->proj_manager );
				$projects_r[$project->id]["class"] = ($pm_users ? 'user-'.$pm_users[$project->proj_manager]['uslug'] : 'user-'.$project->proj_manager );
				$cli_ids[] = $project->cli_id;
				$proj_ids[] = $project->id;
			}
		}
		// used Client ID list from proj query to get client names and write into to proj array, added to title td
		if( !empty($cli_ids) ){
			$cli_results = $wpdb->get_results("SELECT id, name FROM ".$wpdb->prefix . "pm_cli WHERE id IN(" . implode(',', $cli_ids).")" ); 
			if($cli_results){
				$cli_names = array();
				foreach($cli_results as $cli){
					$cli_names[$cli->id] = $cli->name;
				}
				foreach($projects_r as $proj_id => $proj ){
					$projects_r[$proj_id]['title'] = $cli_names[$proj['cli_id']].'::'.$projects_r[$proj_id]['title'];
				}
			}
		}
		// used proj IDs from proj query to get all punched time and write into to proj array, in the hours td
		if( !empty($proj_ids) ){
			
			$proj_results = $wpdb->get_results("SELECT proj_id, hours FROM ".$wpdb->prefix . "pm_time WHERE proj_id IN(" . implode(',', $proj_ids).")" ); // collect Projects based on Query	
			if($proj_results){
				$proj_pnchd = array();
				foreach($proj_results as $proj){
					if( !isset($proj_pnchd[$proj->proj_id]) ) $proj_pnchd[$proj->proj_id] = 0;
					$proj_pnchd[$proj->proj_id] += $proj->hours;
				}
				if($proj_pnchd){
					foreach($projects_r as $proj_id => $proj ){
						if( isset( $proj_pnchd[$proj_id] ) ) {
							$proj_perc = ( $projects_r[$proj_id]['hours'] ? round( 100 * ( $proj_pnchd[$proj_id] / $projects_r[$proj_id]['hours'] ) , 2) : 0 );
							$projects_r[$proj_id]['hours'] = round($proj_pnchd[$proj_id], 2) . ' of ' . $projects_r[$proj_id]['hours'] . ' est';
							$projects_r[$proj_id]['hours'] .= "\n<div class=\"t8-pm-pbar\">";
							$projects_r[$proj_id]['hours'] .= "\n\t <div style=\"width:  $proj_perc%\">$proj_perc%</div>";
							$projects_r[$proj_id]['hours'] .= "\n</div>";
						} else {
							$projects_r[$proj_id]['hours'] .= ' est';
						}
					}
				}
			}
		}
		
		return $projects_r;
	}

  function column_default( $item, $column_name ) {
    switch( $column_name ) { 
        case 'title':
        case 'start_date':
        case 'end_date':
        case 'hours':
        case 'price':
        case 'proj_manager':
            return $item[ $column_name ];
        default:
            return print_r( $item, true ) ; //Show the whole array for troubleshooting purposes
    }
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
		$current = ( isset($_REQUEST['proj_status']) ? $_REQUEST['proj_status'] : '1');
		
		//Proposal link
		$prop_url = add_query_arg('proj_status','0');
		$class = ($current == '0' ? 'current' :'proposals');
		$views['proposals'] = "<a href=\"{$prop_url}\" class=\"{$class}\" >Proposals</a>";
		
		//Active link
		$class = ($current == '1' ? 'current' :'active');
		$active_url = remove_query_arg('proj_status');
		$views['active'] = "<a href=\"{$active_url }\" class=\"{$class}\" >Active</a>";
		
		//Archived link
		$arch_url = add_query_arg('proj_status','2');
		$class = ($current == '2' ? 'current' :'archived');
		$views['archived'] = "<a href=\"{$arch_url}\" class=\"{$class}\" >Archived</a>";

		//Trash link
		$trash_url = add_query_arg('proj_status','3');
		$class = ($current == '3' ? 'current' :'trash');
		$views['trash'] = "<a href=\"{$trash_url}\" class=\"{$class}\" >Trash</a>";
		
		return $views;
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

		echo '<tr id="projrow-'. $item['ID'] .'" class="' . $row_class . ' ' . $item['class'] . '">';
//		echo '<pre>'; print_r($item); echo '</pre>';
		$this->single_row_columns( $item );
		echo '</tr>';
	}
		
	function get_sortable_columns() {
	  $sortable_columns = array(
		'title' 			=> array('title',false),
		'start_date'    => array('start_date',false),
		'end_date'    => array('end_date',false),
		'price'      	=> array('price',false),
		'est_hours'     => array('hours',false),
		'proj_manager'  => array('proj_manager',false)
	  );
	  return $sortable_columns;
	}
	function get_columns(){
		$columns = array(
			'title' => __( 'Title', 't8_pm_projtable' ),
			'start_date'    => __( 'Start', 't8_pm_projtable' ),
			'end_date'    => __( 'End', 't8_pm_projtable' ),
			'price'      => __( 'Budget', 't8_pm_projtable' ),
			'hours'      => __( 'Hours', 't8_pm_projtable' ),
			'proj_manager'      => __( 'Lead', 't8_pm_projtable' )
		);
		 return $columns;
	}
	function prepare_items() {
		$columns  = $this->get_columns();
		$hidden   = array();
		$sortable = $this->get_sortable_columns();
		$this->_column_headers = array( $columns, $hidden, $sortable );
		$this->items = $this->project_data();
		//Retrieve $customvar for use in query to get items.
     	//$customvar = ( isset($_REQUEST['customvar']) ? $_REQUEST['customvar'] : 'all');
		$proj_status = ( isset($_REQUEST['proj_status']) ? $_REQUEST['proj_status'] : 'active');
	}
	function column_title($item) {
	  $actions = array(
				'view'      => sprintf('<a title="%s" href="?page=%s&action=%s&project=%s">View</a>','Manage this project',$_REQUEST['page'],'view',$item['ID'] ),
				'edit'      => sprintf('<a title="%s" href="?page=%s&action=%s&project=%s">Edit</a>','Edit this project',$_REQUEST['page'],'edit',$item['ID'] ),
				'duplicate'      => sprintf('<a title="%s" href="?page=%s&action=%s&project=%s">Duplicate</a>','Create a new project based on this one',$_REQUEST['page'],'duplicate',$item['ID'] ),
				'trash'    => sprintf('<a class="trashproj" data-proj="%s" data-nonce="%s" title="%s" href="?page=%s&action=%s&project=%s">Trash</a>',$item['ID'],$this->nonce,'Move this project to the trash',$_REQUEST['page'],'trash',$item['ID']  ),
			);
	
	  return sprintf('%1$s %2$s', $item['title'], $this->row_actions($actions) );
	}

} //class t8_pm_Project_Table
//eof