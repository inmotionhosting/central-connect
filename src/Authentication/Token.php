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

use Central\Connect\Option;

/**
 * Class: Central
 *
 * Setup Access Token authentication method.
 *
 * @since 2.0.0
 */
class Token {

	/**
	 * Upper bound on an environment token presented for remote validation.
	 *
	 * @since 2.0.7
	 *
	 * @var int
	 */
	const MAX_TOKEN_LENGTH = 1024;

	/**
	 * Upper bound on an environment id presented for remote validation.
	 *
	 * @since 2.0.7
	 *
	 * @var int
	 */
	const MAX_ENVIRONMENT_ID_LENGTH = 128;

	/**
	 * Save an access token for the user.
	 *
	 * @since 2.0.0
	 *
	 * @param WP_User $user    WP User to create an access token for.
	 * @param string  $expires A date/time string to be parsed by strtotim() for the expiration time.
	 *
	 * @return array  Access token.
	 */
	public function create( $user, $expires = '+2 hours' ) {
		$accessTokens = get_user_option( 'bgc_access_tokens', $user->ID );
		$accessTokens = is_array( $accessTokens ) ? $accessTokens : array();
		$accessTokens = $this->cleanUpOldTokens( $accessTokens );

		$rawToken = wp_generate_password( 128 );

		$newAccessToken = array(
			'site_url'          => get_site_url(),
			'login_url'         => wp_login_url(),
			'wp_user_id'        => $user->ID,
			'access_token_hash' => wp_hash_password( $rawToken ),
			'expires_at'        => strtotime( $expires ),
			'issued_at'         => time(),
		);

		$accessTokens[] = $newAccessToken;

		update_user_option( $user->ID, 'bgc_access_tokens', $accessTokens );

		unset( $newAccessToken['access_token_hash'] );
		$newAccessToken['access_token'] = base64_encode( $user->ID . ':' . $rawToken );

		return $newAccessToken;
	}

	/**
	 * Is this the presented token formatted as a Central Token?
	 *
	 * @since 2.0.0
	 *
	 * @param string $providedToken Authentication token provided.
	 */
	public function isBGToken( $providedToken ) {
		$providedToken = preg_replace( '/Bearer\s+/', '', $providedToken );
		$tokenValue    = base64_decode( $providedToken );
		$tokenValue    = $tokenValue ? $tokenValue : '';
		$split         = explode( ':', $tokenValue );
		$userId        = ! empty( $split[0] ) ? $split[0] : null;
		$providedToken = ! empty( $split[1] ) ? $split[1] : null;
		return is_numeric( $userId ) && $userId && $providedToken;
	}

	/**
	 * Validate a Central token with the Central API.
	 *
	 * Boolean wrapper around validateEnvironmentToken() kept for callers that
	 * only need a yes/no answer.
	 *
	 * @since 1.0.0
	 *
	 * @param string $token         Token to validate.
	 * @param string $environmentId User's Central Environment ID.
	 *
	 * @return boolean Is the token valid?
	 */
	public function remoteValidate( $token, $environmentId ) {
		return true === $this->validateEnvironmentToken( $token, $environmentId );
	}

