<?php ?>
<div class="wrap t8-pm">
    <?php if(isset($t8_pm_warning)){ ?><div id="message" class="error"><?php echo $t8_pm_warning; ?></div><?php } ?>
    <?php if(isset($t8_pm_updated)){ ?><div id="message" class="updated"><?php echo $t8_pm_updated; ?></div><?php } ?>
<?php	// 
	if(isset($_POST['manage-cli'])){
		global $wpdb;
		$t8_pm_cli_table = $wpdb->prefix . "pm_cli";
		if($_POST["manage-cli"]=="create"){
			$t8_pm_cli_name = esc_html($_POST['t8_pm_client_name']);
			$sql = "SELECT id FROM ".$t8_pm_cli_table." WHERE name = '".$t8_pm_cli_name."'"; // see if Client name exists
			$match = $wpdb->get_results( $sql );
			if(!$match){ // Dept name does not exist
				$results1 = $wpdb->insert( $t8_pm_cli_table, array( 'name' => $t8_pm_cli_name ) );
				// $dept_id = $wpdb->insert_id; // grab the generated id for new dept
				$t8_pm_updated .= "<p>".$t8_pm_cli_name." Client has been created.</p>";
			}else{ // Dept name does exist
				$t8_pm_warning .= "<p>Client name already exists.</p>";
			}
		}elseif($_POST["manage-cli"]=="edit"){
			$t8_pm_cli_name = esc_html($_POST['t8_pm_client_name']);
			$t8_pm_cli_id = esc_html($_POST['t8_pm_cli_id']);
			if($_POST['submit'] === 'Delete'){
				if($t8_pm_cli_id!=''){
					$sql = "DELETE FROM ".$t8_pm_cli_table." WHERE id = '".$t8_pm_cli_id."'"; // delete it
					$match = $wpdb->query( $wpdb->prepare( $sql ));
					if($match) $t8_pm_updated .= "<p>Client has been deleted.</p>";
					$t8_pm_proj_table = $wpdb->prefix . "pm_projects";
					$results = $wpdb->update( $t8_pm_proj_table, array( 'cli_id' => "1" ), array( 'cli_id' => $t8_pm_cli_id ) );
					if($results) $t8_pm_updated .= "<p>Projects have been moved to Internal.</p>";
				}else{ 
					$t8_pm_warning .= "<p>Client could not be deleted.</p>";
				}
			}else{ // not Delete, Edit
				if($t8_pm_cli_name!=''){
					$sql = "SELECT id FROM ".$t8_pm_cli_table." WHERE name = '".$t8_pm_cli_name."'"; // see if Department name exists
					$match = $wpdb->get_results( $sql );
					if(!$match){ // Cli name does not exist, can change to new name
						$results = $wpdb->update( $t8_pm_cli_table, array( 'name' => $t8_pm_cli_name ), array( 'id' => $t8_pm_cli_id ) );
						if($results) $t8_pm_updated .= "<p>".$t8_pm_cli_name." Client has been updated.</p>";
					}else{ // Dept name does exist
						$t8_pm_warning .= "<p>Client name already exists.</p>";
					}
				}else{ // Not chnging name so just use id to update users list
					$t8_pm_warning .= "<p>New Client name was blank.</p>";
				}
			}
		}elseif($_POST["manage-cli"]=="delete"){
			$t8_pm_id = esc_html($_POST['t8_pm_del_dept']);
			if($t8_pm_id!=''){
				$sql = "DELETE FROM ".$t8_pm_dept_table." WHERE id = '".$t8_pm_id."'"; // delete it
				$match = $wpdb->query( $wpdb->prepare( $sql ));
				$t8_pm_updated .= "<p>Department has been deleted.</p>";
			}else{ 
				$t8_pm_warning .= "<p>Department could not be deleted.</p>";
			}
		}else{
			$t8_pm_warning .= "<p>Department could not be edited.</p>";
		}
	} //end if $_POST
	include_once( plugin_dir_path(__FILE__).'t8-lists.php' ); //load in lists 
	$t8_user_select = array();  //build user select list
	if($pm_users){ foreach($pm_users as $pm_userid => $pm_user){ $t8_user_select[] = '<option value="'.$pm_userid.'">'.$pm_user["uname"].'</option>'; } }
