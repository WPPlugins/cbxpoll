<?php
/**
 *
 * @package   cbxpoll_Admin
 * @author    codeboxr <info@codeboxr.com>
 * @license   GPL-2.0+
 * @link      http://codeboxr.com/product/cbx-poll-for-wordpress/
 * @copyright 2016 codeboxr
 */

if (!function_exists('get_editable_roles')) {
    require_once(ABSPATH . '/wp-admin/includes/user.php');
}

/**
 * for initialize global settings fileds
 */
if (!function_exists('cbxpoll_setting_init')):

    function cbxpoll_setting_init()
    {

        $sections = array(
            array(
                'id' => 'cbxpoll_global_settings',
                'title' => __('CBX Poll Settings', 'cbxpoll')
            ));

        $sections = apply_filters('cbxpoll_setting_sections_init', $sections);

        $fields = array('cbxpoll_global_settings' => array());
        $fields = apply_filters('cbxpoll_setting_fields_init', $fields);

        $settings_api = new CBXPoll_Settings();
        $settings_api->set_sections($sections);
        $settings_api->set_fields($fields);
        //initialize them
        $settings_api->admin_init();
    }
endif;

// end of function cbxpoll_setting_init
/**
 * Class cbxpoll_Admin
 * main admin class
 */
class CBXPoll_Admin
{


    protected static $instance = null;

    protected $plugin_screen_hook_suffix = 'cbxpoll';

    /**
     * Initialize the plugin by loading admin scripts & styles and adding a
     * settings page and menu.
     *
     * @since     1.0.0
     */
    private function __construct()
    {

        // loding global settings api
        require_once(plugin_dir_path(__FILE__) . "class.settings-api.php");
        //get public class instance
        $plugin = cbxpoll::get_instance();
        $this->plugin_slug = $plugin->get_plugin_slug();
        // Load admin style sheet and JavaScript.
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_styles'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));

        $plugin_basename = plugin_basename(plugin_dir_path(__DIR__) . $this->plugin_slug . '.php');

        // add settings link
        add_filter('plugin_action_links_' . $plugin_basename, array($this, 'add_action_links'));
        // calls automatically when initialize
        // add_action( 'admin_init', cbxpoll_setting_init);
        add_action('admin_notices', array($this, 'add_notice_poll'));
        add_action('admin_init', array($this, 'init_poll'));

        add_action('admin_init', array($this, 'on_cbxpoll_delete'));

        // add global settings
        add_action('admin_menu', array($this, 'add_plugin_admin_menu'));
        // add custom status column in table
        add_filter('manage_edit-cbxpoll_columns', array($this, 'add_new_poll_columns'));
        add_action('manage_cbxpoll_posts_custom_column', array($this, 'manage_poll_columns'));
        add_filter('manage_edit-cbxpoll_sortable_columns', array($this, 'cbcbxpoll_columnsort'));

        // add custom script for meta box
        //add_action('admin_head',array( $this,'add_custom_scripts_metabox'));

        // add meta box and hook save meta box
        add_action('add_meta_boxes', array($this, 'cbxpoll_metabox_display_callback'));
        add_action('save_post', array($this, 'cbxpoll_metabox_save'));

        //
        //add_action('wp_insert_post', array( $this,'cb_poll_buddypresspost_check'));
        //add_action( 'publish_cbxpoll', array( $this,'cb_poll_buddypresspost_check'), 10, 2 );
        // add_action( 'transition_post_status',  array( $this,'cb_poll_buddypresspost_check'), 10, 3 );
        //action links
        // add_filter( 'plugin_action_links_' . plugin_basename(__FILE__), array( $this,'cbxpoll_plugin_action_links') );
    }

    /**
     * Adds hook for post delete
     */
    function on_cbxpoll_delete()
    {
        add_action('delete_post', array($this, 'cbxpoll_delete_vote'), 10);
    }

    /**
     * Delete vote on poll type post
     *
     * @param type $postid
     */
    function cbxpoll_delete_vote($poll_id)
    {
        global $wpdb;

        $post_type = get_post_type($poll_id);
        if ($post_type !== false && $post_type == 'cbxpoll') {
            $poll_table = cbxpollHelper::cbx_poll_table_name();
            $wpdb->query($wpdb->prepare("DELETE FROM $poll_table WHERE poll_id = %d", $poll_id));
        }


    }

