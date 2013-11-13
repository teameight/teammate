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
                                    $selected_user = 0;
                                    if(isset($projs[$t8_pm_proj_id]["proj_manager"])) $selected_user = $projs[$t8_pm_proj_id]["proj_manager"];
                                    echo t8_pm_assign_select( $selected_user, true );
                                    ?>
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
		// $t8_pm_proj_mstones = array(); // get ready to build the milestone array, tasks grouped by mstone
		if( isset( $t8_pm_p_tasks ) && !empty( $t8_pm_p_tasks ) ) { 
			foreach( $t8_pm_p_tasks as $tid => $task ){ // sort task array into mstones
				$t8_pm_proj_mstones[$task['stage']]['tasks'][$tid] = $task;
				$t8_pm_p_mskeys[] = $task['stage'];
			}
			foreach( $t8_pm_proj_mstones as $mstone_id => $mstone_name ){ //removes empty milestones ???
				if(	!in_array( $mstone_id, $t8_pm_p_mskeys ) )$t8_pm_p_milestones[$mstone_id] = '';
			}
		} else {
			$t8_pm_proj_mstones = array(
				0 => array(
                    'name' => '',
                    'deadline' => '',
                    'hours' => 0,
                    'tasks' => array(
                        '0' => array(
                                "title" => '',
                                "hours" => 0,
                                "assign" => 0,
                                "status" => 0,
                            )
                        )
                    )
			);		
		} 
        //echo '<pre>'; print_r($t8_pm_proj_mstones); echo '</pre>';

        t8_pm_mstone_form_table($t8_pm_proj_mstones);

?>
        </div>
        <p><button id="add-mstone"<?php if($_GET['action'] == "edit") echo ' class="editproj"'; ?> type="button">Add Milestone</button></p>
        <input type="hidden" name="write-proj" value="<?php echo $t8_pm_action; ?>" />
        <input type="hidden" name="t8_pm_proj_id" value="<?php echo $t8_pm_proj_id; ?>" />
        <p class="submit clear">
            <input type="submit" class="button-primary" value="<?php if($_GET['action'] == "edit") echo 'Save Changes'; else echo 'Create Project'; ?>" />
            &nbsp;&nbsp;&nbsp;<a href="<?php echo admin_url( 'admin.php?page=t8-teammate/t8-teammate.php_projects' ); ?>">Cancel</a>
        </p>
    </form>
</div>
<?php 