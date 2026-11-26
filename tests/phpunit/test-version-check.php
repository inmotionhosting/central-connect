<?php
/**
 * Tests for the PHP / WordPress version gate.
 *
 * @package Central_Connect
 */

/**
 * Class: Test_Version_Check
 */
class Test_Version_Check extends WP_UnitTestCase {

	/**
	 * Arguments received by the version-check callback.
	 *
	 * @var array|null
	 */
	private $received;

	/**
	 * Restore the plugin's own requirements after each test.
	 */
	public function tear_down() {
		Central_Connect_Version_Check::init( plugin_basename( CENTRAL_CONNECT_FILE ), '5.0', '7.4' );
		parent::tear_down();
	}

	/**
	 * Records the callback arguments.
	 *
	 * @param mixed ...$args Arguments passed by the version check.
	 */
	public function record( ...$args ) {
		$this->received = $args;
	}

	/**
	 * Extra init() arguments after the callback are forwarded to it.
	 */
	public function test_callback_receives_extra_arguments() {
		Central_Connect_Version_Check::init( 'central-connect/central-connect.php', '5.0', '7.4', array( $this, 'record' ), 'first', 2 );

		$this->assertSame( array( 'first', 2 ), $this->received );
	}

	/**
	 * A callback without extra arguments is called with none.
	 */
	public function test_callback_without_extra_arguments() {
		Central_Connect_Version_Check::init( 'central-connect/central-connect.php', '5.0', '7.4', array( $this, 'record' ) );

		$this->assertSame( array(), $this->received );
	}

	/**
	 * The running PHP satisfies the plugin minimum and fails an impossible one.
	 */
	public function test_php_minimum() {
		Central_Connect_Version_Check::init( 'central-connect/central-connect.php', '5.0', '7.4', '__return_null' );
		$this->assertFalse( Central_Connect_Version_Check::is_bad_php() );

		Central_Connect_Version_Check::init( 'central-connect/central-connect.php', '5.0', '99.0', '__return_null' );
		$this->assertTrue( Central_Connect_Version_Check::is_bad_php() );
		remove_action( 'admin_notices', array( 'Central_Connect_Version_Check', 'admin_notice' ) );
		remove_action( 'admin_init', array( 'Central_Connect_Version_Check', 'deactivate' ) );
	}

	/**
	 * The plugin header and readme advertise the same PHP minimum the gate enforces.
	 */
	public function test_declared_php_minimum_matches_gate() {
		$header = get_file_data( CENTRAL_CONNECT_FILE, array( 'RequiresPHP' => 'Requires PHP' ) );
		$readme = get_file_data( CENTRAL_CONNECT_PATH . 'readme.txt', array( 'RequiresPHP' => 'Requires PHP' ) );
		$source = file_get_contents( CENTRAL_CONNECT_FILE ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

		$this->assertSame( '7.4', $header['RequiresPHP'] );
		$this->assertSame( '7.4', $readme['RequiresPHP'] );
		$this->assertMatchesRegularExpression( "/Central_Connect_Version_Check::init\\([^;]*'7\\.4'/", $source );
	}
}
