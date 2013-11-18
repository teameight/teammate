<?php
/*
	Plugin Name: Team Eight Project Management - Alpha
	Description: Alpha release on Nov 10th 2013!
	Version: 1.0
	Author: Andrew Mowe, Spencer Hansen
	Author URI: http://www.team-eight.com
	License: GPLv2
*/
// include the functions file
include_once("t8-teammate-functions.php");

function t8_pm_create_menu() {
	$menu = add_menu_page( 'Teammate Project Management', 'Teammate', 'edit_posts', __FILE__, 't8_pm_settings_page' ); // create the menu and dashboard page
	$submenu_proj = add_submenu_page( __FILE__, 'Teammate Projects', 'Projects', 'edit_posts', __FILE__.'_projects', 't8_pm_projects_page' ); // projects subpage
	$submenu_capacity = add_submenu_page( __FILE__, 'Teammate Capacity', 'Capacity', 'edit_posts', __FILE__.'_capacity', 't8_pm_capacity_page' ); // projects subpage
	$submenu_reports = add_submenu_page( __FILE__, 'Teammate Reports', 'Reports', 'edit_posts', __FILE__.'_reports', 't8_pm_reports' ); // reports subpage, LATER:customize capability to plugin specific
	$submenu_mgmt = add_submenu_page( __FILE__, 'Teammate Mgmt', 'Mgmt', 'edit_posts', __FILE__.'_mgmt', 't8_pm_mgmt_page' ); // departments subpage, LATER:customize capability to plugin specific

   /* load our stylesheets and scripts on each page */
	add_action( 'admin_print_styles-' . $menu, 't8_pm_dash_scripts' );
	add_action( 'admin_print_styles-' . $submenu_mgmt, 't8_pm_admin_scripts' );
	add_action( 'admin_print_styles-' . $submenu_capacity, 't8_pm_admin_scripts' );
	add_action( 'admin_print_styles-' . $submenu_reports, 't8_pm_admin_scripts' );
	add_action( 'admin_print_styles-' . $submenu_proj, 't8_pm_admin_scripts' );

	add_action( 'admin_head-'. $menu, 't8_pm_styles' );
	add_action( 'admin_head-'. $submenu_mgmt, 't8_pm_styles' );
	add_action( 'admin_head-'. $submenu_capacity, 't8_pm_styles' );
	add_action( 'admin_head-'. $submenu_reports, 't8_pm_styles' );
	add_action( 'admin_head-'. $submenu_proj, 't8_pm_styles' );
}
add_action( 'admin_menu', 't8_pm_create_menu' );

// install script
register_activation_hook( __FILE__, 't8_pm_install' );

// enque scripts and styles and other page prep
function t8_pm_all_page_scripts() {
	wp_register_style( 'TeammateStylesheet', plugins_url('taid.css', __FILE__) );
	wp_enqueue_style( 'TeammateStylesheet' );
	wp_enqueue_script( 'jquery-ui-datepicker' );
	wp_enqueue_script( 'jquery-ui-sortable' );
	wp_enqueue_script( 'jquery-ui-button' );
	wp_enqueue_script( 'jquery-ui-core' );
	wp_enqueue_script( 'jquery-ui-widget' );
	wp_enqueue_script( 'jquery-ui-position' );
	wp_enqueue_script( 'jquery-ui-autocomplete' );
	wp_enqueue_script( 'jquery-ui-tooltip', plugins_url( 'js/jquery.ui.tooltip.js' , __FILE__ ));
	wp_enqueue_script( 'pm-spencer', plugins_url( 'js/pm-spencer.js' , __FILE__ ) );
}
function t8_pm_all_page_styles() {
	wp_enqueue_style( 'jquery.ui.theme', plugins_url( '/smoothness/jquery-ui.css', __FILE__ ) );
}
function t8_pm_admin_scripts() {
	t8_pm_all_page_scripts();
	t8_pm_all_page_styles();
}
function t8_pm_dash_scripts() {
    add_action('wp_print_styles', 'load_fonts'); // !!! read up on this, do we still need this?
	t8_pm_all_page_scripts();
	wp_enqueue_script( 'easypiechart', plugins_url( 'js/jquery.easypiechart.js' , __FILE__ ) );
	wp_enqueue_script( 'pm-dashboard', plugins_url( 'js/pm-dashboard.js' , __FILE__ ) );
	t8_pm_all_page_styles();
}

