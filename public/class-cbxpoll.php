<?php
/**
 * @package   cbxpoll
 * @author    codeboxr <info@codeboxr.com>
 * @license   GPL-2.0+
 * @link      http://codeboxr.com/product/cbx-poll-for-wordpress/
 * @copyright 2016 codeboxr
 */

//require_once(plugin_dir_path(__FILE__) . '../admin/class-cbxpoll-admin.php');
require_once(plugin_dir_path(__FILE__) . '/../widgets/single/cbxpollsingle-widget.php');

/**
 * Class cbxpoll
 */
class cbxpoll
{

	const VERSION = '';

	protected $plugin_slug = 'cbxpoll';

	protected static $instance = null;

	public static $version = CBX_POLL_PLUGIN_VERSION;

	/**
	 * __construct function of this class
	 */
	private function __construct()
	{

		//$setting_api    = get_option( 'cbxpoll_global_settings');

		add_filter('cbxpoll_display_options', array($this, 'cbxpoll_display_options_text'));

		// init cookie and language and meta
		add_action('init', array($this, 'init_cbxpoll'));
		//add_action('init', array($this,'create_poll_post_type' ));

		// Activate plugin when new blog is added
		add_action('wpmu_new_blog', array($this, 'activate_new_site'));
		// Load public-facing style sheet and JavaScript.

		add_action('wp_enqueue_scripts', array($this, 'wp_enqueue_styles'));
		add_action('wp_enqueue_scripts', array($this, 'wp_enqueue_scripts'));

		// archive page
		//add_shortcode('smartpollarchive', array('cbxpoll', 'poll_archive_shortcode'));

		//adding shortcode
		add_shortcode('cbxpolls', array($this, 'cbxpolls_shortcode')); //all
		add_shortcode('cbxpoll', array($this, 'cbxpoll_shortcode')); //single

		//Show poll in details poll post type
		if (!is_admin())
		{
			add_filter('the_content', array($this, 'cbxpoll_the_content'));
		}

		// ajax for voting
		add_action("wp_ajax_cbxpoll_user_vote", array($this, "cbxpoll_ajax_vote"));
		add_action("wp_ajax_nopriv_cbxpoll_user_vote", array($this, "cbxpoll_ajax_vote"));

		// ajax for read more page
		add_action("wp_ajax_cbxpoll_list_pagination", array($this, "cbxpoll_ajax_poll_list"));
		add_action("wp_ajax_nopriv_cbxpoll_list_pagination", array($this, "cbxpoll_ajax_poll_list"));

		add_action('widgets_init', array($this, 'singlecbxpollwidget'));

	}// end of construct function

	function singlecbxpollwidget()
	{

		register_widget('CBXPollsingleWidget');
	}

	/**
	 *  Add Text type poll result display method
	 *
	 * @param array $methods
	 *
	 * @return array
	 */
	public function cbxpoll_display_options_text($methods)
	{

		//$methods = array(
		$methods['text'] = array(
				'title'  => __('Text', 'cbxpoll'),
				'method' => array($this, 'cbxpoll_result_text_display')
			);
		//);

		return $methods;
	}

	/**
	 * @param $atts
	 *
	 * @return string
	 * poll listing
	 */
	public function poll_archive_shortcode($atts)
	{

		$options = shortcode_atts(array(
			                          'per_page' => '',
		                          ), $atts);

		$args = array(
			'post_type'      => 'cbxpoll',
			'orderby'        => 'title',
			'order'          => 'ASC',
			'post_status'    => 'any',
			'posts_per_page' => -1,
		);

		$my_query = new WP_Query($args);
		$output = '<ul>';
		while ($my_query->have_posts()) : $my_query->the_post();
			$output .= '<li><a href = "' . get_the_permalink() . '">' . get_the_title() . '</a></li>';
		endwhile;

		wp_reset_query(); //needed
		$output .= '</ul>';

		return $output;
	}

	/**
	 * cbxpoll_ajax_poll_list
	 */
	public function cbxpoll_ajax_poll_list()
	{

		check_ajax_referer('cbxpollslisting', 'security');

		global $wpdb, $post;

		$post_per_page = intval($_POST['per_page']);
		$current_page  = intval($_POST['page_no']);

		$output = self::poll_list($post_per_page, $current_page);

		//$poll_page_data ['content'] = json_encode($content);

		echo wp_json_encode($output);
		wp_die();
	}

	/**
	 * List polls
	 *
	 * @param string $per_page
	 * @param        $page_number
	 *
	 * @return array
	 */
	public static function poll_list($per_page = 5, $page_number)
	{
		$output = array();

		$args = array(
			'post_type'      => 'cbxpoll',
			'post_status'    => 'publish',
			'posts_per_page' => $per_page,
			'paged'          => $page_number
		);

		$content = '';

		$cb_query = new WP_Query($args);

//                    echo '<pre>';
//                    print_r($cb_query);
//                    echo '</pre>';

		if ($cb_query->have_posts())
		{
			$output['found']         = 1;
			$output['found_posts']   = $cb_query->found_posts;
			$output['max_num_pages'] = $cb_query->max_num_pages;

			while ($cb_query->have_posts()) : $cb_query->the_post();
				$poll_id = get_the_ID();
				$content .= cbxpoll::cbxpoll_single_display($poll_id, 'shortcode');

			endwhile;
		}
		else
		{
			$output['found'] = 1;
		}
		// end of if have post

		wp_reset_query(); //needed

		$output['content'] = $content;

		return $output;
	}