    /**
     * Init the Poll Engine
     *
     * @return array
     * initialize with init
     */
    function init_poll()
    {
        // for language
        cbxpoll_setting_init();

        global $wp_roles;
        // now this is for meta box
        $roles = $wp_roles->get_names();


        $setting_api = get_option('cbxpoll_global_settings');

        $default_user_roles                 = isset($setting_api['user_roles']) ? $setting_api['user_roles'] : array('administrator', 'editor', 'author', 'contributor', 'subscriber', 'guest');
        //$default_back_color                = isset($setting_api['back_color']) ? $setting_api['back_color'] : '#e4e4e4';
        $default_never_expire               = isset($setting_api['never_expire']) ? $setting_api['never_expire'] : 0;
        $default_content                    = isset($setting_api['content']) ? $setting_api['content'] : 0;
        $default_result_chart               = isset($setting_api['result_chart_type']) ? $setting_api['result_chart_type'] : 'text';
        $default_show_result_all            = isset($setting_api['show_result_all']) ? $setting_api['show_result_all'] : 0;
        $default_show_result_before_expire  = isset($setting_api['show_result_before_expire']) ? $setting_api['show_result_before_expire'] : 0;


        // Field Array
        $prefix = '_cbxpoll_';

        $roles = array_merge($roles, array('guest' => 'Guest'));


        //$poll_display_options = array('text' => __('Text', 'cbxpoll'));
        $poll_display_methods = cbxpollHelper::cbxpoll_display_options();
        $poll_display_methods = cbxpollHelper::cbxpoll_display_options_linear($poll_display_methods);


        $start_date = new DateTime();
        $timestamp = time() - 86400;
        $end_date = strtotime("+7 day", $timestamp);

        $post_meta_fields = array(

            '_cbxpoll_start_date' => array(
                                        'label' => __('Start Date', 'cbxpoll'),
                                        'desc' => __('Poll Start Date. [<strong> Note:</strong> Field required. Default is today]', 'cbxpoll'),
                                        'id' => '_cbxpoll_start_date',
                                        'type' => 'date',
                                        'default' => $start_date->format('Y-m-d H:i:s')
                                    ),
            '_cbxpoll_end_date' => array(
                                        'label' => __('End Date', 'cbxpoll'),
                                        'desc' => __('Poll End Date.  [<strong> Note:</strong> Field required. Default is next seven days. ]', 'cbxpoll'),
                                        'id' => '_cbxpoll_end_date',
                                        'type' => 'date',
                                        'default' => date('Y-m-d H:i:s', $end_date)
                                    ),
            '_cbxpoll_user_roles' => array(
                                        'label' => __('Who Can Vote', 'cbxpoll'),
                                        'desc' => __('Which user role will have vote capability', 'cbxpoll'),
                                        'id' => '_cbxpoll_user_roles',
                                        'type' => 'multiselect',
                                        'options' => $roles,
                                        'default' => $default_user_roles
                                    ),
            '_cbxpoll_content' => array(
                'label' => __('Show Poll Description', 'cbxpoll'),
                'desc' => 'Select if you want to show content.',
                'id' => '_cbxpoll_content',
                'type' => 'radio',
                'default' => $default_content,
                'options' => array(
                    '1' => __('Yes', 'cbxpoll'),
                    '0' => __('No', 'cbxpoll')
                )

            ),
            '_cbxpoll_never_expire' => array(
                                        'label' => __('Never Expire', 'cbxpoll'),
                                        'desc' => 'Select if you want your poll to never expire.',
                                        'id' => '_cbxpoll_never_expire',
                                        'type' => 'radio',
                                        'default' => $default_never_expire,
                                        'options' => array(
                                            '1' => __('Yes', 'cbxpoll'),
                                            '0' => __('No', 'cbxpoll')
                                        )
                                    ),

            '_cbxpoll_show_result_before_expire' => array(
                                                        'label' => __('Show result before expires', 'cbxpoll'),
                                                        'desc' => __('Select if you want poll to show result before expires. After expires the result will be shown always. Please check it if poll never expires.', 'cbxpoll'),
                                                        'id' => '_cbxpoll_show_result_before_expire',
                                                        'type' => 'radio',
                                                        'default' => $default_show_result_before_expire,
                                                        'options' => array(
                                                            '1' => __('Yes', 'cbxpoll'),
                                                            '0' => __('No', 'cbxpoll')
                                                        )
                                                    ),
            '_cbxpoll_show_result_all' => array(
                                                    'label' => __('Show result to all', 'cbxpoll'),
                                                    'desc' => __('Check this if you want to show result to them who can not vote.', 'cbxpoll'),
                                                    'id' => '_cbxpoll_show_result_all',
                                                    'type' => 'radio',
                                                    'default' => $default_show_result_all,
                                                    'options' => array(
                                                        '1' => __('Yes', 'cbxpoll'),
                                                        '0' => __('No', 'cbxpoll')
                                                    )
                                                ),
            '_cbxpoll_result_chart_type' => array(
                                                'label' => __('Result Chart Style', 'cbxpoll'),
                                                'desc' => __('Select how you want to show poll result.', 'cbxpoll'),
                                                'id' => '_cbxpoll_result_chart_type',
                                                'type' => 'select',
                                                'options' => $poll_display_methods,  //new poll display method can be added via plugin
                                                'default' => $default_result_chart
                                            ),
            '_cbxpoll_multivote' => array(
                                                'label' => __('Enable Multi Choice', 'cbxpoll'),
                                                'desc' => __('Can user vote multiple option', 'cbxpoll'),
                                                'id' => '_cbxpoll_multivote',
                                                'type' => 'radio',
                                                'default' => '0',
                                                'options' => array(
                                                    '1' => __('Yes', 'cbxpoll'),
                                                    '0' => __('No', 'cbxpoll')
                                                )
                                            ),
        );

        return apply_filters('cbxpoll_fields', $post_meta_fields);
    }

    /**
     * Hook custom meta box
     */
    function cbxpoll_metabox_display_callback()
    {

        //add meta box in left side to show poll setting
        add_meta_box(
            'pollcustom_meta_box',                              // $id
            __('CBX Poll Options', 'cbxpoll'),  // $title
            array($this, 'cbxpoll_metabox_display'),           // $callback
            'cbxpoll',                                      // $page
            'normal',                                           // $context
            'high');                                            // $priority

        //add meta box in right col to show the result
        add_meta_box(
            'pollresult_meta_box',                              // $id
            __('Poll Result', 'cbxpoll'),  // $title
            array($this, 'cbxpoll_metaboxresult_display'),           // $callback
            'cbxpoll',                                      // $page
            'side',                                           // $context
            'low');
    }

