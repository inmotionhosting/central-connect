<?php
/**
 * File: Router.php
 *
 * Setup the Router.
 *
 * @since      2.0.0
 * @package    BoldGrid\Connect\Rest
 * @author     InMotion Hosting <central-dev@inmotionhosting.com>
 * @link       https://boldgrid.com
 */

namespace Central\Connect\Health;

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
				$this->registerScan();
			}
		);
	}

	/**
	 * Setup route to remove plugins.
	 *
	 * @since 2.0.0
	 *
	 * @return void
	 */
	private function registerScan() {
		register_rest_route(
			'bgc/v1',
			'/health/',
			array(
				'methods' => 'GET',
				'callback' => function () {
					$stats = new Stats();
					$response = new \WP_REST_Response( $stats->getSiteHealth() );

					return $response;
				},
				'permission_callback' => array( $this, 'pluginPermissionCheck' ),
			)
		);
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
