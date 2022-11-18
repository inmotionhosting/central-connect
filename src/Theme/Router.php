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

namespace Central\Connect\Theme;

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
	protected $themeInstaller;

	/**
	 * Register routes.
	 *
	 * @since 2.0.0
	 *
	 * @return void
	 */
	public function register() {
		$this->themeInstaller = new Installer();

		add_action(
			'rest_api_init',
			function () {
				$this->registerSwitchTheme();
				$this->registerInstall();
				$this->registerRemove();
				$this->registerList();
			}
		);
	}

	/**
	 * Setup route to activate a theme.
	 *
	 * @since 2.0.0
	 *
	 * @return void
	 */
	public function registerSwitchTheme() {
		register_rest_route(
			'bgc/v1',
			'/themes/',
			array(
				'methods' => 'PATCH',
				'callback' => function ( $request ) {
					$response = array( 'error' => 'Unable to switch theme' );
					$status = 400;
					$stylesheet = $request->get_param( 'stylesheet' ) ?: '';
					switch_theme( $stylesheet );

					$currentTheme = wp_get_theme();
					if ( $stylesheet === get_stylesheet() ) {
						$response = $this->themeInstaller->formatThemeResource( $currentTheme );
						$status = 200;
					}

					$response = new \WP_REST_Response( $response );
					$response->set_status( $status );

					return $response;
				},
				'permission_callback' => array( $this, 'permissionCheck' ),
				'args' => array(
					'stylesheet' => array(
						'required' => true,
						'description' => 'Theme stylesheet name',
						'type' => 'string',
						'validate_callback' => array( $this, 'validateThemeExists' ),
						'sanitize_callback' => 'sanitize_title',
					),
				),
			)
		);
	}

	/**
	 * Setup route to install a theme.
	 *
	 * @since 2.0.0
	 *
	 * @return void
	 */
	private function registerInstall() {
		register_rest_route(
			'bgc/v1',
			'/themes/',
			array(
				'methods' => 'POST',
				'callback' => function ( $request ) {
					$themes = $request->get_param( 'themes' );
					$activeStylesheet = $request->get_param( 'active_stylesheet' );

					foreach ( $themes as $theme ) {
						$this->themeInstaller->install( $theme );
					}

					if ( $activeStylesheet ) {
						switch_theme( $activeStylesheet );
					}

					$response = $this->themeInstaller->getCollection();
					$response = new \WP_REST_Response( $response );
					$response->set_status( 201 );

					return $response;
				},
				'permission_callback' => array( $this, 'permissionCheck' ),
				'args' => array(
					'active_stylesheet' => array(
						'type' => 'string',
						'description' => 'Name of a stylesheet to activate after install.',
						'sanitize_callback' => 'sanitize_title',
					),
					'themes' => array(
						'required' => true,
						'type' => 'array',
						'description' => 'List of theeme zips and stylesheets to install.',
						'items' => array(
							'type' => 'string',
						),
						'sanitize_callback' => function ( $assets ) {
							$cleanAssets = array();
							foreach ( $assets as $asset ) {
								$wpOrgUrl = 'https://downloads.wordpress.org/theme/' . $asset . '.latest-stable.zip';
								if ( wp_http_validate_url( $asset ) ) {
									$cleanAssets[] = $asset;
								} else if ( wp_http_validate_url( $wpOrgUrl ) ) {
									$cleanAssets[] = $wpOrgUrl;
								}
							}

							return $cleanAssets;
						},
					),
				),
			)
		);
	}

	/**
	 * Setup route to remove plugins.
	 *
	 * @since 2.0.0
	 *
	 * @return void
	 */
	private function registerRemove() {
		register_rest_route(
			'bgc/v1',
			'/themes/',
			array(
				'methods' => 'DELETE',
				'callback' => function ( $request ) {
					$stylesheets = $request->get_param( 'stylesheets' );

					$this->themeInstaller->delete( $stylesheets );

					// @TODO --- Revert to default theme if active theme is deleted.

					$updatedList = $this->themeInstaller->getCollection();

					$response = new \WP_REST_Response( $updatedList );

					return $response;
				},
				'permission_callback' => array( $this, 'permissionCheck' ),
				'args' => array(
					'stylesheets' => array(
						'required' => true,
						'description' => 'A list of theme file stylesheets',
						'type' => 'array',
						'validate_callback' => function ( $stylesheets ) {
							$valid = true;
							foreach ( $stylesheets as $stylesheet ) {
								if ( ! $this->validateThemeExists( $stylesheet ) ) {
									$valid = false;
									break;
								}
							}

							return $valid;
						},
						'sanitize_callback' => function ( $stylesheets ) {
							foreach ( $stylesheets as &$stylesheet ) {
								$stylesheet = sanitize_title( $stylesheet );
							}

							return $stylesheets;
						},
						'items' => array(
							'type' => 'string',
						),
					),
				),
			)
		);
	}

	/**
	 * Setup route to list user themes.
	 *
	 * @since 2.0.0
	 *
	 * @return void
	 */
	private function registerList() {
		register_rest_route(
			'bgc/v1',
			'/themes/',
			array(
				'methods' => 'GET',
				'callback' => function () {
					$response = $this->themeInstaller->getCollection();

					$response = new \WP_REST_Response( $response );

					return $response;
				},
				'permission_callback' => array( $this, 'permissionCheck' ),
			)
		);
	}

	/**
	 * Make sure the given theme stylesheet exists.
	 *
	 * @since 2.0.0
	 *
	 * @param array $files
	 * @return boolean     Do all the files exist.
	 */
	public function validateThemeExists( $stylesheet ) {
		return wp_get_theme( $stylesheet )->exists();
	}

	/**
	 * Make sure current can activate plugins for all Plugin api calls.
	 *
	 * @since 2.0.0
	 *
	 * @return boolean Has Access?
	 */
	public function permissionCheck() {
		return current_user_can( 'switch_themes' );
	}
}
