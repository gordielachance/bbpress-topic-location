<?php

/**
* Output the location of the post (address,latitude,longitude,input when geo-located)

* @param int $post_id Optional. Post id
* @uses bbptl_get_post_info() To get the post location
*/
function bbptl_post_info( $post_id = 0, $infokey='Address' ) {
        echo bbptl_get_post_info( $post_id, $infokey );
}

/**
* Return the location of the post (address,latitude,longitude,input when geo-located)
*
* @param int $post_id Optional. Post id
* @uses bbptl_get_location_raw() To get the location infos
* @uses apply_filters() Calls 'bbptl_get_location' with the address,reply id and location infos
* @return string Address
*/
function bbptl_get_post_info( $post_id = 0,$infokey='Address' ) {
    global $post;
    if(!$post_id) $post_id = $post->ID;

    $location = bbptl_get_location_raw($post_id);

    if (!$location) return false;
    
    if(!isset($location[$infokey])) return false;

    return apply_filters( 'bbptl_get_post_info', $location[$infokey], $post_id,$location );
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
        
        //TO FIX TO CHECK

	// Check query
	if ( !empty( $wp_query->bbptl_is_search ) && ( true == $wp_query->bbptl_is_search ) )
		$retval = true;

	// Check query name
	if ( empty( $retval ) && bbp_is_query_name( 'bbptl_is_search' ) )
		$retval = true;

	// Check $_GET
	if ( empty( $retval ) && 
                (
                    ((isset( $_GET[bbp_topic_location()->lat_rewrite_id] ) )&&isset( $_GET[bbp_topic_location()->lng_rewrite_id] ))

                    ||(isset( $_GET[bbp_topic_location()->addr_rewrite_id]))
                    )
                )
		$retval = true;

	return (bool) apply_filters( 'bbptl_is_search', $retval );
}

/**
 * Output the searched latitude
 *
 *
 * @param string $lat Optional. Search latitude
 * @uses bbptl_get_search_latitude() To get the search latitude
 */
function bbptl_search_latitude( $latitude = '' ) {
	echo bbptl_get_search_latitude( $latitude );
}

	/**
	 * Get the searched latitude
	 *
	 *
	 * If latitude is supplied, it is used. Otherwise check the
	 * search rewrite id query var.
	 *
	 * @param string $latitude Optional. Search latitude
	 * @uses sanitize_title() To sanitize the latitude
	 * @uses get_query_var*( To get the latitude from query var 'bbptl_lat'
	 * @return bool|string Latitude on success, false on failure
	 */
	function bbptl_get_search_latitude( $latitude = '' ) {

		$latitude = !empty( $latitude ) ? sanitize_title( $latitude ) : get_query_var( bbp_topic_location()->lat_rewrite_id );

		if ( !empty( $latitude ) )
			return $latitude;

		return false;
	}
        
/**
 * Output the searched longitude
 *
 *
 * @param string $lng Optional. Search longitude
 * @uses bbptl_get_search_longitude() To get the search longitude
 */
function bbptl_search_longitude( $longitude = '' ) {
	echo bbptl_get_search_longitude( $longitude );
}

	/**
	 * Get the searched longitude
	 *
	 *
	 * If longitude is supplied, it is used. Otherwise check the
	 * search rewrite id query var.
	 *
	 * @param string $longitude Optional. Search longitude
	 * @uses sanitize_title() To sanitize the longitude
	 * @uses get_query_var*( To get the longitude from query var 'bbptl_lng'
	 * @return bool|string Latitude on success, false on failure
	 */
	function bbptl_get_search_longitude( $longitude = '' ) {

		$longitude = !empty( $longitude ) ? sanitize_title( $longitude ) : get_query_var( bbp_topic_location()->lng_rewrite_id );

		if ( !empty( $longitude ) )
			return $longitude;

		return false;
	}

/**
 * Output the searched distance
 *
 *
 * @param string $dist Optional. Search distance
 * @uses bbptl_get_search_distance() To get the search distance
 */
function bbptl_search_distance( $distance = '' ) {
	echo bbptl_get_search_distance( $distance );
}

	/**
	 * Get the searched distance
	 *
	 *
	 * If distance is supplied, it is used. Otherwise check the
	 * search rewrite id query var.
	 *
	 * @param string $distance Optional. Search distance
	 * @uses sanitize_title() To sanitize the distance
	 * @uses get_query_var*( To get the distance from query var 'bbptl_dist'
	 * @return bool|string Latitude on success, false on failure
	 */
	function bbptl_get_search_distance( $distance = '' ) {

		$distance = !empty( $distance ) ? sanitize_title( $distance ) : get_query_var( bbp_topic_location()->dist_rewrite_id );

		if (empty( $distance ) ){
                    return bbp_topic_location()->distance;
                }
                
                return $distance;
	}
        
/**
 * Output the searched address
 *
 *
 * @param string $dist Optional. Search address
 * @uses bbptl_get_search_address() To get the search address
 */
function bbptl_search_address( $address = '' ) {
	echo bbptl_get_search_address( $address );
}

	/**
	 * Get the searched address
	 *
	 *
	 * If address is supplied, it is used. Otherwise check the
	 * search rewrite id query var.
	 *
	 * @param string $address Optional. Search address
	 * @uses sanitize_title() To sanitize the address
	 * @uses get_query_var*( To get the address from query var 'bbptl_dist'
	 * @return bool|string Latitude on success, false on failure
	 */
	function bbptl_get_search_address( $address = '' ) {

		$address = !empty( $address ) ? sanitize_title( $address ) : get_query_var( bbp_topic_location()->addr_rewrite_id );

		if (!empty( $address ) ){
                    return $address;
                }
                
                return false;
	}
  
function bbptl_unit_name() {
    echo bbptl_get_unit_name();
}
      
    /**
     * Get the geo unit name (kilometers, miles, ...)
     * @return type
     */
    function bbptl_get_unit_name() {
        $unit = bbptl_get_current_unit();
        return $unit['name'];
    }

function bbptl_post_type_list($post_types) {
    echo bbptl_get_post_type_list($post_types);
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
    

/**
 * Field to save the geolocation for posts
 * @global type $post
 */
function bbptl_save_post_geolocation_field(){
                global $post;
                $post_obj = get_post_type_object( $post->post_type );
    ?>
    <p class="bbptl_location_field clearable">
            <label for="bbptl_location"><?php _e('Location','bbptl' );?>:</label><br />
            <input type="text" id="bbptl_location" value="<?php bbp_topic_location()->form_topic_location(); ?>" tabindex="<?php bbp_tab_index(); ?>" size="40" name="bbptl_location"/>
    </p>
    <?php
}

/**
 * Field to search posts with an address.  Should be almost exactly the same function than above.
 * @global type $post
 */
function bbptl_search_posts_geolocation_field(){
                global $post;
                $post_obj = get_post_type_object( $post->post_type );
    ?>
    <p class="bbptl_location_field clearable">
            <label for="<?php echo bbp_topic_location()->addr_rewrite_id;?>"><?php _e('Location','bbptl' );?>:</label><br />
            <input type="text" id="<?php echo bbp_topic_location()->addr_rewrite_id;?>" value="<?php bbptl_search_address(); ?>" tabindex="<?php bbp_tab_index(); ?>" name="<?php echo bbp_topic_location()->addr_rewrite_id;?>"/>
    </p>
    <?php
}
   
?>