    function cbxpoll_metaboxresult_display()
    {

        global $post;
        $poll_postid = $post->ID;

        $poll_output = cbxpoll:: show_single_poll_result($poll_postid, 'shortcode', 'text');

        echo $poll_output;
    }

    /**
     * Show cbxpoll meta box in poll edit screen
     */
    function cbxpoll_metabox_display()
    {

        global $post;
        $post_meta_fields = self::init_poll();


     /*   echo '<pre>';
        print_r($post_meta_fields);
        echo '</pre>';*/


        $prefix = '_cbxpoll_';
        $poll_postid = 0;
        $poll_number = 0;

        $is_voted = 0;
        $poll_answers = array();
        $poll_colors = array();


        if (isset($post->ID) && $post->ID > 0):
            $poll_postid = $post->ID;

            $is_voted = intval(get_post_meta($poll_postid, $prefix . 'is_voted', true));
            $poll_answers = get_post_meta($poll_postid, $prefix . 'answer', true);
            $poll_colors = get_post_meta($poll_postid, $prefix . 'answer_color', true);


            wp_nonce_field('cbxpoll_meta_box', 'cbxpoll_meta_box_nonce');

            echo '<div class="cbxpoll_answer_wrap">';
            echo '<h4>' . __('Add Poll Answers', 'cbxpoll') . '</h4>';
            echo __('<p>[<strong>Note : </strong>  <span>Please select different color for each field. If any poll has  more than one vote answer title, color and sorting will be locked.]</span></p>', 'cbxpoll');


            echo '<ul id="cbx_poll_answers_items" class="cbx_poll_answers_items cbx_poll_answers_items_' . $post->ID . '">';


            if (is_array($poll_answers) && count($poll_answers) > 0) {

                foreach ((array)$poll_answers as $index => $poll_answer) {

                    if (isset($poll_answer)) {

                        echo self::cbxpoll_answer_fields($poll_number, $poll_answer, $poll_colors[$index], $is_voted, $prefix);
                        $poll_number++;
                    }
                }
            } else {

                $default_answers = 3;
                $default_answers_titles = array(
                    __('Yes', 'cbxpoll'), __('No', 'cbxpoll'), __('No comments', 'cbxpoll')
                );

                $default_answers_colors = array(
                    '#2f7022', '#dd6363', '#e4e4e4'
                );

                for ($i = 0; $i < $default_answers; $i++) {
                    echo '<li class="cbx_poll_items" id="cbx-poll-answer-' . $i . '">
                        <input type="text"  style="width:330px;" name="_cbxpoll_answer[' . $i . ']" value="' . $default_answers_titles[$i] . '"   id="cbxpoll_answer-' . $i . '" class="cbxpoll_answer"/>
                        <input type="text"  id="cbxpoll_answer_color-' . $i . '" class="cbxpoll_answer_color" name="_cbxpoll_answer_color[' . $i . ']" size="100"  value="' . $default_answers_colors[$i] . '" />
                        <span class="cbx_pollremove dashicons dashicons-trash" title="' . __('Remove', 'cbxpoll') . '"></span> <i class="cbpollmoveicon">' . __('move', 'cbxpoll') . '</i></li>';
                }


                /*
                echo '<li class="cbx_poll_items" id="cbx-poll-answer-2">
                    <input type="text"  style="width:330px;" name="_cbxpoll_answer[' . 2 . ']" value="' . __('No', 'cbxpoll') . '"   id="' . 2 . '" class="cb_title"/>
                    <input type="text"  id="color-data-2' . $poll_postid . '" class="cbxpoll_answer_color" name="_cbxpoll_answer_color[' . 2 . ']" size="100"  value="' . '#dd6363' . '" />
                     <span class="cbx_pollremove dashicons dashicons-trash" title="' . __('Remove', 'cbxpoll') . '"></span> <i class="cbpollmoveicon">' . __('move', 'cbxpoll') . '</i></li>';

                echo '<li class="cbx_poll_items " id="cbx-poll-answer-3">
                    <input type="text"  style="width:330px;" name="_cbxpoll_answer[' . 3 . ']" value="' . __('No Comment', 'cbxpoll') . '"   id="' . 3 . '" class="cb_title"/>
                    <input type="text"  id="color-data-3' . $poll_postid . '" class="cbxpoll_answer_color" name="_cbxpoll_answer_color[' . 3 . ']" size="100"  value="' . '#e4e4e4' . '" />
                    <span class="cbx_pollremove dashicons dashicons-trash" title="' . __('Remove', 'cbxpoll') . '"></span> <i class="cbpollmoveicon">' . __('move', 'cbxpoll') . '</i></li>';
                */

                $poll_number = $default_answers;
            }
            echo '</ul>';


            //var_dump($is_voted);
            ?>
            <!--span id="cb-polls-here"></span-->
            <?php if (!$is_voted): ?>
            <a id="add-cbx-poll-answer"
               class="add-cbx-poll-answer button button-primary add-cbx-poll-answer-<?php echo $poll_postid; ?>"><?php echo __('Add More Answer', 'cbxpoll'); ?></a>
        <?php endif; ?>
            <br>


            <?php
            echo '</div>';


            echo '<table class="form-table">';

            foreach ($post_meta_fields as $field) {

                $meta = get_post_meta($poll_postid, $field['id'], true);
                //var_dump($field['default']);
                //var_dump($meta);

                if ($meta == '' && isset($field['default'])) {

                    $meta = $field['default'];
                }

                $label = isset($field['label']) ? $field['label'] : '';

                echo '<tr>';
                echo '<th><label for="' . $field['id'] . '">' . $label . '</label></th>';
                echo '<td>';


                switch ($field['type']) {

                    case 'date':


                        echo '<input type="text" class="cbxpollmetadatepicker" name="' . $field['id'] . '" id="' . $field['id'] . '-date-' . $poll_postid . '" value="' . $meta . '" size="30" />
			            <span class="description">' . $field['desc'] . '</span>';
                        break;

                    case 'colorpicker':


                        echo '<input type="text" class="cbxpoll-colorpicker" name="' . $field['id'] . '" id="' . $field['id'] . '-date-' . $poll_postid . '" value="' . $meta . '" size="30" />
			             <span class="description">' . $field['desc'] . '</span>';
                        break;

                    case 'multiselect':

                        //var_dump($field['options']);

                        //var_dump($meta);


                        echo '<select name="' . $field['id'] . '[]" id="' . $field['id'] . '-chosen-' . $poll_postid . '" class="chosen-select" multiple="multiple">';
                        if (isset($field['optgroup']) && intval($field['optgroup'])) {

                            foreach ($field['options'] as $optlabel => $data) {
                                echo '<optgroup label="' . $optlabel . '">';
                                foreach ($data as $key => $val)
                                    echo '<option value="' . $key . '"', is_array($meta) && in_array($key, $meta) ? ' selected="selected"' : '', ' >' . $val . '</option>';
                                echo '<optgroup>';
                            }

                        } else {
                            foreach ($field['options'] as $key => $val)
                                echo '<option value="' . $key . '"', is_array($meta) && in_array($key, $meta) ? ' selected="selected"' : '', ' >' . $val . '</option>';
                        }


                        echo '</select><span class="description">' . $field['desc'] . '</span>';
                        break;

                    case 'select':
                        echo '<select name="' . $field['id'] . '" id="' . $field['id'] . '-select-' . $poll_postid . '" class="cb-select select-' . $poll_postid . '">';

                        if (isset($field['optgroup']) && intval($field['optgroup'])) {

                            foreach ($field['options'] as $optlabel => $data) {
                                echo '<optgroup label="' . $optlabel . '">';
                                foreach ($data as $index => $option) {
                                    echo '<option ' . (($meta == $index) ? ' selected="selected"' : '') . ' value="' . $index . '">' . $option . '</option>';
                                }

                            }
                        } else {
                            foreach ($field['options'] as $index => $option) {
                                echo '<option ' . (($meta == $index) ? ' selected="selected"' : '') . ' value="' . $index . '">' . $option . '</option>';
                            }
                        }


                        echo '</select><br/><span class="description">' . $field['desc'] . '</span>';
                        break;
                    case 'radio':

                        echo '<fieldset>
								<legend class="screen-reader-text"><span>input type="radio"</span></legend>';
                        foreach ($field['options'] as $key => $value) {
                            echo '<label title="g:i a" for="' . $field['id'] . '-radio-' . $poll_postid . '-' . $key . '">
										<input id="' . $field['id'] . '-radio-' . $poll_postid . '-' . $key . '" type="radio" name="' . $field['id'] . '" value="' . $key . '" ' . (($meta == $key) ? '  checked="checked" ' : '') . '  />
										<span>' . $value . '</span>
									</label><br>';


                        }
                        echo '</fieldset>';
                        echo '<br/><span class="description">' . $field['desc'] . '</span>';
                        break;
                    // checkbox
                    case 'checkbox':
                        echo '<input type="checkbox" name="' . $field['id'] . '" id="' . $field['id'] . '-checkbox-' . $poll_postid . '" class="cb-checkbox checkbox-' . $poll_postid . '" ', $meta ? ' checked="checked"' : '', '/>
                    <span for="' . $field['id'] . '">' . $field['desc'] . '</span>';
                        break;
                    // checkbox_group
                    case 'checkbox_group':
                        if ($meta == '') {
                            $meta = array();
                            foreach ($field['options'] as $option) {
                                array_push($meta, $option['value']);
                            }
                        }

                        foreach ($field['options'] as $option) {
                            echo '<input type="checkbox" value="' . $option['value'] . '" name="' . $field['id'] . '[]" id="' . $option['value'] . '-mult-chk-' . $poll_postid . '-field-' . $field['id'] . '" class="cb-multi-check mult-check-' . $poll_postid . '"', $meta && in_array($option['value'], $meta) ? ' checked="checked"' : '', ' />
                        <label for="' . $option['value'] . '">' . $option['label'] . '</label><br/>';
                        }

                        echo '<span class="description">' . $field['desc'] . '</span>';
                        break;

                }
                echo '</td>';
                echo '</tr>';
            }
            echo '</table>';

        else:
            echo __('Please save the post once enter poll answers.', 'cbxpoll');
        endif;

        ?>
        <script type="text/javascript">

            jQuery(document).ready(function ($) {

                var count = <?php echo $poll_number - 1; ?>;
                var post_id = <?php echo $poll_postid; ?>;

                console.log('poll count' + count);

                var polledit = {
                    remove_label: '<?php echo __('Remove', 'cbxpoll'); ?>',
                    move_label: '<?php echo __('move', 'cbxpoll'); ?>',
                    answer_label: '<?php echo __('Answer', 'cbxpoll'); ?>'
                };

                // add more answer
                $("#add-cbx-poll-answer").click(function (event) {
                    event.preventDefault();

                    //console.log((function(m,s,c){return (c ? arguments.callee(m,s,c-1) : '#') + s[m.floor(m.random() * s.length)]})(Math,'0123456789ABCDEF',5));

                    count++;
                    var color_array = [];
                    var randomcolor = (function (m, s, c) {
                        return (c ? arguments.callee(m, s, c - 1) : '#') + s[m.floor(m.random() * s.length)]
                    })(Math, '0123456789ABCDEF', 5);

                    $('#cbx_poll_answers_items').append('' +
                        '<li class="cbx_poll_items" id="cbx-poll-answer-' + count + '">' +
                        '<input type="text" style="width:330px;" name="_cbxpoll_answer[' + count + ']" value=" ' + polledit.answer_label + ' ' + (count + 1) + '" id="cbxpoll_answer-' + count + '" class="cbxpoll_answer" />  ' +
                        '<input type="text" id="cbxpoll_answer_color-' + count + '" class="cbxpoll_answer_color" name="_cbxpoll_answer_color[' + count + ']" size="100"  value="' + randomcolor + '" />' +
                        '<span class="cbx_pollremove dashicons dashicons-trash" title="' + polledit.remove_label + '"></span> <i class="cbpollmoveicon">' + polledit.move_label + '</i></li>');


                    var colorOptions = {
                        change: function (event, ui) {
                        },
                        // a callback to fire when the input is emptied or an invalid color
                        clear: function () {
                        },
                        // hide the color picker controls on load
                        hide: true,
                        palettes: true
                    };

                    //console.log(colorOptions);


                    $('#cbx_poll_answers_items').find('.cbxpoll_answer_color').last().wpColorPicker(colorOptions);


                });

                // remove answer field
                $(".cbx_pollremove").on('click', function () {
                    $(this).parent().remove();
                });

                $('.chosen-select').chosen();


            });
        </script>
        <?php

    }

