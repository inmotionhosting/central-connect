<?php
/**
 * File: class-boldgrid-connect-login.php
 *
 * Login support functionality.
 *
 * @link       https://www.boldgrid.com
 * @since      1.0.0
 *
 * @package    Boldgrid_Connect
 * @subpackage Boldgrid_Connect/includes
 * @copyright  BoldGrid.com
 * @version    $Id$
 * @author     InMotion Hosting <central-dev@inmotionhosting.com>
 */

/**
 * Class: Boldgrid_Connect_Login
 *
 * Login support functionality.
 *
 * @since      1.0.0
 * @package    Boldgrid_Connect
 * @author     InMotion Hosting <central-dev@inmotionhosting.com>
 *
 * phpcs:disable WordPress.VIP
 */
class Central_Connect_Login {
	/**
	 * Run hooks for login support.
	 *
	 * @since 1.0.0
	 *
	 * @see Boldgrid_Connect_Login::authenticate()
	 *
	 * @global $pagenow The current page/script filename, according to WordPress.
	 */
	public function setup() {
		$login = $this;

		add_action(
			'init',
			function () use ( $login ) {
				global $pagenow;

				if ( 'wp-login.php' === $pagenow && get_option( 'boldgrid_api_key' ) ) {
					$login->authenticate();
				}
			},
			20
		);
	}

	/**
	 * Get the user to login.
	 *
	 * @since 1.0.0
	 *
	 * @see get_users()
	 * @see is_wp_error()
	 *
	 * @return WP_User|false
	 */
	public function get_user() {
		$args = array(
			'role'   => 'administrator',
			'order'  => 'DESC',
			'number' => 2,
		);

		$users = get_users( $args );

		return ! empty( $users ) && ! is_wp_error( $users ) ? $users[0] : false;
	}

	/**
	 * Login to the users WordPress based on remote authentication.
	 *
	 * @since 1.0.0
	 *
	 * @see user_admin_url()
	 * @see is_user_logged_in()
	 * @see wp_safe_redirect()
	 * @see Boldgrid_Connect_Login::get_user()
	 * @see Boldgrid_Connect_Login::remote_validate()
	 * @see Boldgrid_Connect_Login::login()
	 * @see wp_redirect()
	 */
	public function authenticate() {
		// Authentication parameters.
		$token        = ! empty( $_REQUEST['token'] ) ? sanitize_text_field( $_REQUEST['token'] ) : null; // phpcs:ignore WordPress.CSRF.NonceVerification.NoNonceVerification
		$redirect_url = ! empty( $_REQUEST['redirect_url'] ) ? $_REQUEST['redirect_url'] : user_admin_url(); // phpcs:ignore WordPress.CSRF.NonceVerification.NoNonceVerification

		if ( is_user_logged_in() && $token ) {
			wp_safe_redirect( $redirect_url );
			exit;
		}

		$valid = false;

		if ( $token ) {
			$user = $this->get_user();
			if ( ! empty( $_REQUEST['environment_id'] ) ) {
				$environment_id = sanitize_text_field( $_REQUEST['environment_id'] );
				$tokenValidator = new \BoldGrid\Connect\Authentication\Token();
				if ( ! empty( $_REQUEST['has_access_token'] ) ) {
					$user = $tokenValidator->getValidUser( $token );
					$valid = $user && ! empty( $user->ID );

					// Validate an environment auth code remotely.
				} else {
					$valid = $user && $tokenValidator->remoteValidate( $token, $environment_id );
				}

				// Verify the site token.
			} else {
				$valid = $user && $this->remote_validate( $token );
			}

			// Log the user in.
			if ( $valid ) {
				$this->login( $user, $redirect_url );
			}
		}

		if ( ! $valid && $token ) {
			wp_redirect( 'wp-login.php' );
			exit;
		}
	}

	/**
	 * Log the user in.
	 *
	 * @since 1.0.0
	 *
	 * @see wp_clear_auth_cookie()
	 * @see wp_set_current_user()
	 * @see wp_set_auth_cookie()
	 * @see wp_safe_redirect()
	 *
	 * @param Wp_User $user         WordPress user object.
	 * @param string  $redirect_url URL address for redirection.
	 */
	protected function login( $user, $redirect_url ) {
		wp_clear_auth_cookie();
		wp_set_current_user( $user->ID );
		wp_set_auth_cookie( $user->ID, true );
		wp_safe_redirect( $redirect_url );
		exit;
	}

	/**
	 * Make API call to validate.
	 *
	 * @since 1.0.0
	 *
	 * @see Boldgrid_Connect_Service::get()
	 * @see wp_remote_post()
	 * @see wp_remote_retrieve_response_code()
	 * @see wp_remote_retrieve_body()
	 *
	 * @param  string $token Login token.
	 * @return bool
	 */
	public function remote_validate( $token ) {
		do_action( 'bgc_remote_validate' );

		$configs   = Boldgrid_Connect_Service::get( 'configs' );
		$url       = $configs['asset_server'] . $configs['ajax_calls']['verify_site_token'];
		$args      = array(
			'method'  => 'POST',
			'body'    => array(
				'key'   => get_option( 'boldgrid_api_key' ),
				'token' => $token,
			),
			'timeout' => 10,
		);

		$response  = wp_remote_post( $url, $args );
		$http_code = wp_remote_retrieve_response_code( $response );
		$body      = json_decode( wp_remote_retrieve_body( $response ), true );

		return 200 === $http_code && ! empty( $body['validated'] );
	}
}