	/**
	 * Validate a Central environment token, guarding the outbound call.
	 *
	 * This path is reachable by unauthenticated visitors (REST /bgc/v1/auth/
	 * and wp-login.php), so everything that can be decided locally is decided
	 * before the site contacts Central with its own API key:
	 *
	 * - a site without a Central API key is not connected and never relays;
	 * - malformed tokens / environment ids are rejected without a relay;
	 * - an opt-in caller allowlist (`central_connect_auth_allowed_ips`);
	 * - per-client and per-site rate limits bound how many outbound
	 *   validation calls a visitor can cause;
	 * - when the site already knows its environment id, that stored value is
	 *   what gets sent to Central. A request naming a different environment
	 *   is only relayed through a much smaller "re-pair" bucket so a one-time
	 *   legitimate move keeps working but the route cannot be used to spray
	 *   arbitrary environment ids at Central.
	 *
	 * Failures return immediately; the previous 3 second sleep let any
	 * visitor pin a PHP worker for free and has been replaced by the limiter.
	 *
	 * @since 2.0.7
	 *
	 * @param string $token         Token to validate.
	 * @param string $environmentId Central Environment ID named by the caller.
	 *
	 * @return true|\WP_Error True when Central confirms the token, otherwise a
	 *                        WP_Error carrying an HTTP status in its data.
	 */
	public function validateEnvironmentToken( $token, $environmentId ) {
		$siteKey = get_option( 'boldgrid_api_key' );
		if ( ! is_string( $siteKey ) || '' === trim( $siteKey, " \n\r\t\v\0" ) ) {
			return new \WP_Error(
				'central_connect_not_connected',
				'This site is not connected to Central.',
				array( 'status' => 403 )
			);
		}

		if ( ! self::isWellFormedToken( $token ) ) {
			return new \WP_Error(
				'central_connect_invalid_token',
				'The remote access token is malformed.',
				array( 'status' => 400 )
			);
		}

		if ( ! self::isWellFormedEnvironmentId( $environmentId ) ) {
			return new \WP_Error(
				'central_connect_invalid_environment',
				'The environment id is malformed.',
				array( 'status' => 400 )
			);
		}

		$callerCheck = $this->checkAllowedCaller();
		if ( is_wp_error( $callerCheck ) ) {
			return $callerCheck;
		}

		$storedEnvironmentId = Option\Connect::get( 'environment_id' );
		$storedEnvironmentId = is_scalar( $storedEnvironmentId ) ? (string) $storedEnvironmentId : '';
		$rebind              = '' !== $storedEnvironmentId && ! hash_equals( $storedEnvironmentId, (string) $environmentId );

		$throttle = $this->throttleRemoteValidation( $rebind );
		if ( is_wp_error( $throttle ) ) {
			return $throttle;
		}

		$environmentToSend = $rebind || '' === $storedEnvironmentId ? (string) $environmentId : $storedEnvironmentId;

		do_action( 'bgc_remote_validate' );

		$configs = \Central_Connect_Service::get( 'configs' );
		$url     = $configs['asset_server'] . $configs['ajax_calls']['verify_env_token'];
		$args    = array(
			'body'    => array(
				'token'          => $token,
				'environment_id' => $environmentToSend,
				'key'            => $siteKey,
			),
			'timeout' => 15,
		);

		$response = wp_remote_get( $url, $args );
		$httpCode = wp_remote_retrieve_response_code( $response );
		$body     = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 200 === $httpCode && ! empty( $body['is_valid'] ) ) {
			$this->releaseRemoteValidation();
			return true;
		}