	/**
	 * Function to parse all poll shortcode
	 *
	 * @param $atts
	 *
	 * @return string
	 */
	public static function cbxpolls_shortcode($atts)
	{

		$nonce          = wp_create_nonce('cbxpollslisting');
		$show_load_more = true;

		$options = shortcode_atts(array(
			                          'per_page' => 5,
		                          ), $atts);

		$per_page            = (int) $options['per_page']; //just for check now its 2 after get from args
		$current_page_number = 1;

		$content = '<div class="cbxpoll-listing-wrap">';
		$content .= '<div class="cbxpoll-listing">';

		$poll_list_output = self::poll_list($per_page, $current_page_number);

		//var_dump($poll_list_output);

		if (intval($poll_list_output['found']))
		{
			$content .= $poll_list_output['content'];
		}
		else
		{
			$content .= __('No poll found', 'cbxpoll');
			$show_load_more = false;
		}

		$content .= '</div>';

		$current_page_number++;

		if ($show_load_more && $poll_list_output['max_num_pages'] == 1)
		{
			$show_load_more = false;
		}

		//$image_path = plugins_url('cbxpoll/public/assets/css/busy.gif');

		//$content .= '<div class = "cbxpoll_ajax_link " ><img src="' . $image_path . '" class = "cbxpoll_busy_icon"/>';

		if ($show_load_more && (int) $options['per_page'] != -1 && $options['per_page'] != '')
		{

			$content .= '<p class="cbxpoll-listing-more"><a class="cbxpoll-listing-trig" href="#" data-security="' . $nonce . '" data-page-no="' . $current_page_number . '"  data-busy ="0" data-per-page="' . $per_page . '">' . __('View More Polls', 'cbxpoll') . '<span class="cbvoteajaximage cbvoteajaximagecustom"></span></a></p>';
		}

		$content .= '</div>';

		return $content;
	}

	/**
	 * Shortcode to show single poll [cbxpoll id="comma separated poll id"]
	 *
	 * @param array $atts
	 *
	 * @return string
	 */
	public function cbxpoll_shortcode($atts)
	{

		$setting_api = get_option('cbxpoll_global_settings');

		$global_result_chart_type = isset($setting_api['result_chart_type']) ? $setting_api['result_chart_type'] : 'text';

		$options = shortcode_atts(array(
			                          'id'         => '',
			                          'chart_type' => $global_result_chart_type
		                          ), $atts);

		$poll_ids = explode(',', $options['id']);

		$output = '';

		foreach ($poll_ids as $poll_id)
		{
			$output .= self::cbxpoll_single_display($poll_id, 'shortcode', $options['chart_type']);
		}

		return $output;
	}

	/**
	 * Load language  for cbxpoll
	 *
	 * @return array
	 * initialize with init
	 */
	function init_cbxpoll()
	{

		global $wp_roles;
		$path        = dirname(plugin_basename(__FILE__)) . '../languages/';
		$lang_loaded = load_plugin_textdomain('cbxpoll', false, $path);

		self::init_cookie();

		self::create_cbxpoll_post_type();

	}

