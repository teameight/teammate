<?php ?>
	<div class="wrap create-project t8-pm created">
        <?php if( isset( $t8_pm_warning ) ){ ?><div id="message" class="error"><?php echo $t8_pm_warning; ?></div><?php } ?>
        <?php if( isset( $t8_pm_updated ) ){ ?><div id="message" class="updated"><?php echo $t8_pm_updated; ?></div><?php } ?>
        <form id="build-proj" name="build-proj" class="conted" method="post" >
        <p class="proj_title">
        	<select name="t8_pm_client_id">
            <?php 
                $cpt_sels = t8_pm_cli_proj_task_selects( 1, $t8_pm_client_id ); 
                echo $cpt_sels['cli'];
            ?>
           </select> :: <input type="text" placeholder="Project" name="t8_pm_proj_name" id="t8_pm_proj_name" value="<?php echo $t8_pm_proj_name; ?>">


        </p>
        <div id="proj_status">
            status <select name="t8_pm_proj_status">
                <?php foreach($status_r as $status_key => $status_name){ //
                    $selected = ''; if($status_key == $t8_pm_status) $selected = ' selected="selected"';
                     echo '<option value="'.$status_key.'"'.$selected.'>'.$status_name.'</option>'; 
                } ?>
            </select>
        </div>
        <table>
            <tr valign="top">
                <td>
                    <p class="dates"><input type="text" class="datepicker" value="<?php echo $t8_pm_start_date; ?>" id="addprojstart" name="t8_pm_proj_start" required="required" /> to <input type="text" class="datepicker" value="<?php echo $t8_pm_end_date; ?>" id="addprojend" name="t8_pm_proj_end" required="required" /></p>
                    <div class="budget">	
                            <div id="tot-hours"><?php echo $t8_pm_hoursums; ?>hrs</div> 
                            <div id="price">$<input type="text" value="<?php echo $t8_pm_price; ?>" name="t8_pm_proj_budget" id="t8_pm_proj_budget"><br>
                            <small>Suggested minimum: <strong>$<?php echo $t8_pm_hoursums * 60; //need to move this to plugin options !!! ?></strong><br />
                            (Estimated Hours x $<span>60<?php echo ''; ?></span>)</small></div>
                    </div>
                    <table class="form-table">
                        <tr valign="top">
                            <th scope="row"><label for="t8_pm_proj_manager">Project Manager</label></th>
                            <td>
                                <select name="t8_pm_proj_manager">
									<?php 
                                    if( isset($pm_users) ){ 
                                        foreach( $pm_users as $user_id => $user ){ 
											if( is_numeric($user_id) ){
												if( $t8_pm_proj_id ) $selected = ( $user_id == $projs[$t8_pm_proj_id]["proj_manager"] ? ' selected="selected"' : '' );
												echo '<option value="'.$user_id.'"'.$selected.'>'.$user["uname"].'</option>'; 
											}
                                        } 
                                    } ?>
                                </select>
                            </td>
                        </tr>
                    </table>

                </td>
                <td>
                    <label for="t8_pm_proj_features">Features</label><br>
                    <textarea name="t8_pm_proj_features" cols="60" rows="8"><?php if( isset( $t8_pm_proj_features ) ) echo $t8_pm_proj_features; ?></textarea>
                </td>			
		</table>
        <div id="tasks">
        <?php
		$t8_pm_p_milestones = array(); // get ready to build the milestone array, tasks grouped by mstone
		if( isset( $t8_pm_p_tasks ) && !empty( $t8_pm_p_tasks ) ) { 
			foreach( $t8_pm_p_tasks as $task_key => $task_data ){ // sort task array into mstones
				$t8_pm_p_milestones[$task_data['stage']][$task_key] = $task_data;
				$t8_pm_p_mskeys[] = $task_data['stage'];
			}
			foreach( $t8_pm_proj_mstones as $mstone_id => $mstone_name ){ //removes empty milestones ???
				if(	!in_array( $mstone_id, $t8_pm_p_mskeys ) )$t8_pm_p_milestones[$mstone_id] = '';
			}
		} else {
			$t8_pm_p_milestones = array(
				0 => array(
					'new-0' => array(
							"title" => '',
							"desc" => '',
							"hours" => 0,
							"assign" => '',
							"status" => 0,
						)
					)
			);		
		} 
		foreach($t8_pm_p_milestones as $mstone_id => $task_item){ 
			//get the total task hours
			$thours = 0;
			if(!empty( $task_item ) ) {
				foreach($task_item as $task_key => $task_data){ 
					$thours+= $task_data['hours'];
				}
			} ?>
            <table class="wp-list-table widefat milestone" data-mid="<?php echo $mstone_id; ?>" id="mstone-<?php echo $mstone_id; ?>" cellspacing="0">
                <thead>
                    <tr>
                        <th class="m-title">
                            <?php if( $mstone_id == 0 ) { 
                                echo 'General Tasks <input type="hidden" name="mstone[0][name]" value="0" />'; 
                            } else {?>
                            <input type="text" placeholder="Milestone" class="item-title" name="mstone[<?php echo $mstone_id; ?>][name]" value="<?php if( !empty( $t8_pm_proj_mstones ) ) echo $t8_pm_proj_mstones[$mstone_id]['name']; else echo $mstone_id; ?>" />
                            <?php } ?>
                        </th>
                        <th><input type="text" placeholder="deadline" class="datepicker mstone-deadline" value="<?php if( $t8_pm_proj_mstones[$mstone_id]['deadline'] ) echo $t8_pm_proj_mstones[$mstone_id]['deadline']; else echo $t8_pm_end_date; ?>" name="mstone[<?php echo $mstone_id; ?>][deadline]" /></th>
                        <th class="mstone-hours" ><input type="hidden" size="4" name="mstone[<?php echo $mstone_id; ?>][hours]" value="<?php echo $thours; ?>" /> <span><?php echo $thours; ?></span> hrs</th>
                        <th><?php if( $mstone_id != 0 ){ ?><button class="delete-mstone button" type="button">Delete</button><?php } ?></th>
                    </tr>
                </thead>
                <tfoot>
                    <tr>
                        <th>Task Name</th>
                        <th>Assign</th>
                        <th class="num">Est Hrs</th>
                        <th><button class="add-task button" type="button">Add Task</button></th>
                    </tr>
                </tfoot>
                <tbody class="the-list">
                <?php $i=0;
                if(!empty( $task_item ) ) {
                    foreach($task_item as $task_key => $task_data){
                        $task_num = $task_key+1; $i++; 
                        ?>
                    <tr id="t8-pm-task-<?php echo $task_key; ?>" data-tid="<?php echo $task_key; ?>" class="task <?php if($i%2) echo 'alternate'; ?>" valign="top">
                        <td class="task-title page-title column-title" >
                            <input placeholder="Task" class="item-title" type="text" name="task[<?php echo $task_key; ?>][title]" value="<?php echo $task_data['title']; ?>" required="required" />
                        </td>
                        <td class="task-assign">
                            <input class="item-stage" type="hidden" name="task[<?php echo $task_key; ?>][stage]" value="<?php echo $mstone_id; ?>" />
                    <?php
                    if(isset($task_data['capability'])){ //if is new project, load capabilities from project type
                        arsort($task_data['capability']); // sort cap array to put highest cap at top, keep index assoc.
                        ?>
                        <select name="task[<?php echo $task_key; ?>][assign]">
                        <?php 
                        foreach($task_data['capability'] as $user_id => $cap){
                            reset($task_data['capability']); 
                            echo '<option value="'.$user_id.'" >'.$pm_users[$user_id]["uname"].' ('.$cap.')</option>'; 
                        } // build options from depts array ?>
                        </select>
                        <?php
                    } else { //exsting project, show all staff with assign selected ?>
                        <select name="task[<?php echo $task_key; ?>][assign]">
                        <?php 
                        if( isset( $pm_users ) ){ 
                            foreach( $pm_users as  $user_id => $user ){ 
                            $selected = ''; if($user_id == $task_data['assign']) $selected = ' selected="selected"';
                            echo '<option value="'.$user_id.'"'.$selected.'>'.$user["uname"].'</option>'; 
                        } } // build options from depts array ?>
                        </select>
    <?php			} ?>
                        </td>
                        <td class="task-hours num">
                            <input type="text" class="task-esthours" name="task[<?php echo $task_key; ?>][hours]" value="<?php echo $task_data['hours']; ?>" /> hrs
                        </td>
                        <td class="task-hours">
                            <a class="delete-task" data-tid="<?php echo $task_key; ?>">Delete</a>
                        </td>
                    </tr>
                        <?php
                    } //end foreach $task_item
                } else {
                    
                }// end if task_item ?>
                </tbody>
            </table>    
<?php
		} //end foreach($t8_pm_p_milestones as $mstone_id => $task_item){  ?>
        </div>
        <p><button id="add-mstone"<?php if($_GET['action'] == "edit") echo ' class="editproj"'; ?> type="button">Add Milestone</button></p>
        <input type="hidden" name="write-proj" value="<?php echo $t8_pm_action; ?>" />
        <input type="hidden" name="t8_pm_ptype" value="<?php echo $t8_pm_ptype; ?>" />
        <input type="hidden" name="t8_pm_proj_id" value="<?php echo $t8_pm_proj_id; ?>" />
        <p class="submit clear">
            <input type="submit" class="button-primary" value="<?php if($_GET['action'] == "edit") echo 'Save Changes'; else echo 'Create Project'; ?>" />
            &nbsp;&nbsp;&nbsp;<a href="<?php echo admin_url( 'admin.php?page=t8-teammate/t8-teammate.php_projects' ); ?>">Cancel</a>
        </p>
    </form>
</div>
<?php 