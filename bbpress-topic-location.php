<?php
/*
 * Plugin Name: bbPress Topic Location
 * Plugin URI: http://wordpress.org/extend/plugins/bbpress-pencil-unread
 * Description: This plugin adds the ability to geo-locate a topic in bbPress.
 * Author: G.Breant
 * Version: 1.0.7
 * Author URI: http://bit.ly/cc-sndbox
 * License: GPL2+
 * Text Domain: bbptl
 * Domain Path: /languages/
 */


class bbPressTopicLocation {

    /** Version ***************************************************************/

    /**
     * @public string plugin version
     */
    public $version = '1.0.7';

    /**
     * @public string plugin DB version
     */
    public $db_version = '101';

    /** Paths *****************************************************************/

    public $file = '';

    /**
     * @public string Basename of the plugin directory
     */
    public $basename = '';

    /**
     * @public string Absolute path to the plugin directory
     */
    public $plugin_dir = '';

    /**
     * @public string Absolute path to the theme directory
     */

    public $templates_dir = '';

    /** URLs ******************************************************************/

    /**
     * @public string URL to the plugin directory
     */

    public $plugin_url = '';

    /**
    * @var The one true Instance
    */
    private static $instance;

    public static function instance() {
        if ( ! isset( self::$instance ) ) {
            self::$instance = new bbPressTopicLocation;
            self::$instance->setup_globals();
            self::$instance->includes();
            self::$instance->setup_actions();
        }
        return self::$instance;
    }

    static $meta_key_db_version = 'bbptl-db';
    static $meta_key_options = 'bbptl-options';

    var $options_default = array();
    var $options = array();

    public $post_types = array();

    public $lat_rewrite_id;
    public $lng_rewrite_id;
    public $dist_rewrite_id;
    public $addr_rewrite_id;
    public $geo_units; //array of units
    public $earth_radius_miles;// radius of the earth in miles

    /**
     * A dummy constructor to prevent from being loaded more than once.
     *
     */
    private function __construct() { /* Do nothing here */ }


    function setup_globals() {
        /** Paths *************************************************************/
        $this->file       = __FILE__;
        $this->basename   = plugin_basename( $this->file );
        $this->plugin_dir = plugin_dir_path( $this->file );
        $this->plugin_url = plugin_dir_url ( $this->file );
        $this->templates_dir = $this->plugin_dir.'theme/';

        $this->prefix = 'bbptl';

        $this->lat_rewrite_id = 'bbptl_search_lat';
        $this->lng_rewrite_id = 'bbptl_search_lng';
        $this->dist_rewrite_id = 'bbptl_search_dist';
        $this->addr_rewrite_id = 'bbptl_search_addr';

        $this->earth_radius_miles=3959;

        $this->geo_units=array(
            array(
                'slug'      => 'miles',
                'factor'    => 1,
                'name'      =>__('miles','bbptl')
            ),
            array(
                'slug'      => 'km',
                'factor'    => 0.621371192, //for miles conversion
                'name'      =>__('km','bbptl')
            )
        );

        //options
        $this->options_default = array(
            '_bbptl_gmaps_apikey'=>     null,
            '_bbptl_geo_unit'=>         'miles',
            '_bbptl_distance'=>         '25',
        );

    }

    function includes(){

        require( $this->plugin_dir . 'bbptl-functions.php');
        require( $this->plugin_dir . 'bbptl-widgets.php');

        if (is_admin()){
            require( $this->plugin_dir . 'bbptl-admin.php');
        }
    }


