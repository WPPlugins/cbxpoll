<?php

 
 // Prevent direct file access
if ( ! defined ( 'ABSPATH' ) ) {
	exit;
}


class CBXPollsingleWidget extends WP_Widget {

    /**
     *
     * Unique identifier for your widget.
     *
     *
     * The variable name is used as the text domain when internationalizing strings
     * of text. Its value should match the Text Domain file header in the main
     * widget file.
     *
     * @since    1.0.0
     *
     * @var      string
     */
    protected $widget_slug = 'cbxpollsingle'; //main parent plugin's language file

	/*--------------------------------------------------*/
	/* Constructor
	/*--------------------------------------------------*/

	/**
	 * Specifies the classname and description, instantiates the widget,
	 * loads localization files, and includes necessary stylesheets and JavaScript.
	 */
	public function __construct() {

		parent::__construct(
			$this->get_widget_slug(),
			__( 'CBXPoll Single Widget', 'cbxpoll' ),
			array(
				'classname'  => $this->get_widget_slug().'-class',
				'description' => __( 'Displays single poll', 'cbxpoll' )
			)
		);

		// Register admin styles and scripts
		add_action( 'admin_print_styles', array( $this, 'register_admin_styles' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'register_admin_scripts' ) );

		// Register site styles and scripts
		add_action( 'wp_enqueue_scripts', array( $this, 'register_widget_styles' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'register_widget_scripts' ) );


		//add_action( 'switch_theme', array( $this, 'flush_widget_cache' ) );

	} // end constructor


    /**
     * Return the widget slug.
     *
     * @since    1.0.0
     *
     * @return    Plugin slug variable.
     */
    public function get_widget_slug() {
        return $this->widget_slug;
    }

	/*--------------------------------------------------*/
	/* Widget API Functions
	/*--------------------------------------------------*/

	/**
	 * Outputs the content of the widget.
	 *
	 * @param array args  The array of form elements
	 * @param array instance The current instance of the widget
	 */
	public function widget( $args, $instance ) {

		
		// Check if there is a cached output
		$cache = wp_cache_get( $this->get_widget_slug(), 'widget' );

		if ( !is_array( $cache ) )
			$cache = array();

		if ( ! isset ( $args['widget_id'] ) )
			$args['widget_id'] = $this->id;

		if ( isset ( $cache[ $args['widget_id'] ] ) )
			return print $cache[ $args['widget_id'] ];
		
		// go on with your widget logic, put everything into a string and …


		extract( $args, EXTR_SKIP );

		$widget_string = $before_widget;

		$title         = apply_filters( 'widget_title', empty( $instance['title'] ) ? __( 'CBXPoll Single Widget', 'cbxpoll' ) : $instance['title'], $instance, $this->id_base );
		// Defining the Widget Title
		if ( $title ) {
			$widget_string .= $args['before_title'] . $title . $args['after_title'];
		}
		else{
			$widget_string .= $args['before_title'] .  $args['after_title'];
		}


		//ob_start();

		$instance = apply_filters('cbxpollsinglewidget_widget', $instance);

		$instance['chart_type'] = isset($instance['chart_type'])? esc_attr($instance['chart_type']): 'text';
		$instance['poll_id']    = isset($instance['poll_id'])? esc_attr($instance['poll_id']): '';

		extract($instance);


		if(intval($poll_id) > 0 ){
			$widget_string .=   cbxpoll::cbxpoll_single_display($poll_id, 'widget', $chart_type );
		}
		else{
			$widget_string .= '<p>'.__('Poll id missing', 'cbxpoll').'</p>';
		}




		//$widget_string .= ob_get_clean();

		$widget_string .= $after_widget;


		/*$cache[ $args['widget_id'] ] = $widget_string;

		wp_cache_set( $this->get_widget_slug(), $cache, 'widget' );

		print $widget_string;*/

		echo $widget_string;

	} // end widget
	
	
	public function flush_widget_cache() 
	{
    	wp_cache_delete( $this->get_widget_slug(), 'widget' );
	}
	/**
	 * Processes the widget's options to be saved.
	 *
	 * @param array new_instance The new instance of values to be generated via the update.
	 * @param array old_instance The previous instance of values before the update.
	 */
	public function update( $new_instance, $old_instance ) {

		$instance = $old_instance;



		$instance['title']             = sanitize_text_field( $new_instance['title'] );
		$instance['chart_type']        = esc_attr( $new_instance['chart_type'] );
		$instance['poll_id']           = esc_attr( $new_instance['poll_id'] );



		$instance = apply_filters('cbxpollsinglewidget_update', $instance, $new_instance);



		return $instance;

	} // end widget

	/**
	 * Generates the administration form for the widget.
	 *
	 * @param array instance The array of keys and values for the widget.
	 */
	public function form( $instance ) {

		$fields = array(
			'title'                 => __( 'CBXPoll Single Widget', 'cbxpoll' ),
			'chart_type'            => 'text', //chart type
			'poll_id'               => 0, //poll id
		);

		$fields = apply_filters('cbxpollsinglewidget_widget_form_fields', $fields);

		$instance = wp_parse_args(
			(array) $instance,
			$fields
		);

		$instance = apply_filters('cbxpollsinglewidget_widget_form', $instance);

		extract( $instance, EXTR_SKIP );


		// Display the admin form
		include( plugin_dir_path(__FILE__) . 'views/admin.php' );

	} // end form

	/*--------------------------------------------------*/
	/* Public Functions
	/*--------------------------------------------------*/




	/**
	 * Registers and enqueues admin-specific styles.
	 */
	public function register_admin_styles() {

		wp_enqueue_style( $this->get_widget_slug().'-admin-styles', plugins_url( 'css/cbxsinglepoll-widget-admin.css', __FILE__ ) );

	} // end register_admin_styles

	/**
	 * Registers and enqueues admin-specific JavaScript.
	 */
	public function register_admin_scripts() {

		wp_enqueue_script( $this->get_widget_slug().'-admin-script', plugins_url( 'js/cbxsinglepoll-widget-admin.js', __FILE__ ), array('jquery') );

	} // end register_admin_scripts

	/**
	 * Registers and enqueues widget-specific styles.
	 */
	public function register_widget_styles() {

		wp_enqueue_style( $this->get_widget_slug().'-widget-styles', plugins_url( 'css/cbxsinglepoll-widget-public.css', __FILE__ ) );

	} // end register_widget_styles

	/**
	 * Registers and enqueues widget-specific scripts.
	 */
	public function register_widget_scripts() {

		wp_enqueue_script( $this->get_widget_slug().'-script', plugins_url( 'js/cbxsinglepoll-widget-public.js', __FILE__ ), array('jquery') );

	} // end register_widget_scripts

} // end class



