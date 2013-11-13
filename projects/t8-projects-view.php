<?php
$t8_pm_proj_id = esc_html($_GET['project']);
$projs = t8_pm_get_projs( $t8_pm_proj_id );

$t8_pm_client_id = $projs[$t8_pm_proj_id]["cli_id"];
$t8_pm_proj_name = $projs[$t8_pm_proj_id]["name"];
$t8_pm_client_id = $projs[$t8_pm_proj_id]["cli_id"];
$t8_pm_staff = $projs[$t8_pm_proj_id]["staff"];
$t8_pm_est_hours = $projs[$t8_pm_proj_id]["est_hours"];
$t8_pm_status = $projs[$t8_pm_proj_id]["status"];
$t8_pm_start_date = $projs[$t8_pm_proj_id]["start_date"];
$t8_pm_end_date = $projs[$t8_pm_proj_id]["end_date"];
$t8_pm_price = $projs[$t8_pm_proj_id]["price"];
$t8_pm_proj_manager = $projs[$t8_pm_proj_id]["proj_manager"];

$task_results = $wpdb->get_results("SELECT * FROM ".$wpdb->prefix . "pm_tasks WHERE proj_id = ".$t8_pm_proj_id ); // collect task with this project id
if($task_results){ foreach($task_results as $task){ // build array with id as key
	$t8_pm_p_tasks[$task->id]['task-title'] = $task->task_title;
	$t8_pm_p_tasks[$task->id]['task-desc'] = $task->task_desc;
	$t8_pm_p_tasks[$task->id]['proj-id'] = $task->proj_id;
	$t8_pm_p_tasks[$task->id]['cli-id'] = $task->cli_id;
	$t8_pm_p_tasks[$task->id]['est-hours'] = $task->est_hours;
	$t8_pm_p_tasks[$task->id]['assign'] = $task->assign;
	$t8_pm_p_tasks[$task->id]['status'] = $task->status;
	$t8_pm_p_tasks[$task->id]['stage'] = $task->stage;
	$t8_pm_p_tasks[$task->id]['task-notes'] = $task->notes;
	$t8_pm_p_tasks[$task->id]['subtasks'] = unserialize($task->subtasks);
}}
$t8_pm_hoursums = array();
if($t8_pm_p_tasks){foreach($t8_pm_p_tasks as $task){ 
	$t8_pm_hoursums[] = $task["est-hours"];
}}
$t8_pm_hoursums = array_sum($t8_pm_hoursums);
$t8_pm_action = 'view';
//krsort($t8_pm_p_tasks);
uasort($t8_pm_p_tasks, "t8_pm_custom_sort");
$task_status = array( "Current", "Submitted", "Completed");
//	echo '<pre>Tasks:'; print_r($t8_pm_p_tasks); echo '</pre>';
//		echo '<pre>Ptypes:'; print_r($proj_types[$t8_pm_ptype]["default_tasks"]); echo '</pre>';
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
				<a href="<?php echo admin_url( 'admin.php?page=t8-teammate/t8-teammate.php_projects&project='.$t8_pm_proj_id.'&action=edit' ); ?>" title="Edit <?php echo $t8_pm_proj_name;?>">Edit</a> |
				</li>
				<li>
				<a href="<?php echo admin_url( 'admin.php?page=t8-teammate/t8-teammate.php_projects&project='.$t8_pm_proj_id.'&action=trash' ); ?>" title="Trash <?php echo $t8_pm_proj_name;?>">Trash</a>
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
	<table class="wp-list-table widefat" cellspacing="0">
		<thead>
			<tr>
				<th scope='col'  class='manage-column column-title'>Task Title</th>
				<th scope='col'  class='manage-column num'>Stage</th>
				<th scope='col'  class='manage-column'>Assign</th>
				<th scope='col'  class='manage-column'>Status</th>
				<th scope='col'  class='manage-column num'>Subtasks</th>
				<th scope='col'  class='manage-column num'>Estimated Hours</th>
			</tr>
		</thead>
		<tfoot>
			<tr>
				<th scope='col'  class='manage-column column-title'>Task Title</th>
				<th scope='col'  class='manage-column num'>Stage</th>
				<th scope='col'  class='manage-column'>Assign</th>
				<th scope='col'  class='manage-column'>Status</th>
				<th scope='col'  class='manage-column num'>Subtasks</th>
				<th scope='col'  class='manage-column num'>Estimated Hours</th>
			</tr>
		</tfoot>
		<tbody id="the-list">
		<?php $i=0;
		// echo '<pre>'; print_r( $t8_pm_p_tasks ); echo '</pre>';
		wp_nonce_field('check','t8_pm_nonce');
		foreach($t8_pm_p_tasks as $tid => $task){
				$task_num = $tid+1; $i++; 
				?>
			<tr id="t8-pm-task-<?php echo $tid; ?>" class="task <?php if($i%2) echo 'alternate'; ?>" valign="top">
				<td class="task-title column-title" >
					<?php echo $task['task-title']; ?>
					<div class="row-actions"> 
						<a class="inline-edit-task" href="#" >View Task and Subtasks</a>
					</div>
				</td>
				<td class="task-stage num">
					<?php echo $task['stage']; ?>
				</td>
				<td class="task-assign <?php echo 'user-'. $pm_users[$task['assign']]['uslug']; if($current_user->display_name != $pm_users[$task['assign']]["uname"]) echo ' not-curr-user'; ?>">
					<?php echo $pm_users[$task['assign']]["uname"]; ?>
				</td>
				<td class="task-status">
					<?php t8_pm_task_statuses( $tid, $task ); ?>
				</td>
				<td class="task-subtasks num"><?php if($task["subtasks"] && $task["subtasks"][0]["s-title"] != "") echo count($task["subtasks"]);?></td>
				<td class="task-hours num">
					<?php echo $task['est-hours']; ?>
				</td>
			</tr>
			<tr class="inline-editor-task hidden">
				<td class="colspanchange" colspan="7">
					 <table class="form-table">
						<tr>
						   <p> Task Description: <?php echo $task['task-desc']; ?></p>
						</tr>
						<tr>
							<th scope="row"><label for="task[<?php echo $tid; ?>][notes]">Notes</label><br><span></span></th>
							<td>
								<textarea name="task[<?php echo $tid; ?>][notes]" class="task-<?php echo $tid; ?>-notes" cols="40" rows="4"><?php echo $task['task-notes']; ?></textarea><br />
								<a href="#" class="button-secondary t8-pm-savenotes task-<?php echo $tid; ?>" >Save Notes</a>
							</td>
						</tr>
					</table>
						<?php if($task["subtasks"] && $task["subtasks"][0]["s-title"] != ""){ ?>
					<div class="subtasks">
						<table class="form-table">
							<thead>
								<tr>
									<th scope='col'  class='manage-column column-title'>Subtask Title</th>
									<th scope='col'  class='manage-column column-title'>Description</th>
									<th scope='col'  class='manage-column'>Assign</th>
									<th scope='col'  class='manage-column'>Status</th>
									<th scope='col'  class='manage-column num'>Estimated Hours</th>
							   </tr>
							</thead>
							<tfoot>
								<tr>
									<th scope='col'  class='manage-column column-title'>Subtask Title</th>
									<th scope='col'  class='manage-column column-title'>Description</th>
									<th scope='col'  class='manage-column'>Assign</th>
									<th scope='col'  class='manage-column'>Status</th>
									<th scope='col'  class='manage-column num'>Estimated Hours</th>
								</tr>
							</tfoot>
							<tbody>
			 <?php    foreach($task["subtasks"] as $st_index => $subtask){ // loop through the subtasks within this task ?>
						<tr class="subtask sbt-<?php echo $st_index; ?>">
							<td class="task-title column-title" >
								<?php echo $subtask['s-title']; ?>
							</td>
							<td><?php echo $subtask['s-desc']; ?></td>
							<td class="caps" <?php
					foreach($task["subtasks"] as $sub) {
						if($sub['s-assign'] == $current_user->ID) { ?>
							style="background: #<?php echo $user_color; ?>"
				<?php		break;
						}
					}
					?>>
								<?php echo $pm_users[$subtask['s-assign']]["uname"]; ?>
							</td>
							<td class="task-status">
								<?php 
								if( $task['stage'] == '0' ) { 
									echo 'Ongoing';	
								}elseif( $task['status'] == '0' ) { 
									echo 'Incomplete';	
								}elseif( $task['status'] == '1' ) { 
									echo 'In Review';	
								}else{
									echo 'Completed';
								} 
								?>
							</td>                                    
							<td class="num"><?php echo $subtask['s-hours']; ?></td>
						</tr>
						<?php } //end foreach subtasks ?>
						</tbody>
					</table>    
					</div>
			 <?php } //end if subtasks ?>
			  </td>
			</tr>

<?php 	} // end foreach($t8_pm_p_tasks as $tid => $task) ?>
		</tbody>
	</table>    
</div>
</div>
<?php 
//eof