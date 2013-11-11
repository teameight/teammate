<?php
if( !isset($t8_pm_warning) ) $t8_pm_warning = '';
$t8_pm_warning .= "<p>ITS ALIVE!</p>";
global $wpdb; //set ur globals

// Process Schedule Project Form
	//Project variables
	$t8_pm_proj_id = esc_html($_GET['project']);
	$proj_cli = $clients[$project->cli_id]["name"]; ?>
    <h3><?php echo $proj_cli; ?>::<?php echo $project->name; ?></h3>
	<?php 
	t8_pm_display_schedule( $t8_pm_proj_id, $pm_users );
	/*
	$return = t8_pm_reschedule( $t8_pm_proj_id );
	$pm_schedule = $return['pm_schedule'];
	$dly_cpcty = $return['dly_cpcty'];
	t8_pm_dispschedule( $t8_pm_proj_id, $pm_users, $pm_schedule, $dly_cpcty );
	*/
//eof