	/**
	 * Shows a single poll
	 *
	 * @param integer $post_id cbxpoll post type id
	 * @param string  $reference
	 *
	 * @return string
	 *
	 */
	public static function cbxpoll_single_display($post_id = 0, $reference = 'shortcode', $result_chart_type = '')
	{

		//if poll id
		if (intval($post_id) == 0)
		{
			return '';
		}

		global $wpdb;

		//$user_id = get_current_user_id();
        $current_user = wp_get_current_user();
		$user_id = $current_user->ID;
		$user_ip      = self::get_ipaddress();

		if ($user_id == 0)
		{

			$user_session = $_COOKIE[CBX_POLL_COOKIE_NAME]; //this is string
			//$user_ip      = self::get_ipaddress();

		}
		elseif ($user_id > 0)
		{

			$user_session = 'user-' . $user_id; //this is string
			//$user_ip      = self::get_ipaddress();
		}

		$setting_api = get_option('cbxpoll_global_settings');
		$poll_table  = cbxpollHelper::cbx_poll_table_name();

		//poll informations from meta

		$poll_start_date                = get_post_meta($post_id, '_cbxpoll_start_date', true); //poll start date
		$poll_end_date                  = get_post_meta($post_id, '_cbxpoll_end_date', true); //poll end date
		$poll_user_roles                = get_post_meta($post_id, '_cbxpoll_user_roles', true); //poll user roles
		//$poll_back_color                = get_post_meta($post_id, '_cbxpoll_back_color', true); //poll background color
		$poll_content                   = get_post_meta($post_id, '_cbxpoll_content', true); //poll content
		$poll_never_expire              = get_post_meta($post_id, '_cbxpoll_never_expire', true); //poll never epire
		$poll_show_result_before_expire = get_post_meta($post_id, '_cbxpoll_show_result_before_expire', true); //poll never epire
		$poll_show_result_all           = get_post_meta($post_id, '_cbxpoll_show_result_all', true); //show_result_all
		$poll_result_chart_type         = get_post_meta($post_id, '_cbxpoll_result_chart_type', true); //chart type
		$poll_is_voted                  = intval(get_post_meta($post_id, '_cbxpoll_is_voted', true)); //at least a single vote

		//new field from v1.0.1

		$poll_multivote                 = intval(get_post_meta($post_id, '_cbxpoll_multivote', true)); //at least a single vote

		$vote_input_type = ($poll_multivote)? 'checkbox' : 'radio';

		//$global_result_chart_type   = isset($setting_api['result_chart_type'])? $setting_api['result_chart_type']: 'text';
		//$poll_result_chart_type = get_post_meta($post_id, '_cbxpoll_result_chart_type', true);

		$result_chart_type = ($result_chart_type != '') ? $result_chart_type : $poll_result_chart_type;

		//fallback as text if addon no installed
		$result_chart_type = self::chart_type_fallback($result_chart_type); //make sure that if chart type is from pro addon then it's installed

		$poll_answers = get_post_meta($post_id, '_cbxpoll_answer', true);

		$poll_answers = is_array($poll_answers) ? $poll_answers : array();
		$poll_colors  = get_post_meta($post_id, '_cbxpoll_answer_color', true);

		$log_method = '';
		if(isset($setting_api['logmethod'])){
			$log_method = $setting_api['logmethod'];
		}


		$log_metod = ($log_method != '') ? $log_method : 'both';

		$is_poll_expired = new DateTime($poll_end_date) < new DateTime(); //check if poll expired from it's end data
		$is_poll_expired = ($poll_never_expire == '1') ? false : $is_poll_expired; //override expired status based on the meta information

		$poll_allowed_user_group = empty($poll_user_roles) ? $setting_api['user_roles'] : $poll_user_roles;

		$cb_question_list_to_find_ans = array();
		foreach ($poll_answers as $poll_answer)
		{
			array_push($cb_question_list_to_find_ans, $poll_answer);
		}

		//$image_path           = plugins_url('cbxpoll/public/assets/css/busy.gif');
		//$poll_vote_busy_image = '<img src="' . $image_path . '" class ="cbvoteajaximage cbvoteajaximagecustom" style ="width:16px;" />';

		$poll_output = '';

		$nonce = wp_create_nonce('cbxpolluservote');

		$poll_output .= '<div class="cbxpoll_wrapper cbxpoll_wrapper-' . $post_id . ' cbxpoll_wrapper-' . $reference . '" data-reference ="' . $reference . '" >';
		$poll_output .= ' <div class="cbxpoll-qresponse cbxpoll-qresponse-' . $post_id . '"></div>';

		//check if the poll started still
		if (new DateTime($poll_start_date) <= new DateTime())
		{

			if ($reference != 'content_hook')
			{
				$poll_output .= '<h3>' . get_the_title($post_id) . '</h3>';
			}

			$poll_is_voted_by_user = 0;

			if ($log_metod == 'cookie')
			{

				$sql                   = $wpdb->prepare("SELECT COUNT(ur.id) AS count FROM $poll_table ur WHERE  ur.poll_id=%d AND ur.user_id=%d AND ur.user_cookie = %s", $post_id, $user_id, $user_session);
				$poll_is_voted_by_user = $wpdb->get_var($sql);

			}
			elseif ($log_metod == 'ip')
			{

				$sql                   = $wpdb->prepare("SELECT COUNT(ur.id) AS count FROM $poll_table ur WHERE  ur.poll_id=%d AND ur.user_id=%d AND ur.user_ip = %s", $post_id, $user_id, $user_ip);
				$poll_is_voted_by_user = $wpdb->get_var($sql);

			}
			else if ($log_metod == 'both')
			{

				$sql               = $wpdb->prepare("SELECT COUNT(ur.id) AS count FROM $poll_table ur WHERE  ur.poll_id=%d AND ur.user_id=%d AND ur.user_cookie = %s", $post_id, $user_id, $user_session);
				$vote_count_cookie = $wpdb->get_var($sql);

				$sql           = $wpdb->prepare("SELECT COUNT(ur.id) AS count FROM $poll_table ur WHERE  ur.poll_id=%d AND ur.user_id=%d AND ur.user_ip = %s", $post_id, $user_id, $user_ip);
				$vote_count_ip = $wpdb->get_var($sql);

				if ($vote_count_cookie >= 1 || $vote_count_ip >= 1)
				{
					$poll_is_voted_by_user = 1;
				}

			}

			if ($is_poll_expired)
			{ // if poll has expired

				$sql           = $wpdb->prepare("SELECT ur.id AS answer FROM $poll_table ur WHERE  ur.poll_id=%d  ", $post_id);
				$cb_has_answer = $wpdb->get_var($sql);

				if ($cb_has_answer != null)
				{

					$poll_output .= self:: show_single_poll_result($post_id, $reference, $result_chart_type);
				}

				$sql             = $wpdb->prepare("SELECT ur.user_answer AS answer FROM $poll_table ur WHERE  ur.poll_id=%d AND ur.user_id=%d AND ur.user_ip = %s AND ur.user_cookie = %s ", $post_id, $user_id, $user_ip, $user_session);
				$answers_by_user = $wpdb->get_var($sql);

				$answers_by_user_html = '';

				if ($answers_by_user !== NULL)
				{
					$answers_by_user                       = maybe_unserialize($answers_by_user);
					if(is_array($answers_by_user)){
						$user_answers_texual = array();
						foreach($answers_by_user  as $uchoice){
							$user_answers_texual[]= $poll_answers[$uchoice];
						}

						$answers_by_user_html = implode(", ", $user_answers_texual);
					}
					else{
						$answers_by_user						= intval($answers_by_user);
						$answers_by_user_html 					= $poll_answers[$answers_by_user];

					}

					if ($answers_by_user_html != "")
					{
						$poll_output .= '<p class="cbxpoll-voted-info cbxpoll-voted-info-' . $post_id . '">' . sprintf(__('The Poll is out of date. You have already voted for <strong>"%s"</strong>', 'cbxpoll'), $answers_by_user_html) . '</p>';
					}
					else
					{
						$poll_output .= '<p class="cbxpoll-voted-info cbxpoll-voted-info-' . $post_id . '"> ' . sprintf(__('The Poll is out of date. You have already voted for <strong>"%s"</strong>', 'cbxpoll'), $answers_by_user_html) . '</p>';

					}

				}
				else
				{
					$poll_output .= '<p class="cbxpoll-voted-info cbxpoll-voted-info-' . $post_id . '"> ' . __('The Poll is out of date. You have not voted.', 'cbxpoll') . '</div>';
				}

			} // end of if poll expired
			else
			{

				if (is_user_logged_in())
				{
					global $current_user;
					$this_user_role = $current_user->roles;
				}
				else
				{
					$this_user_role = array('guest');
				}

				$allowed_user_group = array_intersect($poll_allowed_user_group, $this_user_role);

				//current user is not allowed
				if ((sizeof($allowed_user_group)) < 1)
				{

					//we know poll is not expired, and user is not allowed to vote
					//now we check if the user i allowed to see result and result is allow to show before expire
					if ($poll_show_result_all == '1' && $poll_show_result_before_expire == '1')
					{

						if ($poll_is_voted)
						{
							$poll_output .= self::show_single_poll_result($post_id, $reference, $result_chart_type);
						}

						$poll_output .= '<p class="cbxpoll-voted-info cbxpoll-voted-info-' . $post_id . '"> ' . __('You are not allowed to vote.', 'cbxpoll') . '</p>';


					}
					$poll_output .= '<p class="cbxpoll-voted-info cbxpoll-voted-info-' . $post_id . '"> ' . __('You are not allowed to view the result.', 'cbxpoll') . '</p>';

				}
				else
				{
					//current user is allowed

					//current user has voted this once
					if ($poll_is_voted_by_user)
					{

						$sql             = $wpdb->prepare("SELECT ur.user_answer AS answer FROM $poll_table ur WHERE  ur.poll_id=%d AND ur.user_id=%d AND ur.user_ip = %s AND ur.user_cookie = %s ", $post_id, $user_id, $user_ip, $user_session);
						$answers_by_user = $wpdb->get_var($sql);



						if ($answers_by_user !== NULL)
						{
							$answers_by_user                       = maybe_unserialize($answers_by_user);
							if(is_array($answers_by_user)){
								$user_answers_texual = array();
								foreach($answers_by_user  as $uchoice){
									$user_answers_texual[]= $poll_answers[$uchoice];
								}

								$answers_by_user_html = implode(", ", $user_answers_texual);
							}
							else{
								$answers_by_user						= intval($answers_by_user);
								$answers_by_user_html 					= $poll_answers[$answers_by_user];

							}



							if ($answers_by_user_html != "")
							{
								$poll_output .= '<p class="cbxpoll-voted-info cbxpoll-voted-info-' . $post_id . '">' . sprintf(__('You have already voted for <strong>"%s"</strong>', 'cbxpoll'), $answers_by_user_html) . '</p>';
							}
							else
							{
								$poll_output .= '<p class="cbxpoll-voted-info cbxpoll-voted-info-' . $post_id . '">' . __('You have already voted ', 'cbxpoll') . '</p>';

							}
						}

						if ($poll_show_result_before_expire == '1')
						{
							$poll_output .= self:: show_single_poll_result($post_id, $reference, $result_chart_type);
						}

					}
					else
					{
						//current user didn't vote yet

						$poll_output .= '
                                <div class="cbxpoll_answer_wrapper cbxpoll_answer_wrapper-' . $post_id . '" data-id="' . $post_id . '">
                                    <form class="cbxpoll-form cbxpoll-form-' . $post_id . '">
                                        <div class="cbxpoll-form-insidewrap cbxpoll-form-insidewrap-' . $post_id . '">
                                            <ul class="cbxpoll-form-ans-list cbxpoll-form-ans-list-' . $post_id . '">';

						//listing poll answers as radio button
						foreach ($poll_answers as $index => $answer){

							$input_name = 'cbxpoll_user_answer';
							if($poll_multivote){
								$input_name .= '-'.$index;
							}
							$poll_output .= '<li>';
							$poll_output .= '<input type="'.$vote_input_type.'" value="' . $index . '" class="cbxpoll_single_answer cbxpoll_single_answer-radio cbxpoll_single_answer-radio-' . $post_id . '" data-pollcolor = "' . $poll_colors[$index] . ' "data-post-id="' . $post_id . '" name="'.$input_name.'"  data-answer="' . $answer . ' " id="cbxpoll_single_answer-radio-' . $index . '-' . $post_id . '"  />';
							$poll_output .= '<label for="cbxpoll_single_answer-radio-' . $index . '-' . $post_id . '"><span class="cbxpoll_single_answer cbxpoll_single_answer-text cbxpoll_single_answer-text-' . $post_id . '"  data-post-id="' . $post_id . '" data-answer="' . $answer . ' ">' . $answer . '</span></label>';
							$poll_output .= '</li>';
						}

						//$poll_output .= '<li style = "list-style-type:none;"><p class = "cbxpoll_ajax_link">' . $poll_vote_busy_image . '<button class="cbxpoll_vote_btn" data-reference = "' . $reference . '" data-charttype = "' . $result_chart_type . '" data-busy = "0" data-post-id="' . $post_id . '"  data-security="'.$nonce.'" >' . __('Vote', 'cbxpoll') . '</button></p></li>';
						$poll_output .= '<li style = "list-style-type:none;"><p class = "cbxpoll_ajax_link"><button class="cbxpoll_vote_btn" data-reference = "' . $reference . '" data-charttype = "' . $result_chart_type . '" data-busy = "0" data-post-id="' . $post_id . '"  data-security="' . $nonce . '" >' . __('Vote', 'cbxpoll') . '<span class="cbvoteajaximage cbvoteajaximagecustom"></span></button></p></li>';

						$poll_output .= '
                                            </ul>
                                         </div>
                                    </form>
                                    <div class="cbxpoll_clearfix"></div>
                                </div>';

					}
					// end of if voted
				}
				// end of allowed user
			}
			// end of pole expires

			

		}//poll didn't start yet
		else
		{
			$poll_output = __('Poll Status: Yet to start', 'cbxpoll');
		}

		$poll_output .= '</div>'; //end of cbxpoll_wrapper

		return $poll_output;
	}

