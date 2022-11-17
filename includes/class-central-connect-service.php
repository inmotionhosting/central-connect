<?php
/**
 * File: class-boldgrid-connect-service.php
 *
 * Handle services.
 *
 * @link       https://www.boldgrid.com
 * @since      1.0.0
 *
 * @package    Boldgrid_Connect
 * @subpackage Boldgrid_Connect/includes
 * @copyright  BoldGrid.com
 * @version    $Id$
 * @author     BoldGrid <support@boldgrid.com>
 */

/**
 * Class: Boldgrid_Connect_Service
 *
 * Handle services.
 *
 * @since      1.0.0
 */
abstract class Central_Connect_Service {
	/**
	 * Array of service objects.
	 *
	 * @since  1.0.0
	 * @access protected
	 *
	 * @var    array
	 */
	protected static $services;

	/**
	 * Register a service.
	 *
	 * Stores instance into an array.
	 *
	 * @since  1.0.0
	 *
	 * @param  string $name      Name of service.
	 * @param  mixed  $instance  Instance of service.
	 */
	public static function register( $name, $instance ) {
		self::$services[ $name ] = $instance;
	}

	/**
	 * Get a service by name.
	 *
	 * @since  1.0.0
	 *
	 * @param  string $name Name of Service.
	 * @return mixed        Service Instance.
	 */
	public static function get( $name ) {
		return self::$services[ $name ];
	}
}
