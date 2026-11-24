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

namespace Central\Connect\Authentication;

use Central\Connect\Option;

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
		add_action(
			'rest_api_init',
			function () {
				$this->registerAuthenticate();
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
	private function registerAuthenticate() {
		register_rest_route(
			'bgc/v1',
			'/auth/',
			array(
				'methods' => 'POST',
				/**
				 * This route is reachable without a WordPress session on purpose:
				 * Central presents an environment token here to obtain a local
				 * access token. Authorization is enforced inside the callback by
				 * Token::validateEnvironmentToken(), which decides everything it
				 * can locally (connected site, well-formed input, rate limits)
				 * before relaying the token to Central for confirmation.
				 */
				'permission_callback' => '__return_true',
				'callback' => function ( $request ) {
					$environmentId = $request->get_param( 'environment_id' );
					$tokenVal      = $request->get_param( 'token' );

					$token  = new Token();
					$result = $token->validateEnvironmentToken( $tokenVal, $environmentId );
					if ( is_wp_error( $result ) ) {
						return $result;
					}

					Option\Connect::update( 'environment_id', (string) $environmentId );

					// If the remote token is valid assign a local token.
					$user = $this->selectUser( $request->get_param( 'user_id' ) );
					if ( is_wp_error( $user ) ) {
						return new \WP_REST_Response(
							array(
								'errors' => array(
									'name' => $user->get_error_code(),
									'message' => $user->get_error_message(),
								),
							),
							400
						);
					}

					$tokenHelper = new Token();
					$accessToken = $tokenHelper->create( $user );

					return new \WP_REST_Response( $accessToken );
				},
				'args' => array(
					'token' => array(
						'required' => true,
						'type' => 'string',
						'description' => 'Central Authentication token',
					),
					'environment_id' => array(
						'required' => true,
						'type' => 'string',
						'description' => 'Central Environment ID the token was issued for',
					),
					'user_id' => array(
						'required' => false,
						'type' => 'string',
						'description' => 'User ID to authenticate as',
					),
				),
			)
		);
	}

	/**
	 * Find a user to issue an access token for.
	 *
	 * Every bgc/v1 route requires administrator-level capabilities
	 * (activate_plugins, switch_themes, manage_options), so the token is
	 * only useful - and only issued - for a user who can manage_options.
	 *
	 * When Central names a user it must exist; there is no silent fallback
	 * to the default administrator for an unknown id. When no user is named
	 * the site's administrator account is used, which is the trust model of
	 * the product: a Central-confirmed environment token acts as the site
	 * administrator.
	 *
	 * @since 2.0.0
	 *
	 * @param string|null $userId User Id from the request, if any.
	 * @return \WP_User|\WP_Error User to issue a token for, or an error whose
	 *                            code/message are returned to the caller.
	 */
	private function selectUser( $userId ) {
		$user = false;

		if ( null !== $userId && '' !== $userId ) {
			if ( is_numeric( $userId ) && (int) $userId > 0 ) {
				$user = get_user_by( 'id', (int) $userId );
			}
		} else {
			$login = new \Central_Connect_Login();
			$user  = $login->get_user();
		}

		if ( ! $user instanceof \WP_User ) {
			return new \WP_Error( 'user_not_found', 'Unable to find a user to authenticate as.' );
		}

		if ( ! user_can( $user, 'manage_options' ) ) {
			return new \WP_Error( 'user_not_qualified', 'Please try again with a WordPress administrator account.' );
		}

		return $user;
	}
}
