<?php
function example_update( $request ) {
	$option = $request->get_param( 'name' );
	if ( ! $this->isAllowedOption( $option ) ) {
		return $this->optionNotAllowed();
	}
	update_option( $option, $request->get_param( 'value' ) );
}