    function setup_actions(){

        //localization (nothing to localize yet, so disable it)
        add_action('init', array($this, 'load_plugin_textdomain'));

        //upgrade
        add_action( 'plugins_loaded', array($this, 'upgrade'));

        //settings link
        add_filter("plugin_action_links_$this->basename", array($this, 'settings_link' ));

        //once bbPress is loaded
        //register scripts & styles
        add_action('init', array($this, 'register_scripts_styles'));

        //widgets
        add_action( 'widgets_init', 'bbptl_search_widget_init' );

        //BBPRESS
        add_action('bbp_init',array($this, 'bbpress_has_init'));

        //https notice
        add_action('admin_notices',array($this, 'https_notice'));


        //FRONTEND

        //scripts & styles
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts_styles'));

        //save topic geo
        add_action( 'bbp_new_topic',array( $this, 'frontend_save_geodata'),10,2); //new topic
        add_action('bbp_edit_topic',array( $this, 'frontend_save_geodata'),10,2); //existing topic

        //display location
        add_filter('bbp_get_topic_class', array($this,"post_location_class"),10,2);
        add_filter('bbp_get_reply_class', array($this,"post_location_class"),10,2);

        add_action ('bbp_theme_after_reply_content', array(__class__,"post_location_html"));
        add_action ('bbp_theme_after_topic_content', array(__class__,"post_location_html"));

        //display location as icon
        add_action('bbp_theme_after_topic_meta','bbptl_location_html');

        //add geo location field (frontend)
        add_action('bbp_theme_after_topic_form_tags', array(__class__,'get_post_edit_location_html'));
        //new topic - check geolocation
        add_action( 'bbp_new_topic_pre_extras',array( $this, 'new_geolocation_field'));
        //existing topic - check geolocation
        add_action( 'bbp_edit_topic_pre_extras',array( $this, 'edit_geolocation_field' ));

        //new topic - validate location
        //TOUFIX TOUCHECK URGENT
        add_filter('bbptl_new_location_pre',array( $this, 'get_geocoding'));
        //existing topic - validate location
        add_filter('bbp_edit_topic_pre_geolocation',array( $this, 'get_geocoding'));


        //SEARCH
        //
        //query vars
        add_filter('query_vars', array($this,'search_query_vars'));
        add_filter( 'posts_clauses', array($this,'set_post_clauses'),10,2);
        add_filter('posts_request',array($this,'debug_search_query'),10,2);


        //BBPRESS

        //define if the query is a bbpress search
        //see http://bbpress.trac.wordpress.org/ticket/2355
        add_filter('bbp_before_has_search_results_parse_args', array($this,'bbpress_identify_search_query'));
        add_filter('bbp_after_has_search_results_parse_args', array($this,'bbpress_add_dummy_keyword'));

        add_action('pre_get_posts',array($this,'bbpress_remove_dummy_keyword'));
        add_filter('pre_get_posts',array($this,'bbpress_filter_search_query'));

        //warn users about the geolocated search
        //bbPress hooks are not very practical for us so we have two.
        add_action( 'bbp_template_before_search_results_loop', array($this,'bbpress_message_has_results'));//if results
        add_action( 'bbp_template_after_search_results', array($this,'bbpress_message_has_no_results'));//if no results

        //AJAX

        add_action('wp_ajax_bbptl_get_gmaps_location', array($this,'get_ajax_geocoding'));
        add_action('wp_ajax_nopriv_bbptl_get_gmaps_location', array($this,'get_ajax_geocoding'));


    }


    public function load_plugin_textdomain(){
        load_plugin_textdomain($this->prefix, FALSE, $this->plugin_dir.'/languages/');
    }


    function upgrade(){
        global $wpdb;

        $current_version = get_option(self::$meta_key_db_version);

        if ( $current_version == $this->db_version ) return false;

        //install
        if(!$current_version){
            //handle SQL
            //require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            //dbDelta($sql);
        }

        //update DB version
        update_option( self::$meta_key_db_version, $this->db_version );
    }
    
    function settings_link($links) { 

        return array_merge( $links, array(
            'settings' => '<a href="options-general.php?page=bbpress">'.__('Settings').'</a>'
            )
        );
    }
    
    public function get_option($name){
        $default = self::get_default_option($name);
        $value = get_option( $name, $default );
        return apply_filters('bbptl_get_option',$value,$name);
    }

    public function get_default_option($name){
        if (!isset($this->options_default[$name])) return;
        return $this->options_default[$name];
    }
        
    /**
     * Since we fetch bbpress post types with bbpress native functions 
     * (so, there will be a fatal error if bbpress native functions are not loaded),
     * We need to retrieve the supported post types with the function 'get_supported_post_types',
     * Which has a filter.
     * When bbpress has init ('bbp_init'), we add a filter on 'bbptl_get_supported_post_types' 
     * to add bbpress post types to the supported post types.
     * @return type
     */
    function get_supported_post_types(){
        $post_types = $this->post_types;
        $post_types = apply_filters('bbptl_get_supported_post_types',$post_types);
        return $post_types;
    }

