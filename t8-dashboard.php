<?php
include_once( plugin_dir_path(__FILE__).'t8-lists.php' );
$current_user = wp_get_current_user();

// check if day is specified in url, if not use current day
date_default_timezone_set('America/New_York');
$showday = $today = getdate( strtotime( 'today' ) );
if(isset($_GET['d']) && $_GET['d']!= '') {
	$showday = getdate( strtotime( $_GET['d'] ) );
}
$day = $showday['yday'];
$year = $showday['year'];
$day2day = $today['yday'];
$year2day = $today['year'];
// echo '<pre>'; print_r($today); echo '</pre>';
?>
<div class="wrap t8-pm">
    <?php if(isset($t8_pm_warning)){ ?><div id="message" class="error"><?php echo $t8_pm_warning; ?></div><?php } ?>
    <?php if(isset($t8_pm_updated)){ ?><div id="message" class="updated"><?php echo $t8_pm_updated; ?></div><?php } ?>
<?php
	if ( function_exists('wp_nonce_field') ) wp_nonce_field('t8_pm_nonce','t8_pm_nonce');
	
global $wpdb;
	$wpdb->show_errors = true;
	//Open Project list
	function custom_sort($a, $b) { // !!! this needs a unique name and to be moved to functions
		return $a["stage"] > $b["stage"];
	}

	//Client list
	$cli_results = $wpdb->get_results("SELECT * FROM ".$wpdb->prefix . 'pm_cli' ); // collect Client names and id
	if($cli_results){ foreach($cli_results as $client){ // build array with id as key
		$clients[$client->id]["name"] = $client->name;
	}}
	//Project Name list
	$proj_name_results = $wpdb->get_results("SELECT id, name, end_date, proj_manager, misc FROM ".$wpdb->prefix . 'pm_projects' ); // collect Project names and id
	if($proj_name_results){ foreach($proj_name_results as $proj){
		$projnames[$proj->id]['name'] = $proj->name;
		$projnames[$proj->id]['end_date'] = $proj->end_date;
		$projnames[$proj->id]['proj_manager'] = $proj->proj_manager;
		$misc = unserialize($proj->misc);
		if(!empty( $misc['milestones'])) $projnames[$proj->id]['mstones'] = $misc['milestones'];
	}}
	$t8_active_proj_results = $wpdb->get_results("SELECT id FROM ".$wpdb->prefix . 'pm_projects WHERE status = 2' ); // collect Project names and id
	if($t8_active_proj_results){ foreach($t8_active_proj_results as $proj){
		$t8_active_proj[] = $proj->id;
	}}
	$schedTasks = array();
//echo '<pre>'; print_r($projnames); echo '</pre>';
			$dayplanner_results = get_user_meta($current_user->ID, 'dayplanner', true);
			if(empty($dayplanner_results)) { // Dayplanner does not exist yet for this user
				$dayplanner = array();
				$dayplanner[$year][$day] = '';
				$add_dayplanner = update_user_meta($current_user->ID, 'dayplanner', $dayplanner);
				if($add_dayplanner) {
					$dayplanner = get_user_meta($current_user->ID, 'dayplanner', true);
				} else {
					$dayplanner = array();
				}
			} else { // got it, process it and save it if need be
				$dayplanner = array();
			// Check if any dayplanner items are from before today, move them all to today, if so update dayplanner
				$t8_pm_update_plnnr = 0;
				foreach( $dayplanner_results as $ryear => $todoR ){
					if($ryear < $year2day){ // this item in the dayplanner is so last year
						foreach( $todoR as $rday => $tasks ){
							foreach($tasks as $tasktype => $taskr ) {
								foreach( $taskr as $bumindex => $task ) {
									$dayplanner[$year2day][$day2day][$tasktype][] = $task; // update these old todos to today
								}
							}
						}
						$t8_pm_update_plnnr = 1;
					}else{
						foreach( $todoR as $rday => $tasks ){
							if($rday < $day2day){ // this item in the dayplanner is so yesterday or before !!! good way to check if user hasn't logged in in awhile
								foreach($tasks as $tasktype => $taskr ) {
									foreach( $taskr as $bumindex => $task ) {
										if ( !in_array( $task, $dayplanner[$year2day][$day2day][$tasktype])) 
											$dayplanner[$year2day][$day2day][$tasktype][] = $task; // update these old todos to today
									}
								}
								$t8_pm_update_plnnr = 1;
							}else{ // its up to date, file it normal, check for duplicates
								if( is_array($tasks) ){
									foreach($tasks as $tasktype => $taskr ) {
										foreach( $taskr as $bumindex => $task ) {
											if(!empty($dayplanner[$ryear][$rday][$tasktype])){ 
												if ( !in_array( $task, $dayplanner[$ryear][$rday][$tasktype])) $dayplanner[$ryear][$rday][$tasktype][] = $task; // update these old todos to today
											}else{
												$dayplanner[$ryear][$rday][$tasktype][] = $task;
											}
										}
									}
								}
							} //end else ($rday < $day)
						}
					} //end else ($ryear < $year)
				}
				if($t8_pm_update_plnnr) $add_dayplanner = update_user_meta($current_user->ID, 'dayplanner', $dayplanner);
			}
