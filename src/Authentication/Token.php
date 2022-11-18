<?php
/**
 * File: Central.php
 *
 * Setup Access Token authentication method.
 *
 * @since      2.0.0
 * @package    BoldGrid\Connect\Rest
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
class Token {

	/**
	 * Save an access token for the user.
	 *
	 * @since 2.0.0
	 *
	 * @param WP_User $user WP User to create an access token for.
	 * @return array Access token.
	 */
	public function create( $user, $expires = '+2 hours' ) {
		$accessTokens = get_user_option( 'bgc_access_tokens', $user->ID );
		$accessTokens = is_array( $accessTokens ) ? $accessTokens : array();
		$accessTokens = $this->cleanUpOldTokens( $accessTokens );

		$rawToken = wp_generate_password( 128 );

		$newAccessToken = array(
			'site_url' => get_site_url(),
			'login_url' => wp_login_url(),
			'wp_user_id' => $user->ID,
			'access_token_hash' => wp_hash_password( $rawToken ),
			'expires_at' => strtotime( $expires ),
			'issued_at' => time(),
		);

		$accessTokens[] = $newAccessToken;

		update_user_option( $user->ID, 'bgc_access_tokens', $accessTokens );

		unset( $newAccessToken['access_token_hash'] );
		$newAccessToken['access_token'] = base64_encode( $user->ID . ':' . $rawToken );

		return $newAccessToken;
	}

	/**
	 * Is this the presented token formatted as a BG Token?
	 *
	 * @since 2.0.0
	 *
	 * @param string $environmentId Environment ID.
	 */
	public function isBGToken( $providedToken ) {
		$providedToken = preg_replace( '/Bearer\s+/', '', $providedToken );
		$tokenValue = base64_decode( $providedToken );
		$tokenValue = $tokenValue ? $tokenValue : '';
		$split = explode( ':', $tokenValue );
		$userId = ! empty( $split[0] ) ? $split[0] : null;
		$providedToken = ! empty( $split[1] ) ? $split[1] : null;
		return is_numeric( $userId ) && $userId && $providedToken;
	}

	/**
	 * Validate a Central token with the BoldGrid API.
	 *
	 * @since X.X.X
	 *
	 * @param string $token Api
	 * @param string $environmentId Api
	 * @return boolean      Is the token valid?
	 */
	public function remoteValidate( $token, $environmentId ) {
		do_action( 'bgc_remote_validate' );

		$configs = \Central_Connect_Service::get( 'configs' );
		$url     = $configs['asset_server'] . $configs['ajax_calls']['verify_env_token'];
		$args    = array(
			'body'    => array(
				'token' => $token,
				'environment_id' => $environmentId,
				'key' => get_option( 'boldgrid_api_key' ),
			),
			'timeout' => 15,
		);

		$response = wp_remote_get( $url, $args );
		$httpCode = wp_remote_retrieve_response_code( $response );
		$body     = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 200 === $httpCode && ! empty( $body['is_valid'] ) ) {
			return true;
		} else {
			sleep( 3 );
			return false;
		}
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
	 * Validate a given token for a user.
	 *
	 * @since 2.0.0
	 *
	 * @param string $providedToken Access Token from user.
	 * @param int    $userId        User Id to auth as.
	 * @return WP_User
	 */
	public function getValidUser( $providedToken ) {
		$tokenValue = base64_decode( $providedToken );
		$tokenValue = $tokenValue ? $tokenValue : '';
		$split = explode( ':', $tokenValue );
		$userId = ! empty( $split[0] ) ? $split[0] : null;
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