    function bbpress_get_supported_post_types($post_types=array()){

        $bbp_post_types = apply_filters('bbptl_bbpress_post_types',array(
            bbp_get_topic_post_type(),
            //bbp_get_reply_post_type()
        ));

        return array_merge($bbp_post_types,$post_types);

    }

    //SEARCH

    function search_query_vars($vars){
            $vars[] = $this->dist_rewrite_id;
            $vars[] = $this->addr_rewrite_id;
            $vars[] = $this->lng_rewrite_id;
            $vars[] = $this->lat_rewrite_id;
            return $vars;
    }
    /**
     * This is a separate function so, potentially, other plugins can use it.
     * @param type $query
     * @return type
     */

    function filter_query($query){

        $latitude =     get_query_var( bbptl()->lat_rewrite_id );
        $longitude =    get_query_var( bbptl()->lng_rewrite_id );
        $address =      get_query_var( bbptl()->addr_rewrite_id );
        $distance =     get_query_var( bbptl()->dist_rewrite_id );
        if (!$distance) $distance = $bbptl()->get_option( '_bbptl_distance');
        
        //query
        $coords = ( $latitude && $longitude ) ? $geo_query = sprintf('%s,%s',$latitude,$longitude) : null;
        $geo_query = $coords ? $coords : $address;
        if(!isset($geo_query)) return $query;

        $query->set('bpptl_origin_point_input',$geo_query);

        //origin
        if ( !$origin_point = $this->get_geocoding($geo_query) ) return $query;
        $origin_point['Distance']= $distance;

        $query->set('bpptl_origin_point',$origin_point);
        $query->set('is_bpptl',true);

        return $query;
    }


    function set_post_clauses($clauses, $query){
        global $wpdb;

        if(!$query->get('is_bpptl')) return $clauses;

        $origin_point = $query->get('bpptl_origin_point');

        $latitude = $origin_point['Latitude'];
        $longitude = $origin_point['Longitude'];
        $maxdistance = $origin_point['Distance'];

        if (!$latitude || !$longitude || !$maxdistance) return $clauses;

        $current_unit = bbptl_get_current_unit_obj();
        $mult_factor = $current_unit['factor'];
        $radius = $this->earth_radius_miles*$mult_factor;

        $clauses['fields'].=sprintf(', ( %1s * acos( cos( radians(%2s) ) * cos( radians( latitude.meta_value ) ) * cos( radians( longitude.meta_value ) - radians(%3s) ) + sin( radians(%4s) ) * sin( radians( latitude.meta_value ) ) ) ) AS distance',$radius,$latitude,$longitude,$latitude);
        $clauses['join'].=sprintf(" LEFT JOIN %1s latitude on latitude.post_id = wp_posts.ID and latitude.meta_key = '%2s' LEFT JOIN %3s longitude on longitude.post_id = wp_posts.ID and longitude.meta_key = '%4s'",$wpdb->postmeta,'_bbptl_lat',$wpdb->postmeta,'_bbptl_lng');
        $clauses['where'].=sprintf(" HAVING distance <%1s",$maxdistance);

        return $clauses;
    }

    function debug_search_query($request,$query) {

        if(!WP_DEBUG_DISPLAY) return $request;
        if(!$query->get('is_bpptl')) return $request;
        print_r('<p>');
        print_r($request);
        print_r('</p>');

        return $request;

    }

    function bbpress_has_init(){
        add_filter('bbptl_get_supported_post_types',array($this,'bbpress_get_supported_post_types'));
    }




    //define if the query is a bbpress search
    //see http://bbpress.trac.wordpress.org/ticket/2355
    function bbpress_identify_search_query($args){
        $args['is_bbp_search']=true;
        return $args;
    }

    function bbpress_add_dummy_keyword($args){

        if(!$args['s']) 
            $args['s']='bbptl-dummy-term'; //see https://bbpress.trac.wordpress.org/ticket/2356

        return $args;
    }

    function bbpress_remove_dummy_keyword($query){
        if(!$query->get('is_bbp_search')) return $query;

        $search_terms = $query->get('s');
        if(!$search_terms) return $query;

        if($search_terms!='bbptl-dummy-term') return $query;

        $query->set('s',false);

        return $query;
    }