		return new \WP_Error(
			'restx_logged_out',
			'Sorry, your remote access token is not valid.',
			array( 'status' => 403 )
		);
	}

	/**
	 * Consume rate-limit budget for an outbound Central validation call.
	 *
	 * Shared by every unauthenticated path that can make the site contact
	 * Central (environment-token and site-token validation). Buckets exist
	 * per client IP and per site so a distributed caller is still bounded.
	 *
	 * Every applicable bucket is checked before any is consumed, so a refused
	 * request costs nothing: rejected re-pair attempts do not drain the normal
	 * buckets, and once a site-wide bucket is full no per-IP transient is
	 * written. Site-wide buckets are checked ahead of per-IP ones.
	 *
	 * @since 2.0.7
	 *
	 * @param bool $rebind Whether the caller named an environment id different
	 *                     from the one the site already stores.
	 *
	 * @return true|\WP_Error True when the call may proceed, WP_Error (429) otherwise.
	 */
	public function throttleRemoteValidation( $rebind = false ) {
		$limits  = self::rateLimits();
		$limiter = new RateLimiter();
		$ip      = RateLimiter::clientIp();

		$checks = array( array( 'remote_validate:total', $limits['total'] ) );

		if ( $rebind ) {
			$checks[] = array( 'remote_validate:rebind:site', $limits['rebind_site'] );
		}

		$checks[] = array( 'remote_validate:site', $limits['site'] );

		if ( $rebind ) {
			$checks[] = array( 'remote_validate:rebind:ip:' . $ip, $limits['rebind_ip'] );
		}

		$checks[] = array( 'remote_validate:ip:' . $ip, $limits['ip'] );

		foreach ( $checks as $check ) {
			list( $scope, $limit ) = $check;
			if ( $limiter->isLimited( $scope, $limit['limit'], $limit['window'] ) ) {
				return $this->tooManyAttempts( $limiter, $scope, $limit['window'] );
			}
		}

		foreach ( $checks as $check ) {
			list( $scope, $limit ) = $check;
			if ( ! $limiter->attempt( $scope, $limit['limit'], $limit['window'] ) ) {
				return $this->tooManyAttempts( $limiter, $scope, $limit['window'] );
			}
		}

		return true;
	}

	/**
	 * Refund the per-IP and per-site budget after Central confirms a token.
	 *
	 * An unauthenticated caller cannot obtain a Central-confirmed token, so
	 * confirmed validations (dashboard refreshes, Central backend jobs) do not
	 * count against the tight `ip` / `site` buckets. They still count against
	 * the larger `total` bucket, which bounds all outbound calls.
	 *
	 * @since 2.0.7
	 *
	 * @return void
	 */
	public function releaseRemoteValidation() {
		$limits  = self::rateLimits();
		$limiter = new RateLimiter();

		$limiter->release( 'remote_validate:ip:' . RateLimiter::clientIp(), $limits['ip']['window'] );
		$limiter->release( 'remote_validate:site', $limits['site']['window'] );
	}

	/**
	 * Build the 429 error returned when a remote-validation bucket is full.
	 *
	 * @since 2.0.7
	 *
	 * @param RateLimiter $limiter Limiter instance.
	 * @param string      $scope   Exhausted bucket.
	 * @param int         $window  Bucket window in seconds.
	 *
	 * @return \WP_Error
	 */
	private function tooManyAttempts( $limiter, $scope, $window ) {
		return new \WP_Error(
			'central_connect_too_many_attempts',
			'Too many authentication attempts. Please try again later.',
			array(
				'status'      => 429,
				'retry_after' => $limiter->retryAfter( $scope, $window ),
			)
		);
	}

	/**
	 * Rate-limit configuration for remote validation calls.
	 *
	 * `ip` and `site` count calls Central did not confirm (confirmed calls are
	 * refunded); `total` counts every outbound call; the `rebind_*` buckets
	 * count calls naming an environment other than the stored one.
	 *
	 * @since 2.0.7
	 *
	 * @return array Buckets keyed by name, each with `limit` and `window` (seconds).
	 */
	public static function rateLimits() {
		$defaults = array(
			'ip'          => array(
				'limit'  => 5,
				'window' => 600,
			),
			'site'        => array(
				'limit'  => 20,
				'window' => 600,
			),
			'total'       => array(
				'limit'  => 120,
				'window' => 600,
			),
			'rebind_ip'   => array(
				'limit'  => 2,
				'window' => 3600,
			),
			'rebind_site' => array(
				'limit'  => 5,
				'window' => 3600,
			),
		);

		/**
		 * Filters the rate limits applied to outbound Central validation calls.
		 *
		 * @since 2.0.7
		 *
		 * @param array $limits Buckets keyed by `ip`, `site`, `total`, `rebind_ip`, `rebind_site`,
		 *                      each an array with integer `limit` and `window` (seconds).
		 */
		$limits = apply_filters( 'central_connect_auth_rate_limits', $defaults );
		if ( ! is_array( $limits ) ) {
			return $defaults;
		}

		foreach ( $defaults as $name => $default ) {
			if ( empty( $limits[ $name ] ) || ! is_array( $limits[ $name ] ) ) {
				$limits[ $name ] = $default;
				continue;
			}
			$limits[ $name ]['limit']  = isset( $limits[ $name ]['limit'] ) ? max( 1, (int) $limits[ $name ]['limit'] ) : $default['limit'];
			$limits[ $name ]['window'] = isset( $limits[ $name ]['window'] ) ? max( 1, (int) $limits[ $name ]['window'] ) : $default['window'];
		}

		return $limits;
	}

	/**
	 * Enforce the optional caller IP allowlist.
	 *
	 * Disabled by default (empty list). When set, it gates every
	 * unauthenticated path that relays a token to Central: the REST
	 * `/bgc/v1/auth/` route and both wp-login.php token flows (environment
	 * token and site token).
	 *
	 * Those requests do not come only from Central's servers. The Central
	 * dashboard calls `/bgc/v1/auth/` from the customer's browser, and
	 * single sign-on to wp-login.php is a browser request, so both arrive
	 * from the customer's own IP. An allowlist of Central egress addresses
	 * alone will break dashboard connections and SSO; only use it when the
	 * callers are known (server-to-server traffic, a proxy, or office IPs).
	 *
	 * @since 2.0.7
	 *
	 * @return true|\WP_Error
	 */
	public function checkAllowedCaller() {
		/**
		 * Filters the list of client IPs allowed to trigger remote token validation.
		 *
		 * @since 2.0.7
		 *
		 * @param string[] $allowed Exact IP addresses. Empty means no restriction.
		 */
		$allowed = apply_filters( 'central_connect_auth_allowed_ips', array() );
		if ( ! is_array( $allowed ) || empty( $allowed ) ) {
			return true;
		}

		$ip = RateLimiter::clientIp();
		foreach ( $allowed as $candidate ) {
			if ( is_string( $candidate ) && '' !== $candidate && hash_equals( $candidate, $ip ) ) {
				return true;
			}
		}

		return new \WP_Error(
			'central_connect_caller_not_allowed',
			'This client is not allowed to authenticate remotely.',
			array( 'status' => 403 )
		);
	}

	/**
	 * Whether a presented environment token is plausibly a Central token.
	 *
	 * Printable, non-whitespace ASCII with a bounded length. The check is
	 * intentionally loose about format but strict about what cannot be a
	 * token, so arbitrary visitor payloads are not forwarded to Central.
	 *
	 * @since 2.0.7
	 *
	 * @param mixed $token Raw request value.
	 *
	 * @return bool
	 */
	public static function isWellFormedToken( $token ) {
		if ( ! is_string( $token ) || '' === $token || strlen( $token ) > self::MAX_TOKEN_LENGTH ) {
			return false;
		}

		return 1 === preg_match( '/^[\x21-\x7E]+$/', $token );
	}

	/**
	 * Whether a presented environment id is plausibly a Central environment id.
	 *
	 * @since 2.0.7
	 *
	 * @param mixed $environmentId Raw request value.
	 *
	 * @return bool
	 */
	public static function isWellFormedEnvironmentId( $environmentId ) {
		if ( is_int( $environmentId ) ) {
			$environmentId = (string) $environmentId;
		}

		if ( ! is_string( $environmentId ) || '' === $environmentId || strlen( $environmentId ) > self::MAX_ENVIRONMENT_ID_LENGTH ) {
			return false;
		}

		return 1 === preg_match( '/^[A-Za-z0-9._:-]+$/', $environmentId );
	}

	/**
	 * Validate WP Token.
	 *
	 * @since 2.0.0
	 *
	 * @param string $headerValue Header value.
	 * @return WP_User
	 */
	public function getAuthenticatedUser( $headerValue ) {
		$tokenValue = preg_replace( '/Bearer\s+/', '', $headerValue );

		return $this->getValidUser( $tokenValue );
	}

	/**
	 * Authenticate an access token presented by an unauthenticated caller.
	 *
	 * Wraps getValidUser() with a per-client failure limit. Each failed
	 * attempt costs a password-hash comparison per stored token for the
	 * targeted user, so repeated failures from one client are refused before
	 * the lookup instead of being slowed down with sleep(), which only tied
	 * up a PHP worker for the caller. A successful authentication clears the
	 * client's failure bucket.
	 *
	 * Used by both entry points that accept an access token from the
	 * network: the X-BGC-Auth REST header and the wp-login.php
	 * has_access_token flow.
	 *
	 * @since 2.0.7
	 *
	 * @param string $providedToken Access token (base64 "user:token"), without a Bearer prefix.
	 *
	 * @return \WP_User|\WP_Error The user, or a WP_Error with status 401 (invalid) or 429 (limited).
	 */
	public function authenticateAccessToken( $providedToken ) {
		$limits  = self::accessTokenFailureLimits();
		$limiter = new RateLimiter();
		$scope   = 'access_token_failures:ip:' . RateLimiter::clientIp();

		if ( $limiter->isLimited( $scope, $limits['limit'], $limits['window'] ) ) {
			return new \WP_Error(
				'central_connect_too_many_attempts',
				'Too many failed authentication attempts. Please try again later.',
				array(
					'status'      => 429,
					'retry_after' => $limiter->retryAfter( $scope, $limits['window'] ),
				)
			);
		}

		$user = $this->getValidUser( $providedToken );
		if ( $user instanceof \WP_User ) {
			/**
			 * Tokens are only issued to users who can manage options, but
			 * enforce the same requirement when a token is used so an older
			 * token for a demoted or non-admin account cannot be replayed.
			 */
			if ( ! user_can( $user, 'manage_options' ) ) {
				$limiter->attempt( $scope, $limits['limit'], $limits['window'] );

				return new \WP_Error(
					'restx_logged_out',
					'Sorry, your access token is invalid.',
					array( 'status' => 401 )
				);
			}

			$limiter->reset( $scope );
			return $user;
		}

		$limiter->attempt( $scope, $limits['limit'], $limits['window'] );

		return new \WP_Error(
			'restx_logged_out',
			'Sorry, your access token is invalid.',
			array( 'status' => 401 )
		);
	}

	/**
	 * Rate limit applied to failed access-token attempts per client.
	 *
	 * @since 2.0.7
	 *
	 * @return array {
	 *     @type int $limit  Failed attempts allowed per window.
	 *     @type int $window Window length in seconds.
	 * }
	 */
	public static function accessTokenFailureLimits() {
		$defaults = array(
			'limit'  => 20,
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
	 * Validate a given token for a user.
	 *
	 * @since 2.0.0
	 *
	 * @param string $providedToken Access Token from user.
	 *
	 * @return WP_User
	 */
	public function getValidUser( $providedToken ) {
		$tokenValue    = base64_decode( $providedToken );
		$tokenValue    = $tokenValue ? $tokenValue : '';
		$split         = explode( ':', $tokenValue );
		$userId        = ! empty( $split[0] ) ? $split[0] : null;
		$providedToken = ! empty( $split[1] ) ? $split[1] : null;

		$user = false;
		if ( $userId && $providedToken ) {

			// Get saved access tokens.
			$accessTokens = get_user_option( 'bgc_access_tokens', $userId );

			if ( is_array( $accessTokens ) ) {
				$matchedToken = $this->findMatchingToken( $accessTokens, $providedToken );

				// Validate expiration.
				$valid = ! empty( $matchedToken['expires_at'] ) && $matchedToken['expires_at'] > time();

				// Get user object.
				if ( $valid ) {
					$user = get_user_by( 'id', $userId );
				}
			}
		}

		return $user;
	}

	/**
	 * Remove old access tokens.
	 *
	 * @since 2.0.0
	 *
	 * @param array $accessTokens List of saved access tokens.
	 * @return array Valid tokens.
	 */
	private function cleanUpOldTokens( $accessTokens ) {
		$updatedAccessTokens = array();
		foreach ( $accessTokens as $accessToken ) {
			if ( ! empty( $accessToken['expires_at'] ) ) {

				// Token will remain for at least an hour.
				if ( ( $accessToken['expires_at'] + 3600 ) > time() ) {
					$updatedAccessTokens[] = $accessToken;
				}
			}
		}

		return $updatedAccessTokens;
	}

	/**
	 * Find a matching token.
	 *
	 * @since 2.0.0
	 *
	 * @param array  $accessTokens   List of access tokens for this user in the DB.
	 * @param string $providedToken Users Token.
	 * @return array.               Token args.
	 */
	private function findMatchingToken( $accessTokens, $providedToken ) {
		// Find matching value.
		$matchedTokenParams = array_filter(
			$accessTokens,
			function ( $token ) use ( $providedToken ) {
				$match = false;
				if ( ! empty( $token['access_token_hash'] ) ) {
					if ( wp_check_password( $providedToken, $token['access_token_hash'] ) ) {
						$match = true;
					}
				}

				return $match;
			}
		);

		return reset( $matchedTokenParams );
	}
}
