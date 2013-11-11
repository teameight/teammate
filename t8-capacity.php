<?php
if( !isset($t8_pm_warning) ) $t8_pm_warning = '';
$t8_pm_warning .= "<p>ITS ALIVE!</p>";
global $wpdb; //set ur globals
include_once( plugin_dir_path(__FILE__).'t8-lists.php' );

// Process Schedule Project Form
	//Project variables
	?>
    <h3>Capacity Calendar</h3>
	<?php 
	t8_pm_cap_calendar( $pm_users );
	/*
	$return = t8_pm_reschedule( $t8_pm_proj_id );
	$pm_schedule = $return['pm_schedule'];
	$dly_cpcty = $return['dly_cpcty'];
	t8_pm_dispschedule( $t8_pm_proj_id, $pm_users, $pm_schedule, $dly_cpcty );
	*/
//eof