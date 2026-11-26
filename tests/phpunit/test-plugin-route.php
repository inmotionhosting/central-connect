<?php
/**
 * Tests for the /bgc/v1/plugins/ file argument validation.
 *
 * @package Central_Connect
 */

/**
 * Class: Test_Plugin_Route
 */
class Test_Plugin_Route extends Central_Connect_Test_Case {

	/**
	 * Route under test.
	 */
	const ROUTE = '/bgc/v1/plugins';

	/**
	 * Temporary files created for the test.
	 *
	 * @var string[]
	 */
	private $created = array();

	/**
	 * Decoy directory next to the plugins directory.
	 *
	 * All traversal decoys live inside this sandbox so that, should the
	 * validation ever regress, core delete_plugins() can only remove the
	 * sandbox (it deletes dirname( WP_PLUGIN_DIR . $file ) recursively) and
	 * never wp-content itself. Do not point a decoy path directly at a
	 * file in wp-content.
	 */
	const DECOY_DIR = 'cc-decoy-dir';

	/**
	 * Create a fake installed plugin and decoy files.
	 */
	public function set_up() {
		parent::set_up();

		$plugin_dir = trailingslashit( WP_PLUGIN_DIR ) . 'cc-test-plugin';
		if ( ! is_dir( $plugin_dir ) ) {
			mkdir( $plugin_dir );
		}
		$this->created[] = $plugin_dir . '/cc-test-plugin.php';
		file_put_contents( $plugin_dir . '/cc-test-plugin.php', "<?php\n/*\nPlugin Name: CC Test Plugin\n*/\n" );

		$decoy_dir = dirname( WP_PLUGIN_DIR ) . '/' . self::DECOY_DIR;
		if ( ! is_dir( $decoy_dir ) ) {
			mkdir( $decoy_dir );
		}
		$this->created[] = $decoy_dir . '/cc-decoy.php';
		file_put_contents( $decoy_dir . '/cc-decoy.php', "<?php\n// decoy\n" );

		$this->created[] = trailingslashit( WP_PLUGIN_DIR ) . 'cc-not-a-plugin.txt';
		file_put_contents( trailingslashit( WP_PLUGIN_DIR ) . 'cc-not-a-plugin.txt', 'decoy' );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
	}

	/**
	 * Remove temporary files.
	 */
	public function tear_down() {
		foreach ( $this->created as $file ) {
			if ( file_exists( $file ) ) {
				unlink( $file );
			}
		}
		foreach ( array( trailingslashit( WP_PLUGIN_DIR ) . 'cc-test-plugin', dirname( WP_PLUGIN_DIR ) . '/' . self::DECOY_DIR ) as $dir ) {
			if ( is_dir( $dir ) ) {
				rmdir( $dir );
			}
		}

		parent::tear_down();
	}

	/**
	 * Paths that exist but are not installed plugins are refused.
	 *
	 * @dataProvider traversal_paths
	 *
	 * @param string $path Plugin file argument.
	 */
	public function test_traversal_paths_are_rejected( $path ) {
		$this->assertFileExists( trailingslashit( WP_PLUGIN_DIR ) . $path, 'Precondition: the decoy must exist so only validation can stop it.' );

		foreach ( array( 'PUT', 'DELETE' ) as $method ) {
			$params = array( 'files' => array( $path ) );
			if ( 'PUT' === $method ) {
				$params['active'] = false;
			}
			$response = $this->rest( $method, self::ROUTE, $params );

			$this->assertSame( 400, $response->get_status(), $method . ' must reject ' . $path );
			$this->assertSame( 'rest_invalid_param', $response->as_error()->get_error_code() );
		}

		$this->assertFileExists( trailingslashit( WP_PLUGIN_DIR ) . $path, 'Decoy must survive.' );
	}

	/**
	 * Existing files outside the installed plugin set.
	 *
	 * @return array[]
	 */
	public function traversal_paths() {
		return array(
			'parent dir'       => array( '../' . self::DECOY_DIR . '/cc-decoy.php' ),
			'nested traversal' => array( 'cc-test-plugin/../../' . self::DECOY_DIR . '/cc-decoy.php' ),
			'non-plugin file'  => array( 'cc-not-a-plugin.txt' ),
		);
	}

	/**
	 * A real installed plugin basename passes validation.
	 */
	public function test_installed_plugin_is_accepted() {
		$response = $this->rest( 'PUT', self::ROUTE, array( 'files' => array( 'cc-test-plugin/cc-test-plugin.php' ), 'active' => false ) );

		$this->assertSame( 200, $response->get_status() );
	}

	/**
	 * Unauthenticated callers are refused before validation runs.
	 */
	public function test_requires_capability() {
		wp_set_current_user( 0 );

		$response = $this->rest( 'DELETE', self::ROUTE, array( 'files' => array( 'cc-test-plugin/cc-test-plugin.php' ) ) );

		$this->assertSame( 401, $response->get_status() );
	}
}