	/**
	 * Get result from a single poll
	 *
	 * @param int $post_id
	 */
	public static function show_single_poll_result($poll_id, $reference, $result_chart_type = 'text')
	{

		//var_dump($result_chart_type);

		global $wpdb;
        //$user_id = get_current_user_id();
        $current_user = wp_get_current_user();
        $user_id = $current_user->ID;


        if ($user_id == 0)
		{
			$user_session = $_COOKIE[CBX_POLL_COOKIE_NAME]; //this is string
			$user_ip      = self::get_ipaddress();
		}
		elseif ($user_id > 0)
		{
			$user_session = 'user-' . $user_id; //this is string
			$user_ip      = self::get_ipaddress();
		}

		$setting_api                    = get_option('cbxpoll_global_settings');
		$poll_start_date                = get_post_meta($poll_id, '_cbxpoll_start_date', true); //poll start date
		$poll_end_date                  = get_post_meta($poll_id, '_cbxpoll_end_date', true); //poll end date
		$poll_user_roles                = get_post_meta($poll_id, '_cbxpoll_user_roles', true); //poll user roles
		//$poll_back_color                = get_post_meta($poll_id, '_cbxpoll_back_color', true); //poll background color
		$poll_content                   = get_post_meta($poll_id, '_cbxpoll_content', true); //poll content
		$poll_never_expire              = get_post_meta($poll_id, '_cbxpoll_never_expire', true); //poll never epire
		$poll_show_result_before_expire = get_post_meta($poll_id, '_cbxpoll_show_result_before_expire', true); //poll never epire
		$poll_show_result_all           = get_post_meta($poll_id, '_cbxpoll_show_result_all', true); //show_result_all

		$poll_result_chart_type = get_post_meta($poll_id, '_cbxpoll_result_chart_type', true); //chart type

		$result_chart_type = self::chart_type_fallback($result_chart_type);

		$poll_answers = get_post_meta($poll_id, '_cbxpoll_answer', true);
		$poll_answers = is_array($poll_answers) ? $poll_answers : array();

		$poll_colors = get_post_meta($poll_id, '_cbxpoll_answer_color', true);
		$poll_colors = is_array($poll_colors) ? $poll_colors : array();

		$total_results = cbxpollHelper::get_pollResult($poll_id);

		$poll_result = array();

		$poll_result['reference'] = $reference;
		$poll_result['poll_id']   = $poll_id;
		$poll_result['total']     = count($total_results);

		$poll_result['colors'] = $poll_colors;
		$poll_result['answer'] = $poll_answers;
		//$poll_result['results']    		= json_encode($total_results);
		$poll_result['chart_type'] = $result_chart_type;
		$poll_result['text']       = '';

		$poll_answers_weight = array();

		foreach ($total_results as $result)
		{
			$user_ans                       = maybe_unserialize($result['user_answer']);
			if(is_array($user_ans)){
				foreach($user_ans as $u_ans){
					$old_val                        = isset($poll_answers_weight[$u_ans]) ? intval($poll_answers_weight[$u_ans]) : 0;
					$poll_answers_weight[$u_ans] = ($old_val + 1);
				}
			}
			else{
				$user_ans						= intval($user_ans);
				$old_val                        = isset($poll_answers_weight[$user_ans]) ? intval($poll_answers_weight[$user_ans]) : 0;
				$poll_answers_weight[$user_ans] = ($old_val + 1);
			}
		}

		$poll_result['answers_weight'] = $poll_answers_weight;

		//ready mix :)
		$poll_weighted_labels = array();
		foreach ($poll_answers as $index => $answer)
		{
			$poll_weighted_labels[$answer] = isset($poll_answers_weight[$index]) ? $poll_answers_weight[$index] : 0;
		}

		$poll_result['weighted_label'] = $poll_weighted_labels;

		ob_start();

		echo '<div class="cbxpoll_result_wrap cbxpoll_result_wrap_' . $reference . ' cbxpoll_'.$result_chart_type.'_result_wrap cbxpoll_'.$result_chart_type.'_result_wrap_' . $poll_id . ' cbxpoll_result_wrap_' . $reference . '_' . $poll_id . ' ">';
		$poll_display_methods = cbxpollHelper::cbxpoll_display_options();
		$poll_display_method  = $poll_display_methods[$result_chart_type];
		//$object               = $poll_display_method['class'];
		$method               = $poll_display_method['method'];

		if ($method != '' && is_callable($method))
		{
			call_user_func_array($method, array($poll_id, $reference, $poll_result));
		}
		
		echo '</div>';		

		$output = ob_get_contents();
		ob_end_clean();

		return $output;

	}


