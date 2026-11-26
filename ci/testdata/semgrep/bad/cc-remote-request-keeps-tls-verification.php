<?php
wp_remote_post(
	'https://central.example.com/api',
	array(
		'timeout'   => 10,
		'sslverify' => false,
	)
);
