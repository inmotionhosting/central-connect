<?php
/**
 * File: Server.php
 *
 * Setup the Rest Server extension.
 *
 * @since      2.0.0
 * @package    Central\Connect\Rest
 * @author     InMotion Hosting <central-dev@inmotionhosting.com>
 * @link       https://boldgrid.com
 */

namespace Central\Connect\Rest;

use Central\Connect;

/**
 * Class: Server
 *
 * Setup the Rest Server extension.
 *
 * @since 2.0.0
 */
class Server {

	/**
	 * Bind necessarry listeners for the REST API endpoints.
	 *
	 * @since 2.0.0
	 */
	public function initialize() {

		// Allow for remote authetication to the API by validating tokens with BoldGrid Central.
		$authentication = new Connect\Authentication\Central();
		$authentication->initialize();

		// Setup plugin Routes.
		$pluginRouter = new Connect\Plugin\Router();
		$pluginRouter->register();

		// Setup Theme Routes.
		$themeRouter = new Connect\Theme\Router();
		$themeRouter->register();

		// Setup Options Routes.
		$optionRouter = new Connect\Option\Router();
		$optionRouter->register();

		// Setup Site Health Routes.
		$healthRouter = new Connect\Health\Router();
		$healthRouter->register();

		// Setup Site Cache Routes.
		$cacheRouter = new Connect\Cache\Router();
		$cacheRouter->register();

		// Setup Site Cache Routes.
		$analyticsRouter = new Connect\Analytics\Router();
		$analyticsRouter->register_routes();

		$this->registerCorsHooks();
	}

	/**
	 * Restrict CORS to allowlisted Central portal origins.
	 *
	 * @since 2.0.0
	 */
	private function registerCorsHooks() {
		$cors = new Cors();

		add_action( 'send_headers', array( $cors, 'sendHeadDiscoveryHeaders' ) );
		add_filter( 'rest_pre_serve_request', array( $cors, 'sendRestCorsHeaders' ), PHP_INT_MAX, 4 );
	}
}