	/**
	 * Display poll result as text method
	 * 
	 * @param int $poll_id
	 * @param string $reference
	 * 
	 * @param string $poll_result
	 */
	public function cbxpoll_result_text_display($poll_id, $reference = 'shortcode', $poll_result)
	{

		$total  = $poll_result['total'];
		$colors = $poll_result['colors'];



		$output_result = '';

		if ($total > 0)
		{
			$output = '<p>' . sprintf(__('Total votes: %d', 'cbxpoll'), number_format_i18n($total)) . '</p>';
			$output .= '<ul>';



			$total_percent = 0;
			$i = 0;
			foreach ($poll_result['weighted_label'] as $answer => $vote)
			{
				$percent = ($vote * 100) / $total;
				$total_percent += $percent;
				$output_result .= '<li style="color:' . $colors[$i] . ';"><strong>' . $answer . ': ' . $vote . ' (' . number_format_i18n($percent, 2) . '%)</strong></li>';
				$i++;
			}

			//var_dump($total_percent);

			if($total_percent > 0){
				$output_result = '';
				$i = 0;
				foreach ($poll_result['weighted_label'] as $answer => $vote)
				{
					$percent = ($vote * 100) / $total;
					$re_percent = ($percent * 100)/$total_percent;

					//$total_percent += $total_percent;

					$output_result .= '<li style="color:' . $colors[$i] . ';"><strong>' . $answer . ': ' . $vote . ' (' . number_format_i18n($re_percent, 2) . '%)</strong></li>';
					$i++;
				}
			}

			$output .= $output_result;
			$output .= '</ul>';
		}
		else
		{
			$output = '<p>' . __('No vote yet', 'cbxpoll') . '</p>';
		}

		echo $output;
	}

		

	/**
	 * Append poll with the poll post type description
	 *
	 * @param $content
	 *
	 * @return string
	 */
	function cbxpoll_the_content($content)
	{

		global $post;

		if (is_singular('cbxpoll'))
		{
			$content .= cbxpoll::cbxpoll_single_display($post->ID, 'content_hook');
		}

		return $content;

	}

	/**
	 * Create custom post type poll
	 */
	function create_cbxpoll_post_type()
	{

		register_post_type('cbxpoll',
		                   array(
			                   'labels'      => array(
				                   'name'          => __('CBX Polls', 'cbxpoll'),
				                   'singular_name' => __('CBX Poll', 'cbxpoll')
			                   ),
			                   'menu_icon'   => plugins_url('assets/css/poll.png', __FILE__), // 16px16
			                   'public'      => true,
			                   'has_archive' => true,
		                   )
		);
	}

	/**
	 * Return the plugin slug.
	 *
	 * @since    1.0.0
	 *
	 * @return    Plugin slug variable.
	 *
	 *
	 */
	public function get_plugin_slug()
	{

		return $this->plugin_slug;
	}

	/**
	 * called when plugin activated
	 */
	public static function install_plugin()
	{

		$check_poll_user_group = get_option('cbxpoll_global_settings');

		cbxpollHelper::install_table();

	}

	/**
	 * Return an instance of this class.
	 *
	 * @since     1.0.0
	 *
	 * @return    object    A single instance of this class.
	 */
	public static function get_instance()
	{

		// If the single instance hasn't been set, set it now.
		if (null == self::$instance)
		{
			self::$instance = new self;
		}

		return self::$instance;
	}

	/**
	 * Fired when the plugin is activated.
	 *
	 * @since    1.0.0
	 *
	 * @param    boolean $network_wide       True if WPMU superadmin uses
	 *                                       "Network Activate" action, false if
	 *                                       WPMU is disabled or plugin is
	 *                                       activated on an individual blog.
	 */

