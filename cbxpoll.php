<?php
/**
 * Plugin Name:       CBX Poll
 * Plugin URI:        http://codeboxr.com/product/cbx-poll-for-wordpress/
 * Description:       Responsive Smart Poll for wordpress
 * Version:           1.0.5
 * Author:            codeboxr
 * Author URI:        http://codeboxr.com
 * Text Domain:       cbxpoll
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

	define('CBX_POLL_PLUGIN_VERSION', '1.0.5');
	define('CBX_POLL_COOKIE_EXPIRATION', time() + 1209600); //Expiration of 14 days.

	define('CBX_POLL_COOKIE_NAME', 'cbxpoll-cookie');
	define('CBX_POLL_RAND_MIN', 0);
	define('CBX_POLL_RAND_MAX', 999999);

	define('CBX_POLL_COOKIE_EXPIRATION_14DAYS', time() + 1209600); //Expiration of 14 days.
	define('CBX_POLL_COOKIE_EXPIRATION_7DAYS', time() + 604800); //Expiration of 7 days.

/*----------------------------------------------------------------------------*
 * Public-Facing Functionality
 *----------------------------------------------------------------------------*/

/*
 *
 *including main public page and data.php page
 */
require_once( plugin_dir_path( __FILE__ ) . 'public/class-cbxpoll.php' );
require_once( plugin_dir_path( __FILE__ )  . 'includes/class-cbxpoll-helper.php' );


/*
 * Register hooks that are fired when the plugin is activated or deactivated.
 * When the plugin is deleted, the uninstall.php file is loaded.
 *
 *
 */
register_activation_hook( __FILE__, array( 'cbxpoll', 'install_plugin' ) );
register_deactivation_hook( __FILE__, array( 'cbxpoll', 'deactivate' ) );


/*
 *
 *
 * - //get instance of main public page
 */
add_action( 'plugins_loaded', array( 'cbxpoll', 'get_instance' ) );


if ( is_admin() && ( ! defined( 'DOING_AJAX' ) || ! DOING_AJAX ) ) {

	require_once( plugin_dir_path( __FILE__ ) . 'admin/class-cbxpoll-admin.php' );
	add_action( 'plugins_loaded', array( 'cbxpoll_Admin', 'get_instance' ) );

}