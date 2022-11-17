<?php
/**
* File: Installer.php
*
* Plugin install actions.
*
* @since      2.0.0
* @package    BoldGrid\Connect\Rest
* @author     BoldGrid <support@boldgrid.com>
* @link       https://boldgrid.com
*/

namespace BoldGrid\Connect\Plugin;

/**
* Class: Installer
*
* Plugin install actions.
*
* @since 2.0.0
*/
class Installer {

	/**
	 * List installed plugins.
	 *
	 * @since 2.0.0
	 *
	 * @return void
	 */
	public function getCollection() {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		wp_cache_delete( 'plugins', 'plugins' );

		$updates = get_site_transient( 'update_plugins' );

		$plugins = [];
		foreach ( \get_plugins() as $filePath => $plugin ) {
			$plugin['File'] = $filePath;
			$plugin['IsActive'] = is_plugin_active( $filePath );

			if ( isset( $updates->response[ $filePath ] ) ) {
				$plugin['Update'] = $updates->response[ $filePath ];
			}

			$plugins[] = $plugin;
		}

		return $plugins;
	}

	/**
	 * Delete a plugin.
	 *
	 * @since 2.0.0
	 *
	 * @param array $files List of plugin files.
	 * @return void
	 */
	public function delete( $files ) {
		include_once ABSPATH . 'wp-admin/includes/misc.php';
		include_once ABSPATH . 'wp-admin/includes/plugin.php';
		delete_plugins( $files );
	}

	/**
	 * Install a plugin.
	 *
	 * @since 2.0.0
	 */
	public function install( $plugin_zip ) {
		include_once ABSPATH . 'wp-admin/includes/misc.php';
		include_once ABSPATH . 'wp-admin/includes/plugin.php';
		include_once CENTRAL_CONNECT_PATH . 'includes/class-central-connect-upgrader.php';

		add_filter( 'upgrader_package_options', function ( $options ) {
			$options['clear_destination'] = true;
			$options['clear_working'] = true;

			return $options;
		} );

		wp_cache_flush();

		$upgrader = new \Plugin_Upgrader( new \Central_Connect_Upgrader_Skin() );
		$upgrader->install( $plugin_zip );

		return $upgrader->plugin_info();
	}
}