// build styles for custom user colors
function t8_pm_styles(){
	global $pm_users;

	echo '<style type="text/css">'."\n";
	echo ".wp-list-table .column-title { \n\twidth: 30%; \n}\n";
	echo ".wp-list-table .column-hours { \n\twidth: 15%; \n}\n";
	echo ".wp-list-table .column-start_date, \n.wp-list-table .column-end_date { \n\twidth: 18%; \n}\n";
	if( isset($pm_users) ){
		foreach( $pm_users as $user_id => $user ) { 
			$ucolor = $user['color'];
			if( $ucolor && $ucolor != '' ){
				$uslug = $user['uslug'];
				echo ".bar.user-$uslug, \n.bar div.user-$uslug { \n\tbackground-color: #$ucolor ; \n}\n";
				echo "li .user-$uslug, \n.wp-list-table tr.user-$uslug > td:first-child { \n\tborder-left:4px solid #$ucolor ; \n}\n";
			}
		}
	}
	echo '</style>';
}


// individual page functions
function t8_pm_settings_page() {
	include_once("t8-dashboard.php");
} // end func t8_pm_settings_page

function t8_pm_mgmt_page() { 
	include_once("t8-mgmt.php");
} // end func t8_pm_departments_page

function t8_pm_capacity_page() { 
	include_once("t8-capacity.php");
} // end func t8_pm_departments_page


function t8_pm_projects_page() {
	include_once("t8-projects.php");
} // end func t8_pm_projects_page

function t8_pm_reports() { 
	// include files for Reports List Class
	if( ! class_exists( 'WP_List_Table' ) ) {
	    require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
	}
	include_once( plugin_dir_path(__FILE__).'reports/t8-time-class-list-table.php' );
?>
	<div class="wrap t8-pm">
		<?php if(isset($t8_pm_warning)){ ?><div class="error"><?php echo $t8_pm_warning; ?></div><?php } ?>
		<?php if(isset($t8_pm_updated)){ ?><div class="updated"><?php echo $t8_pm_updated; ?></div><?php } ?>
		<?php 
			$t8_pm_timetable = new t8_pm_Time_Table();

			$start = ( !empty( $_GET['start'] ) ? date( 'Y/m/d', strtotime( $_GET['start'] ) ) : date('Y/m/01') );
			$end = ( !empty( $_GET['end'] ) ? date( 'Y/m/d', strtotime( $_GET['end'] ) ) : date('Y/m/d') );
			wp_nonce_field('t8_pm_nonce','t8_pm_nonce');
			?>
			<h2>Reports
				<form action="<?php echo admin_url( 'admin.php' ); ?>" method="get" >
					<input type="text" class="smDtPicker"  name="start" id="dp-start" value="<?php echo $start; ?>" />
					<input type="text" class="smDtPicker"  name="end" id="dp-end" value="<?php echo $end; ?>" />
					<input type="hidden" name="page" value="<?php echo $_GET["page"]; ?>" />
					<?php if( isset($_GET["orderby"]) ) echo '<input type="hidden" name="orderby" value="'. $_GET["orderby"] .'" />'; ?>
					<?php if( isset($_GET["user"]) ) echo '<input type="hidden" name="user" value="'. $_GET["user"] .'" />'; ?>
	                <input type="submit" value="G0">
                </form>
			</h2> 
			<?php
			$t8_pm_timetable->views(); 
			$t8_pm_timetable->prepare_items(); 
			$t8_pm_timetable->display(); 
		?>
	</div>
	<?php
} // end func t8_pm_reports
//eof