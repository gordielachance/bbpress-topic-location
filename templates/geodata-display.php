<?php
global $bbptl_geodata;

$geodata = ($bbptl_geodata) ? $bbptl_geodata : new bbPressTopicLocationGeoData();
$is_bbp_search = ( isset(bbpress()->search_query) && method_exists(bbpress()->search_query,'get') );
$origin_point = bbpress()->search_query->get('bpptl_origin_point');
$unit = bbptl_get_current_unit_obj();
?>

<p class="bbp-topic-meta bbptl-post-location">
    <span class="bbptl-post-address"><?php echo $geodata->input; ?></span>
    <?php
    //display distance from input location
    if( $is_bbp_search && $origin_point && ( $distance = bbptl_get_distance($origin_point->lat,$origin_point->lon,$geodata->lat,$geodata->lon) ) ){
        ?>
        <span class="bbptl-post-distance"><?php printf(__('(at %1$s %2$s)','bbpts'),$distance,$unit['name']);?></span>
        <?php
    }
    ?>
</p>