<?php
function bg_connect_autoload ($pClassName) {
	if ( false === strpos( $pClassName, 'BoldGrid\\Connect' ) ) {
		return;
	}
	$updatedClass = str_replace( 'BoldGrid\Connect\\', '', $pClassName );
	$path = __DIR__ . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . $updatedClass . '.php';
	$path = str_replace( '\\', '/', $path );
	if ( file_exists( $path ) && $pClassName !== $updatedClass ) {
		include( $path );
		return;
	}
}
spl_autoload_register( 'bg_connect_autoload' );