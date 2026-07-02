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

namespace Central\Connect\Option;

/**
 * Class: Router
 *
 * Setup the Router.
 *
 * @since 2.0.0
 */
class Router {

	/**
	 * Options that may be read, written, or deleted through the REST API.
	 *
	 * The endpoints expose generic option access, so the option name must be
	 * restricted to the BoldGrid/Central options the platform manages.
	 * Without this allowlist a caller could read or overwrite arbitrary core
	 * WordPress options (e.g. admin_email, siteurl, active_plugins) leading to
	 * full site takeover.
	 *
	 * @since 2.0.0
	 *
	 * @var string[]
	 */
	const ALLOWED_OPTIONS = array(
		'central_connect',
		'boldgrid_api_key',
		'boldgrid_api_url',
		'boldgrid_connect_provider',
		'boldgrid_connect_analytics',
		'boldgrid_connect_hide_menu',
		'bg_connect_configs',
		'bglib_configs',
	);

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
				$this->registerGet();
				$this->registerDelete();
				$this->registerUpdate();
			}
		);
	}

	/**
	 * Setup route to get options.
	 *
	 * @since 2.0.0
	 *
	 * @return void
	 */
	public function registerGet() {
		register_rest_route(
			'bgc/v1',
			'/options/',
			array(
				'methods' => 'GET',
				'callback' => function ( $request ) {
					$option = $request->get_param( 'name' );

					if ( ! $this->isAllowedOption( $option ) ) {
						return $this->optionNotAllowed();
					}

					$optionVal = get_option( $option, null );

					$response = new \WP_REST_Response(
						array(
							'data' => $optionVal,
						)
					);

					return $response;
				},
				'permission_callback' => array( $this, 'permissionCheck' ),
				'args' => array(
					'name' => array(
						'required' => true,
						'description' => 'Option name.',
						'type' => 'string',
					),
				),
			)
		);
	}

	/**
	 * Setup route to delete options.
	 *
	 * @since 2.0.0
	 *
	 * @return void
	 */
	public function registerDelete() {
		register_rest_route(
			'bgc/v1',
			'/options/',
			array(
				'methods' => 'DELETE',
				'callback' => function ( $request ) {
					$name = $request->get_param( 'name' );

					if ( ! $this->isAllowedOption( $name ) ) {
						return $this->optionNotAllowed();
					}

					delete_option( $name );

					$response = new \WP_REST_Response( array() );
					$response->set_status( 204 );

					return $response;
				},
				'permission_callback' => array( $this, 'permissionCheck' ),
				'args' => array(
					'name' => array(
						'required' => true,
						'description' => 'Option name.',
						'type' => 'string',
					),
				),
			)
		);
	}

	/**
	 * Setup route to update options.
	 *
	 * @since 2.0.0
	 *
	 * @return void
	 */
	public function registerUpdate() {
		register_rest_route(
			'bgc/v1',
			'/options/',
			array(
				'methods' => 'POST',
				'callback' => function ( $request ) {
					$option = $request->get_param( 'name' );
					$newValue = $request->get_param( 'value' );

					if ( ! $this->isAllowedOption( $option ) ) {
						return $this->optionNotAllowed();
					}

					update_option( $option, $newValue );

					if ( 'boldgrid_api_key' === $option ) {
						delete_transient( 'boldgrid_api_data' );
						delete_site_transient( 'boldgrid_api_data' );
					}

					$response = new \WP_REST_Response(
						array(
							'data' => get_option( $option, null ),
						)
					);

					return $response;
				},
				'permission_callback' => array( $this, 'permissionCheck' ),
				'args' => array(
					'name' => array(
						'required' => true,
						'description' => 'Option name.',
						'type' => 'string',
					),
					'value' => array(
						'required' => true,
						'description' => 'Option Value.',
					),
				),
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
	public function permissionCheck() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Determine whether an option name may be accessed through the API.
	 *
	 * @since 2.0.0
	 *
	 * @param mixed $name Option name from the request.
	 * @return boolean Whether the option is allowed.
	 */
	private function isAllowedOption( $name ) {
		return is_string( $name ) && in_array( $name, self::ALLOWED_OPTIONS, true );
	}

	/**
	 * Build the response returned when an option is not in the allowlist.
	 *
	 * @since 2.0.0
	 *
	 * @return \WP_Error
	 */
	private function optionNotAllowed() {
		return new \WP_Error(
			'rest_forbidden_option',
			'You are not allowed to access this option.',
			array( 'status' => 403 )
		);
	}
}
