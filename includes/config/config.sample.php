<?php
/**
 * File: config.sample.php
 *
 * Plugin sample configuration file.
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

// Prevent direct calls.
if ( ! defined( 'WPINC' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	exit();
}

/**
 * Copy this sample file to config.local.php and update it with any variables that you would like to override.
 */
return array(
	'asset_server' => 'https://api-dev.boldgrid.com',
);
