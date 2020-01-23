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

                $chunks = explode(WP_PLUGIN_DIR,bbptl()->plugin_dir);
                $plugin_dirname = $chunks[1];

		if ( file_exists( STYLESHEETPATH . $plugin_dirname . $template_name ) ) {
			$located_template = STYLESHEETPATH . $plugin_dirname . $template_name;

			break;
		} else if ( file_exists( TEMPLATEPATH . $plugin_dirname . $template_name ) ) {
			$located_template = TEMPLATEPATH . $plugin_dirname . $template_name;
			break;
		} else if ( file_exists( bbptl()->templates_dir . $template_name ) ) {
                        $located_template = bbptl()->templates_dir . $template_name;
			break;
		}
	}

	if ( $load && ( !empty( $located_template ))){

            load_template( $located_template, $require_once );

        }

	return $located_template;
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

    $current_unit = bbptl_get_current_unit_obj();
    $mult_factor = $current_unit['factor'];
    $radius = bbptl()->earth_radius_miles/$mult_factor;

    // Calculate the distance
    $distance = $radius * acos(
            cos($lat2) * cos($lng2) * cos($lat1) * cos($lng1) +
            cos($lat2) * sin($lng2) * cos($lat1) * sin($lng1) +
            sin($lat2) * sin($lat1));

    $distance = is_nan($distance) ? 0 : $distance;

    if($round) $distance = round($distance,1);

    return $distance;
}

function bbptl_get_current_unit_obj(){

    $available = bbptl()->geo_units;
    $selected = bbptl()->get_option( '_bbptl_geo_unit');

    foreach($available as $unit){
        if ($unit['slug'] == $selected) return $unit;
    }

}

/*
Is secure origin ? Required for HTML Geolocation API
https://github.com/gordielachance/bbpress-topic-location/issues/2
chrome://flags/#unsafely-treat-insecure-origin-as-secure
*/
function bbptl_is_secure_origin() {
			(!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
			|| $_SERVER['SERVER_PORT'] == 443;
}

/**
 * Check if current page is a search page
 *
 * @since bbPress (r4579)
 *
 * @global WP_Query $wp_query To check if WP_Query::bbp_is_search is true
 * @uses bbp_is_query_name() To get the query name
 * @return bool Is it a search page?
 */
function bbptl_is_search() {
	global $wp_query;

    if(!bbp_is_search()) return false;

	// Assume false
	$retval = false;

	// Check query
	if ( !empty( $wp_query->bbptl_is_search ) && ( true == $wp_query->bbptl_is_search ) )
		$retval = true;

	// Check query name
	if ( empty( $retval ) && bbp_is_query_name( 'bbptl_is_search' ) )
		$retval = true;

	// Check $_GET
	if ( empty( $retval ) &&
        (
            ((isset( $_GET[bbptl()->lat_rewrite_id] ) )&&isset( $_GET[bbptl()->lng_rewrite_id] ))

            ||(isset( $_GET[bbptl()->addr_rewrite_id]))
            )
        )
		$retval = true;

	return (bool) apply_filters( 'bbptl_is_search', $retval );
}

/**
 * Returns the post types formatted (eg. 'forums, topics and replies');
 * @return string
 */

function bbptl_get_post_type_list($post_types){

    if(!$post_types) return false;

    foreach ((array)$post_types as $post_type){
        $post_obj = get_post_type_object( $post_type );
        $names[]=$post_obj->label;
    }

    if(count($names)>1){
        $start = array_slice($names, 0,count($names)-1);
        $end = end($names);

        $string = sprintf(__('%1s and %2s','bbptl'),implode(', ',$start),$end);
    }else{
        $string = $names[0];
    }

    return strtolower($string);
}

?>
