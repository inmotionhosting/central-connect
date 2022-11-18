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
				'callback' => function ( $request ) {

					// Reach out to our API servers to validate their token.
					$environmentId = $request->get_param( 'environment_id' );
					$tokenVal = $request->get_param( 'token' );
					$token = new Token();
					if ( ! $token->remoteValidate( $tokenVal, $environmentId ) ) {
						return new \WP_Error(
							'restx_logged_out',
							'Sorry, your remote access token is not valid.',
							array( 'status' => 403 )
						);
					}

					Option\Connect::update( 'environment_id', $environmentId );

					// If the remote token is valid assign a local token.
					$userId = $request->get_param( 'user_id' );

					// Find the requested user, or default.
					$user = $this->selectUser( $userId );

					if ( $user !== false && user_can( $userId, 'manage_options' ) ) {
						$response = new \WP_REST_Response(
							array(
								'errors' => array(
									'name' => 'user_not_qualified',
									'message' => 'Please try again with a WordPress administrator account.',
								),
							)
						);

						$response->set_status( 400 );
					}

					// User found, create token and return to view.
					if ( $user ) {

						$tokenHelper = new Token();
						$accessToken = $tokenHelper->create( $user );
						$response = new \WP_REST_Response( $accessToken );

						// User not found.
					} else {
						$response = new \WP_REST_Response(
							array(
								'errors' => array(
									'name' => 'user_not_found',
									'message' => 'Unable to find a user to authenticate as.',
								),
							)
						);

						$response->set_status( 400 );
					}

					return $response;
				},
				'args' => array(
					'token' => array(
						'required' => true,
						'type' => 'string',
						'description' => 'BoldGrid Authentication token',
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
	 * Find a user to log in as.
	 *
	 * @since 2.0.0
	 *
	 * @param string $userId User Id.
	 * @return WP_User User to login as.
	 */
	private function selectUser( $userId ) {
		$user = false;

		if ( $userId ) {
			$user = get_user_by( 'id', $userId );
		}

		if ( empty( $user ) ) {
			$login = new \Central_Connect_Login();
			$user = $login->get_user();
		}

		return $user;
	}
}
