<?php
function example_login( $user_id ) {
	wp_set_auth_cookie( $user_id, true );
}
