<?php
?>
<style type="text/css">
	.wp-list-table .column-title { width: 30%; }
	.wp-list-table .column-hours { width: 15%; }
	.wp-list-table .column-start_date, 
	.wp-list-table .column-end_date { width: 18%; }
<?php 
if( isset($pm_users) ){
	foreach( $pm_users as $user_id => $user ) { 
		$ucolor = $user['color'];
		if( $ucolor && $ucolor != '' ){
			$uslug = $user['uslug'];
			echo ".user-$uslug { background-color: #$ucolor ; }\n";
			echo ".wp-list-table tr.$uslug > td:first-child { border-left:4px solid #$ucolor ; }\n";
		}
	}
}
?>
</style>

<?php



global $wpdb; //set ur globals

	//Client list
	$cli_results = $wpdb->get_results("SELECT id, name, status FROM ".$wpdb->prefix . 'pm_cli' ); // collect Client names and id
	if($cli_results){ foreach($cli_results as $client){ // build array with id as key
		$clients[$client->id]["name"] = $client->name;
		$clients[$client->id]["status"] = $client->status;
	}}


//get Project(s)
function t8_pm_get_projs( $t8_pm_proj_id ) {
	global $wpdb; //set ur globals
	if( is_array( $t8_pm_proj_id ) ) {
		$proj_results = $wpdb->get_results("SELECT * FROM ".$wpdb->prefix . "pm_projects WHERE id IN('". implode("', '", $t8_pm_proj_id ) . "')"); // collect Projects
	} else {
		$proj_results = $wpdb->get_results("SELECT * FROM ".$wpdb->prefix . "pm_projects WHERE id = " . $t8_pm_proj_id ); // collect Project
	}
	if($proj_results){ foreach($proj_results as $project){ // build array with id as key
		$projs[$project->id] = array( // build proj array
			"name" => 			$project->name,
			"cli_id" => 		$project->cli_id,
			"est_hours" => 		$project->est_hours,
			"status" => 		$project->status,
			"start_date" => 	$project->start_date,
			"end_date" => 		$project->end_date,
			"price" => 			$project->price,
			"proj_manager" => 	$project->proj_manager,
			"misc" => 			unserialize($project->misc)
		);
		if( !is_array( $projs[$project->id]["misc"] ) ) $projs[$project->id]["misc"] = array( 'features' => $projs[$project->id]["misc"] );
	}}
	return $projs;
}
	//Project Status List
		$status_r = array ( "Proposed", "Active" ,"Archived", "Trashed");
?>