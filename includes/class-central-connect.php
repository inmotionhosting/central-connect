<?php
/**
 * File: class-central-connect.php
 *
 * @link       https://central.inmotionhosting.com
 * @since      1.0.0
 *
 * @package    Central_Connect
 * @subpackage Central_Connect/includes
 * @copyright  InMotionHosting.com
 * @version    $Id$
 * @author     InMotion Hosting <central-dev@inmotionhosting.com>
 */

 use Central\Connect;

/**
 * Class: Central_Connect
 *
 * @since      1.0.0
 * @package    Central_Connect
 * @subpackage Central_Connect/includes
 * @author     InMotion Hosting <central-dev@inmotionhosting.com>
 */
class Central_Connect {
	/**
	 * The loader that's responsible for maintaining and registering all hooks that power
	 * the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      Central_Connect_Loader    $loader    Maintains and registers all hooks for the plugin.
	 */
	protected $loader;

	/**
	 * Define the core functionality of the plugin.
	 *
	 * Set the plugin name and the plugin version that can be used throughout the plugin.
	 * Load the dependencies, define the locale, and set the hooks for the admin area and
	 * the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function __construct() {
		$this->load_dependencies();
		$this->define_admin_hooks();
		$this->load_analytics();
		$this->load_rest_api();
		$this->upgrade();
	}

	/**
	 * Load the required dependencies for this plugin.
	 *
	 * Include the following files that make up the plugin:
	 *
	 * - Boldgrid_Connect_Loader. Orchestrates the hooks of the plugin.
	 * - Boldgrid_Connect_Service. Handles service objects for the plugin.
	 * - BoldGrid_Connect_Config. Defines configuration properties of the plugin.
	 * - Boldgrid_Connect_Login. Facilitates use of a remote secure login token.
	 *
	 * Create an instance of the loader which will be used to register the hooks
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 *
	 * @see Boldgrid_Connect_Loader()
	 */
	private function load_dependencies() {
		require_once CENTRAL_CONNECT_PATH . 'autoload.php';

		/**
		 * The class responsible for orchestrating the actions and filters of the core plugin.
		 */
		require_once CENTRAL_CONNECT_PATH . 'includes/class-central-connect-loader.php';

		/**
		 * The class responsible for handling service objects.
		 */
		require_once CENTRAL_CONNECT_PATH . 'includes/class-central-connect-service.php';

		/**
		 * The class responsible for loading the configuration array as a service object.
		 */
		require_once CENTRAL_CONNECT_PATH . 'includes/class-central-connect-config.php';

		/**
		 * The class responsible for login via secure token.
		 */
		require_once CENTRAL_CONNECT_PATH . 'includes/class-central-connect-login.php';

		/**
		 * The class responsible for login via secure token.
		 */
		require_once CENTRAL_CONNECT_PATH . 'includes/class-central-connect-upgrade.php';

		$this->loader = new Central_Connect_Loader();
	}

	/**
	 * Load the rest API.
	 *
	 * @since 2.0.0
	 */
	private function load_rest_api() {
		$server = new Connect\Rest\Server();
		$server->initialize();
	}

	/**
	 * Count front end page views.
	 *
	 * @since 2.0.0
	 *
	 * @return void
	 */
	public function load_analytics() {
		$views = new Connect\Analytics\Views();
		$views->initialize();
	}

	/**
	 * Adds version upgrade specific functionality.
	 *
	 * @return void
	 */
	public function upgrade() {
		$upgrade = new Central_Connect_Upgrade();
		$this->loader->add_action( 'plugins_loaded', $upgrade, 'upgrade_db_check' );
	}

