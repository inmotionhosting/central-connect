<?php
function example_nonce() {
	if ( empty( $_POST['example_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['example_nonce'] ) ), 'example' ) ) {
		wp_die( esc_html__( 'Invalid nonce.', 'central-connect' ) );
	}

	if ( ! defined( 'WPINC' ) ) {
		die;
	}

	wp_safe_redirect( admin_url() );
	exit;
}
