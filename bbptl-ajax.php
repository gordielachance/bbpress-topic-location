<?php

function bbptl_ajax_coords_to_address() {
    
    $lat=$lng=$addr=$geo_input='';

    $json = array(
        'success'=>false
    );

    if(isset($_POST['_bbptl_lat'])) $lat = trim($_POST['_bbptl_lat']);
    if(isset($_POST['_bbptl_lng'])) $lng = trim($_POST['_bbptl_lng']);
    if(isset($_POST['_bbptl_addr'])) $addr = trim($_POST['_bbptl_addr']);
    
    if($lat&&$lng){
        $geo_input=$lat.','.$lng;
    }elseif($addr){
        $geo_input=$addr;
    }

    if(!$geo_input) return $json; //abord
    
    $json['geo_input']=$geo_input;

    $bbptl_geolocation = bbptl()->validate_geolocation($geo_input);
    
    if($bbptl_geolocation){
        $json['success']=true;
        $json = array_merge($bbptl_geolocation,$json);
    }
    
    echo json_encode( $json );
    
    die();
    
}

add_action('wp_ajax_bbptl_coords_to_address', 'bbptl_ajax_coords_to_address');
add_action('wp_ajax_nopriv_bbptl_coords_to_address', 'bbptl_ajax_coords_to_address'); //not logged in

?>
