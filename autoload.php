<?php
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
