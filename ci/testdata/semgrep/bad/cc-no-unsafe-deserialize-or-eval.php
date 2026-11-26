<?php
function example_unserialize( $request ) {
	return unserialize( $request->get_param( 'payload' ) );
}

function example_extract() {
	extract( $_POST );
}
