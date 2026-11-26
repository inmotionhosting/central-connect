<?php
wp_remote_post(
	'https://central.example.com/api',
	array(
		'timeout'   => 10,
		'sslverify' => true,
	)
);
$data = json_decode( wp_remote_retrieve_body( $response ), true );