    /**
     * Filter the bbpress search query.
     * @param type $query
     * @return type
     */
    function bbpress_filter_search_query(&$query){

        //check is the query that fetches the posts for the bbpress search
        if (!$query->get('is_bbp_search')) return $query;


        //restrict search to supported post types
        $bbptl_post_types=array();
        $query_post_types = $query->get('post_type');
        $allowed_post_types = $this->bbpress_get_supported_post_types();

        foreach((array)$query_post_types as $post_type){
            if (!in_array($post_type,$allowed_post_types)) continue;
            $bbptl_post_types[]=$post_type;
        }

        //abord
        if(!$bbptl_post_types) return $query;

        $query->set('post_type',$bbptl_post_types);


        return $this->filter_query($query);
    }

    function bbpress_message_has_results(){
        $bbp = bbpress();
        if(!isset($bbp->search_query->found_posts)) return false;
        $count = $bbp->search_query->found_posts;
        if(!$count) return false; //will be bbpress_message_has_no_results instead

        $this->bbpress_message_results();
    }

    function bbpress_message_has_no_results(){

        $bbp = bbpress();
        if(!isset($bbp->search_query->found_posts)) return false;
        $count = $bbp->search_query->found_posts;
        if($count) return false; //will be bbpress_message_has_results instead

        $this->bbpress_message_results();
    }

    function bbpress_message_results(){
        $bbp = bbpress();
        if(!isset($bbp->search_query)) return false;

        $query = $bbp->search_query;
        $geo_input = $query->get('bpptl_origin_point_input');

        if (!$query->get('is_bbp_search')) return false;
        if(!$query->get('bpptl_origin_point_input')) return false;

        if (!$geo_input) return false;

        bbptl_locate_template('feedback-geolocated.php',true);
    }


    /**
     * scripts_styles()
     */
    function register_scripts_styles(){
        wp_register_script($this->prefix, $this->plugin_url . '_inc/js/bbptl.js',array('jquery'),$this->version);
        wp_register_style($this->prefix, $this->plugin_url . '_inc/css/bbptl.css' );
    }


    function enqueue_scripts_styles() {

            //SCRIPTS

            wp_enqueue_script($this->prefix);

            //localize vars
            $localize_vars['geo_error_navigator']=__('Your browser do not supports geolocation','bbptl');
            $localize_vars['geo_error_timeout']=__('Time out','bbptl');
            $localize_vars['geo_error_unavailable']=__('Position unavailable','bbptl');
            $localize_vars['geo_error_capability']=__('Permission denied','bbptl');
            $localize_vars['geo_error']=__('Unknown error','bbptl');
            $localize_vars['geo_placeholder']=__('Location','bbptl');

            wp_localize_script($this->prefix,$this->prefix.'L10n', $localize_vars);

            //STYLES
            wp_enqueue_style($this->prefix);
            wp_enqueue_style('dashicons');
    }




    function scripts_backend( $hook ) {
            global $post;

            if ( $hook == 'post-new.php' || $hook == 'post.php' ) {
                    if (in_array($post->post_type,$this->get_supported_post_types())) {     
                            echo "<br/><br/>scripts_backend";
                            //wp_enqueue_script(  'myscript', get_stylesheet_directory_uri().'/js/myscript.js' );
                    }
            }
    }

    static function parse_coordinates($location) {

        $location = trim($location);

        preg_match_all("/-?\d+[\.|,]\d+/", $location, $coords, PREG_SET_ORDER);

        if(empty($coords)) return false;

        $lat = str_replace(',', '.', $coords[0][0]);
        $lng = str_replace(',', '.', $coords[1][0]);


        if($lat&&$lng) return array($lat,$lng);


    }

    function new_geolocation_field(){
            global $bbptl_geolocation;

            $bbptl_geolocation = isset( $_POST['bbptl_topic_geo']['input'] ) ? trim($_POST['bbptl_topic_geo']['input']) : null;

            // Filter and sanitize
            $bbptl_geolocation = apply_filters( 'bbptl_new_location_pre',$bbptl_geolocation);

            // No topic location
            if ( empty( $bbptl_geolocation ) )
                    bbp_add_error( 'bbptl_geolocation', __( '<strong>ERROR</strong>: The location cannot be empty.','bbptl' ) );
    }
    function edit_geolocation_field($topic_id){
            global $bbptl_geolocation;

            $bbptl_geolocation = isset( $_POST['bbptl_topic_geo']['input'] ) ? trim($_POST['bbptl_topic_geo']['input']) : null;

            // Filter and sanitize
            $bbptl_geolocation = apply_filters( 'bbp_edit_topic_pre_geolocation',$bbptl_geolocation);

            // No topic location
            if ( empty( $bbptl_geolocation ) )
                    bbp_add_error( 'bbptl_geolocation', __( '<strong>ERROR</strong>: The location cannot be empty.','bbptl' ) );
    }

