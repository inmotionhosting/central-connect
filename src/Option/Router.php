<?php
/**
* File: Router.php
*
* Setup the Router.
*
* @since      2.0.0
* @package    BoldGrid\Connect\Rest
* @author     BoldGrid <support@boldgrid.com>
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
	 * Register routes.
	 *
	 * @since 2.0.0
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'rest_api_init', function () {
			$this->registerGet();
			$this->registerDelete();
			$this->registerUpdate();
		} );
	}

	/**
	 * Setup route to get options.
	 *
	 * @since 2.0.0
	 *
	 * @return void
	 */
	public function registerGet() {
		register_rest_route( 'bgc/v1', '/options/', [
			'methods' => 'GET',
			'callback' => function ( $request ) {
				$option = $request->get_param( 'name' );
				$optionVal = get_option( $option, null );

				$response = new \WP_REST_Response( [
					'data' => $optionVal
				] );

				return $response;
			},
			'permission_callback' => [ $this, 'permissionCheck' ],
			'args' => [
				'name' => [
					'required' => true,
					'description' => 'Option name.',
					'type' => 'string',
				],
			]
		] );
	}

	/**
	 * Setup route to delete options.
	 *
	 * @since 2.0.0
	 *
	 * @return void
	 */
	public function registerDelete() {
		register_rest_route( 'bgc/v1', '/options/', [
			'methods' => 'DELETE',
			'callback' => function ( $request ) {
				$name = $request->get_param( 'name' );
				delete_option( $name );

				$response = new \WP_REST_Response( [] );
				$response->set_status( 204 );

				return $response;
			},
			'permission_callback' => [ $this, 'permissionCheck' ],
			'args' => [
				'name' => [
					'required' => true,
					'description' => 'Option name.',
					'type' => 'string',
				],
			]
		] );
	}

	/**
	 * Setup route to update options.
	 *
	 * @since 2.0.0
	 *
	 * @return void
	 */
	public function registerUpdate() {
		register_rest_route( 'bgc/v1', '/options/', [
			'methods' => 'POST',
			'callback' => function ( $request ) {
				$option = $request->get_param( 'name' );
				$newValue = $request->get_param( 'value' );

				update_option( $option, $newValue );

				if ( 'boldgrid_api_key' === $option ) {
					delete_transient( 'boldgrid_api_data' );
					delete_site_transient( 'boldgrid_api_data' );
				}

				$response = new \WP_REST_Response( [
					'data' => get_option( $option, null )
				] );

				return $response;
			},
			'permission_callback' => [ $this, 'permissionCheck' ],
			'args' => [
				'name' => [
					'required' => true,
					'description' => 'Option name.',
					'type' => 'string',
				],
				'value' => [
					'required' => true,
					'description' => 'Option Value.',
				],
			]
		] );
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
}