	public static function activate($network_wide)
	{

		if (function_exists('is_multisite') && is_multisite())
		{

			if ($network_wide)
			{

				// Get all blog ids
				$blog_ids = self::get_blog_ids();

				foreach ($blog_ids as $blog_id)
				{

					switch_to_blog($blog_id);
					self::single_activate();
				}

				restore_current_blog();

			}
			else
			{
				self::single_activate();
			}

		}
		else
		{
			self::single_activate();
		}

	}

	/**
	 * Fired when the plugin is deactivated.
	 *
	 * @since    1.0.0
	 *
	 * @param    boolean $network_wide       True if WPMU superadmin uses
	 *                                       "Network Deactivate" action, false if
	 *                                       WPMU is disabled or plugin is
	 *                                       deactivated on an individual blog.
	 */
	public static function deactivate($network_wide)
	{

		if (function_exists('is_multisite') && is_multisite())
		{

			if ($network_wide)
			{

				// Get all blog ids
				$blog_ids = self::get_blog_ids();

				foreach ($blog_ids as $blog_id)
				{

					switch_to_blog($blog_id);
					self::single_deactivate();

				}

				restore_current_blog();

			}
			else
			{
				self::single_deactivate();
			}

		}
		else
		{
			self::single_deactivate();
		}

	}

	/**
	 * Fired when a new site is activated with a WPMU environment.
	 *
	 * @since    1.0.0
	 *
	 * @param    int $blog_id ID of the new blog.
	 */
	public function activate_new_site($blog_id)
	{

		if (1 !== did_action('wpmu_new_blog'))
		{
			return;
		}

		switch_to_blog($blog_id);
		self::single_activate();
		restore_current_blog();

	}

