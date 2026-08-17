<?php
/**
 * PHP CLI runner for Central Connect REST CORS tests.
 *
 * Usage (from the repository root):
 *   php tests/run.php
 *
 * @package Central_Connect
 */

require_once __DIR__ . '/bootstrap.php';

$test   = new RestCorsTest();
$methods = get_class_methods( $test );
$passed  = 0;
$failed  = 0;
$failures = array();

foreach ( $methods as $method ) {
	if ( 0 !== strpos( $method, 'test' ) ) {
		continue;
	}

	try {
		$test->setUp();
		$test->$method();
		$test->tearDown();
		echo 'PASS  ' . $method . PHP_EOL;
		$passed++;
	} catch ( Exception $e ) {
		$test->tearDown();
		echo 'FAIL  ' . $method . ': ' . $e->getMessage() . PHP_EOL;
		$failed++;
		$failures[] = $method . ': ' . $e->getMessage();
	}
}

echo PHP_EOL . $passed . ' passed, ' . $failed . ' failed' . PHP_EOL;

if ( $failed > 0 ) {
	echo PHP_EOL . 'Failures:' . PHP_EOL;
	foreach ( $failures as $failure ) {
		echo ' - ' . $failure . PHP_EOL;
	}
	exit( 1 );
}

exit( 0 );
