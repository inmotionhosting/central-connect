<?php
register_rest_route(
	'bgc/v1',
	'/example/',
	array(
		'methods'             => 'POST',
		'callback'            => 'example_callback',
		'permission_callback' => '__return_true',
	)
);
register_rest_route(
	'bgc/v1',
	'/example-closure/',
	array(
		'methods'             => 'POST',
		'callback'            => 'example_callback',
		'permission_callback' => function () {
			return true;
		},
	)
);
