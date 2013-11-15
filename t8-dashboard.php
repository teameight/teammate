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
								if( is_array($tasks) ){
									foreach($tasks as $tasktype => $taskr ) {
										foreach( $taskr as $bumindex => $task ) {
											if( !isset($dayplanner[$year2day][$day2day][$tasktype]) ) $dayplanner[$year2day][$day2day][$tasktype] = array();
											if ( !in_array( $task, $dayplanner[$year2day][$day2day][$tasktype])) 
												$dayplanner[$year2day][$day2day][$tasktype][] = $task; // update these old todos to today
										}
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
					$t8_pm_punched[$punched->id] = array(
						'task_id' 		=> $punched->task_id,
						'proj_id' 		=> $punched->proj_id,
						'cli_id' 		=> $punched->cli_id,
						'start_time' 	=> $punched->start_time,
						'end_time' 		=> $punched->end_time,
						'hours' 		=> $punched->hours
					);
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
						$task_due = ($task->due != '' ? $task->due : $projnames[$task->proj_id]['end_date']);
						$days_left = human_time_diff( $today[0], strtotime($task_due) ); // old: ceil( (strtotime($task_due) - $today[0])/(60*60*24) ); 
						
						$t8_pm_day_tasks[$task->id] = array(
						    'title' 		=> $task->task_title,  
						    'cli-id' 		=> ($task->cli_id != 0 ? $task->cli_id : ''),
						    'proj-id' 		=> ($task->proj_id != 0 ? $task->proj_id : ''),
						    'cli-name' 		=> ($task->cli_id != 0 ? $clients[$task->cli_id]['name'] : ''),  
						    'proj-name' 	=> ($task->proj_id != 0 ? $projnames[$task->proj_id]['name'] : ''),  
						    'assign' 		=> $task->assign,
						    'stage' 		=> $task->stage,
						    'status' 		=> $task->status,
						    'due' 			=> $task_due,
						    'hours' 		=> $task->est_hours,
						    'days-left' 	=> $days_left 
						);
					}
				}
			}

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
			$t8_pm_cpt_sels = t8_pm_cli_proj_task_selects( 1, $punchin['cli'], $punchin['proj'], $punchin['task'] );
			//$punchin = $t8_pm_cpt_sels['punchin'];
		} else {
			$t8_pm_cpt_sels = t8_pm_cli_proj_task_selects( 1 );
		} 
		if( !isset($punchin['task']) ) {
			$punchin['task'] = 0;
		}
		?>
	<div id="dashboard-wrap" class="cf">
<?php 
		if( ($showday[0] + 60 ) > $today[0] ) { 
?>
		<div class="dashboard all-tasks">
            <h3 class="th">Your Tasks</h3>
            <div class="container">
	            <div class="list sort" id="all-your-tasks">
<?php 
        	if( !empty( $schedTasks ) && !empty($t8_pm_day_tasks) ) {
                foreach ($schedTasks as $tid) { // use dayplanner task array as key for pulling out queried tasks
					if(!isset($dayplanner[$year][$day]['task'])) $dayplanner[$year][$day]['task'] = array();
					if( !in_array( $tid, $dayplanner[$year][$day]['task'] ) ){ 
                        $taskR = array();
                        $taskR[$tid] = $t8_pm_day_tasks[$tid];
						t8_pm_dtask( $taskR, $punchin['task'] );	
                    }
                }
            } else { 
?>
					<div class="empty<?php echo (!empty($t8_pm_day_tasks) ? ' hidden' : ''); ?>">
						<h3>Not much to do.</h3>
					</div>
<?php
	        }
?>
	            </div>
            </div>
            <h3 class="th">Common Tasks</h3>
            <div class="container">
                <div class="list sort" id="common-tasks">
<?php 
			if( !empty( $commonTasks ) && !empty($t8_pm_day_tasks) ) {
                foreach ($commonTasks as $tid) { // use dayplanner task array as key for pulling out queried tasks
					if(!isset($dayplanner[$year][$day]['task'])) $dayplanner[$year][$day]['task'] = array();
					if( !in_array( $tid, $dayplanner[$year][$day]['task'] ) ){ 
                        $taskR = array();
                        $taskR[$tid] = $t8_pm_day_tasks[$tid];
						t8_pm_dtask( $taskR, $punchin['task'] );
					}
                }
       		} else { 
?>
				<div class="empty<?php echo (!empty($t8_pm_day_tasks) ? ' hidden' : ''); ?>">
					<h3>Not a lot going on</h3>
				</div>
<?php
        	}
?> 
               </div>
            </div>
        </div>
<?php 
		} // end if( ($showday[0] + 60 ) > $today[0] ) 
?>
        <div class="today-wrap">
        	<a class="prevday" href="<?php echo add_query_arg('d',$prevday); ?>" title="Previous Day">previous day</a>
        	<a class="nextday" href="<?php echo add_query_arg('d',$nextday); ?>" title="Next Day">next day</a>
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
<?php 
		if( ($showday[0] + 60 ) > $today[0] ) { 
?>
                    <div class="leftcol">
                        <h3 class="th">Planner <a class="add orphan" href="">+</a></h3>
                        <div class="list sort" id="planner">
                            <div class="empty<?php echo (!empty($t8_pm_day_tasks) ? ' hidden' : ''); ?>">
                                <h3>Drag Tasks Here to Start</h3>
                            </div>
							<?php
	 		if( !empty( $dayplanner[$year][$day]['task']) && !empty($t8_pm_day_tasks) ) {
	            foreach ($dayplanner[$year][$day]['task'] as $tid) { // use dayplanner task array as key for pulling out queried tasks
	                $taskR = array();
	                $taskR[$tid] = $t8_pm_day_tasks[$tid];
					t8_pm_dtask( $taskR, $punchin['task'] );
	            }
	        }
							?>
                        </div>
                    </div>
                    <div class="rightcol">
                        <div class="flyoutwrap"></div>
                    </div>
<?php 
		} // end if( ($showday[0] + 60 ) > $today[0] ) ?>
                </div>
            </div>
            <div id="punched-tasks" class="dashboard">
                <h3 class="th">Punched Time</h3>
				<?php 
		if( !empty( $punched_tasks) && !empty($t8_pm_day_tasks) ) {
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
                    }else{ 
				?>
                <div class="empty">
                    <h3>No Punched Time on this day, yet.</h3>
                </div>
<?php 
					} 
?>
            </div>
        </div>
	</div>
</div>
<?php
//eof