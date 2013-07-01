<?php

/**
 * Search 
 *
 * @package bbPress
 * @subpackage Theme
 */

?>

<form role="search" method="get" id="bbptl-search-form" action="<?php bbp_search_url(); ?>">
	<div>
		<label class="screen-reader-text hidden" for="bbp_search"><?php _e( 'Search for:', 'bbpress' ); ?></label>
		<input tabindex="<?php bbp_tab_index(); ?>" type="text" value="<?php echo esc_attr( bbp_get_search_terms() ); ?>" name="bbp_search" id="bbp_search" />
                <?php bbptl_search_widget_geolocation_fields();?>
		<input tabindex="<?php bbp_tab_index(); ?>" class="button" type="submit" id="bbptl_search_submit" value="<?php esc_attr_e( 'Search', 'bbpress' ); ?>" />
	</div>
</form>