    /**
     * Show poll answer fields
     *
     * @param $cb_poll_counter
     * @param null $poll_title_args
     * @param null $poll_color_args
     * @return string
     * return previously saved color and answer metaboxs
     */
    function cbxpoll_answer_fields($poll_counter, $poll_title = '', $poll_color = '', $is_voted = 0, $prefix = '_cbxpoll_')
    {

        global $post;
        $poll_postid = $post->ID;

        $input_type = 'text';
        $color_class = 'cbxpoll_answer_color';

        if ($is_voted) {
            $input_type = 'hidden';
            $color_class = 'cbxpoll_answer_color_voted';
        }


        $cb_poll_output =

            '<li class="cbx_poll_items" id="cbx-poll-answer-' . $poll_counter . '">
                 
                <input type="' . $input_type . '"  style="width:330px;" name="' . $prefix . 'answer[' . $poll_counter . ']" value="' . $poll_title . '"   id="cbxpoll_answer-' . $poll_counter . '" class="cbxpoll_answer"/>
                <input type="' . $input_type . '"  id="cbxpoll_answer_color-' . $poll_counter . '" class="' . $color_class . '" name="' . $prefix . 'answer_color[' . $poll_counter . ']" size="100"  value="' . $poll_color . '" />';

        if ($is_voted) {
            $cb_poll_output .= '<h4 style="margin:0px; color:#fff; padding:2px 20px; background-color: ' . $poll_color . '">' . $poll_title . '</h4>';
        }

        if (!$is_voted) {
            $cb_poll_output .= '<span class="cbx_pollremove dashicons dashicons-trash" title="' . __('Remove', 'cbxpoll') . '"></span> <i class="cbpollmoveicon">' . __('move', 'cbxpoll') . '</i>';
        }


        $cb_poll_output .= '</li>';


        return $cb_poll_output;

    }
    /**
     *
     * this is for adding js in meta box field like datepicker
     */
    /*
    function add_custom_scripts_metabox(){

        global  $post;
        $poll_postid = $post->ID;
        global  $post_meta_fields ;
        $post_meta_fields   = self::init_poll();

        if(is_object($post))
            $cb_post_type       = get_post_type($poll_postid);
        else
            $cb_post_type  = '';

        if($cb_post_type == 'cbxpoll'){

            $css_icon = '<style>#icon-edit { background:transparent url('.plugins_url( 'assets/css/poll_hover.png', __FILE__ ).') no-repeat; }     </style>';
            echo $css_icon;

        }

    }
    */


