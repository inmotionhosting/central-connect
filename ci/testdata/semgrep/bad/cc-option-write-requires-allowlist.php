<?php
function example_update( $request ) {
	$option = $request->get_param( 'name' );
	update_option( $option, $request->get_param( 'value' ) );
}

function example_delete( $request ) {
	$name = $request['name'];
	delete_option( $name );
}
