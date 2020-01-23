<?php
global $bbptl_geodata;

$geodata = ($bbptl_geodata) ? $bbptl_geodata : new bbPressTopicLocationGeoData();
$classes = array('bbptl-location-field','clearable');
?>
<div class="<?php echo implode(' ',$classes);?>">
    <p>
        <label><?php _e('Location:','bbptl' );?></label>
        <div class="bbp-browser-geolocation-notice bbp-template-notice notice is-dismissible"><ul><li><? _e("Leave empty and click 'Search' to detect your current location.",'bbptl');?></li></ul></div>
        <span class="bbptl_search_row">
            <input name="bbptl_topic_geo[input]" class="bbptl_topic_geodata" type="text" value="<?php echo $geodata->input; ?>" tabindex="<?php bbp_tab_index(); ?>" size="40" placeholder="<?php _e('Enter location','wpsstm');?>" />
            <a class="bbptl_row_bt bbptl_search_pos_bt" href="#"><?php _e('Search','bbptl');?></a>
            <a class="bbptl_row_bt bbptl_clear_pos_bt" href="#"><?php _e('Clear','bbptl');?></a>
        </span>
    </p>
    <p class="bbptl_coordinates">
        <input name="bbptl_topic_geo[lat]" class="bbptl_topic_geodata" type="text" value="<?php echo $geodata->lat; ?>" placeholder="<?php _e('Latitude','wpsstm');?>" />
        <input name="bbptl_topic_geo[lon]" class="bbptl_topic_geodata" type="text" value="<?php echo $geodata->lon; ?>" placeholder="<?php _e('Longitude','wpsstm');?>" />
    </p>
</div>
