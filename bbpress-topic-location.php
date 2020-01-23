<?php
/*
 * Plugin Name: bbPress Topic Location
 * Plugin URI: https://wordpress.org/plugins/bbpress-topic-location/
 * Description: This plugin brings topics geolocation to bbPress, and can filter topics by location and radius.
 * Author: G.Breant
 * Version: 1.0.9
 * Author URI: https://profiles.wordpress.org/grosbouff
 * License: GPL2+
 * Text Domain: bbptl
 */


class bbPressTopicLocation {

    /** Version ***************************************************************/

    /**
     * @public string plugin version
     */
    public $version = '1.0.9';

    /**
     * @public string plugin DB version
     */
    public $db_version = '102';

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
        $this->templates_dir = $this->plugin_dir.'templates/';

        $this->prefix = 'bbptl';

        $this->lat_rewrite_id = 'bbptl_search_lat';
        $this->lng_rewrite_id = 'bbptl_search_lng';
        $this->dist_rewrite_id = 'bbptl_search_dist';
        $this->addr_rewrite_id = 'bbptl_search_input';

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

        add_action('init', array($this, 'load_plugin_textdomain'));
        add_action('init', array($this, 'register_scripts_styles'));
        add_action('widgets_init', 'bbptl_search_widget_init' );
        add_action('plugins_loaded', array($this, 'upgrade'));
        add_filter("plugin_action_links_$this->basename", array($this, 'settings_link' ));
        add_action('admin_notices',array($this, 'https_notice'));

        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts_styles'));

        /*
        Queries
        */
        //
        //query vars
        add_filter('query_vars', array($this,'search_query_vars'));
        add_filter('posts_clauses', array($this,'set_post_clauses'),10,2);

        add_action('pre_get_posts',array($this,'bbpress_remove_dummy_keyword'));
        add_filter('pre_get_posts',array($this,'bbpress_filter_search_query'));

        /*
        Ajax
        */

        add_action('wp_ajax_bbptl_get_geocoding', array($this,'get_ajax_geocoding'));
        add_action('wp_ajax_nopriv_bbptl_get_geocoding', array($this,'get_ajax_geocoding'));

        /*
        bbPress
        we also have the 'bbp_init' hook
        */

        //save topic geo
        add_action('bbp_new_topic',array( $this, 'frontend_save_post_geo'),10,2); //new topic
        add_action('bbp_edit_topic',array( $this, 'frontend_save_post_geo'),10,2); //existing topic

        //post classes
        add_filter('bbp_get_topic_class', array($this,"post_location_class"),10,2);
        add_filter('bbp_get_reply_class', array($this,"post_location_class"),10,2);

        //display location template
        add_action('bbp_theme_after_reply_content', array(__class__,"filter_bbp_post_template"));
        add_action('bbp_theme_after_topic_content', array(__class__,"filter_bbp_post_template"));
        add_action('bbp_theme_after_topic_meta', array(__class__,"filter_bbp_post_template"));

        //edit location template
        add_action('bbp_theme_after_topic_form_tags', array(__class__,'post_edit_location_html'));

        //define if the query is a bbpress search
        //see http://bbpress.trac.wordpress.org/ticket/2355
        add_filter('bbp_before_has_search_results_parse_args', array($this,'bbpress_identify_search_query'));
        add_filter('bbp_after_has_search_results_parse_args', array($this,'bbpress_add_dummy_keyword'));