    /**
     * Save cbxpoll metabox
     *
     * @param $post_id
     * @return bool
     */
    function cbxpoll_metabox_save($post_id)
    {

        global $post;
        $post = get_post($post_id);
        $status = $post->post_status;

        $prefix = '_cbxpoll_';

        // Check if our nonce is set.
        if (!isset($_POST['cbxpoll_meta_box_nonce'])) {
            return;
        }

        // Verify that the nonce is valid.
        if (!wp_verify_nonce($_POST['cbxpoll_meta_box_nonce'], 'cbxpoll_meta_box')) {
            return;
        }

        // If this is an autosave, our form has not been submitted, so we don't want to do anything.
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        // Check the user's permissions.
        if (isset($_POST['post_type']) && 'cbxpoll' == $_POST['post_type']) {

            if (!current_user_can('edit_post', $post_id)) {
                return;
            }
        }

        /*if ($status === 'new' || $status === 'auto-draft') {

            $poll_data  = array();
            $title_data = array();

        } else {

            $poll_data  = get_post_meta($post_id, $prefix . 'answer_color', true);
            $title_data = get_post_meta($post_id, $prefix . 'answer', true);
        }*/
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return $post_id;


        //handle answer colors
        if (isset($_POST[$prefix . 'answer_color'])) {

            //

            $colors = $_POST[$prefix . 'answer_color'];
            foreach ($colors as $index => $color) {
                $colors[$index] = self::sanitize_hex_color($color);
            }


            $unique_color = array_unique($colors);

            if ((count($unique_color)) == (count($colors))) {
                update_post_meta($post_id, $prefix . 'answer_color', $colors);
            } else {
                $error = '<div class="error"><p>' . __('Error::Answer Color repeat error', 'cbxpoll') . '</p></div>';
                update_option('cbxpoll_error_msg', $error);

                return false;
            }
        } else {
            delete_post_meta($post_id, $prefix . 'answer_color');
        }

        //handle answer titles
        if (isset($_POST[$prefix . 'answer'])) {
            $titles = $_POST[$prefix . 'answer'];

            foreach ($titles as $index => $title) {
                $titles[$index] = sanitize_text_field($title);
            }

            update_post_meta($post_id, $prefix . 'answer', $titles);
        } else {
            delete_post_meta($post_id, $prefix . 'answer');
        }

        self::cbxpoll_metabox_extra_save($post_id);
    }

