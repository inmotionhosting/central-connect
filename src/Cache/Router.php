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

namespace Central\Connect\Cache;

/**
 * Class: Router
 *
 * Setup the Router.
 *
 * @since 2.0.0
 */
class Router {

	/**
	 * Register routes.
	 *
	 * @since 2.0.0
	 *
	 * @return void
	 */
	public function register() {
		add_action(
			'rest_api_init',
			function () {
				$this->registerGetConfig();
				$this->registerUpdateConfig();
			}
		);
	}

	/**
	 * Setup route to remove settings.
	 *
	 * @since 2.0.0
	 *
	 * @return void
	 */
	private function registerGetConfig() {
		register_rest_route(
			'bgc/v1',
			'/w3cache/',
			array(
				'methods' => 'GET',
				'callback' => function () {
					return $this->getSettings();
				},
				'permission_callback' => array( $this, 'pluginPermissionCheck' ),
			)
		);
	}

	/**
	 * Setup route to update settings.
	 *
	 * @since 2.0.0
	 *
	 * @return void
	 */
	private function registerUpdateConfig() {
		register_rest_route(
			'bgc/v1',
			'/w3cache/',
			array(
				'methods' => 'POST',
				'callback' => function () {
					if ( function_exists( 'w3tc_config' ) && class_exists( '\W3TC\Dispatcher' ) ) {
						$config = \w3tc_config();
						$config->set( 'pgcache.enabled', true );
						$config->save();

						$environment = \W3TC\Dispatcher::component( 'Root_Environment' );
						$environment->fix_in_wpadmin( $config, true );
					}

					$settings = $this->getSettings();

					$status = 500;
					if ( ! empty( $settings['pgcache.enabled'] ) ) {
						$status = 200;
					}

					$response = new \WP_REST_Response( $settings );
					$response->set_status( $status );

					return $response;
				},
				'permission_callback' => array( $this, 'pluginPermissionCheck' ),
			)
		);
	}

	/**
	 * Get the page cache settings.
	 *
	 * @return array Settings
	 */
	public function getSettings() {
		$values = array();
		if ( function_exists( 'w3tc_config' ) ) {
			$config = \w3tc_config();

			$values = array();
			$options = array( 'pgcache.enabled' );
			foreach ( $options as $option ) {
				$values[ $option ] = $config->get( $option );
			}
		}

		return $values;
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
