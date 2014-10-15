<?php

/**
 * Check wheter or not a post as a location attached.
 * @param type $post_id
 * @return boolean
 */
function bbptl_post_has_geo($post_id = false){
    if ($post_location = bbptl_get_location_obj($post_id)) return true;
    return false;
}

/**
 * Return the address of a geo-located post, and allow filters on it.
 * Filters can be used here to mask the real address, etc.
 * @param type $post_id
 */
function bbptl_get_post_address($post_id = false){
    if ($post_location = bbptl_get_location_obj($post_id)){
        return apply_filters('bbptl_get_post_address',$post_location['Address']);
    }
    
}
    
    function bbptl_post_address($post_id = false){
        echo bbptl_get_post_address($post_id);
    }
    
/**
 * Return the latitude of a geo-located post, and allow filters on it.
 * @param type $post_id
 */
function bbptl_get_post_latitude($post_id = false){
    if ($post_location = bbptl_get_location_obj($post_id)) {
        return apply_filters('bbptl_get_post_latitude',$post_location['Latitude']);
    }
    
}

    function bbptl_post_latitude($post_id = false){
        echo bbptl_get_post_latitude($post_id);
    }
   
/**
 * Return the latitude of a geo-located post, and allow filters on it.
 * @param type $post_id
 */
function bbptl_get_post_longitude($post_id = false){
    if ($post_location = bbptl_get_location_obj($post_id)){
        return apply_filters('bbptl_get_post_longitude',$post_location['Longitude']);
    }
    
}

    function bbptl_post_longitude($post_id = false){
        echo bbptl_get_post_longitude($post_id);
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
                    ((isset( $_GET[bbptl()->lat_rewrite_id] ) )&&isset( $_GET[bbptl()->lng_rewrite_id] ))

                    ||(isset( $_GET[bbptl()->addr_rewrite_id]))
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

		$latitude = !empty( $latitude ) ? sanitize_title( $latitude ) : get_query_var( bbptl()->lat_rewrite_id );

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

		$longitude = !empty( $longitude ) ? sanitize_title( $longitude ) : get_query_var( bbptl()->lng_rewrite_id );

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

		$distance = !empty( $distance ) ? sanitize_title( $distance ) : get_query_var( bbptl()->dist_rewrite_id );

		if (empty( $distance ) ){
                        return $selected = bbptl()->get_option( '_bbptl_distance');
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

		$address = !empty( $address ) ? sanitize_title( $address ) : get_query_var( bbptl()->addr_rewrite_id );

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
        $unit = bbptl_get_current_unit_obj();
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
            <input type="text" id="bbptl_location" value="<?php bbptl()->form_topic_location(); ?>" tabindex="<?php bbp_tab_index(); ?>" size="40" name="bbptl_location"/>
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
            <label for="<?php echo bbptl()->addr_rewrite_id;?>"><?php _e('Location','bbptl' );?>:</label><br />
            <input type="text" id="<?php echo bbptl()->addr_rewrite_id;?>" value="<?php bbptl_search_address(); ?>" tabindex="<?php bbp_tab_index(); ?>" name="<?php echo bbptl()->addr_rewrite_id;?>"/>
    </p>
    <?php
}

function bbptl_location_html($post_id = false){
    echo bbptl_get_location_html($post_id);
}

function bbptl_get_location_html($post_id = false){
        global $post;
        $bbp = bbpress();
        if (!$post_id) $post_id = $post->ID;
        
        if( !bbptl_post_has_geo($post_id) ) return false;
        
        
        
        $post_location = bbptl_get_location_obj($post_id);

        ob_start();

        ?>
        <p class="bbp-topic-meta bbptl-post-location">
            <span class="bbptl-post-address"><?php bbptl_post_address($post_id); ?></span>
            
            <?php
            
            //display distance from input location
            if( isset($bbp->search_query) && method_exists($bbp->search_query,'get') && ($origin_point = $bbp->search_query->get('bpptl_origin_point'))){

                if ( $distance = bbptl_get_distance($origin_point['Latitude'],$origin_point['Longitude'],$post_location['Latitude'],$post_location['Longitude']) ){
                    ?>
                    <span class="bbptl-post-distance"><?php printf(__('(at %1$s %2$s)','bbpts'),$distance,bbptl_get_unit_name());?></span>
                    <?php
                }

            }
            ?>
                
        </p>
        <?php
        
        $output = ob_get_contents();
        ob_end_clean();
        return apply_filters('bbptl_get_location_html',$output,$post_id);

    }


   
?>
