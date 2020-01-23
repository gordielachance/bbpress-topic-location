<?php
$unit = bbptl_get_current_unit_obj();
$distance =     get_query_var( bbptl()->dist_rewrite_id );
if (!$distance) $distance = bbptl()->get_option( '_bbptl_distance');
?>

<form role="search" method="get" id="bbptl-search-form" action="<?php bbp_search_url(); ?>">
	<div>
		<label class="screen-reader-text hidden" for="bbp_search"><?php _e( 'Search for:', 'bbpress' ); ?></label>
		<input tabindex="<?php bbp_tab_index(); ?>" type="text" value="<?php echo esc_attr( bbp_get_search_terms() ); ?>" name="bbp_search" id="bbp_search" />
        <div id="bbptl_search_fields" class="clearable">
            <p class="bbptl-location-field clearable">
                <label for="<?php echo bbptl()->addr_rewrite_id;?>"><?php _e('Location','bbptl' );?>:</label>
                <input type="text" id="<?php echo bbptl()->addr_rewrite_id;?>" value="<?php echo get_query_var( bbptl()->addr_rewrite_id ); ?>" tabindex="<?php bbp_tab_index(); ?>" name="<?php echo bbptl()->addr_rewrite_id;?>"/>
            </p>
            <p class="bbptl_distance_field">
                <label for="bbptl_search_dist"><?php _e( 'Distance:', 'bbptl' ); ?></label>
                <input tabindex="<?php bbp_tab_index(); ?>" type="text" value="<?php echo esc_attr( $distance ); ?>" name="bbptl_search_dist" id="bbptl_search_dist" size="2"/>
                <span class="bbptl_distance_unit"><?php echo $unit['name'];?></span>
            </p>
        </div>
		<input tabindex="<?php bbp_tab_index(); ?>" class="button" type="submit" id="bbptl_search_submit" value="<?php esc_attr_e( 'Search', 'bbpress' ); ?>" />
	</div>
</form>
