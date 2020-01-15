<?php
/**
 * bbPress Search Widget
 *
 * Adds a widget which displays the forum search form
 *
 * @since bbPress (r4579)
 *
 * @uses WP_Widget
 */
class BBPTL_Search_Widget extends WP_Widget {

	/**
	 * bbPress Search Widget
	 *
	 * Registers the search widget
	 *
	 * @since bbPress (r4579)
	 *
	 * @uses apply_filters() Calls 'bbp_search_widget_options' with the
	 *                        widget options
	 */
	public function __construct() {
		$widget_ops = apply_filters( 'bbptl_search_widget_options', array(
			'classname'   => 'bbptl_search_widget_display',
			'description' => __( 'The bbPress forum search form, improved with geolocation.', 'bbptl' )
		) );

		parent::__construct( false, __( '(bbPress) Geo Search Form', 'bbptl' ), $widget_ops );
	}

	/**
	 * Register the widget
	 *
	 * @since bbPress (r4579)
	 *
	 * @uses register_widget()
	 */
	public static function register_widget() {
		register_widget( 'BBPTL_Search_Widget' );
	}

	/**
	 * Displays the output, the search form
	 *
	 * @since bbPress (r4579)
	 *
	 * @uses apply_filters() Calls 'bbp_search_widget_title' with the title
	 * @uses get_template_part() To get the search form
	 */
	public function widget( $args, $instance ) {

		// Get widget settings
		$settings = $this->parse_settings( $instance );

		// Typical WordPress filter
		$settings['title'] = apply_filters( 'widget_title',            $settings['title'], $instance, $this->id_base );

		// bbPress filter
		$settings['title'] = apply_filters( 'bbptl_search_widget_title', $settings['title'], $instance, $this->id_base );

		echo $args['before_widget'];

		if ( !empty( $settings['title'] ) ) {
			echo $args['before_title'] . $settings['title'] . $args['after_title'];
		}
                
        //TO FIX
        //bad coding.  But without this, ajaxurl is not defined.
        ?>
        <script type="text/javascript">
        var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
        </script>
        <?php

		bbptl_locate_template('search-form.php',true);

		echo $args['after_widget'];
	}

	/**
	 * Update the widget options
	 *
	 * @since bbPress (r4579)
	 *
	 * @param array $new_instance The new instance options
	 * @param array $old_instance The old instance options
	 */
	public function update( $new_instance, $old_instance ) {
		$instance          = $old_instance;
		$instance['title'] = strip_tags( $new_instance['title'] );

		return $instance;
	}

	/**
	 * Output the search widget options form
	 *
	 * @since bbPress (r4579)
	 *
	 * @param $instance Instance
	 * @uses BBP_Search_Widget::get_field_id() To output the field id
	 * @uses BBP_Search_Widget::get_field_name() To output the field name
	 */
	public function form( $instance ) {

		// Get widget settings
		$settings = $this->parse_settings( $instance ); ?>

		<p>
			<label for="<?php echo $this->get_field_id( 'title' ); ?>"><?php _e( 'Title:', 'bbpress' ); ?>
				<input class="widefat" id="<?php echo $this->get_field_id( 'title' ); ?>" name="<?php echo $this->get_field_name( 'title' ); ?>" type="text" value="<?php echo esc_attr( $settings['title'] ); ?>" />
			</label>
		</p>
		<p>
			<label for="<?php echo $this->get_field_id( 'distance' ); ?>"><?php _e( 'Default distance:', 'bbptl' ); ?>
				<input class="widefat" id="<?php echo $this->get_field_id( 'distance' ); ?>" name="<?php echo $this->get_field_name( 'distance' ); ?>" type="text" value="<?php echo esc_attr( $settings['distance'] ); ?>" />
			</label>
		</p>
		<?php
	}

	/**
	 * Merge the widget settings into defaults array.
	 *
	 * @since bbPress (r4802)
	 *
	 * @param $instance Instance
	 * @uses bbp_parse_args() To merge widget settings into defaults
	 */
	public function parse_settings( $instance = array() ) {
            
            $dist = bbptl()->get_option( '_bbptl_distance');
            
            
                return wp_parse_args($instance, array(
			'title'     => __( 'Search Forums', 'bbpress' ),
                        'distance'  => $dist
		));
	}

}


function bbptl_search_widget_init() {
	register_widget( 'BBPTL_Search_Widget' );
}


?>
