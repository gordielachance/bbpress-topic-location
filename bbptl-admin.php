<?php

class bbPressTopicLocationBackend {
    function __construct(){
        $this->setup_globals();
        $this->includes();
        $this->setup_actions();
    }
    function setup_globals(){
        
    }
    function includes(){
        
    }
    function setup_actions(){

        add_action('admin_enqueue_scripts', array( $this, 'scripts_styles' ) );
        add_action('add_meta_boxes',array( $this, 'geodata_metabox'));
        add_action('save_post',array( $this, 'backend_save_post_geo' ) );
        
        //settings section
        //http://www.hudsonatwell.co/tutorials/bbpress-development-add-settings/
        add_filter('bbp_admin_get_settings_sections', array( $this, 'add_settings_section'));
        add_filter('bbp_admin_get_settings_fields', array( $this, 'register_settings_fields'));
        add_filter('bbp_map_settings_meta_caps', array( $this, 'setting_add_permissions_autodelete') , 10, 4);
        
        //add_action( 'admin_menu', array( $this, 'settings_page_init' ) );
        //add_action( 'admin_init', array( $this, 'page_init' ) );

    }
    
    
    function add_settings_section($sections){
        $sections['bbp_settings_tl'] = array(
            'title'    => __( 'Topic Location', 'bbptl' ),
            'callback' => array(&$this,'settings_section_header'),
            'page'     => 'discussion'
        );

        return $sections;
    }
    
    //capability required to show those settings
    function setting_add_permissions_autodelete ( $caps, $cap, $user_id, $args ){
        if ($cap=='bbp_settings_tl')
            $caps = array( bbpress()->admin->minimum_capability );

        return $caps;
    }
    
    function settings_section_header(){
        ?>
        <p><?php esc_html_e( 'Settings for geolocation of topics', 'bbptl' ); ?></p>
        <?php
    }
    
    function register_settings_fields($settings){
        
        $settings['bbp_settings_tl'] = array(
            '_bbptl_geo_unit' => array(
                'title'             => __( 'Distance unit', 'bbptl' ),
                'callback'          => array(&$this,'setting_geo_unit'),
                'sanitize_callback' => 'sanitize_text_field',
                'args'              => array()
            ),
            '_bbptl_distance' => array(
                'title'             => __( 'Default distance', 'bbptl' ),
                'callback'          => array(&$this,'setting_distance'),
                'sanitize_callback' => 'intval',
                'args'              => array()
            ),
        );

        return $settings;
    }

    function setting_geo_unit() {
        
        $available = bbptl()->geo_units;
        $selected = bbptl()->get_option( '_bbptl_geo_unit');
        
        ?>
        
        <select name="_bbptl_geo_unit" <?php bbp_maybe_admin_setting_disabled( '_bbptl_geo_unit' ); ?>>
            <?php
            foreach ($available as $unit){
                ?><option <?php selected( $selected, $unit['slug'] ); ?> value="<?php echo $unit['slug'];?>"><?php echo $unit['name'];?></option><?php
            }
            ?>
        </select>
        
        <label for="_bbptl_geo_unit">
            <?php _e( 'Unit used for distances', 'bbptl' ); ?>
        </label>
        <?php
    }
    
    function setting_distance() {
        
        $default = bbptl()->get_default_option('_bbptl_distance');
        ?>

        <input name="_bbptl_distance" type="number" min="1" step="1" value="<?php bbp_form_option( '_bbptl_distance', $default ); ?>" class="small-text"<?php bbp_maybe_admin_setting_disabled( '_bbptl_distance' ); ?> />
        <label for="_bbptl_distance"><?php esc_html_e( 'Default distance when searching results within a perimeter', 'bbptl' ); ?></label>

        <?php
    }

    function scripts_styles( $hook ) {
        global $post;

        if ( $hook != 'post-new.php' && $hook != 'post.php' ) return;
        if ( !in_array($post->post_type,bbptl()->get_supported_post_types()) ) return;

        bbptl()->enqueue_scripts_styles();

    }

    function geodata_metabox(){
        global $post;
        $post_obj = get_post_type_object( get_post_type($post) );
        foreach((array)bbptl()->get_supported_post_types() as $post_type){
            add_meta_box( 'bbptl_location_metabox',__( 'Geodata','bbptl' ),array('bbPressTopicLocation','post_edit_location_html'),$post_type, 'normal', 'high' );  
        }
    }
    
    function backend_save_post_geo($post_id){

        // Bail if doing an autosave
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE )
                return $post_id;

        // Bail if not a post request
        if ( 'POST' != strtoupper( $_SERVER['REQUEST_METHOD'] ) )
                return $post_id;

        // Bail if post_type do not match
        if (!in_array(get_post_type( $post_id ),bbptl()->get_supported_post_types()))
                return;

        // Bail if current user cannot edit this post
        $post_obj = get_post_type_object( get_post_type( $post_id ) ); 

        if ( !current_user_can( $post_obj->cap->edit_post, $post_id ) )
                return $post_id;
        
        $data = isset($_POST['bbptl_topic_geo']) ? $_POST['bbptl_topic_geo'] : null;
        $geodata = new bbPressTopicLocationGeoData();
        
        $geodata->lat = isset($data['lat']) ? $data['lat'] : null;
        $geodata->lon = isset($data['lon']) ? $data['lon'] : null;
        $geodata->input = isset($data['input']) ? $data['input'] : null;
        
        return $geodata->saveForPost($post_id);

    }
    
}

new bbPressTopicLocationBackend();
