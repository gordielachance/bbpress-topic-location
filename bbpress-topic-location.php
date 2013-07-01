<?php
/*
 * Plugin Name: bbPress Topic Location
 * Plugin URI: http://wordpress.org/extend/plugins/bbpress-pencil-unread
 * Description: This plugin adds the ability to geo-locate a topic in bbPress.
 * Author: G.Breant
 * Version: 1.0.6
 * Author URI: http://bit.ly/cc-sndbox
 * License: GPL2+
 * Text Domain: bbptl
 * Domain Path: /languages/
 */


class bbp_topic_location {

	/** Version ***************************************************************/

	/**
	 * @public string plugin version
	 */
	public $version = '1.0.6';

	/**
	 * @public string plugin DB version
	 */
	public $db_version = '100';
	
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
                        self::$instance = new bbp_topic_location;
                        self::$instance->setup_globals();
                        self::$instance->includes();
                        self::$instance->setup_actions();
                }
                return self::$instance;
        }
        
	
        
        public $post_types = array();
        
        public $lat_rewrite_id;
        public $lng_rewrite_id;
        public $dist_rewrite_id;
        public $addr_rewrite_id;
        public $geo_units; //array of units
        public $current_geo_unit; //key of current unit from the array
        public $distance;
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
                    'miles'=>array(
                        'factor'=>1,
                        'name'=>'miles'
                    ),
                    'km'=>array(
                        'factor'=>0.621371192, //for miles conversion
                        'name'=>'km'
                    )
                );
                
                $this->current_geo_unit=apply_filters('bbpts_current_geo_unit','miles'); //array key
                
                $this->distance='25';
	}
        
	function includes(){
            
            
            require( $this->plugin_dir . 'bbptl-functions.php');
            require( $this->plugin_dir . 'bbptl-template-tags.php');
            require( $this->plugin_dir . 'bbptl-ajax.php');
            require( $this->plugin_dir . 'bbptl-widgets.php');

            if (is_admin()){
            }
	}

	
	function setup_actions(){
            
                //localization (nothing to localize yet, so disable it)
                add_action('init', array($this, 'load_plugin_textdomain'));
                //upgrade
                add_action( 'plugins_loaded', array($this, 'upgrade'));
                //once bbPress is loaded
                //register scripts & styles
                add_action('init', array($this, 'register_scripts_styles'));
                
                //widgets
                add_action( 'widgets_init', 'bbptl_search_widget_init' );
                
                //BBPRESS
                add_action('bbp_init',array($this, 'bbpress_has_init'));
                
                //BACKEND
                
                //add_action('wp_head', array($this, 'head_custom_scripts'));
		add_action( 'admin_enqueue_scripts', array( $this, 'scripts_styles_backend' ) ); //backend
                
		//add geo location field (backend)
		add_action( 'add_meta_boxes',array( $this, 'backend_geolocation_field'));

		//save geolocation (backend)
		add_action( 'save_post',array( $this, 'backend_save_geolocation' ) );
                
                
                //FRONTEND
                
                //scripts & styles
                add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts_styles'));
                
		//new topic - save geolocation (frontend)
		add_action( 'bbp_new_topic',array( $this, 'frontend_save_geolocation'));
                
		//edit topic - save geolocation (frontend)
		add_action('bbp_edit_topic_post_extras',array( $this, 'frontend_save_geolocation'));

		//display location in topic
		add_action ('bbp_theme_after_reply_content',array( $this, 'location_display' ));
                add_action ('bbp_theme_after_topic_content',array( $this, 'location_display' ));

		//display location as icon
		add_action('bbp_theme_after_topic_meta',array( $this, 'location_display' ));
                
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
           
            $version_db_key = $this->prefix.'-db-version';
           
            $current_version = get_option($version_db_key);

            if ($current_version==$this->db_version) return false;
               
            //install

            if(!$current_version){
                //version <1.0.2
                //rename post metas
                
                $rename_metas[] = "UPDATE  `{$wpdb->prefix}postmeta` SET `meta_key`='_bbptl_lng' WHERE `meta_key`='_bbp_topic_geo_long'";
                $rename_metas[] = "UPDATE  `{$wpdb->prefix}postmeta` SET `meta_key`='_bbptl_lat' WHERE `meta_key`='_bbp_topic_geo_lat'";
                $rename_metas[] = "UPDATE  `{$wpdb->prefix}postmeta` SET `meta_key`='_bbptl_info' WHERE `meta_key`='_bbp_topic_geo_info'";
                $sql = implode(';',$rename_metas);

                require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
                dbDelta($sql);
            }

            //update DB version
            update_option($version_db_key, $this->db_version );
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
            
            $current_unit = bbptl_get_current_unit();
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

	
	function scripts_styles_backend( $hook ) {
		global $post;

		if ( $hook != 'post-new.php' && $hook != 'post.php' ) return;
		if (!in_array($post->post_type,$this->get_supported_post_types())) return;
		
		$this->enqueue_scripts_styles();

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

                                        $location = bbptl_get_location_raw($topic_id);
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
		
		function backend_geolocation_field(){
                        global $post;
                        $post_obj = get_post_type_object( get_post_type($post) );
                        foreach((array)$this->get_supported_post_types() as $post_type){
                            add_meta_box( 'bbptl_location_metabox',sprintf(__( 'Geolocate this %s','bbptl' ),$post_obj->labels->singular_name),'bbptl_save_post_geolocation_field',$post_type, 'normal', 'high' );  
                        }
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

        function frontend_save_geolocation($post_id){
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
		
		function backend_save_geolocation($post_id){
			global $bbptl_geolocation;

			// Bail if doing an autosave
			if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE )
				return $post_id;

			// Bail if not a post request
			if ( 'POST' != strtoupper( $_SERVER['REQUEST_METHOD'] ) )
				return $post_id;

			// Bail if post_type do not match
			if (!in_array(get_post_type( $post_id ),$this->get_supported_post_types()))
				return;

			// Bail if current user cannot edit this post
                        $post_obj = get_post_type_object( get_post_type( $post_id ) ); 

			if ( !current_user_can( $post_obj->cap->edit_post, $post_id ) )
				return $post_id;
                        

			//validate input
			if ( !empty( $_POST['bbptl_location'] ) )
					$bbptl_geolocation = $_POST['bbptl_location'];

			// Filter and sanitize
			$bbptl_geolocation = apply_filters( 'bbptl_new_location_pre',$bbptl_geolocation);

			// No topic location
			if ( empty( $bbptl_geolocation ) )
					bbp_add_error( 'bbptl_geolocation', __( '<strong>ERROR</strong>: The location cannot be empty.','bbptl' ) );
			
			//save location
			$success = $this->frontend_save_geolocation($post_id);
			
			return $post_id;
				
				
		}

        function location_display(){
            global $post;
            $bbp = bbpress();
            $post_location = bbptl_get_location_raw($post->ID);
            $distance='';
            if(!$post_location) return false;
            
            //distance
            if(isset($bbp->search_query)){
                $query = $bbp->search_query;
                if (method_exists($query,'get')){
                    $origin_point = $query->get('bpptl_origin_point');

                    if($origin_point){
                        $distance = bbptl_get_distance($origin_point['Latitude'],$origin_point['Longitude'],$post_location['Latitude'],$post_location['Longitude']);
                        $distance.=' '.bbptl_get_unit_name();
                    }
                }

            }


            ?>
            <p class="bbp-topic-meta bbptl-post-location">
				<img src="<?php echo apply_filters('bbptl_location_icon',$this->plugin_url.'_inc/images/home_icon.gif');?>" alt="<?php echo bbptl_get_post_info($post->ID);?>"/>
                <span><?php echo bbptl_get_post_info($post->ID); ?></span>
                <?php if($distance){
                    ?>
                    <span class="bbptl-post-distance"><?php printf(__('(at %s)','bbpts'),$distance);?></span>
                    <?php
                }
                ?>
            </p>
            <?php
        }

}

function bbp_topic_location(){
    return bbp_topic_location::instance();
}

bbp_topic_location();