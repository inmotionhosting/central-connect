<?php
register_rest_route(
	'bgc/v1',
	'/example/',
	array(
		'methods'             => 'GET',
		'callback'            => 'example_callback',
		'permission_callback' => array( $this, 'permissionCheck' ),
	)
);
register_rest_route(
	'bgc/v1',
	'/example-cap/',
	array(
		'methods'             => 'POST',
		'callback'            => 'example_callback',
		'permission_callback' => function () {
			return current_user_can( 'manage_options' );
		},
	)
);
