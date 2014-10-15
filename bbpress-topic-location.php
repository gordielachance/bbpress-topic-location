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
            '_bbptl_geo_unit'         => 'miles',
            '_bbptl_distance'          => '25',

        );

    }

    function includes(){

        require( $this->plugin_dir . 'bbptl-functions.php');
        require( $this->plugin_dir . 'bbptl-template-tags.php');
        require( $this->plugin_dir . 'bbptl-ajax.php');
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


            //FRONTEND

            //scripts & styles
            add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts_styles'));

            //new topic - save geolocation (frontend)
            add_action( 'bbp_new_topic',array( $this, 'frontend_save_geolocation'));

            //edit topic - save geolocation (frontend)
            add_action('bbp_edit_topic_post_extras',array( $this, 'frontend_save_geolocation'));

            //display location
            add_filter('bbp_get_topic_class', array(&$this,"post_location_class"),10,2);
            add_filter('bbp_get_reply_class', array(&$this,"post_location_class"),10,2);
            
            add_action ('bbp_theme_after_reply_content','bbptl_location_html');
            add_action ('bbp_theme_after_topic_content','bbptl_location_html');

            //display location as icon
            add_action('bbp_theme_after_topic_meta','bbptl_location_html');

            //add geo location field (frontend)
            add_action('bbp_theme_after_topic_form_tags','bbptl_save_post_geolocation_field');
            //new topic - check geolocation
            add_action( 'bbp_new_topic_pre_extras',array( $this, 'new_geolocation_field'));
            //existing topic - check geolocation
            add_action( 'bbp_edit_topic_pre_extras',array( $this, 'edit_geolocation_field' ));

            //new topic - validate location
            add_filter('bbptl_new_location_pre',array( $this, 'validate_geolocation'));
            //existing topic - validate location
            add_filter('bbp_edit_topic_pre_geolocation',array( $this, 'validate_geolocation'));


            //SEARCH
            //
            //query vars
            add_filter('query_vars', array(&$this,'search_query_vars'));
            add_filter( 'posts_clauses', array(&$this,'set_post_clauses'),10,2);
            add_filter('posts_request',array(&$this,'debug_search_query'),10,2);


            //BBPRESS

            //define if the query is a bbpress search
            //see http://bbpress.trac.wordpress.org/ticket/2355
            add_filter('bbp_before_has_search_results_parse_args', array(&$this,'bbpress_identify_search_query'));
            add_filter('bbp_after_has_search_results_parse_args', array(&$this,'bbpress_add_dummy_keyword'));

            add_action('pre_get_posts',array(&$this,'bbpress_remove_dummy_keyword'));
            add_filter('pre_get_posts',array(&$this,'bbpress_filter_search_query'));

            //warn users about the geolocated search
            //bbPress hooks are not very practical for us so we have two.
            add_action( 'bbp_template_before_search_results_loop', array(&$this,'bbpress_message_has_results'));//if results
            add_action( 'bbp_template_after_search_results', array(&$this,'bbpress_message_has_no_results'));//if no results


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

    function filter_query(&$query){

        $latitude = bbptl_get_search_latitude();
        $longitude = bbptl_get_search_longitude();
        $address = bbptl_get_search_address();
        $distance = bbptl_get_search_distance();

        if ($latitude&&$longitude) {
                $geo_query = $latitude.','.$longitude;
                unset($address);
        }elseif ($address) {
            $geo_query = $address;
        }

        //abord
        if(!isset($geo_query)) return $query;

        $query->set('bpptl_origin_point_input',$geo_query);

        $origin_point = $this->validate_geolocation($geo_query);

        //abord
        if(!$origin_point) return $query;

        //distance
        $origin_point['Distance']=$distance;

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
        add_filter('bbptl_get_supported_post_types',array(&$this,'bbpress_get_supported_post_types'));
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
        wp_register_style($this->prefix, $this->plugin_url . '_inc/bbptl.css' );
    }


    function enqueue_scripts_styles() {

            //SCRIPTS

            wp_enqueue_script($this->prefix);

            //localize vars
            $localize_vars['loading']=__('Loading...','bbptl');
            $localize_vars['input_empty_text']=__('Guess my location','bbptl');
            $localize_vars['input_not_empty_text']=__('Validate this address','bbptl');
            $localize_vars['geo_error_navigator']=__('Your browser do not supports geolocation','bbptl');
            $localize_vars['geo_error_timeout']=__('Time out','bbptl');
            $localize_vars['geo_error_unavailable']=__('Position unavailable','bbptl');
            $localize_vars['geo_error_capability']=__('Permission denied','bbptl');
            $localize_vars['geo_error']=__('Unknown error','bbptl');
            $localize_vars['geo_placeholder']=__('Location','bbptl');

            wp_localize_script($this->prefix,$this->prefix.'L10n', $localize_vars);

            //STYLES

            wp_enqueue_style($this->prefix);
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



    /**
    * Output value of topic location field
    *
    * @since bbPress (r2976)
    * @uses get_form_topic_location() To get the value of topic location field
    */
    function form_topic_location() {
            echo $this->get_form_topic_location();
    }
            /**
            * Return value of topic location field
            *
            * @since bbPress (r2976)
            *
            * @uses bbp_is_topic_edit() To check if it's the topic edit page
            * @uses apply_filters() Calls 'get_form_topic_location' with the location
            * @return string Value of topic location field
            */
            function get_form_topic_location() {
                    global $post;

                    $topic_location = '';

                    // Get _POST data
                    if ( 'post' == strtolower( $_SERVER['REQUEST_METHOD'] ) && isset( $_POST['bbptl_location'] ) ) {
                            $topic_location = $_POST['bbptl_location'];

                    // Get edit data
                    } elseif ( !empty( $post ) ) {

                            // Post is a topic
                            if ( bbp_get_topic_post_type() == $post->post_type ) {
                                    $topic_id = $post->ID;
                            }


                            // Topic exists
                            if ( !empty( $topic_id ) ) {
                                    //don't use bbptl_get_post_address() here as there may have be filters on it. We need the real address.
                                    $location = bbptl_get_location_obj($topic_id);
                                    $topic_location = $location['Address'];

                            }


                    // No data
                    }

                    return apply_filters( 'bbp_get_form_topic_location', esc_attr( $topic_location ) );
            }


    function location_to_coordinates($location) {

        preg_match_all("/-?\d+[\.|,]\d+/", $location, $coords, PREG_SET_ORDER);

        if(empty($coords)) return false;

        $lat = str_replace(',', '.', $coords[0][0]);
        $lng = str_replace(',', '.', $coords[1][0]);


        if($lat&&$lng) return array($lat,$lng);


    }


    function validate_geolocation($input=false){

            $input = trim($input);
            if(!$input)return false;

            if($coords = $this->location_to_coordinates($input)){
                $args['latlng']=$coords[0].','.$coords[1];
            }else{
                $args['address']=urlencode($input);
            }

            $args['sensor']='false';
            $gmaps_url = add_query_arg($args,'http://maps.google.com/maps/api/geocode/json');
            $geocode=file_get_contents($gmaps_url);
            $output=json_decode($geocode);

            if ($output->status=='OK') {
                    $result = $output->results[0];
                    $position['Latitude']= $result->geometry->location->lat;
                    $position['Longitude']= $result->geometry->location->lng;
                    $position['Address'] = $result->formatted_address;
                    $position['Input'] = $input;
            }else {
                bbp_add_error( 'bbptl_geolocation_unknown', __( '<strong>ERROR</strong>: We were unable to find this location.','bbptl' ) );
                return false;
            }

            return $position;
    }



    function new_geolocation_field(){
            global $bbptl_geolocation;

            if ( !empty( $_POST['bbptl_location'] ) )
                    $bbptl_geolocation = $_POST['bbptl_location'];

            // Filter and sanitize
            $bbptl_geolocation = apply_filters( 'bbptl_new_location_pre',$bbptl_geolocation);

            // No topic location
            if ( empty( $bbptl_geolocation ) )
                    bbp_add_error( 'bbptl_geolocation', __( '<strong>ERROR</strong>: The location cannot be empty.','bbptl' ) );
    }
    function edit_geolocation_field($topic_id){
            global $bbptl_geolocation;

            if ( !empty( $_POST['bbptl_location'] ) )
                    $bbptl_geolocation = $_POST['bbptl_location'];

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


        $has_geo = bbptl_post_has_geo($post_id);
        

        if ($has_geo){
                $classes[]='has-location';
        }
        return $classes;
    }

}

function bbptl(){
    return bbPressTopicLocation::instance();
}

bbptl();