    /**
     * Save cbxpoll meta fields except poll color and titles
     *
     * @param $post_id
     * @return bool|void
     */
    function cbxpoll_metabox_extra_save($post_id)
    {

        global $post_meta_fields;
        $post_meta_fields = self::init_poll();

        $prefix = '_cbxpoll_';


        $cb_date_array = array();
        foreach ($post_meta_fields as $field) {

            $old = get_post_meta($post_id, $field['id'], true);
            $new = $_POST[$field['id']];
            /*
            if ($field['id'] == $prefix . 'logmethod' && !is_array($new)) {

                $cbpollerror = '<div class="error"><p>' . __('Error::Check at least one loging check method', 'cbxpoll') . '</p></div>';
                update_option('poll_error_msg', $cbpollerror);

                return false; //might stop processing here
            } else
                */
            if (($prefix . 'start_date' == $field['id'] && $new == '') || ($prefix . 'end_date' == $field['id'] && $new == '')) {

                $cbpollerror = '<div class="error"><p>' . __('Error:: Start or End date any one empty', 'cbxpoll') . '</p></div>';
                update_option('cbxpoll_error_msg', $cbpollerror);

                return false; //might stop processing here
            } else {

                //todo: sanitization of fields based on input type

                //if ($new && $new != $old) {
                update_post_meta($post_id, $field['id'], $new);
                /*} elseif ('' == $new && $old) {
                    delete_post_meta($post_id, $field['id'], $old);
                }*/
            }

        }

    }

    /**
     * trying admin notice
     * save error log while saving poll meta
     */

    public function add_notice_poll()
    {

        $error = get_option('cbxpoll_error_msg');
        if ($error != '') echo $error;
        update_option('cbxpoll_error_msg', '');

    }

    /**
     * function cbcbxpoll_columnsort
     * make poll table columns sortable
     */
    function cbcbxpoll_columnsort($columns)
    {

        $columns['startdate'] = 'startdate';
        $columns['enddate'] = 'enddate';
        $columns['pollstatus'] = 'pollstatus';
        $columns['pollvotes'] = 'pollvotes';

        return $columns;
    }

    /**
     * @param $column_name
     * show data to poll table custom column
     */
    public function manage_poll_columns($column_name)
    {

        global $wpdb, $post;
        $post_id = $post->ID;

        $end_date = get_post_meta($post_id, '_cbxpoll_end_date', true);
        $start_date = get_post_meta($post_id, '_cbxpoll_start_date', true);
        $never_expire = get_post_meta($post_id, '_cbxpoll_never_expire', true);
        $total_votes = intval(get_post_meta($post_id, '_cbxpoll_total_votes', true));

        switch ($column_name) {

            case 'pollstatus':
                // Get number of images in gallery
                if ($never_expire == '1') {
                    if (new DateTime($start_date) > new DateTime()) {
                        echo '<span class="dashicons dashicons-calendar"></span> ' . __('Yet to Start', 'cbxpoll'); //
                    } else {
                        echo '<span class="dashicons dashicons-yes"></span> ' . __('Active', 'cbxpoll');
                    }

                } else {
                    if (new DateTime($start_date) > new DateTime()) {
                        echo '<span class="dashicons dashicons-calendar"></span> ' . __('Yet to Start', 'cbxpoll'); //
                    } else if (new DateTime($start_date) <= new DateTime() && new DateTime($end_date) > new DateTime()) {
                        echo '<span class="dashicons dashicons-yes"></span> ' . __('Active', 'cbxpoll');
                    } else if (new DateTime($end_date) <= new DateTime()) {
                        echo '<span class="dashicons dashicons-lock"></span> ' . __('Expired', 'cbxpoll');
                    }
                }
                break;
            case 'startdate':
                echo $start_date;
                break;
            case 'enddate':
                echo $end_date;
                break;
            case 'pollvotes':
                //$end_date = get_post_meta($post_id, '_cbxpoll_end_date', true);
                echo apply_filters('cbxpoll_admin_listing_votes', $total_votes, $post_id);
                break;
            default:
                break;
        } // end swi

    }

