<?php
$t8_pm_proj_id = esc_html($_GET['project']);
$t8_pm_proj = t8_pm_get_projs( $t8_pm_proj_id );

$t8_pm_client_id = $t8_pm_proj[$t8_pm_proj_id]["cli_id"];
$t8_pm_proj_name = $t8_pm_proj[$t8_pm_proj_id]["name"];
$t8_pm_client_id = $t8_pm_proj[$t8_pm_proj_id]["cli_id"];
$t8_pm_est_hours = $t8_pm_proj[$t8_pm_proj_id]["est_hours"];
$t8_pm_status = $t8_pm_proj[$t8_pm_proj_id]["status"];
$t8_pm_start_date = $t8_pm_proj[$t8_pm_proj_id]["start_date"];
$t8_pm_end_date = $t8_pm_proj[$t8_pm_proj_id]["end_date"];
$t8_pm_price = $t8_pm_proj[$t8_pm_proj_id]["price"];
$t8_pm_proj_manager = $t8_pm_proj[$t8_pm_proj_id]["proj_manager"];
/**
 * Milestone and task view table.
 *
 * Generates a display table of tasks grouped by milestone.
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
$t8_pm_mstones = $t8_pm_proj[$t8_pm_proj_id]['misc']['milestones'];
//echo '<pre>'; print_r($t8_pm_mstones); echo '</pre>';
global $wpdb;
$task_results = $wpdb->get_results("SELECT * FROM ".$wpdb->prefix . "pm_tasks WHERE proj_id = ".$t8_pm_proj_id ); // collect task with this project id
$t8_pm_hoursums = array();
if($task_results){ foreach($task_results as $task){ // build array with id as key
	if(!isset($t8_pm_proj[$task->stage])) $t8_pm_proj[$task->stage] = array();
	$t8_pm_mstones[$task->stage]['tasks'][$task->id] = array(
		'title' => $task->task_title,
		'assign' => $task->assign,
		'hours' => $task->est_hours,
		'status' => $task->status
	);
	$t8_pm_hoursums[] = $task->est_hours;
}}

$t8_pm_hoursums = array_sum($t8_pm_hoursums);
$t8_pm_action = 'view';
//krsort($t8_pm_p_tasks);
$task_status = array( "Current", "Submitted", "Completed");
//echo '<pre>proj:'; print_r($t8_pm_proj); echo '</pre>';
$clients = t8_pm_get_clis( $t8_pm_client_id );
?>
<div class="wrap view-project t8-pm">
	<h2><?php echo $clients[$t8_pm_client_id]['name']; ?> :: <?php echo $t8_pm_proj_name; ?></h2>
	<?php t8_pm_display_schedule( $t8_pm_proj_id, $pm_users ); ?>
	<div class="tablenav">
	<?php // if($t8_pm_status > 0 && $t8_pm_status < 3) { ?>
		<div class="alignleft">
			<p>Status: <?php echo $status_r[$t8_pm_status];?> | Budget: $<?php echo number_format( $t8_pm_price ); ?></p>
			<ul class="subsubsub">
				<li>
				<a href="<?php echo add_query_arg('action','edit'); ?>" title="Edit <?php echo $t8_pm_proj_name;?>">Edit</a>
				</li>
			</ul>
		</div>
		<div class="alignright actions t8-pm-datebox">
			<?php
			$end = strtotime($t8_pm_end_date);
			$sta = strtotime($t8_pm_start_date);
			$now = time();
			$all = $end - $sta;
			$past = $now - $sta;
			$per = round((100 * $past)/$all);
			$t8_pm_proj_hours = $t8_pm_est_hours*1.10; //add 10% to project est_hours. 
			$t8_pm_dly_cpcty = t8_pm_getWorkDays($t8_pm_start_date, $t8_pm_end_date, $t8_pm_proj_hours); //calculate number of work days between project start and end, build array
			$t8_pm_daysLeft = 0;
			$nowtime = time();
			foreach($t8_pm_dly_cpcty as $daystamp => $timeallowed){
				if( $nowtime <= $daystamp ) $t8_pm_daysLeft++;
			} 
			?>
			<p class="displaying-num"><?php echo $t8_pm_daysLeft; ?> Workdays Left</p>
			<div class="t8-pm-datechart">
				<div class="t8-pm-datefill" style="width: <?php echo $per; ?>%;"></div>
			</div>
		</div>
	 </div>
<div id="tasks" data-proj="<?php echo $t8_pm_proj_id; ?>">
	<?php t8_pm_mstone_view_table( $t8_pm_mstones ); ?>  
</div>
</div>
<?php 
//eof