	/**
	 * ajax function for vote
	 */
	function cbxpoll_ajax_vote()
	{

		//security check
		check_ajax_referer('cbxpolluservote', 'nonce');

		global $wpdb;

		$poll_result          = array();
		$poll_result['error'] = 0;

		$setting_api = get_option('cbxpoll_global_settings');
        //$user_id = get_current_user_id();
        $current_user = wp_get_current_user();
        $user_id = $current_user->ID;


        //$current_user = wp_get_current_user();

		$poll_id     = intval($_POST['poll_id']);


		$user_answer_t = $_POST['user_answer'];

		$user_answer_t = maybe_unserialize($user_answer_t); //why maybe
		parse_str($user_answer_t, $user_answer);


		$user_answer_final = array();
		foreach($user_answer as $answer){
			$user_answer_final[] = $answer;
		}

		$user_answer_final = maybe_serialize($user_answer_final);

		$chart_type  = esc_attr($_POST['chart_type']);
		$reference   = esc_attr($_POST['reference']);

		$poll_info  = get_post($poll_id);
		$poll_table = cbxpollHelper::cbx_poll_table_name();


		if ($user_id == 0)
		{
			$user_session   = $_COOKIE[CBX_POLL_COOKIE_NAME]; //this is string
			$user_ip        = self::get_ipaddress();
			$this_user_role = array('guest');

		}
		elseif ($user_id > 0)
		{
			$user_session = 'user-' . $user_id; //this is string
			$user_ip      = self::get_ipaddress();
			global $current_user;
			$this_user_role = $current_user->roles;
		}
		//}

		//poll informations from meta

		$poll_start_date                = get_post_meta($poll_id, '_cbxpoll_start_date', true); //poll start date
		$poll_end_date                  = get_post_meta($poll_id, '_cbxpoll_end_date', true); //poll end date
		$poll_user_roles                = get_post_meta($poll_id, '_cbxpoll_user_roles', true); //poll user roles
		//$poll_back_color                = get_post_meta($poll_id, '_cbxpoll_back_color', true); //poll background color
		$poll_content                   = get_post_meta($poll_id, '_cbxpoll_content', true); //poll content
		$poll_never_expire              = get_post_meta($poll_id, '_cbxpoll_never_expire', true); //poll never epire
		$poll_show_result_before_expire = get_post_meta($poll_id, '_cbxpoll_show_result_before_expire', true); //poll never epire
		$poll_show_result_all           = get_post_meta($poll_id, '_cbxpoll_show_result_all', true); //show_result_all
		$poll_result_chart_type         = get_post_meta($poll_id, '_cbxpoll_result_chart_type', true); //chart type
		$poll_is_voted                  = intval(get_post_meta($poll_id, '_cbxpoll_is_voted', true)); //at least a single vote

		//$global_result_chart_type   = isset($setting_api['result_chart_type'])? $setting_api['result_chart_type']: 'text';
		$poll_result_chart_type = get_post_meta($poll_id, '_cbxpoll_result_chart_type', true);
		$poll_result_chart_type = ($chart_type != '') ? $chart_type : $poll_result_chart_type; //honor shortcode or widget  as user input

		//fallback as text if addon no installed
		$poll_result_chart_type = self::chart_type_fallback($poll_result_chart_type); //make sure that if chart type is from pro addon then it's installed

		$poll_answers = get_post_meta($poll_id, '_cbxpoll_answer', true);

		$poll_answers = is_array($poll_answers) ? $poll_answers : array();
		$poll_colors  = get_post_meta($poll_id, '_cbxpoll_answer_color', true);

		$log_method = $setting_api['logmethod'];

		$log_metod = ($log_method != '') ? $log_method : 'both';

		$is_poll_expired = new DateTime($poll_end_date) < new DateTime(); //check if poll expired from it's end data
		$is_poll_expired = ($poll_never_expire == '1') ? false : $is_poll_expired; //override expired status based on the meta information

		$poll_allowed_user_group = empty($poll_user_roles) ? $setting_api['user_roles'] : $poll_user_roles;

		$allowed_user_group = array_intersect($poll_allowed_user_group, $this_user_role);

		if (new DateTime($poll_start_date) > new DateTime()){
			$poll_result['error'] = 1;
			$poll_result['text']  = __('Sorry, poll didn\'t start yet.', 'cbxpoll');

			echo json_encode($poll_result);
			die();
		}

		if ($is_poll_expired){

			$poll_result['error'] = 1;
			$poll_result['text']  = __('Sorry, you can not vote as poll already expired.', 'cbxpoll');

			echo json_encode($poll_result);
			die();

		}

		//check if the user has permission to vote
		if ((sizeof($allowed_user_group)) < 1){
			$poll_result['error'] = 1;
			$poll_result['text']  = __('Sorry, you are not allowed to vote.', 'cbxpoll');

			echo json_encode($poll_result);
			die();
		}

	

		$insertArray['poll_id']      = $poll_id;
		$insertArray['poll_title']   = $poll_info->post_title;
		$insertArray['user_name']    = ($user_id == 0) ? 'guest' : $current_user->user_login;
		$insertArray['is_logged_in'] = ($user_id == 0) ? 0 : 1;
		$insertArray['user_cookie']  = ($user_id != 0) ? 'user-' . $user_id : $_COOKIE[CBX_POLL_COOKIE_NAME];
		$insertArray['user_ip']      = self::get_ipaddress();
		$insertArray['user_id']      = $user_id;
		//$insertArray['user_answer']  = $user_answer;
		$insertArray['user_answer']  = $user_answer_final;
		$insertArray['published']    = 1; //need to make this col as published 1 or 0

		//$result_logging_method = $_POST['logging_method'];
		//$result_logging_method = isset($setting_api['logmethod']) ? $setting_api['logmethod'] : 'both';

		$count = 0;

		//for logged in user ip or cookie or ip-cookie should not be used, those option should be used for guest user

		if ($log_method == 'cookie')
		{

			$sql   = $wpdb->prepare("SELECT COUNT(ur.id) AS count FROM $poll_table ur WHERE  ur.poll_id=%d AND ur.user_id=%d AND ur.user_cookie = %s", $insertArray['poll_id'], $user_id, $user_session);
			$count = $wpdb->get_var($sql);

		}
		elseif ($log_method == 'ip')
		{

			$sql   = $wpdb->prepare("SELECT COUNT(ur.id) AS count FROM $poll_table ur WHERE  ur.poll_id=%d AND ur.user_id=%d AND ur.user_ip = %s", $insertArray['poll_id'], $user_id, $user_ip);
			$count = $wpdb->get_var($sql);

		}
		else if ($log_method == 'both')
		{

			$sql               = $wpdb->prepare("SELECT COUNT(ur.id) AS count FROM $poll_table ur WHERE  ur.poll_id=%d AND ur.user_id=%d AND ur.user_cookie = %s", $insertArray['poll_id'], $user_id, $user_session);
			$vote_count_cookie = $wpdb->get_var($sql);
			$sql               = $wpdb->prepare("SELECT COUNT(ur.id) AS count FROM $poll_table ur WHERE  ur.poll_id=%d AND ur.user_id=%d AND ur.user_ip = %s", $insertArray['poll_id'], $user_id, $user_ip);
			$vote_count_ip     = $wpdb->get_var($sql);

			if ($vote_count_cookie >= 1 || $vote_count_ip >= 1)
			{
				$count = 1;
			}
		}

		$poll_result['poll_id'] = $poll_id;

		$poll_result['chart_type'] = $poll_result_chart_type;

		//aready voted
		if ($count >= 1)
		{
			//already voted, just show the result

			$poll_result['error'] = 1;
			$poll_result['text']  = __('You already voted this poll !', 'cbxpoll');

			echo json_encode($poll_result);
			die();

		}
		else
		{
			//user didn't vote and good to go

			$vote_id = cbxpollHelper::update_poll($insertArray); //let the user vote

			if ($vote_id !== false)
			{
				//poll vote action
				//update the post as at least on vote is done to restrict for sorting order and edit answer labels

				add_post_meta($poll_id, '_cbxpoll_is_voted', 1); //added meta
				do_action('cbxpollonvote', $insertArray, $vote_id);
			}
			else
			{

				//at least we show some msg for such case.

				$poll_result['error'] = 1;
				$poll_result['text']  = __('Sorry, something wrong while voting, please refresh this page', 'cbxpoll');

				echo json_encode($poll_result);
				die();
			}
		}

		//$poll_result['user_answer'] = $user_answer;
		$poll_result['user_answer'] = $user_answer_final;
		$poll_result['reference']   = $reference;
		$poll_result['colors']      = wp_json_encode($poll_colors);
		$poll_result['answers']     = wp_json_encode($poll_answers);

		$total_results = cbxpollHelper::get_pollResult($insertArray['poll_id']);

		$total_votes = count($total_results);

		$poll_result['total']       = $total_votes;
		$poll_result['show_result'] = ''; //todo: need to check if user allowed to view result with all condition

		$poll_answers_weight = array();

		foreach ($total_results as $result){
			$user_ans                       = maybe_unserialize($result['user_answer']);
			if(is_array($user_ans)){
				foreach($user_ans as $u_ans){
					$old_val                        = isset($poll_answers_weight[$u_ans]) ? intval($poll_answers_weight[$u_ans]) : 0;
					$poll_answers_weight[$u_ans] = ($old_val + 1);
				}
			}
			else{
				//backword compatible
				$user_ans						= intval($user_ans);
				$old_val                        = isset($poll_answers_weight[$user_ans]) ? intval($poll_answers_weight[$user_ans]) : 0;
				$poll_answers_weight[$user_ans] = ($old_val + 1);

			}


		}

		$poll_result['answers_weight'] = $poll_answers_weight;

		//ready mix :)
		$poll_weighted_labels = array();
		foreach ($poll_answers as $index => $answer)
		{
			$poll_weighted_labels[$answer] = isset($poll_answers_weight[$index]) ? $poll_answers_weight[$index] : 0;
		}
		$poll_result['weighted_label'] = $poll_weighted_labels;

		//this will help to show vote result easily
		//update_post_meta($poll_id, '_cbxpoll_weighted_label', wp_json_encode($poll_weighted_labels)); //meta added
		update_post_meta($poll_id, '_cbxpoll_total_votes', $total_votes); //can help for showing most voted oll //meta added

		$poll_result['text'] = __('Thanks for voting!', 'cbxpoll');

		//we will only show result if permitted and for successful voting only

		//at least a successful vote happen
		//let's check if permission to see result >> as has vote capability to can see result
		//let's check if has permission to see before expire

		if ($poll_show_result_before_expire == '1')
		{
			$poll_result['show_result'] = '1';
			$poll_result['html']        = self:: show_single_poll_result($poll_id, $reference, $chart_type);

		}

		echo wp_json_encode($poll_result);
		die();

	}

