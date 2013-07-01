<?php

/**
 * Retrieve the name of the highest priority template file that exists.
 *
 * Searches in STYLESHEETPATH/$plugin_dirname and TEMPLATEPATH/$plugin_dirname, then in the plugin templates dir.
 *
 * @param string|array $template_names Template file(s) to search for, in order.
 * @param bool $load If true the template file will be loaded if it is found.
 * @param bool $require_once Whether to require_once or require. Default true. Has no effect if $load is false.
 * @return string The template filename if one is located.
 */

function bbptl_locate_template( $template_names, $load = false, $require_once = false ) {

	$located_template = '';
	foreach ( (array) $template_names as $template_name ) {
		if ( ! $template_name )
			continue;
      
                $chunks = explode(WP_PLUGIN_DIR,bbp_topic_location()->plugin_dir);
                $plugin_dirname = $chunks[1];

		if ( file_exists( STYLESHEETPATH . $plugin_dirname . $template_name ) ) {
			$located_template = STYLESHEETPATH . $plugin_dirname . $template_name;
                        
			break;
		} else if ( file_exists( TEMPLATEPATH . $plugin_dirname . $template_name ) ) {
			$located_template = TEMPLATEPATH . $plugin_dirname . $template_name;
			break;
		} else if ( file_exists( bbp_topic_location()->templates_dir . $template_name ) ) {
                        $located_template = bbp_topic_location()->templates_dir . $template_name;
			break;
		}
	}

	if ( $load && ( !empty( $located_template ))){

            load_template( $located_template, $require_once );

        }

	return $located_template;
}

/**
 * Get the geolocation information for a post
 * @global type $post
 * @param type $post_id
 * @return boolean
 */

function bbptl_get_location_raw( $post_id = 0 ) {

    global $post;
    if(!$post_id) $post_id = $post->ID;

    $geo_info = get_post_meta($post_id,'_bbptl_info',true);
    $lat = get_post_meta($post_id,'_bbptl_lat',true);
    $long = get_post_meta($post_id,'_bbptl_lng',true);

    if ((!$lat) || (!$long)) return false;

    $location = $geo_info;
    $location['Latitude'] = $lat;
    $location['Longitude'] = $long;

    return apply_filters( 'bbptl_get_location_raw',$location, $post_id);
}

/**
 * Calculate distance between two points
 * @param type $lat1
 * @param type $lon1
 * @param type $lat2
 * @param type $lon2
 * @param type $round
 * @return type
 */

 function bbptl_get_distance($lat1, $lng1, $lat2, $lng2, $round=true) { 
    // Convert degrees to radians
    $lat1 = deg2rad($lat1);
    $lng1 = deg2rad($lng1);
    $lat2 = deg2rad($lat2);
    $lng2 = deg2rad($lng2);
    
    $current_unit = bbptl_get_current_unit();
    $mult_factor = $current_unit['factor'];
    $radius = bbp_topic_location()->earth_radius_miles/$mult_factor;

    // Calculate the distance
    $distance = $radius * acos(
            cos($lat2) * cos($lng2) * cos($lat1) * cos($lng1) +
            cos($lat2) * sin($lng2) * cos($lat1) * sin($lng1) +
            sin($lat2) * sin($lat1));

    $distance = is_nan($distance) ? 0 : $distance;

    if($round) $distance = round($distance,1);

    return $distance;
}


function bbptl_get_current_unit(){
    $current_geo_unit = bbp_topic_location()->current_geo_unit;
    $geo_units = bbp_topic_location()->geo_units;
    
    return $geo_units[$current_geo_unit];
}

?>
