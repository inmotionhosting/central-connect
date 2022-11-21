<?php
/**
 * File: autoload.php
 *
 * @link       https://central.inmotionhosting.com
 * @since      1.0.0
 *
 * @package    Central_Connect
 * @copyright  InMotionHosting.com
 * @version    $Id$
 * @author     InMotion Hosting <central-dev@inmotionhosting.com>
 */

/**
 * Responsible for class autoloading in plugin.
 *
 * @param string $pClassName Classname to load.
 *
 * @since 1.0.0
 */
function central_connect_autoload( $pClassName ) {
	if ( false === strpos( $pClassName, 'Central\\Connect' ) ) {
		return;
	}
	$updatedClass = str_replace( 'Central\Connect\\', '', $pClassName );
	$path = __DIR__ . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . $updatedClass . '.php';
	$path = str_replace( '\\', '/', $path );
	if ( file_exists( $path ) && $pClassName !== $updatedClass ) {
		include( $path );
		return;
	}
}
spl_autoload_register( 'central_connect_autoload' );