    function save_geolocation($post_id){
        global $bbptl_geolocation;

        $geo_info['Input']=$bbptl_geolocation['Input'];
        $geo_info['Address']=$bbptl_geolocation['Address'];

        $lat=$bbptl_geolocation['Latitude'];
        $long=$bbptl_geolocation['Longitude'];

        if ((!$lat) || (!$long)) {  //no position found and strict mode ON
                delete_post_meta($post_id, '_bbptl_info');
                delete_post_meta($post_id, '_bbptl_lat');
                delete_post_meta($post_id, '_bbptl_lng');

                return false;
        }else {
                update_post_meta($post_id, '_bbptl_info', $geo_info);
                update_post_meta($post_id, '_bbptl_lat', $lat);
                update_post_meta($post_id, '_bbptl_lng', $long);
        }

        return true;
    }
    
    function post_location_class($classes,$post_id){

        $geodata = new bbPressTopicLocationGeoData();
        $geodata->getForPost($post_id);
        
        if( $geodata->isValidCoordinates() ){
            $classes[]='has-location';
        }

        return $classes;
    }

    function https_notice(){
        //if  (bbptl_is_secure() ) return false;
        ?>
        <div class="notice notice-warning is-dismissible">
            <p><strong>bbPress Topic Location</strong> relies on the browser geolocation features.  Therefore, this plugin <a href="https://github.com/gordielachance/bbpress-topic-location/issues/2" target="_blank">requires HTTPS</a>.</p>
        </div>
        <?php

    }
    
    function get_ajax_geocoding() {

        $ajax_data = wp_unslash($_POST);

        $lat=$lng=$addr=$geo_input='';

        $result = array(
            'input'=>       $ajax_data,
            'success'=>     false,
        );

        $lat =          isset($ajax_data['_bbptl_lat']) ? trim($ajax_data['_bbptl_lat']) : null;
        $lng =          isset($ajax_data['_bbptl_lng']) ? trim($ajax_data['_bbptl_lng']) : null;
        $addr =         isset($ajax_data['_bbptl_addr']) ? trim($ajax_data['_bbptl_addr']) : null;
        $geo_input =    ( $lat && $lng ) ? sprintf('%s,%s',$lat,$lng) : $addr;

        if($geo_input){
            $result['geo_input']=$geo_input;

            $response = bbptl()->get_geocoding($geo_input);
            if( is_wp_error($response) ){
                $result['error_code'] = $response->get_error_code();
                $result['message'] = $response->get_error_message();
            }else{
                $result['success']=true;
                $result['geodata']=$response;
            }

        }

        header('Content-type: application/json');
        wp_send_json( $result );

    }
    
    public function get_geocoding($input=false){

            $input = trim($input);
            if(!$input)return false;

            $api_url = 'https://nominatim.openstreetmap.org';
            $api_args = array(
                'format'=>          'json',
                'addressdetails'=>  1,
                'limit'=>           1,
            );

            if($coords = self::parse_coordinates($input)){ //reverse geocoding
                
                $api_url .= '/reverse';
                $api_args['lat'] = $coords[0];
                $api_args['lon'] = $coords[1];

            }else{
                $api_args['limit']=1;
                $api_args['q']=urlencode($input);

            }

            $api_url = add_query_arg($api_args,$api_url);

            self::debug_log($api_url,'check location with Nominatim...');

            $response = wp_remote_get( $api_url );
            $json = wp_remote_retrieve_body( $response );
            if ( is_wp_error($json) ) return $json;
        
            $response=json_decode($json,true);
            self::debug_log($response,'...Nominatim entry');

            if ($response) {
                
                $entry = ( !$coords && isset($response[0]) ) ? $response[0] : $response;
                
                $geodata = new bbPressTopicLocationGeoData();
                $geodata->lat = $entry['lat'];
                $geodata->lon = $entry['lon'];
                $geodata->input = $entry['display_name'];

                return $geodata;
            }else {
                $errortxt = sprintf('[%s] %s','OSM ERROR','error while querying OSM API');
                return new WP_Error( 'bbptl_api_error',$errortxt);
            }
    }
    
