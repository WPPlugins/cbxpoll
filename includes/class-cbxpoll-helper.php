<?php

/**
 * Class cbxpollHelper
 * all database function here
 */
class cbxpollHelper {
    /**
     * create table with plugin activate hook
     */

    public static function install_table() {
        global $wpdb;
        $charset_collate = '';
        if (!empty($wpdb->charset))
            $charset_collate = "DEFAULT CHARACTER SET $wpdb->charset";
        if (!empty($wpdb->collate))
            $charset_collate .= " COLLATE $wpdb->collate";

        require_once(ABSPATH . '/wp-admin/includes/upgrade.php');

        $table_name = self::cbx_poll_table_name();

        // IF NOT EXISTS
        $sql = "CREATE TABLE $table_name (
                  id mediumint(8) unsigned NOT NULL AUTO_INCREMENT,
                  poll_id int(13) NOT NULL,
                  poll_title text NOT NULL,
                  user_name varchar(60) NOT NULL,
                  is_logged_in tinyint(1) NOT NULL,
                  user_cookie varchar(1000) NOT NULL,
                  user_ip varchar(100) NOT NULL,
                  user_id bigint(20) unsigned NOT NULL,
                  user_answer text NOT NULL,
                  published tinyint(3) NOT NULL DEFAULT '1',                    
                  PRIMARY KEY  (id)
            ) $charset_collate;";
        dbDelta($sql);

    }

    /**
     * will be called later when version change and if any database changes
     * calls install table function
     */
    public static function update_table() {
        global $wpdb;
        $version = cbxpoll::$version;
        if (!empty($wpdb->charset))
            $charset_collate = "DEFAULT CHARACTER SET $wpdb->charset";
        if (!empty($wpdb->collate))
            $charset_collate .= " COLLATE $wpdb->collate";

        require_once(ABSPATH . '/wp-admin/includes/upgrade.php');
        //$table_name = self::get_ratingForm_settings_table_name();

        switch($version) {
            case '1.0' :
            case CBX_POLL_PLUGIN_VERSION :

                //var_dump(CBX_POLL_PLUGIN_VERSION); exit;

                $method = 'install_table';
                if(method_exists('cbxpollHelper', $method))
                    self::$method();
                break;
        }
    }

    /**
     * will call this later when plugin uninstalled
     * in can also be written in uninstall.php file
     */
    public static function delete_tables() {
        global $wpdb;
        $table_name[] = self::cbx_poll_table_name();
        $sql = "DROP TABLE IF EXISTS ".implode(', ', $table_name);
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Insert user vote 
     * 
     * @param array $user_vote
     * 
     * @return bool | vote id     
     */
    public static function update_poll($user_vote) {
        global $wpdb;

        //var_dump($user_vote);

        if(!empty($user_vote)) {
            $table_name = self::cbx_poll_table_name();

            $success = $wpdb->insert($table_name, $user_vote, array('%d', '%s', '%s', '%d', '%s', '%s', '%d', '%s', '%d'));
            return ($success) ? $wpdb->insert_id : false;
        }
        return false;
        
    }

       

    /**
     * @return string
     */
    public static function cbx_poll_table_name() {
        global $wpdb;
        return $wpdb->prefix . "cbxpoll_votes";
    }

    /**
     * @param $string
     * @return string
     *
     */
    public static function check_value_type($string) {
        $t = gettype($string);
        $ret = '';

        switch ($t) {
            case 'string' :
                $ret = '\'%s\'';
                break;

            case 'integer':
                //$ret = '\'%d\'';
                $ret = '%d';
                break;
        }

        return $ret;
    }

    /**
     * Returns all votes for any poll
     *
     * @param int $poll_id  cbxpoll type post id
     * @param bool $is_object array or object return type
     *
     *
     * @return mixed
     *
     */
    public static function get_pollResult($poll_id, $is_object = false) {
        global $wpdb;
        $table_name = self::cbx_poll_table_name();

        $sql = $wpdb->prepare("SELECT * FROM $table_name WHERE poll_id=%d", $poll_id);
        $results = $wpdb->get_results($sql, ARRAY_A);


        return $results;
    }// end of function get_pollresult

    /**
     * Returns single vote result by id
     *
     * @param int $vote  single vote id
     * @param bool $is_object array or object return type
     *
     *
     * @return mixed
     *
     */
    public static function get_voteResult($vote_id, $is_object = false) {
        global $wpdb;
        $table_name = self::cbx_poll_table_name();

        $sql = $wpdb->prepare("SELECT * FROM $table_name WHERE id=%d", $vote_id);
        $results = $wpdb->get_results($sql, ARRAY_A);


        return $results;
    }// end of function get_pollresult

    /**
     * @param $array
     * @return array
     */
    public static function check_array_element_value_type($array) {
        $ret = array();

        if(!empty($array)) {
            foreach($array as $val) {
                $ret[] = self::check_value_type($val);
            }
        }

        return $ret;
    } //end of function check_array_element_value_type

    /**
     * Defination of all Poll Display/Chart Types
     *
     * @return array
     */
    public static function cbxpoll_display_options() {
        $methods = array();

        return apply_filters('cbxpoll_display_options', $methods);
    }

    /**
     * Return poll display option as associative array
     *
     * @param array $methods
     *
     * @return array
     */
    public static function cbxpoll_display_options_linear($methods){

        $linear_methods = array();

        foreach($methods as $key => $val){
            $linear_methods[$key] = $val['title'];
        }

        return $linear_methods;
    }

}