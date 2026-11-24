<?php
/**
 * File: Central.php
 *
 * Setup Access Token authentication method.
 *
 * @since      2.0.0
 * @package    Central\Connect\Rest
 * @author     InMotion Hosting <central-dev@inmotionhosting.com>
 * @link       https://boldgrid.com
 */

namespace Central\Connect\Authentication;

/**
 * Class: Central
 *
 * Setup Access Token authentication method.
 *
 * @since 2.0.0
 */
class Central {

	/**
	 * WordPress REST server.
	 *
	 * @since 2.0.0
	 *
	 * @var \WP_REST_Server $restServer Instance of WP_REST_Server.
	 */
	protected $restServer;

	/**
	 * Connect to listeners.
	 *
	 * @since 2.0.0
	 */
	public function initialize() {
		add_action(
			'rest_api_init',
			function( $restServer ) {

				// Store the current Rest server.
				$this->restServer = $restServer;

				add_filter( 'rest_authentication_errors', array( $this, 'addRemoteAuth' ), 20 );
			}
		);

		// Setup authentication routes.
		$router = new Router();
		$router->register();
	}

	/**
	 * Authenticate the user through BoldGrid Central.
	 *
	 * @since 2.0.0
	 *
	 * @param mixed $response Null, true or WP_Error from other auth methods.
	 * @return mixed          Null, true or WP_Error from current auth method.
	 */
	public function addRemoteAuth( $response ) {
		// Another auth method already processed the request.
		if ( ! empty( $response ) ) {
			return $response;
		}

		$authValue = $this->getHeader( 'X-BGC-Auth' );
		if ( $authValue ) {
			$tokenHelper = new Token();

			// Don't try to validate other Auth tokens.
			if ( ! $tokenHelper->isBGToken( $authValue ) ) {
				return $response;
			}

			/**
			 * Each failed attempt costs a password-hash comparison per stored
			 * token, so repeated failures from one client are cut off before
			 * the lookup instead of being slowed down with sleep(), which only
			 * tied up a PHP worker for the attacker.
			 */
			$limits  = self::failureLimits();
			$limiter = new RateLimiter();
			$scope   = 'access_token_failures:ip:' . RateLimiter::clientIp();
			if ( $limiter->isLimited( $scope, $limits['limit'], $limits['window'] ) ) {
				return new \WP_Error(
					'central_connect_too_many_attempts',
					'Too many failed authentication attempts. Please try again later.',
					array(
						'status' => 429,
						'retry_after' => $limiter->retryAfter( $scope, $limits['window'] ),
					)
				);
			}

			// Validate the access token for a user and set them as the current user.
			$user = $tokenHelper->getAuthenticatedUser( $authValue );
			if ( $user ) {
				wp_set_current_user( $user->ID );
				$response = true;

				// If not valid respond with error.
			} else {
				$limiter->attempt( $scope, $limits['limit'], $limits['window'] );

				$response = new \WP_Error(
					'restx_logged_out',
					'Sorry, your access token is invalid.',
					array( 'status' => 401 )
				);
			}
		}

		return $response;
	}

	/**
	 * Rate limit applied to failed X-BGC-Auth attempts per client.
	 *
	 * @since 2.0.7
	 *
	 * @return array {
	 *     @type int $limit  Failed attempts allowed per window.
	 *     @type int $window Window length in seconds.
	 * }
	 */
	public static function failureLimits() {
		$defaults = array(
			'limit'  => 10,
			'window' => 600,
		);

		/**
		 * Filters the failed access-token attempt limit per client IP.
		 *
		 * @since 2.0.7
		 *
		 * @param array $limits Array with integer `limit` and `window` (seconds).
		 */
		$limits = apply_filters( 'central_connect_access_token_failure_limits', $defaults );
		if ( ! is_array( $limits ) ) {
			return $defaults;
		}

		return array(
			'limit'  => isset( $limits['limit'] ) ? max( 1, (int) $limits['limit'] ) : $defaults['limit'],
			'window' => isset( $limits['window'] ) ? max( 1, (int) $limits['window'] ) : $defaults['window'],
		);
	}

	/**
	 * Get a header value case insensitive.
	 *
	 * @since X.X.X
	 *
	 * @param string $headerKey Index of header.
	 * @return string           Value of header.
	 */
	public function getHeader( $headerKey ) {
		$headers = $this->restServer->get_headers( $_SERVER );

		$value = null;
		foreach ( $headers as $key => $header ) {
			$key = str_replace( '_', '-', $key );
			if ( strtolower( $key ) === strtolower( $headerKey ) ) {
				$value = $header;
				break;
			}
		}

		return $value;
	}
}
