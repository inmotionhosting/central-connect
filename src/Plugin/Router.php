<?php
/**
 * File: Router.php
 *
 * Setup the Router.
 *
 * @since      2.0.0
 * @package    Central\Connect\Rest
 * @author     InMotion Hosting <central-dev@inmotionhosting.com>
 * @link       https://boldgrid.com
 */

namespace Central\Connect\Plugin;

use Central\Connect\FileSystem;

/**
 * Class: Router
 *
 * Setup the Router.
 *
 * @since 2.0.0
 */
class Router {

	/**
	 * Reference to plugin installer.
	 *
	 * @since 2.0.0
	 *
	 * @var Installer
	 */
	protected $pluginInstaller;

	/**
	 * Register routes.
	 *
	 * @since 2.0.0
	 *
	 * @return void
	 */
	public function register() {
		$this->pluginInstaller = new Installer();

		add_action(
			'rest_api_init',
			function () {
				$this->registerInstallPlugin();
				$this->registerList();
				$this->registerRemove();
				$this->registerActivateDeactivate();
			}
		);
	}

	/**
	 * Setup route to activate plugins.
	 *
	 * @since 2.0.0
	 *
	 * @return void
	 */
	public function registerActivateDeactivate() {
		register_rest_route(
			'bgc/v1',
			'/plugins/',
			array(
				'methods' => 'PUT',
				'callback' => function ( $request ) {
					$files = $request->get_param( 'files' ) ?: array(); //phpcs:ignore WordPress.PHP.DisallowShortTernary.Found
					$active = $request->get_param( 'active' );

					include_once ABSPATH . 'wp-admin/includes/plugin.php';

					if ( $active ) {
						activate_plugins( $files );
					} else {
						deactivate_plugins( $files );
					}

					$updatedPluginList = $this->pluginInstaller->getCollection();
					$response = new \WP_REST_Response( $updatedPluginList );

					return $response;
				},
				'permission_callback' => array( $this, 'pluginPermissionCheck' ),
				'args' => array(
					'files' => array(
						'required' => true,
						'description' => 'List of plugin file paths e.g. plugin-name/plugin.php',
						'type' => 'array',
						'items' => array(
							'type' => 'string',
						),
						'validate_callback' => array( $this, 'validateFilesExists' ),
					),
					'active' => array(
						'required' => true,
						'type' => 'boolean',
						'description' => 'Is Active state. true to activate plugin false to deactivate.',
					),
				),
			)
		);
	}


	/**
	 * Setup route to remove plugins.
	 *
	 * @since 2.0.0
	 *
	 * @return void
	 */
	private function registerRemove() {
		register_rest_route(
			'bgc/v1',
			'/plugins/',
			array(
				'methods' => 'DELETE',
				'callback' => function ( $request ) {
					$files = $request->get_param( 'files' );

					$this->pluginInstaller->delete( $files );

					$updatedPluginList = $this->pluginInstaller->getCollection();

					$response = new \WP_REST_Response( $updatedPluginList );

					return $response;
				},
				'permission_callback' => array( $this, 'pluginPermissionCheck' ),
				'args' => array(
					'files' => array(
						'required' => true,
						'description' => 'List of plugin file paths e.g. plugin-name/plugin.php',
						'type' => 'array',
						'validate_callback' => array( $this, 'validateFilesExists' ),
						'items' => array(
							'type' => 'string',
						),
					),
				),
			)
		);
	}

	/**
	 * Setup route to list user plugins.
	 *
	 * @since 2.0.0
	 *
	 * @return void
	 */
	private function registerList() {
		register_rest_route(
			'bgc/v1',
			'/plugins/',
			array(
				'methods' => 'GET',
				'callback' => function () {
					$response = $this->pluginInstaller->getCollection();

					$response = new \WP_REST_Response( $response );

					return $response;
				},
				'permission_callback' => array( $this, 'pluginPermissionCheck' ),
			)
		);
	}

	/**
	 * Setup route to install plugins.
	 *
	 * @since 2.0.0
	 *
	 * @return void
	 */
	private function registerInstallPlugin() {
		register_rest_route(
			'bgc/v1',
			'/plugins/',
			array(
				'methods' => 'POST',
				'callback' => function ( $request ) {
					$plugins = $request->get_param( 'plugins' );
					$activate = $request->get_param( 'activate' );

					foreach ( $plugins as $plugin ) {
						$path = $this->pluginInstaller->install( $plugin );
						if ( $activate && $path ) {
							activate_plugin( $path );
						}
					}

					$response = $this->pluginInstaller->getCollection();
					$response = new \WP_REST_Response( $response );

					// Add a custom status code.
					$response->set_status( 201 );

					return $response;
				},
				'permission_callback' => array( $this, 'pluginPermissionCheck' ),
				'args' => array(
					'activate' => array(
						'type' => 'boolean',
						'description' => '1 to activate plugin 0 to deactivate.',
					),
					'plugins' => array(
						'required' => true,
						'type' => 'array',
						'description' => 'List of plugin zips and slugs to install.',
						'items' => array(
							'type' => 'string',
						),
						'sanitize_callback' => function ( $plugins ) {
							$cleanPlugins = array();
							foreach ( $plugins as $plugin ) {
								$wpOrgPlugin = 'https://downloads.wordpress.org/plugin/' . $plugin . '.latest-stable.zip';
								if ( wp_http_validate_url( $plugin ) ) {
									$cleanPlugins[] = $plugin;
								} else if ( wp_http_validate_url( $wpOrgPlugin ) ) {
									$cleanPlugins[] = $wpOrgPlugin;
								}
							}

							return $cleanPlugins;
						},
					),
				),
			)
		);
	}

	/**
	 * Given a list of plugin files, make sure they are all installed plugins.
	 *
	 * Each entry must be a plugin basename known to get_plugins() and pass
	 * validate_file(). An existence check alone is not enough because core's
	 * delete_plugins() does not validate the path it is given.
	 *
	 * @since 2.0.0
	 *
	 * @param array $files Array of plugin basenames (plugin-dir/plugin.php).
	 * @return boolean     Are all entries installed plugins.
	 */
	public function validateFilesExists( $files ) {
		if ( ! is_array( $files ) || empty( $files ) ) {
			return false;
		}

		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		wp_cache_delete( 'plugins', 'plugins' );
		$installed  = get_plugins();
		$fileSystem = new FileSystem();

		foreach ( $files as $file ) {
			if ( ! is_string( $file ) || '' === $file || 0 !== validate_file( $file ) ) {
				return false;
			}

			if ( ! isset( $installed[ $file ] ) ) {
				return false;
			}

			if ( ! $fileSystem->wpFilesystem->is_file( trailingslashit( WP_PLUGIN_DIR ) . $file ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Make sure current can activate plugins for all Plugin api calls.
	 *
	 * @since 2.0.0
	 *
	 * @return boolean Has Access?
	 */
	public function pluginPermissionCheck() {
		return current_user_can( 'activate_plugins' );
	}
}