	/**
	 * initialize cookie
	 */
	public static function init_cookie()
	{

        //$user_id = get_current_user_id();
        $current_user = wp_get_current_user();
        $user_id = $current_user->ID;


        if (!is_admin())
		{
			if (is_user_logged_in())
			{

				$cookie_value = 'user-' . $user_id;

			}
			else
			{

				$cookie_value = 'guest-' . rand(CBX_POLL_RAND_MIN, CBX_POLL_RAND_MAX);
			}

			if (!isset($_COOKIE[CBX_POLL_COOKIE_NAME]) && empty($_COOKIE[CBX_POLL_COOKIE_NAME]))
			{

				setcookie(CBX_POLL_COOKIE_NAME, $cookie_value, CBX_POLL_COOKIE_EXPIRATION_14DAYS, SITECOOKIEPATH, COOKIE_DOMAIN);

				//$_COOKIE var accepts immediately the value so it will be retrieved on page first load.
				$_COOKIE[CBX_POLL_COOKIE_NAME] = $cookie_value;

			}
			elseif (isset($_COOKIE[CBX_POLL_COOKIE_NAME]))
			{

				//var_dump($_COOKIE[CBX_POLL_COOKIE_NAME]);
				if (substr($_COOKIE[CBX_POLL_COOKIE_NAME], 0, 5) != 'guest')
				{
					setcookie(CBX_POLL_COOKIE_NAME, $cookie_value, CBX_POLL_COOKIE_EXPIRATION_14DAYS, SITECOOKIEPATH, COOKIE_DOMAIN);

					//$_COOKIE var accepts immediately the value so it will be retrieved on page first load.
					$_COOKIE[CBX_POLL_COOKIE_NAME] = $cookie_value;
				}
			}
		}

	}

	/**
	 * @return string|void
	 * return ip address
	 */
	public static function get_ipaddress()
	{

		if (empty($_SERVER["HTTP_X_FORWARDED_FOR"]))
		{

			$ip_address = $_SERVER["REMOTE_ADDR"];
		}
		else
		{

			$ip_address = $_SERVER["HTTP_X_FORWARDED_FOR"];
		}

		if (strpos($ip_address, ',') !== false)
		{

			$ip_address = explode(',', $ip_address);
			$ip_address = $ip_address[0];
		}

		return esc_attr($ip_address);
	}

	/**
	 * Get all blog ids of blogs in the current network that are:
	 * - not archived
	 * - not spam
	 * - not deleted
	 *
	 * @since    1.0.0
	 *
	 * @return   array|false    The blog ids, false if no matches.
	 */
	private static function get_blog_ids()
	{

		global $wpdb;
		// get an array of blog ids
		$sql = "SELECT blog_id FROM $wpdb->blogs
			    WHERE archived = '0' AND spam = '0'
			    AND deleted = '0'";

		return $wpdb->get_col($sql);

	}

	/**
	 * Fired for each blog when the plugin is activated.
	 *
	 * @since    1.0.0
	 */
	private static function single_activate()
	{

	}

	/**
	 * Fired for each blog when the plugin is deactivated.
	 *
	 * @since    1.0.0
	 */
	private static function single_deactivate()
	{

	}

	/**
	 * Register and enqueue public-facing style sheet.
	 *
	 * @since    1.0.0
	 */
	public function wp_enqueue_styles()
	{

		wp_enqueue_style($this->plugin_slug . '-plugin-styles', plugins_url('assets/css/cbxpoll_public.css', __FILE__), array(), self::VERSION);
		do_action('cbxpoll_custom_style');
	}

	/**
	 * Register and enqueues public-facing JavaScript files.
	 *
	 * @since    1.0.0
	 */
	public function wp_enqueue_scripts()
	{

		//wp_enqueue_style('wp-color-picker');
		//wp_enqueue_style('thickbox');
		//wp_enqueue_script('wp-color-picker');
		wp_enqueue_script('jquery');
		//wp_enqueue_script('jquery-ui-core');
		//wp_enqueue_script( 'media-upload' );
		//wp_enqueue_script( 'thickbox' );

		//wp_enqueue_script($this->plugin_slug . '-poll-label-script-public', plugins_url('assets/js/poll-label.js', __FILE__), array('jquery'), cbxpoll::VERSION);
		//wp_localize_script($this->plugin_slug . '-poll-label-script-public', 'cbxpolllabel', array('nolabelfound' => __('No Label Found', 'cbxpoll')));

		//wp_enqueue_script($this->plugin_slug . 'cbxpoll-chosen', plugins_url('../admin/assets/js/chosen.jquery.min.js', __FILE__), array('jquery'), cbxpoll::VERSION);
		//wp_enqueue_script( $this->plugin_slug . '-ui-script-public', plugins_url( '../admin/assets/js/jquery-ui.js', __FILE__ ), array( 'jquery' ), cbxpoll::VERSION );
		//wp_enqueue_script($this->plugin_slug . '-chart-script', plugins_url('assets/js/Chart.js', __FILE__), array('jquery'), self::VERSION);

		wp_register_script($this->plugin_slug . '-publicjs', plugins_url('assets/js/cbxpoll-public.js', __FILE__), array('jquery'), self::VERSION);
		wp_localize_script($this->plugin_slug . '-publicjs', 'cbxpollpublic', array(
			                                                   'ajaxurl'        => admin_url('admin-ajax.php'),
			                                                   'noanswer_error' => __('Please select at least one answer', 'cbxpoll')

		                                                   )
		);

		wp_enqueue_script($this->plugin_slug . '-publicjs');

		do_action('cbxpoll_custom_script');
	}

	/**
	 * Result chart type fallback
	 *
	 */
	public static function chart_type_fallback($result_graph)
	{


		$poll_display_methods = cbxpollHelper::cbxpoll_display_options();
		$chart_info = (isset($poll_display_methods[$result_graph]))? $poll_display_methods[$result_graph]: '';

		if($chart_info != '' && is_callable($chart_info['method'])){
			return $result_graph;
		}

		return 'text';
	}

}// end of class