?>
    <?php if(isset($t8_pm_warning)){ ?><div id="message" class="error"><?php echo $t8_pm_warning; ?></div><?php } ?>
    <?php if(isset($t8_pm_updated)){ ?><div id="message" class="updated"><?php echo $t8_pm_updated; ?></div><?php } ?>
    <?php 	
		$cli_status = 0;
		$cli_statuses = array(
			0 => 'active',
			1 => 'retired'
		);
		$cli_action = array(
			0 => 'retire',
			1 => 'activate'
		);
		if( isset($_GET['status']) ) $cli_status = intval( $_GET['status'] );
	?>
    <h3>Create a Client</h3>
    <form id="create-client" name="create-client" method="post" >
        <table class="form-table">
            <tr valign="top">
                <th scope="row"><label for="t8_pm_client_name">Client Name </label></th>
                <td>
                    <input name="t8_pm_client_name" maxlength="6" placeholder="Client" type="text" />
                    <p class="description">Six characters max</p>
                </td>
            </tr>
        </table>
        <input type="hidden" name="manage-cli" value="create">
         <p class="submit">
            <input class="button-primary" type="submit" value="Create Client" name="submit">
        </p>
    </form>
    <h3><?php echo $cli_statuses[$cli_status]; ?> Clients</h3>
    <?php if(isset( $_GET['status'] ) && $_GET['status'] == '1' ){ ?>
    <a href="<?php echo admin_url( 'admin.php' ); ?>?page=t8-teammate/t8-teammate.php_mgmt&tab=clients">Veiw Active Clients</a>
    <?php } else { ?>
    <a href="<?php echo admin_url( 'admin.php' ); ?>?page=t8-teammate/t8-teammate.php_mgmt&tab=clients&status=1">Veiw Retired Clients</a>
    <?php } ?>
    <table class="wp-list-table widefat fixed posts" cellspacing="0">
        <thead>
            <tr>
                <th scope='col' id='cb' class='manage-column column-cb check-column'  style=""><input type="checkbox" /></th>
                <th scope='col' id='name' class='manage-column column-name desc'  style="">
                    <span>Name</span>
                </th>
                <th scope='col' class='manage-column column-stats num'  style="">
                    <span>Collected</span>
                </th>
                <th scope='col' class='manage-column column-stats num'  style="">
                    <span>Overall</span>
                </th>
            </tr>
        </thead>
        <tfoot>
            <tr>
                <th scope='col' id='cb' class='manage-column column-cb check-column'  style=""><input type="checkbox" /></th>
                <th scope='col' id='name' class='manage-column column-name desc'  style="">
                    <span>Name</span>
                </th>
                <th scope='col' class='manage-column column-stats num'  style="">
                    <span>Collected</span>
                </th>
                <th scope='col' class='manage-column column-stats num'  style="">
                    <span>Overall</span>
                </th>
            </tr>
        </tfoot>
        <tbody id="the-list">
    <?php 
    if ( !empty($clients) ) {
    	foreach($clients as $client_id => $client) { if( $client["status"] == $cli_status ) { ?>
            <tr valign="top">
                <th scope="row" class="check-column"><input type="checkbox" name="post[]" value="1330" /></th>
                <form name="edit-cli-<?php echo $client_id; ?>" method="post" >
                <td class="cli-name column-title">
                    <strong><a class="cli-edit"><?php echo $client["name"];?></a></strong>
                    <div class="hidden">
                    	<input name="t8_pm_client_name" type="text" maxlength="5" />
                        <input type="hidden" name="manage-cli" value="edit">
                        <input class="button-primary" type="submit" value="Edit Client" name="submit">
                    </div>
                    <div class="row-actions">
                    	<a href="#" class="cli-status <?php echo $cli_action[$client["status"]]; ?>"><?php echo $cli_action[$client["status"]]; ?></a> | 
                        <input type="hidden" name="t8_pm_cli_id" value="<?php echo $client_id; ?>">
                        <input class="delete" type="submit" value="Delete" name="submit">
                    </div>
                </td>
                <td class="column num"><?php
				 $cliprojs_price = array();
				 $cliproj_results = $wpdb->get_results("SELECT id, name, price FROM ".$wpdb->prefix ."pm_projects WHERE cli_id = $client_id AND status != 1 AND status != 5" );
				 if($cliproj_results){ foreach($cliproj_results as $cliproj){ // build array with id as key
					$cliprojs_price[] = $cliproj->price;
				}}
				//echo '<pre>'; print_r( $cliproj_results ); echo '</pre>';
				 ?></td>
                <td class="column num">
                     <?php  echo '$'. number_format( array_sum( $cliprojs_price ) ); ?>
                </td>
                </form>
            </tr>
    <?php 
        }}
    } ?>
        </tbody>
    </table>
</div>
<?php
//eof