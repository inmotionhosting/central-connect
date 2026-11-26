<?php
function example_missing_nonce_bypass() {
	if ( isset( $_POST['example_nonce'] ) && ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['example_nonce'] ) ), 'example' ) ) {
		wp_die( 'Invalid nonce.' );
	}
}

function example_inverted_nonce() {
	if ( wp_verify_nonce( sanitize_key( wp_unslash( $_POST['example_nonce'] ?? '' ) ), 'example' ) ) {
		wp_die( 'Invalid nonce.' );
	}
}
