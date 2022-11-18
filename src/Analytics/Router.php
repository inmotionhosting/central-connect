<?php
/**
 * File: Router.php
 *
 * Setup the Router.
 *
 * @since      2.0.0
 * @package    Central\Connect\Analytics
 * @author     InMotion Hosting <central-dev@inmotionhosting.com>
 * @link       https://boldgrid.com
 */

namespace Central\Connect\Analytics;

use Central\Connect\Rest\Controller;

/**
 * Class: Router
 *
 * Setup the Router.
 *
 * @since 2.0.0
 */
class Router extends Controller {

	/**
	 * Register routes.
	 *
	 * @since 2.0.0
	 *
	 * @return void
	 */
	public function register_routes() {
		add_action(
			'rest_api_init',
			function () {
				$this->registerFetchStats();
			}
		);
	}

	/**
	 * Setup route to list user plugins.
	 *
	 * @since 2.0.0
	 *
	 * @return void
	 */
	private function registerFetchStats() {
		register_rest_route(
			$this->namespace,
			'/stats',
			array(
				'methods' => 'GET',
				'callback' => function () {
					$stats = new Stats();

					return $stats->getData();
				},
				'permission_callback' => array( $this, 'permissionCheck' ),
			)
		);
	}
}