        //warn users about the geolocated search
        //bbPress hooks are not very practical for us so we have two.
        add_action('bbp_template_before_search_results_loop', array($this,'bbpress_message_has_results'));//if results
        add_action('bbp_template_after_search_results', array($this,'bbpress_message_has_no_results'));//if no results

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
        }else{
            if ($current_version < 102){
            }
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

    public function get_supported_post_types(){

        $types = array();

        if ( did_action('bbp_init') ){
            $types[] = bbp_get_topic_post_type();
            //$types[] = bbp_get_reply_post_type();
        }

        return $types;
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
        $input =        get_query_var( bbptl()->addr_rewrite_id );
        $distance =     get_query_var( bbptl()->dist_rewrite_id );
        if (!$distance) $distance = $bbptl()->get_option( '_bbptl_distance');

        //query
        $coords = ( $latitude && $longitude ) ? $geo_query = sprintf('%s,%s',$latitude,$longitude) : null;
        $geo_query = $coords ? $coords : $input;
        if(!isset($geo_query)) return $query;

        $query->set('bpptl_origin_point_input',$geo_query);

        //origin
        if ( !$origin_point = $this->get_geocoding($geo_query) ) return $query;

        $query->set('bpptl_origin_point',$origin_point);
        $query->set('bpptl_distance',$distance);
        $query->set('is_bpptl',true);

        return $query;
    }


    function set_post_clauses($clauses, $query){
        global $wpdb;

        if(!$query->get('is_bpptl')) return $clauses;

        $origin_point = $query->get('bpptl_origin_point');
        $maxdistance = $query->get('bpptl_distance');

        $latitude = $origin_point->lat;
        $longitude = $origin_point->lon;

        if (!$latitude || !$longitude || !$maxdistance) return $clauses;

        $current_unit = bbptl_get_current_unit_obj();
        $mult_factor = $current_unit['factor'];
        $radius = $this->earth_radius_miles*$mult_factor;

        $clauses['fields'].=sprintf(', ( %1s * acos( cos( radians(%2s) ) * cos( radians( latitude.meta_value ) ) * cos( radians( longitude.meta_value ) - radians(%3s) ) + sin( radians(%4s) ) * sin( radians( latitude.meta_value ) ) ) ) AS distance',$radius,$latitude,$longitude,$latitude);
        $clauses['join'].=sprintf(" LEFT JOIN %1s latitude on latitude.post_id = wp_posts.ID and latitude.meta_key = '%2s' LEFT JOIN %3s longitude on longitude.post_id = wp_posts.ID and longitude.meta_key = '%4s'",$wpdb->postmeta,'_bbptl_lat',$wpdb->postmeta,'_bbptl_lng');
        $clauses['where'].=sprintf(" HAVING distance <%1s",$maxdistance);

        return $clauses;
    }

    //define if the query is a bbpress search
    //see http://bbpress.trac.wordpress.org/ticket/2355
    function bbpress_identify_search_query($args){
        $args['is_bbp_search']=true;
        return $args;
    }

    //see https://bbpress.trac.wordpress.org/ticket/2356
    function bbpress_add_dummy_keyword($args){

        $args['s'] = isset($args['s']) ? $args['s'] : 'bbptl-dummy-term';

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
        $allowed_post_types = $this->get_supported_post_types();

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

        bbptl_locate_template('search-feedback.php',true);
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

            $localize_vars = array(
              'secure_origin'=>           bbptl_is_secure_origin(),
              'no_navigation_support'=>   __('Your browser do not supports geolocation.','bbptl'),
              'error_timeout'=>           __('Time out.','bbptl'),
              'position_unavailable'=>    __('Position unavailable.','bbptl'),
              'permission_denied'=>       __('Geolocation permission has been denied.','bbptl'),
              'unknown_error'=>           __('Unknown error.','bbptl'),
            );

            wp_localize_script($this->prefix,$this->prefix.'L10n', $localize_vars);

            //STYLES
            wp_enqueue_style($this->prefix);
            wp_enqueue_style('dashicons');
    }




    function scripts_backend( $hook ) {
        global $post;

        if ( $hook == 'post-new.php' || $hook == 'post.php' ) {
            if ( in_array($post->post_type,$this->get_supported_post_types()) ) {
                echo "<br/><br/>scripts_backend";
                //wp_enqueue_script(  'myscript', get_stylesheet_directory_uri().'/js/myscript.js' );
            }
        }
    }

    function post_location_class($classes,$post_id){

        $geodata = new bbPressTopicLocationGeoData();
        $geodata->getForPost($post_id);

        if( $geodata->lat && $geodata->lon ){
            $classes[]='has-location';
        }

        return $classes;
    }

    function https_notice(){
        if  (bbptl_is_secure_origin() ) return false;
        $plugin_name = sprintf('<strong>%s</strong>','bbPress Topic Location');
        $link_el = sprintf('<a href="%s" target="_blank">%s</a>','https://github.com/gordielachance/bbpress-topic-location/issues/2','HTML Geolocation API');
        $notice = sprintf(__("Browsers do require HTTPS to detect the user's location using the %s.  Therefore, this %s feature won't be available on this website.",'bbptl'),$link_el,$plugin_name);
        ?>
        <div class="notice notice-warning is-dismissible"><p><?php echo $notice;?></p></div>
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

    static function parse_coordinates($location) {

        $location = trim($location);

        preg_match_all("/-?\d+[\.|,]\d+/", $location, $coords, PREG_SET_ORDER);

        if(empty($coords)) return false;

        $lat = str_replace(',', '.', $coords[0][0]);
        $lng = str_replace(',', '.', $coords[1][0]);


        if($lat&&$lng) return array($lat,$lng);
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

    function frontend_save_post_geo($topic_id,$forum_id){
        $data = isset($_POST['bbptl_topic_geo']) ? $_POST['bbptl_topic_geo'] : null;

        $geodata = new bbPressTopicLocationGeoData();
        $geodata->lat = isset($data['lat']) ? $data['lat'] : null;
        $geodata->lon = isset($data['lon']) ? $data['lon'] : null;
        $geodata->input = isset($data['input']) ? $data['input'] : null;

        return $geodata->saveForPost($topic_id);
    }

    static public function filter_bbp_post_template(){
        echo self::get_post_location_html( get_the_ID() );
    }

    static public function get_post_location_html($post_id){
        global $bbptl_geodata;

        $bbptl_geodata = new bbPressTopicLocationGeoData();
        $bbptl_geodata->getForPost($post_id);

        if( !$bbptl_geodata->lat || !$bbptl_geodata->lon ) return false;

        ob_start();
        bbptl_locate_template('geodata-display.php',true);
        $output = ob_get_contents();
        ob_end_clean();

        return $output;

    }

    static public function post_edit_location_html(){
        echo self::get_post_edit_location_html();
    }

    static public function get_post_edit_location_html(){
        global $post;
        global $bbptl_geodata;
        $bbptl_geodata = new bbPressTopicLocationGeoData();
        $bbptl_geodata->getForPost($post->ID);

        ob_start();
        bbptl_locate_template('geodata-edit.php',true);
        $output = ob_get_contents();
        ob_end_clean();

        return $output;
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
        $lat = (float)get_post_meta($post_id,'_bbptl_lat',true);
        $lon = (float)get_post_meta($post_id,'_bbptl_lng',true);

        $latlon = self::sanitizeCoordinates( array('lat'=>$lat,'lon'=>$lon) );
        $this->lat = $latlon['lat'] ? $latlon['lat'] : null;
        $this->lon = $latlon['lon'] ? $latlon['lon'] : null;

        $this->input = get_post_meta($post_id,'_bbptl_input',true);
    }

    function saveForPost($post_id){

        $latlon = self::sanitizeCoordinates( array('lat'=>$this->lat,'lon'=>$this->lon) );
        $this->lat = $latlon['lat'] ? $latlon['lat'] : null;
        $this->lon = $latlon['lon'] ? $latlon['lon'] : null;

        if ( !$this->input || !$this->lat || !$this->lon ) {
            return $this->deleteForPost($post_id);
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
        delete_post_meta($post_id, '_bbptl_input');
        delete_post_meta($post_id, '_bbptl_lat');
        delete_post_meta($post_id, '_bbptl_lng');

        return true;
    }

    static public function sanitizeCoordinates($latlon = array()){
        return array(
            'lat'=> isset($latlon['lat']) ? self::sanitizeLatitude($latlon['lat']) : null,
            'lon'=> isset($latlon['lon']) ? self::sanitizeLongitude($latlon['lon']) : null,
        );
    }
    static public function sanitizeLatitude($latitude = null) {
      $latitude = (float)$latitude;
      return ( ( $latitude > -90 ) && ( $latitude < 90 ) ) ? $latitude : null;
    }

    static public function sanitizeLongitude($longitude = null) {
      $longitude = (float)$longitude;
      return ( ( $longitude > -180 ) && ( $longitude < 180 ) ) ? $longitude : null;
    }
}

function bbptl(){
    return bbPressTopicLocation::instance();
}

bbptl();