    /**
     * @param $cbxpoll_columns
     * @return mixed
     * poll table columns
     */
    public function add_new_poll_columns($cbxpoll_columns)
    {

        //$cbxpoll_columns['cb']         = '<input type="checkbox" />';
        $cbxpoll_columns['title'] = __('Poll Title', 'cbxpoll');
        $cbxpoll_columns['pollstatus'] = __('Status', 'cbxpoll');
        //$cbxpoll_columns['author']     = __('Author', 'cbxpoll');
        $cbxpoll_columns['startdate'] = __('Start Date', 'cbxpoll');
        $cbxpoll_columns['enddate'] = __('End Date', 'cbxpoll');
        $cbxpoll_columns['date'] = __('Created', 'cbxpoll');
        $cbxpoll_columns['pollvotes'] = __('Votes', 'cbxpoll');

        return $cbxpoll_columns;
    }

    /**
     * @return object|cbxpoll_Admin
     */
    public static function get_instance()
    {
        // If the single instance hasn't been set, set it now.
        if (null == self::$instance) {
            self::$instance = new self;
        }

        return self::$instance;
    }

    /**
     * Register and enqueue admin-specific style sheet.
     *
     *
     * @since     1.0.0
     *
     * @return    null    Return early if no settings page is registered.
     */
    public function enqueue_admin_styles($hook)
    {
        global $post_type;

        if ( (in_array($hook, array('edit', 'post.php', 'post-new.php')) && 'cbxpoll' == $post_type) || ($hook == 'cbxpoll_page_class-cbxpoll-admin')) {

            wp_enqueue_style($this->plugin_slug . '-admin-styles', plugins_url('assets/css/cbxpoll_admin.css', __FILE__), array(), cbxpoll::VERSION);
            wp_enqueue_style($this->plugin_slug . '-chosen', plugins_url('assets/css/chosen.min.css', __FILE__), array(), cbxpoll::VERSION);

            wp_enqueue_style($this->plugin_slug . '-ui-styles', plugins_url('assets/css/ui-lightness/jquery-ui.min.css', __FILE__), array(), cbxpoll::VERSION);
            wp_enqueue_style($this->plugin_slug . '-ui-styles-timepicker', plugins_url('assets/js/jquery-ui-timepicker-addon.min.css', __FILE__), array(), cbxpoll::VERSION);
        }

    }


    /**
     * Register and enqueue admin-specific JavaScript.
     *
     * @since     1.0.0
     *
     * @return    null    Return early if no settings page is registered.
     */
    public function enqueue_admin_scripts($hook)
    {


        //var_dump($hook);

        global $post_type;

        if ( (in_array($hook, array('edit', 'post.php', 'post-new.php')) && 'cbxpoll' == $post_type) || ($hook == 'cbxpoll_page_class-cbxpoll-admin')) {

            wp_enqueue_script('jquery');
            wp_enqueue_style('wp-color-picker');
            wp_enqueue_style('thickbox');
            wp_enqueue_script('wp-color-picker');
            wp_enqueue_script( 'media-upload' );
            // wp_enqueue_script( 'thickbox' );
            wp_enqueue_style('jquery-ui-core'); //jquery ui core
            wp_enqueue_style('jquery-ui-datepicker'); //jquery ui datepicker
            wp_enqueue_style('jquery-ui-sortable'); //jquery ui sortable


            wp_enqueue_script($this->plugin_slug . '-choosen-script', plugins_url('assets/js/chosen.jquery.min.js', __FILE__), array('jquery'), cbxpoll::VERSION);
            //wp_enqueue_script( $this->plugin_slug . '-ui-script', plugins_url( 'assets/js/jquery-ui.js', __FILE__ ), array( 'jquery' ), cbxpoll::VERSION );
            wp_enqueue_script($this->plugin_slug . '-ui-time-script', plugins_url('assets/js/jquery-ui-timepicker-addon.js', __FILE__), array('jquery', 'jquery-ui-datepicker'), cbxpoll::VERSION);

            //wp_enqueue_script( $this->plugin_slug . '-ui-slider-script', plugins_url( 'assets/js/jquery-ui-sliderAccess.js', __FILE__ ), array( 'jquery' ), cbxpoll::VERSION );
            //wp_enqueue_script( $this->plugin_slug . 'sortable-script', plugins_url( 'assets/js/jquery-sortable-min.js', __FILE__ ), array( 'jquery' ), cbxpoll::VERSION );

            wp_enqueue_script($this->plugin_slug . '-admin-script', plugins_url('assets/js/cbxpoll_admin.js', __FILE__), array('jquery'), cbxpoll::VERSION);
        }

    }

    /**
     *  * Add a settings page for this plugin to the Settings menu.
     */
    public function add_plugin_admin_menu()
    {

        $this->plugin_screen_hook_suffix = add_submenu_page('edit.php?post_type=cbxpoll', 'Poll Settings', 'Poll Settings', 'manage_options', basename(__FILE__), array($this, 'display_plugin_admin_page'));

    }

