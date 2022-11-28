<?php
/**
 * File: central-connect.php
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              https://central.inmotionhosting.com
 * @since             1.0.0
 * @package           Central_Connect
 *
 * @wordpress-plugin
 * Plugin Name:       Central Connect
 * Plugin URI:        https://central.inmotionhosting.com
 * Description:       Safe and easy management for all of your WordPress websites. SEO, Backups, 1-click login, site transfers, and more on one dashboard.
 * Version:           2.0.1
 * Author:            InMotion Hosting
 * Author URI:        https://inmotionhosting.com
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       central-connect
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

if ( ! defined( 'CENTRAL_CONNECT_VERSION' ) ) {
	define( 'CENTRAL_CONNECT_VERSION', implode( get_file_data( __FILE__, array( 'Version' ), 'plugin' ) ) );
}

if ( ! defined( 'CENTRAL_CONNECT_PATH' ) ) {
	define( 'CENTRAL_CONNECT_PATH', plugin_dir_path( __FILE__ ) );
}

if ( ! defined( 'CENTRAL_CONNECT_FILE' ) ) {
	define( 'CENTRAL_CONNECT_FILE', __FILE__ );
}

if ( ! class_exists( 'Central_Connect_Version_Check' ) ) {
	require CENTRAL_CONNECT_PATH . 'includes/class-central-connect-version-check.php';
}

// Initalize the version checking.  This checks that the user has at least WordPress v4.0 and PHP v5.6.
// WordPress REST API was added in version 4.7.
// BoldGrid Backup has a minimum PHP version of 5.4 supported.
Central_Connect_Version_Check::init( plugin_basename( __FILE__ ), '5.0', '5.6', 'central_connect_plugin_load' );

/**
 * Kicks off our core plugin code.
 */
function central_connect_plugin_load() {
	if ( ! function_exists( 'central_connect_run' ) ) {
		/**
		 * The core plugin class that is used to define internationalization,
		 * admin-specific hooks, and public-facing site hooks.
		 */
		require CENTRAL_CONNECT_PATH . '/includes/class-central-connect.php';

		/**
		 * Begins execution of the plugin.
		 *
		 * Since everything within the plugin is registered via hooks,
		 * then kicking off the plugin from this point in the file does
		 * not affect the page life cycle.
		 *
		 * @since    1.0.0
		 */
		function central_connect_run() {
			// Load the plugin.
			$plugin = new Central_Connect();
			$plugin->run();
		}

		central_connect_run();
	}
}
