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
//	$submenu_plan = add_submenu_page( __FILE__, 'Teammate Planner', 'Planner', 'manage_options', __FILE__.'_planner', 't8_pm_planner_page' ); // planner subpage
   /* load our stylesheet on each page */
	add_action( 'admin_print_styles-' . $menu, 't8_pm_dash_scripts' );
	add_action( 'admin_print_styles-' . $submenu_mgmt, 't8_pm_admin_scripts' );
	add_action( 'admin_print_styles-' . $submenu_capacity, 't8_pm_admin_scripts' );
	add_action( 'admin_print_styles-' . $submenu_reports, 't8_pm_admin_scripts' );
	add_action( 'admin_print_styles-' . $submenu_proj, 't8_pm_admin_scripts' );
//	add_action( 'admin_print_styles-' . $submenu_plan, 't8_pm_admin_scripts' );
}
add_action( 'admin_menu', 't8_pm_create_menu' );
register_activation_hook( __FILE__, 't8_pm_install' );


function t8_pm_admin_scripts() {
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
	// wp_enqueue_script( 'combobox', plugins_url( 'js/combobox.js' , __FILE__ ), array('jquery-ui-core', 'jquery-ui-autocomplete', 'jquery-ui-widget', 'jquery-ui-position', 'jquery-ui-button', 'jquery-ui-tooltip') );
	wp_enqueue_script( 'pm-spencer', plugins_url( 'js/pm-spencer.js' , __FILE__ ) );
	wp_enqueue_script( 'jquery-ui-autocomplete' );
	wp_enqueue_style( 'jquery.ui.theme', plugins_url( '/smoothness/jquery-ui.css', __FILE__ ) );
}
function t8_pm_dash_scripts() {
    add_action('wp_print_styles', 'load_fonts');
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
	// wp_enqueue_script( 'combobox', plugins_url( 'js/combobox.js' , __FILE__ ), array('jquery-ui-core', 'jquery-ui-autocomplete', 'jquery-ui-widget', 'jquery-ui-position', 'jquery-ui-button', 'jquery-ui-tooltip') );
	wp_enqueue_script( 'easypiechart', plugins_url( 'js/jquery.easypiechart.js' , __FILE__ ) );
	wp_enqueue_script( 'pm-spencer', plugins_url( 'js/pm-spencer.js' , __FILE__ ) );
	wp_enqueue_script( 'pm-dashboard', plugins_url( 'js/pm-dashboard.js' , __FILE__ ) );
	wp_enqueue_script( 'jquery-ui-autocomplete' );
//	wp_register_style('googleFonts', 'http://fonts.googleapis.com/css?family=Oswald:300'); //serving this manually in the style sheet to aviod horiz truncate bug for users with font
//	wp_enqueue_style( 'googleFonts');
	wp_enqueue_style( 'jquery.ui.theme', plugins_url( '/smoothness/jquery-ui.css', __FILE__ ) );
}


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

function t8_pm_reports() { ?>
	<div class="wrap t8-pm">
		<?php if(isset($t8_pm_warning)){ ?><div class="error"><?php echo $t8_pm_warning; ?></div><?php } ?>
		<?php if(isset($t8_pm_updated)){ ?><div class="updated"><?php echo $t8_pm_updated; ?></div><?php } ?>
		<?php 
			$t8_pm_timetable = new t8_pm_Time_Table();

			$start = ( !empty( $_GET['start'] ) ? date( 'Y/m/d', strtotime( $_GET['start'] ) ) : date('Y/m/01') );
			$end = ( !empty( $_GET['end'] ) ? date( 'Y/m/d', strtotime( $_GET['end'] ) ) : date('Y/m/d') );
			if ( function_exists('wp_nonce_field') ) wp_nonce_field('t8_pm_nonce','t8_pm_nonce');
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

/*function t8_pm_planner_page() {
	include_once("t8-planner.php");
} // end func t8_pm_planner_page
*/ 
//eof