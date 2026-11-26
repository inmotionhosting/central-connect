<?php
register_rest_route(
	'bgc/v1',
	'/example/',
	array(
		'methods'  => 'GET',
		'callback' => 'example_callback',
	)
);