    /**
     * Render the settings page for this plugin.
     *
     * @since    1.0.0
     */
    public function display_plugin_admin_page()
    {

        global $wp_roles;
        $roles = $wp_roles->get_names();
        $roles = array_merge($roles, array('guest' => 'Guest'));
        $editor_roles = $wp_roles->get_names();

        $sections = array(
            array(
                'id' => 'cbxpoll_global_settings',
                'title' => __('General Settings', 'cbxpoll')
            )
        );

        $sections = apply_filters('cbxpoll_setting_sections', $sections);


        $poll_display_methods = cbxpollHelper::cbxpoll_display_options();
        $poll_display_methods = cbxpollHelper::cbxpoll_display_options_linear($poll_display_methods);


        $fields = array(
            'cbxpoll_global_settings' => apply_filters('cbxpoll_global_general_fields', array(
                'result_chart_type' => array(
                    'name' => 'result_chart_type',
                    'label' => __('Result Chart Style', 'cbxpoll'),
                    'desc' => __('Poll result display styles, text and polar area display type are free, you can buy more display option from <a href="http://codeboxr.com/product/cbx-smart-poll" target="_blank">here</a>', 'cbxpoll'),
                    'type' => 'select',
                    'default' => 'text',
                    'options' => $poll_display_methods,
                ),
                'user_roles' => array(
                    'name' => 'user_roles',
                    'label' => __('Who Can Vote', 'cbxpoll'),
                    'desc' => __('which user role will have vote capability', 'cbxpoll'),
                    'type' => 'multiselect',
                    'optgroup' => 0,
                    'default' => array('administrator', 'editor', 'author', 'contributor', 'subscriber', 'guest'),

                    'options' => $roles,
                    'placeholder' => __('Select user roles', 'cbxpoll')
                ),

                'content' => array(
                    'name' => 'content',
                    'label' => __('Show Poll Description', 'cbxpoll'),
                    'desc' => __('Show description from poll post type', 'cbxpoll'),
                    'type' => 'radio',
                    'default' => 0,
                    'options' => array(
                        '1' => __('Yes', 'cbxpoll'),
                        '0' => __('No', 'cbxpoll')
                    )
                ),
                'never_expire' => array(
                    'name' => 'never_expire',
                    'label' => __('Never Expire', 'cbxpoll'),
                    'desc' => __('If set polls will never expire. You can also set individual poll end time.', 'cbxpoll'),
                    'type' => 'radio',
                    'default' => 0,
                    'options' => array(
                        '1' => __('Yes', 'cbxpoll'),
                        '0' => __('No', 'cbxpoll')
                    )
                ),
                'show_result_before_expire' => array(
                    'name' => 'show_result_before_expire',
                    'label' => __('Show result before expires', 'cbxpoll'),
                    'desc' => __('Select if you want poll to show result before expires. After expires the result will be shown always. Please check it if poll never expires.', 'cbxpoll'),
                    'type' => 'radio',
                    'default' => 0,
                    'options' => array(
                        '1' => __('Yes', 'cbxpoll'),
                        '0' => __('No', 'cbxpoll')
                    )
                ),
                'show_result_all' => array(
                    'name' => 'show_result_all',
                    'label' => __('Show result to all', 'cbxpoll'),
                    'desc' => __('Check this if you want to show result to them who can not vote.', 'cbxpoll'),
                    'type' => 'radio',
                    'default' => 0,
                    'options' => array(
                        '1' => __('Yes', 'cbxpoll'),
                        '0' => __('No', 'cbxpoll')
                    )
                ),
                'cookiedays' => array(
                    'name' => 'cookiedays',
                    'label' => __('Cookie Expiration Days', 'cbxpoll'),
                    'desc' => __('For guest user cookie is placed in browser, For how many days cookie will not expire. Default is 30 days', 'cbxpoll'),
                    'type' => 'number',
                    'default' => '30',
                    'placeholder' => __('Number of days', 'cbxpoll')

                ),
                'logmethod' => array(
                    'name' => 'logmethod',
                    'label' => __('Log Method', 'cbxpoll'),
                    'desc' => __('Logging method. [<strong> Note:</strong> Please Select at least one or a guest user will vote multiple time for a poll.]', 'cbxpoll'),
                    'type' => 'select',
                    'default' => 'both',
                    'options' => array(
                        'ip' => __('IP', 'cbxpoll'),
                        'cookie' => __('Cookie', 'cbxpoll'),
                        'both' => __('Both', 'cbxpoll')
                    )
                )
            ))
        );

        $fields = apply_filters('cbxpoll_global_fields', $fields);

        $settings_api = new CBXPoll_Settings();
        $settings_api->set_sections($sections);
        $settings_api->set_fields($fields);
        $settings_api->admin_init();

        include_once('sidebar.php');

    }

    /**
     * Add settings action link to the plugins page.
     *
     * @since    1.0.0
     */
    public function add_action_links($links)
    {

        return array_merge(
            array(
                'settings' => '<a href="' . admin_url('edit.php?post_type=cbxpoll&page=class-cbxpoll-admin.php') . '">' . __('Settings', $this->plugin_slug) . '</a>'
            ),
            $links
        );

    }//end of function add_action_links

    /**
     * Sanitizes a hex color.
     *
     * Returns either '', a 3 or 6 digit hex color (with #), or nothing.
     * For sanitizing values without a #, see sanitize_hex_color_no_hash().
     *
     * @since 3.4.0
     *
     * @param string $color
     * @return string|void
     */
    function sanitize_hex_color($color)
    {
        if ('' === $color)
            return '';

        // 3 or 6 hex digits, or the empty string.
        if (preg_match('|^#([A-Fa-f0-9]{3}){1,2}$|', $color))
            return $color;
    }


}// end of class
