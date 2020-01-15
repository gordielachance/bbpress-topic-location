<?php

$bbp = bbpress();
$query = $bbp->search_query;
$origin_point = $query->get('bpptl_origin_point');
$distance = $query->get('bpptl_distance');
$geo_input = $query->get('bpptl_origin_point_input');
$post_types = $query->get('post_type');


if(!$origin_point){ //location could'nt be found
    ?>
    <div class="bbp-template-notice error">
            <p><?php printf(__( "The input location you wanted to search from (%s) wasn't found and has been ignored.", 'bbptl' ),$geo_input); ?></p>
    </div>
    <?php
}else{
    $unit = bbptl_get_current_unit_obj();
    ?>
    <div class="bbp-template-notice">
            <p><?php printf(__( 'This search returns <strong>only</strong> %1s that are geolocated %2s %3s around "%4s"', 'bbptl' ),bbptl_get_post_type_list($post_types),$distance,$unit['name'],$origin_point->input); ?></p>
    </div>
    <?php
}
?>