//			$tasks = $dayplanner[$year][$day];
// echo '<pre>'; print_r($dayplanner); echo '</pre>';
			$today_task_array = $today_assign_array = $today_plan_R = array();
			
// Get Tasks from open projects
			$active_proj_res = $wpdb->get_results( 
				"SELECT * FROM ".$wpdb->prefix . "pm_projects 
				WHERE start_date <= CURDATE() 
				AND status = '1'" 
			); 
			$act_prod_ids = array();
			if ( $active_proj_res ) {
				foreach ($active_proj_res as $proj) {
					$act_prod_ids[]=$proj->id;
				}
			}
			if( !empty( $act_prod_ids ) ){
				$sched_results = $wpdb->get_results( 
					"SELECT * FROM ".$wpdb->prefix . "pm_tasks 
					WHERE proj_id IN(" . implode(',', $act_prod_ids).") 
					AND assign = " . $current_user->ID ." 
					AND status < '2'"
				);
				if($sched_results){
					foreach($sched_results as $sched){ // build array with id as key
						$schedTasks[] = $sched->id;
					}
				}
			}

// echo '<pre>'; print_r($schedTasks); echo '</pre>';


			if(!empty($schedTasks)) $schedTasks = array_reverse($schedTasks);

			// Grab all this users punched time for today
			// $showday[0] is the day's timestamp
			$punched_results = $wpdb->get_results(
					"SELECT * FROM ".$wpdb->prefix."pm_time 
					WHERE user_id = ".$current_user->ID." 
					AND DATE(start_time) = '".$showday['year']."-".$showday['mon']."-".$showday['mday']."'  
					ORDER BY end_time DESC"
			); // !!! need to switch all time entries in database to use mysql time, this currently just gets everything clocked today and after
			$punched_tasks = array();
			if($punched_results){
				foreach($punched_results as $punched){ // build array with id as key
					$t8_pm_punched[$punched->id]['task_id'] = $punched->task_id;
					$t8_pm_punched[$punched->id]['proj_id'] = $punched->proj_id;
					$t8_pm_punched[$punched->id]['cli_id'] = $punched->cli_id;
					$t8_pm_punched[$punched->id]['start_time'] = $punched->start_time;
					$t8_pm_punched[$punched->id]['end_time'] = $punched->end_time;
					$t8_pm_punched[$punched->id]['hours'] = $punched->hours;
					$punched_tasks[] = $punched->id;
					$punched_clis[] = $punched->cli_id;
					$punched_projs[] = $punched->proj_id;
				}
			}
			$common_results = $wpdb->get_results("SELECT * FROM ".$wpdb->prefix . "pm_tasks WHERE assign = 'all' AND status < 2"); // collect tasks from schedule
			if($common_results){
				foreach($common_results as $task){
					$commonTasks[]=$task->id;
				}
			}
// Now get all tasks in one query:
			if( !empty( $dayplanner[$year][$day]['task']) ) {
				$getTasksIdR = array_merge( $schedTasks, $dayplanner[$year][$day]['task'], $punched_tasks );
			}else{
				if(!empty($schedTasks)) $getTasksIdR = $schedTasks;
			}
			if( !empty( $getTasksIdR ) ) {
				if( !empty( $commonTasks ) ) $getTasksIdR = array_merge( $getTasksIdR, $commonTasks );
			}else{
				$getTasksIdR = $commonTasks;
			}
			// !!! probably need to set the aboves to empty arrays first
			
