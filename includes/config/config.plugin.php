<?php
/**
 * File: config.plugin.php
 *
 * Plugin configuration file.
 *
 * @link       https://www.inmotionhosting.com
 * @since      1.0.0
 *
 * @package    Central_Connect
 * @subpackage Central_Connect/admin
 * @copyright  InMotionHosting.com
 * @version    $Id$
 * @author     InMotion Hosting <central-dev@inmotionhosting.com>
 */

if ( ! defined( 'WPINC' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	exit();
}

return array(
	'ajax_calls'            => array(
		'get_plugin_version' => '/api/open/get-plugin-version',
		'get_asset'          => '/api/open/get-asset',
		'verify_site_token'  => '/v1/connect-keys/sites/token',
		'verify_env_token'   => '/v1/environments/auth',
		'validate_env'       => '/v1/register-environment',
	),
	'asset_server'          => 'https://api.boldgrid.com',
	'central_url'           => 'https://www.boldgrid.com/central',
	'plugin_name'           => 'boldgrid-connect',
	'plugin_key_code'       => 'connect',
	'main_file_path'        => CENTRAL_CONNECT_PATH . 'boldgrid-connect.php',
	'plugin_transient_name' => 'boldgrid_connect_version_data',

	'branding' => array(
		'InMotion Hosting' => array(
			'primaryColor' => '#c32227',
			'productName'  => 'InMotion Central',
			'tos'          => 'https://www.inmotionhosting.com/legal/',
			'privacy'      => 'https://www.inmotionhosting.com/privacy/',
			'logo'         => '/assets/img/imh.svg',
			'central_url'  => 'https://central.inmotionhosting.com/wordpress',
			'providerUrl'  => 'https://www.inmotionhosting.com',
		),
		'BoldGrid' => array(
			'primaryColor' => '#f95b26',
			'productName'  => 'BoldGrid Central',
			'tos'          => 'https://www.boldgrid.com/terms-of-service/',
			'privacy'      => 'https://www.boldgrid.com/software-privacy-policy/',
			'logo'         => '/assets/img/boldgrid-logo-vertical-black.svg',
			'central_url'  => 'https://www.boldgrid.com/central',
			'providerUrl'  => 'https://www.boldgrid.com',
		),
	),
);