    function frontend_save_geodata($topic_id,$forum_id){
        $data = isset($_POST['bbptl_topic_geo']) ? $_POST['bbptl_topic_geo'] : null;
        
        $geodata = new bbPressTopicLocationGeoData();
        $geodata->lat = isset($data['lat']) ? $data['lon'] : null;
        $geodata->lon = isset($data['lon']) ? $data['lon'] : null;
        $geodata->input = isset($data['input']) ? $data['input'] : null;
        
        return $geodata->saveForPost($topic_id);
    }
    
    static public function post_location_html(){
        echo self::get_post_location_html( get_the_ID() );
    }
    
    static public function get_post_location_html($post_id){
        $bbp = bbpress();

        $geodata = new bbPressTopicLocationGeoData();
        $geodata->getForPost($post_id);

        if( !$geodata->isValidCoordinates() ) return false;

        ob_start();

        ?>
        <p class="bbp-topic-meta bbptl-post-location">
            <span class="bbptl-post-address"><?php echo $geodata->input; ?></span>

            <?php

            //display distance from input location
            if( isset($bbp->search_query) && method_exists($bbp->search_query,'get') && ($origin_point = $bbp->search_query->get('bpptl_origin_point'))){

                if ( $distance = bbptl_get_distance($origin_point['Latitude'],$origin_point['Longitude'],$geodata->lat,$geodata->lon) ){
                    
                    $unit = bbptl_get_current_unit_obj();
                    ?>
                    <span class="bbptl-post-distance"><?php printf(__('(at %1$s %2$s)','bbpts'),$distance,$unit['name']);?></span>
                    <?php
                }

            }
            ?>

        </p>
        <?php

        $output = ob_get_contents();
        ob_end_clean();
        
        return apply_filters('bbptl_post_location_html',$output,$post_id);

    }

    static public function get_post_edit_location_html(){
        global $post;
        $geodata = new bbPressTopicLocationGeoData();
        $geodata->getForPost($post->ID);

        ?>
        <div class="bbptl_location_field clearable">
            <p>
                <label><?php _e('Location:','bbptl' );?></label>
                <div class="bbp-template-notice"><ul><li><? _e("Leave empty and click 'Search' to detect your current location.",'bbptl');?></li></ul></div>
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
        <?php
    }

    public static function debug_log($data,$title = null) {

        if (WP_DEBUG_LOG !== true) return false;

        $prefix = '[bbptl] ';
        if($title) $prefix.=$title.': ';

        if (is_array($data) || is_object($data)) {
            $data = "\n" . json_encode($data,JSON_UNESCAPED_UNICODE);
        }

        error_log($prefix . $data);
    }

}

class bbPressTopicLocationGeoData{
    var $lat;
    var $lon;
    var $input;

    function getForPost($post_id){
        $this->lat = get_post_meta($post_id,'_bbptl_lat',true);
        $this->lon = get_post_meta($post_id,'_bbptl_lng',true);
        $this->input = get_post_meta($post_id,'_bbptl_input',true);
    }
    
    function saveForPost($post_id){
        
        if ( !$this->isValidCoordinates() ) {
            return new WP_Error('missing_required_coords','Missing required coordinates');
        }
        
        if ( !$this->input ) {
            return new WP_Error('missing_required_address','Missing required address');
        }
        
        update_post_meta($post_id,'_bbptl_lat',$this->lat);
        update_post_meta($post_id,'_bbptl_lng',$this->lon);
        update_post_meta($post_id,'_bbptl_input',$this->input);
        
        return true;
    }
    
    function deleteForPost($post_id){
        //delete_post_meta($post_id, '_bbptl_info');//TOUFIX REMOVE OCCURENCES
        delete_post_meta($post_id, '_bbptl_input');
        delete_post_meta($post_id, '_bbptl_lat');
        delete_post_meta($post_id, '_bbptl_lng');
        
        return true;
    }
    
    //TOUFIX TOUIMPROVE
    function isValidCoordinates(){
        return ($this->lat && $this->lon);
    }
}

function bbptl(){
    return bbPressTopicLocation::instance();
}

bbptl();