	/**
	 * Register all of the hooks related to the admin area functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_admin_hooks() {
		$this->overrideConfigs();

		$config = new Central_Connect_Config();
		$this->loader->add_action( 'init', $config, 'setup_configs' );

		$login = new Central_Connect_Login();
		$login->setup();

		$connectNotice = new Connect\View\Central\ConnectNotice();
		$connectNotice->initialize();

		$installedPlugins = new Connect\Plugin\Installed();
		$installedPlugins->initialize();

		$inspirations = new Connect\Integrations\Inspirations();
		$inspirations->initialize();
	}

	/**
	 * Override the configs.
	 *
	 * @since 2.0.0
	 */
	private function overrideConfigs() {
		$optionConfigs = get_option( 'bg_connect_configs', array() );
		$bg_lib_option = get_option( 'bglib_configs', array() );

		if ( ! empty( $optionConfigs['asset_server'] ) ) {
			$bg_lib_option['api'] = $optionConfigs['asset_server'];
			update_option( 'bglib_configs', $bg_lib_option );
		}

		add_filter(
			'BoldgridDemo/configs',
			function( $configs ) use ( $optionConfigs ) {
				$configs['servers']['asset'] = ! empty( $optionConfigs['asset_server'] ) ? $optionConfigs['asset_server'] : $configs['servers']['asset'];
				return $configs;
			}
		);

		// Inspirations.
		add_filter(
			'boldgrid_inspirations_configs',
			function ( $configs ) use ( $optionConfigs ) {
				$configs['asset_server'] = ! empty( $optionConfigs['asset_server'] ) ? $optionConfigs['asset_server'] : $configs['servers']['asset'];
				return $configs;
			}
		);

		// BoldGrid Library.
		add_filter(
			'Boldgrid\Library\Configs\set',
			function( $configs ) use ( $optionConfigs ) {
				// The timing of this filter doesn't always work as expected, so we should set the bglib_configs option as well.
				$configs['api']     = ! empty( $optionConfigs['asset_server'] ) ? $optionConfigs['asset_server'] : $configs['api'];
				return $configs;
			}
		);

		// BoldGrid Connect Plugin.
		add_filter(
			'boldgrid_connect_config_setup_configs',
			function( $configs ) use ( $optionConfigs ) {
				$configs['asset_server'] = ! empty( $optionConfigs['asset_server'] ) ? $optionConfigs['asset_server'] : $configs['asset_server'];
				$configs['central_url'] = ! empty( $optionConfigs['central_url'] ) ? $optionConfigs['central_url'] : $configs['central_url'];
				return $configs;
			}
		);

		/**
		 * Filters the bg_connect_configs option to merge option with static configs provided.
		 *
		 * @param mixed       $value   Value of the option.
		 * @param string      $option  Option name.
		 * @return false|mixed (Maybe) filtered option value.
		 */
		add_filter(
			'option_bg_connect_configs',
			function( $value, $option ) {
				$value = is_array( $value ) ? $value : array();
				$conf = \Central_Connect_Service::get( 'configs' );

				$confs = ! empty( $conf ) ? array_merge( $conf, $value ) : $value;

				// Brand being passed in from install loop through and update option with correct provider.
				if ( ! empty( $confs['brand'] ) && ! empty( $confs['branding'] ) ) {
					foreach ( $confs['branding'] as $brand => $opts ) {
						if ( strpos( strtolower( $brand ), $confs['brand'] ) !== false ) {
							update_site_option( 'boldgrid_connect_provider', $brand );
							// This option controls hiding various menu items from the plugins/library.
							update_site_option( 'boldgrid_connect_hide_menu', 'InMotion Hosting' === $brand );
						}
					}
				}

				return $confs;
			},
			10,
			2
		);
	}

	/**
	 * Run the loader to execute all of the hooks with WordPress.
	 *
	 * @since    1.0.0
	 */
	public function run() {
		$this->loader->run();
	}

	/**
	 * The reference to the class that orchestrates the hooks with the plugin.
	 *
	 * @since     1.0.0
	 * @return    Boldgrid_Connect_Loader    Orchestrates the hooks of the plugin.
	 */
	public function get_loader() {
		return $this->loader;
	}
}
