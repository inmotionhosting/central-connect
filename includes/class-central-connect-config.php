<?php
/**
 * File: class-boldgrid-connect-config.php
 *
 * Defines configuration properties of the plugin.
 *
 * @link       https://www.boldgrid.com
 * @since      1.0.0
 *
 * @package    Boldgrid_Connect
 * @subpackage Boldgrid_Connect/includes
 * @copyright  BoldGrid.com
 * @version    $Id$
 * @author     BoldGrid <support@boldgrid.com>
 */

/**
 * Class: Boldgrid_Connect_Config.
 *
 * @since 1.0.0
 */
class Central_Connect_Config {
	/**
	 * Setup the configuration.
	 *
	 * @since 1.0.0
	 *
	 * @see Boldgrid_Connect_Service::register()
	 *
	 * @global $wp_filesystem.
	 */
	public function setup_configs() {
		require_once ABSPATH . '/wp-admin/includes/file.php';
		WP_Filesystem();
		global $wp_filesystem;

		// Set the initial configs for those who can't wait.
		$configs = include CENTRAL_CONNECT_PATH . '/includes/config/config.plugin.php';

		// If there are local config overrides, then merge them in.
		$local_config_path = CENTRAL_CONNECT_PATH . '/includes/config/config.local.php';

		if ( $wp_filesystem->exists( $local_config_path ) ) {
			$configs_local = include $local_config_path;

			$configs = array_merge( $configs, $configs_local );
		}

		/**
		 * Allow users to filter the configuration.
		 *
		 * @since 1.0.0
		 *
		 * @param array $configs Configuration array.
		 */
		$configs = apply_filters( 'boldgrid_connect_config_setup_configs', $configs );

		Boldgrid_Connect_Service::register( 'configs', $configs );
	}
}
