<?php
$a = $_POST['a'];
 
switch($a) {
    // return result in json format
    case 'case': header("Content-type: application/json");
 
        //
        alert('ajax');
        //
 
        echo json_encode( array('response'=>'success', 'message'=>'Successfully cleared') );
 
    break;
}