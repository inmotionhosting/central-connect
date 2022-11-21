<?php
/**
 * File: class-central-connect-version-check.php
 *
 * @link       https://central.inmotionhosting.com
 * @since      1.0.0
 *
 * @package    Central_Connect
 * @subpackage Central_Connect/includes
 * @copyright  InMotionHosting.com
 * @version    $Id$
 * @author     InMotion Hosting <central-dev@inmotionhosting.com>
 */

if ( ! class_exists( 'Central_Connect_Version_Check' ) ) {

	/**
	 * Central_Connect_Version_Check Class.
	 *
	 * This class is used to determine if a supported PHP version and
	 * WP version are in use before initializing plugin code.
	 */
	class Central_Connect_Version_Check {

		/**
		 * The main plugin file.
		 *
		 * @var string $plugin Main plugin file.
		 *
		 * @access private
		 */
		private static $plugin;

		/**
		 * Minimum PHP version required.
		 *
		 * @var string $php_version Minimum PHP version required.
		 *
		 * @access private
		 */
		private static $php_version;

		/**
		 * Minimum WordPress version required.
		 *
		 * @var string $wp_version Minimum WordPress version required.
		 *
		 * @access private
		 */
		private static $wp_version;

		/**
		 * Initializes the version checking process.
		 *
		 * @since 1.0.0
		 *
		 * @access public
		 *
		 * @param  string   $plugin      Root plugin file.
		 * @param  string   $wp_version  Minimum WordPress version required.
		 * @param  string   $php_version Minimum PHP version required.
		 * @param  callable $callback    Callback method to call if version check passes.
		 *
		 * @return void
		 */
		public static function init( $plugin, $wp_version, $php_version, $callback = null ) {
			self::$plugin = $plugin;
			self::$php_version = $php_version;
			self::$wp_version = $wp_version;
			if ( self::is_bad() ) {
				// Display warnings for WP-CLI.
				if ( defined( 'WP_CLI' ) ) {
					WP_CLI::warning( self::get_message() );
					self::deactivate();
				} else {
					// Shows the version error notice in the dashboard.
					add_action( 'admin_notices', array( __CLASS__, 'admin_notice' ) );
					// Ensures plugin deactivation happens after the notice is displayed.
					add_action( 'admin_init', array( __CLASS__, 'deactivate' ) );
				}
			} else {
				// Checks for cb and args passed.
				if ( is_callable( $callback ) ) {
					$arguments = array();
					$args = func_num_args();
					for ( $i = 4; $i < $args; $i++ ) {
						$arg = func_get_arg( $i );
						$arguments[] = $arg;
					}
					// Call the callback with any args required.
					call_user_func_array( $callback, $arguments );
				} else {
					// Add success hook to init for plugins to initialize on.
					add_action( 'init', array( __CLASS__, 'success_hook' ) );
				}
			}
		}

		/**
		 * Adds dynamic action for plugin name:init.
		 *
		 * This is where plugins can hook to run their initialization code
		 * once passing the PHP and WordPress version checks.
		 *
		 * @since  1.0.0
		 *
		 * @access public
		 *
		 * @return void
		 */
		public static function success_hook() {
			$file = explode( '/', self::$plugin );
			$name = sanitize_key( $file[0] );
			do_action( "{$name}:init" );
		}

		/**
		 * Responsible for the admin notice display.
		 *
		 * @since  1.0.0
		 *
		 * @access public
		 *
		 * @return void
		 */
		public static function admin_notice() {
			printf( '<div class="error"><p>%s</p></div>', esc_html( self::get_message() ) );
			// Disables the activate message.
			if ( isset( $_GET['activate'] ) ) {
				unset( $_GET['activate'] );
			}
		}

		/**
		 * Deactivate plugin.
		 *
		 * @since  1.0.0
		 *
		 * @access public
		 *
		 * @return void
		 */
		public static function deactivate() {
			error_log( self::get_message() );
			deactivate_plugins( self::$plugin );
		}


		/**
		 * Check that the PHP version and WordPress versions are passing minimum
		 * requirements.
		 *
		 * @since  1.0.0
		 *
		 * @access public
		 *
		 * @return bool   Is this not meeting version requirements?
		 */
		public static function is_bad() {
			return self::is_bad_php() || self::is_bad_wp();
		}

		/**
		 * Checks whether the current PHP version is insufficient.
		 *
		 * @since  1.0.0
		 *
		 * @access public
		 *
		 * @return bool   Is this not meeting PHP version requirements?
		 */
		public static function is_bad_php() {
			return version_compare( PHP_VERSION, self::$php_version, '<' );
		}

		/**
		 * Checks if the current WordPress version meets the minimum requirements.
		 *
		 * @since  1.0.0
		 *
		 * @access public
		 *
		 * @return bool   Is this not meeting WordPress version requirements?
		 */
		public static function is_bad_wp() {
			return version_compare( get_bloginfo( 'version' ), self::$wp_version, '<' );
		}

		/**
		 * Responsible for generating the error message to display.
		 *
		 * @since  1.0.0
		 *
		 * @access public
		 *
		 * @return string The error message.
		 */
		public static function get_message() {
			return sprintf(
				/* translators: %1s: plugin directory name, %2s: Required WordPress version, %3s: Required PHP version, %4s: Current WordPress version, %5s: Current PHP version. */
				__( 'The plugin <code>%1$s</code> requires at least WordPress %2$s and PHP %3$s. You are currently running WordPress %4$s and PHP %5$s.', 'central-connect' ),
				dirname( plugin_basename( self::$plugin ) ),
				self::$wp_version,
				self::$php_version,
				get_bloginfo( 'version' ),
				PHP_VERSION
			);
		}
	}
}
