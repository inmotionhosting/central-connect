<?php
/**
 * PHPUnit bootstrap for Central Connect.
 *
 * Boots the WordPress test library (WP_TESTS_DIR) and loads the plugin as a
 * must-use plugin so its hooks are registered before the test suite runs.
 *
 * @package Central_Connect
 */

$_tests_dir = getenv( 'WP_TESTS_DIR' );

if ( ! $_tests_dir ) {
	$_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
}

if ( ! file_exists( $_tests_dir . '/includes/functions.php' ) ) {
	echo "Could not find {$_tests_dir}/includes/functions.php. Set WP_TESTS_DIR to the WordPress test library." . PHP_EOL; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	exit( 1 );
}

require_once $_tests_dir . '/includes/functions.php';

/**
 * Load the plugin under test.
 *
 * @return void
 */
function _central_connect_tests_load_plugin() {
	require dirname( dirname( __DIR__ ) ) . '/central-connect.php';
}
tests_add_filter( 'muplugins_loaded', '_central_connect_tests_load_plugin' );

require $_tests_dir . '/includes/bootstrap.php';

require_once __DIR__ . '/class-central-connect-test-case.php';