// echo '<pre>'; print_r($getTasksIdR); echo '</pre>'; 
			if( !empty($getTasksIdR) ) {
				$task_results = $wpdb->get_results("SELECT * FROM ".$wpdb->prefix . "pm_tasks WHERE id IN(" . implode(',', $getTasksIdR).")" ); // collect tasks from schedule
				if($task_results){
					foreach($task_results as $task){ // build array with id as key
						$t8_pm_day_tasks[$task->id]['task-title'] = $task->task_title;
						$t8_pm_day_tasks[$task->id]['proj-id'] = ($task->proj_id != 0 ? $task->proj_id : '');
						$t8_pm_day_tasks[$task->id]['proj-name'] = ($task->proj_id != 0 ? $projnames[$task->proj_id]['name'] : '');
						$t8_pm_day_tasks[$task->id]['cli-id'] = ($task->cli_id != 0 ? $task->cli_id : '');
						$t8_pm_day_tasks[$task->id]['cli-name'] = ($task->cli_id != 0 ? $clients[$task->cli_id]['name'] : '');
						$t8_pm_day_tasks[$task->id]['assign'] = $task->assign;
						$t8_pm_day_tasks[$task->id]['stage'] = $task->stage;
						$t8_pm_day_tasks[$task->id]['status'] = $task->status;
						$t8_pm_day_tasks[$task->id]['est-hours'] = $task->est_hours;
						//$t8_pm_day_tasks[$task->id]['h-plan'] = $today_plan_R['task'][$task->id];
						  $task_due = ($task->due != '' ? $task->due : $projnames[$task->proj_id]['end_date']);
						$t8_pm_day_tasks[$task->id]['due'] = $task_due;
						  $days_left = ceil( (strtotime($task_due) - $today[0])/(60*60*24) );
						$t8_pm_day_tasks[$task->id]['days-left'] = $days_left;
						$t8_pm_day_tasks[$task->id]['type'] = 'task';
					}
				}
			}
			// Grab all this users assignments that aren't already in planner
			$not_in_assign = " AND id NOT IN (" . implode(',', $today_assign_array).") ";
			$assign_results = $wpdb->get_results(
				"SELECT * FROM ".$wpdb->prefix."pm_time 
				WHERE user_id = ".$current_user->ID." 
				AND start_time IS NULL 
				". (!empty($today_assign_array) ? $not_in_assign : '') . " 
				ORDER BY end_time LIMIT 20"
			);
			if($assign_results){
				foreach($assign_results as $assign){ // build array with id as key
					$t8_pm_p_tasks[$assign->id]['task-title'] = $assign->notes;
					$t8_pm_p_tasks[$assign->id]['proj-id'] = ($assign->proj_id != 0 ? $assign->proj_id : '');
					$t8_pm_p_tasks[$assign->id]['proj-name'] = ($assign->proj_id != 0 ? $projnames[$assign->proj_id]['name'] : '');
					$t8_pm_p_tasks[$assign->id]['cli-id'] = ($assign->cli_id != 0 ? $assign->cli_id : '');
					$t8_pm_p_tasks[$assign->id]['cli-name'] = ($assign->cli_id != 0 ? $clients[$assign->cli_id]['name'] : '');
					$t8_pm_p_tasks[$assign->id]['assign'] = $assign->user_id;
					$t8_pm_p_tasks[$assign->id]['stage'] = ($assign->task_id != 0 ? $assign->task_id : '');
					$t8_pm_p_tasks[$assign->id]['type'] = 'assign';
				}
			}
			if(!empty($t8_pm_p_tasks)) uasort($t8_pm_p_tasks, "custom_sort");
			$task_status = array( "Current", "Submitted", "Completed");

			
 $prevday = date('M-d-y', $showday[0] - (60*60*24));
 $nextday = date('M-d-y', $showday[0] + (60*60*24));
 if ( date('M-d-y', $showday[0] ) == date('M-d-y') ) {
 	$showdayText = 'Today';
 }elseif( date('M-d-y', $showday[0] ) == date('M-d-y', strtotime('yesterday') ) ){
 	$showdayText = 'Yesterday';
 }elseif( date('M-d-y', $showday[0] ) == date('M-d-y', strtotime('tomorrow') ) ){
 	$showdayText = 'Tomorrow';
 } ?>
        <?php 
			// Get ready for Punchclock
		$startTimer = 0;
		$punchin = get_user_meta( $current_user->ID, "punchin", true);
		if(!empty($punchin ) ){
			$startTimer = $punchin['start_time'];
			$t8_pm_cpt_sels = t8_pm_cli_proj_task_selects( 1, 0, 0, 0, $punchin );
			$punchin = $t8_pm_cpt_sels['punchin'];
		} else {
			$t8_pm_cpt_sels = t8_pm_cli_proj_task_selects( 1 );
		} ?>
	<div id="dashboard-wrap" class="cf">
        <?php if( ($showday[0] + 60 ) > $today[0] ) { ?>
		<div class="dashboard all-tasks">
            <h3 class="th">Your Tasks</h3>
            <div class="container">
            <div class="list sort" id="all-your-tasks">
        <?php 
        	if( !empty( $schedTasks ) && !empty($t8_pm_day_tasks) ) {
                foreach ($schedTasks as $tid) { // use dayplanner task array as key for pulling out queried tasks
                       //echo 'DP'; print_r($dayplanner);
					if(!isset($dayplanner[$year][$day]['task'])) $dayplanner[$year][$day]['task'] = array();
					if( !in_array( $tid, $dayplanner[$year][$day]['task'] ) ){ 
                            $task = $t8_pm_day_tasks[$tid];
                ?>
                <div class="dtask<?php echo ($task['type'] == 'assign' ? ' assign' : '');  echo ( $punchin['task'] == $tid ? ' punching' : ''); ?>" data-proj-id="<?php echo $task['proj-id']; ?>" data-stage="<?php echo $task['stage']; ?>" data-id="<?php echo $tid; ?>" data-cli="<?php echo $task['cli-id']; ?>" data-type="<?php echo $task['type']; ?>" data-hours="<?php echo $task['est-hours']; ?>">
                    <h3><span class="cli-span"><?php echo (strlen($task['cli-name']) < 12 ? $task['cli-name'] : substr($task['cli-name'],0,12).'...'); ?></span>::<span class="proj-span"><?php echo (strlen($task['proj-name']) < 20 ? $task['proj-name'] : substr($task['proj-name'],0,20).'...'); ?></span>::
                    <span class="rdts"><?php echo $task['est-hours']; ?> h est.</span></h3>
                    <p>
                        <span class="task-title"><?php echo $task['task-title']; ?></span>
                        <span class="rdts"><?php echo $task['days-left']; ?> days</span>
                    </p>
                    <div class="x dact">X</div>
                    <div class="send2pc dact">O</div>
                    <div class="extras">
                        <span class="mstone"><?php print_r( $projnames[$task['proj-id']]['mstones'][$task['stage']]['name'] ); ?></span>
                        <div class="task-status rdts">
                            <?php 
                            $t8_pm_inreview_cbox = '<span>In Review</span> <input type="checkbox" name="review[]" checked class="t8-pm-task-status" value="'.$tid.'" />';
                            $t8_pm_submit_cbox = '<span>Submit for Review</span> <input type="checkbox" name="review[]" class="t8-pm-task-status" value="'.$tid.'" />';
                            $t8_pm_complete_cbox = ' <input type="checkbox" name="complete[]" class="t8-pm-task-status" value="'.$tid.'" />';
                            $t8_pm_uncomplete_cbox = ' <input type="checkbox" name="complete[]" checked class="t8-pm-task-status" value="'.$tid.'" />';
                            if( $task['stage'] == '0' ) { 
                                echo 'Ongoing';	
                            }elseif( $task['status'] == '0' ) { 
                                if( $task['proj-man'] == $current_user->ID ) { 
                                    echo '<span>Mark as Complete</span>' . $t8_pm_complete_cbox;
                                }elseif( $task['assign']  == $current_user->ID ) { 
                                    echo $t8_pm_submit_cbox;
                                }else{
                                    echo 'Incomplete';	
                                } 
                            }elseif( $task['status'] == '1' ) { 
                                echo ( $task['proj-man'] == $current_user->ID ? '<span>Approve as Complete</span>' . $t8_pm_complete_cbox : $t8_pm_inreview_cbox );
                            }elseif( $task['status'] == '2' ) { 
                                if( $task['proj-man'] == $current_user->ID || $task['assign']  == $current_user->ID ) echo $t8_pm_uncomplete_cbox;
                                echo '<span>Completed</span>';
                            }else{ echo 'status: ' . $task['status']; }
                        ?>
                       </div>
                    </div>
                </div>
            <?php
                    }
                }
            } else { ?>
				<div class="empty<?php echo (!empty($t8_pm_day_tasks) ? ' hidden' : ''); ?>">
					<h3>Not much to do.</h3>
				</div>

			<?php
            }
        // and again for assignments
            $i = 0;
            if(!empty($t8_pm_p_tasks)){ foreach ($t8_pm_p_tasks as $id => $task) { ?>
                <div class="dtask<?php echo ($task['type'] == 'assign' ? ' assign' : ''); ?>" data-proj-id="<?php echo $task['proj-id']; ?>" data-stage="<?php echo $task['stage']; ?>" data-id="<?php echo $id; ?>" data-cli="<?php echo $task['cli-id']; ?>" data-type="<?php echo $task['type']; ?>" data-hours="<?php echo $task['est-hours']; ?>">
                        <h3><span class="cli-span"><?php echo (strlen($task['cli-name']) < 12 ? $task['cli-name'] : substr($task['cli-name'],0,12).'...'); ?></span>::<span class="proj-span"><?php echo (strlen($task['proj-name']) < 20 ? $task['proj-name'] : substr($task['proj-name'],0,20).'...'); ?></span>::</h3>
                        <div class="rollover">
                            <span class="x dact"></span>
                        </div>
                    <div class="lower">
                            <span class="task-title"><?php echo $task['task-title']; ?></span>
                            <span class="days-left"><?php echo $task['days-left']; ?> days</span>
                        </div>
                    <div class="extras">
                        <span class="assign"><?php echo $task['assign']; ?></span>
                        <span class="est-hours"><?php echo $task['est-hours']; ?> h est.</span>
                    </div>
                </div>
            <?php
            }}
            ?>
            </div>
            </div>
            <h3 class="th">Common Tasks</h3>
            <div class="container">
                <div class="list sort" id="common-tasks">
        <?php if( !empty( $commonTasks ) && !empty($t8_pm_day_tasks) ) {
                foreach ($commonTasks as $tid) { // use dayplanner task array as key for pulling out queried tasks
					if(!isset($dayplanner[$year][$day]['task'])) $dayplanner[$year][$day]['task'] = array();
					if( !in_array( $tid, $dayplanner[$year][$day]['task'] ) ){ 
                        $task = $t8_pm_day_tasks[$tid];
            ?>
            <div class="dtask<?php echo ($task['type'] == 'assign' ? ' assign' : '');  echo ( $punchin['task'] == $tid ? ' punching' : ''); ?>" data-proj-id="<?php echo $task['proj-id']; ?>" data-stage="<?php echo $task['stage']; ?>" data-id="<?php echo $tid; ?>" data-cli="<?php echo $task['cli-id']; ?>" data-type="<?php echo $task['type']; ?>" data-hours="<?php echo $task['est-hours']; ?>">
                <h3><span class="cli-span"><?php echo (strlen($task['cli-name']) < 12 ? $task['cli-name'] : substr($task['cli-name'],0,12).'...'); ?></span>::<span class="proj-span"><?php echo (strlen($task['proj-name']) < 20 ? $task['proj-name'] : substr($task['proj-name'],0,20).'...'); ?></span>::
                <span class="rdts"><?php echo $task['est-hours']; ?> h est.</span></h3>
                <p>
                    <span class="task-title"><?php echo $task['task-title']; ?></span>
                    <span class="rdts"><?php echo $task['days-left']; ?> days</span>
                </p>
                <div class="x dact">X</div>
                <div class="send2pc dact">O</div>
                <div class="extras">
                    <span class="mstone"><?php print_r( $projnames[$task['proj-id']]['mstones'][$task['stage']]['name'] ); ?></span>
                    <div class="task-status rdts">
                        <?php 
                        $t8_pm_inreview_cbox = '<span>In Review</span> <input type="checkbox" name="review[]" checked class="t8-pm-task-status" value="'.$tid.'" />';
                        $t8_pm_submit_cbox = '<span>Submit for Review</span> <input type="checkbox" name="review[]" class="t8-pm-task-status" value="'.$tid.'" />';
                        $t8_pm_complete_cbox = ' <input type="checkbox" name="complete[]" class="t8-pm-task-status" value="'.$tid.'" />';
                        $t8_pm_uncomplete_cbox = ' <input type="checkbox" name="complete[]" checked class="t8-pm-task-status" value="'.$tid.'" />';
                        if( $task['stage'] == '0' ) { 
                            echo 'Ongoing';	
                        }elseif( $task['status'] == '0' ) { 
                            if( $task['proj-man'] == $current_user->ID ) { 
                                echo '<span>Mark as Complete</span>' . $t8_pm_complete_cbox;
                            }elseif( $task['assign']  == $current_user->ID ) { 
                                echo $t8_pm_submit_cbox;
                            }else{
                                echo 'Incomplete';	
                            } 
                        }elseif( $task['status'] == '1' ) { 
                            echo ( $task['proj-man'] == $current_user->ID ? '<span>Approve as Complete</span>' . $t8_pm_complete_cbox : $t8_pm_inreview_cbox );
                        }elseif( $task['status'] == '2' ) { 
                            if( $task['proj-man'] == $current_user->ID || $task['assign']  == $current_user->ID ) echo $t8_pm_uncomplete_cbox;
                            echo '<span>Completed</span>';
                        }else{ echo 'status: ' . $task['status']; }
                    ?>
                   </div>
                </div>
            </div>
        <?php
                }
            }
        } else { ?>
				<div class="empty<?php echo (!empty($t8_pm_day_tasks) ? ' hidden' : ''); ?>">
					<h3>Not a lot going on</h3>
				</div>

			<?php
            }
?> 
               </div>
            </div>
        </div>
        <?php } // end if( ($showday[0] + 60 ) > $today[0] ) ?>


        <div class="today-wrap">
        	<a class="prevday" href="<?php echo admin_url( 'admin.php?page=t8-teammate/t8-teammate.php' ); echo '&d='.$prevday; ?>" title="Previous Day">previous day</a>
            <a class="nextday" href="<?php echo admin_url( 'admin.php?page=t8-teammate/t8-teammate.php' ); echo '&d='.$nextday; ?>" title="Previous Day">next day</a>
            <form action="<?php echo admin_url( 'admin.php' ); ?>" method="get" >
	        	<h2 class="th"><?php echo ( isset( $showdayText ) ? $showdayText : ''); ?> 
            	<input type="text" class="datepicker"  name="d" id="d" value="<?php echo date('D, M d, Y', $showday[0]); ?>" />
                <input type="hidden" name="page" id="timepage" value="<?php echo $_GET["page"]; ?>" />
                <input type="submit" value="G0"></h2>
            </form>
            
            <div id="punch-col" class="cf t8pm-dsh-col">
                <div id="pclock-dash" class="cf">
                    <div class="timer">
                    	<p class="rdts">punch</p>
                        <p class="time-readout" data-start="<?php echo $startTimer; ?>" data-starttest="<?php echo ( time() - $startTimer ) % 60; ?>"><span class="hour"></span> <span class="min">0m</span> <span class="sec">0s</span></p>
                        <p class="pnchHrs"></p>
                    	<p class="rdts">of</p>
                        <p class="estHrs"></p><?php // echo ( $punchin['est_thours'] ? $punchin['est_thours'] : '' ); ?>
                    </div>
                    <div class="manual">
                        <h3>Timer <a class="rdts" href="">switch to manual</a></h3>
                        <select class="pc-cli">
                            <?php echo $t8_pm_cpt_sels['cli']; ?>
                        </select>
                        <select class="pc-proj"<?php echo ( isset( $t8_pm_cpt_sels['proj'] ) ? '' : 'disabled="disabled"'); ?>>
                            <?php echo ( isset( $t8_pm_cpt_sels['proj'] ) ? $t8_pm_cpt_sels['proj'] : 'Project...'); ?>
                        </select>
                        <select class="pc-task"<?php echo ( isset( $t8_pm_cpt_sels['task'] ) ? '' : 'disabled="disabled"'); ?>>
                            <?php echo ( isset( $t8_pm_cpt_sels['task'] ) ? $t8_pm_cpt_sels['task'] : 'Task...'); ?>
                        </select>
                        <p class="pc-desc disabled">
                            <input type="text" placeholder="notes" name="desc" >
                        </p>
                        <div class="time cf">
                            <div class="starttime">
                                <p><?php echo ( $startTimer ? date("g:i a", $startTimer) : '-:--' ); ?></p>
                                <label>start</label>
                            </div>
                            <div class="endtime">
                                <p><?php echo ( $startTimer ? date("g:i a") : '-:--' ); ?></p>
                                <label>end</label>
                            </div>
                            <div class="date">
                                <p><?php echo ( $startTimer ? date("m/d/y", $startTimer) : date("m/d/y") ); ?></p>
                                <label>date</label>
                            </div>
                        </div>
                    </div>
                    <?php 
					$buttonText = 'Punch In';
					$buttonClass = 'start_timer';
					$butt2 = '';
					if($startTimer){
						$butt2 = '<button type="button" class="secondary">X</button>';
						$buttonClass = 'needselect';
						if( !isset( $t8_pm_cpt_sels['selcli'] ) ){
							$buttonText = 'Please Select A Client';
						} elseif( !isset( $t8_pm_cpt_sels['proj'] ) ) {
							$buttonText = 'Please Select A Project';
						} elseif( !isset( $t8_pm_cpt_sels['task'] ) ) {
							$buttonText = 'Please Select A Task';
						}else{
							$buttonClass = 'punch_timer';
							$buttonText = 'Punch Out';
						}
					}
					?>
                    <div id="timer-actions" class="<?php echo $buttonClass; ?>">
                    	<button type="button" class="btn"><?php echo $buttonText; ?></button><?php echo $butt2; ?>
                    </div>
                </div>
                <div class="dashboard today-tasks t8pm-dsh-col" data-day="<?php echo $day; ?>" data-year="<?php echo $year; ?>">
                    <?php if( ($showday[0] + 60 ) > $today[0] ) { ?>
                    <div class="leftcol">
                        <h3 class="th">Planner <a class="add orphan" href="">+</a></h3>
                        <div class="list sort" id="planner">
                            <div class="empty<?php echo (!empty($t8_pm_day_tasks) ? ' hidden' : ''); ?>">
                                <h3>Drag Tasks Here to Start</h3>
                            </div>
                        <?php if( !empty( $dayplanner[$year][$day]['task']) && !empty($t8_pm_day_tasks) ) {
                                foreach ($dayplanner[$year][$day]['task'] as $tid) { // use dayplanner task array as key for pulling out queried tasks
                                    $task = $t8_pm_day_tasks[$tid];
                            ?>
                                <div class="dtask<?php echo ($task['type'] == 'assign' ? ' assign' : ''); if(is_array($punchin)) echo ( $punchin['task'] == $tid ? ' punching' : ''); ?>" data-proj-id="<?php echo $task['proj-id']; ?>" data-stage="<?php echo $task['stage']; ?>" data-id="<?php echo $tid; ?>" data-cli="<?php echo $task['cli-id']; ?>" data-type="<?php echo $task['type']; ?>" data-hours="<?php echo $task['est-hours']; ?>">
                                    <h3><span class="cli-span"><?php echo (strlen($task['cli-name']) < 12 ? $task['cli-name'] : substr($task['cli-name'],0,12).'...'); ?></span>::<span class="proj-span"><?php echo (strlen($task['proj-name']) < 20 ? $task['proj-name'] : substr($task['proj-name'],0,20).'...'); ?></span>::
                                    <span class="rdts"><?php echo $task['est-hours']; ?> h est.</span></h3>
                                    <p><span class="task-title"><?php echo $task['task-title']; ?></span><span class="rdts"><?php echo $task['days-left']; ?> days</span></p>
                                    <div class="x dact">X</div>
                                    <div class="send2pc dact">O</div>
                                    <div class="extras extra-planner">
                                        <span class="mstone"><?php echo $projnames[$task['proj-id']]['mstones'][$task['stage']]['name']; ?></span>
                                        <div class="task-status rdts">
                                            <?php 
                                            $t8_pm_inreview_cbox = '<span>In Review</span> <input type="checkbox" name="review[]" checked class="t8-pm-task-status" value="'.$tid.'" />';
                                            $t8_pm_submit_cbox = '<span>Submit for Review</span> <input type="checkbox" name="review[]" class="t8-pm-task-status" value="'.$tid.'" />';
                                            $t8_pm_complete_cbox = ' <input type="checkbox" name="complete[]" class="t8-pm-task-status" value="'.$tid.'" />';
                                            $t8_pm_uncomplete_cbox = ' <input type="checkbox" name="complete[]" checked class="t8-pm-task-status" value="'.$tid.'" />';
                                            if( $task['stage'] == '0' ) { 
                                                echo 'Ongoing';	
                                            }elseif( $task['status'] == '0' ) { 
                                                if( $projnames[$task->proj_id]['proj_manager'] == $current_user->ID ) { 
                                                    echo '<span>Mark as Complete</span>' . $t8_pm_complete_cbox;
                                                }elseif( $task['assign']  == $current_user->ID ) { 
                                                    echo $t8_pm_submit_cbox;
                                                }else{
                                                    echo 'Incomplete';	
                                                } 
                                            }elseif( $task['status'] == '1' ) { 
                                                if( $task['proj-man'] == $current_user->ID ) { 
                                                    echo '<span>Approve as Complete</span>' . $t8_pm_complete_cbox;
                                                }else{
                                                    echo $t8_pm_inreview_cbox;	
                                                }
                                            }elseif( $task['status'] == '2' ) { 
                                                if( $task['proj-man'] == $current_user->ID || $task['assign']  == $current_user->ID ) { 
                                                    echo $t8_pm_uncomplete_cbox;
                                                }
                                                echo '<span>Completed</span>';
                                            }else{ echo 'status: ' . $task['status']; }
                                        ?>
                                        </div>
                                    </div>
                                </div>
                            <?php } ?>
                        <?php } ?>
                        </div>
                    </div>
                     <div class="rightcol">
                        <div class="flyoutwrap"></div>
                    </div>
                    <?php } // end if( ($showday[0] + 60 ) > $today[0] ) ?>
                </div>
            </div>
            <div id="punched-tasks" class="dashboard">
                 <h3 class="th">Punched Time</h3>
                <?php if( !empty( $punched_tasks) && !empty($t8_pm_day_tasks) ) {
                        foreach ($punched_tasks as $tid) { // use dayplanner task array as key for pulling out queried tasks
                            $task = $t8_pm_punched[$tid];
                            $startNtime = date("g:i a", $task['start_time']);
                            $endNtime = date("g:i a", $task['end_time']);
                            echo '<div class="dtask" data-proj-id="'. $task['proj_id'] .'" data-id="'. $task['task_id'] .'" data-cli="'. $task['cli_id'] .'" data-hours="'. $task['hours'] .'">
                                    <h3><span class="cli-span">'. $clients[$task['cli_id']]['name'] .'</span>::<span class="proj-span">'. $projnames[$task['proj_id']]['name'] .'</span>::
                                    <span class="rdts">'. $task['hours'] .' hrs</span></h3>
                                    <p>
                                        <span class="task-title">'. $t8_pm_day_tasks[$task['task_id']]['task-title'] .'</span>
                                        <span class="rdts">'. $startNtime .' - '. $endNtime .'</span>
                                    </p>
                                    <div class="send2pc dact">O</div>
                                </div>';
                        } 
                    }else{ ?>
                <div class="empty">
                    <h3>No Punched Time on this day, yet.</h3>
                </div>
                <?php } ?>
            </div>
        </div>
</div>
</div>
<?php
//eof