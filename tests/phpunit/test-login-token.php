<?php
/**
 * Tests for the wp-login.php token flow (Central_Connect_Login).
 *
 * @package Central_Connect
 */

/**
 * Thrown by the wp_redirect filter so authenticate() cannot exit the process.
 */
class Central_Connect_Test_Redirect extends Exception {}

/**
 * Class: Test_Login_Token
 */
class Test_Login_Token extends Central_Connect_Test_Case {

	/**
	 * Set up redirect interception.
	 */
	public function set_up() {
		parent::set_up();

		add_filter( 'wp_redirect', array( $this, 'intercept_redirect' ) );
		add_filter( 'send_auth_cookies', '__return_false' );
	}

	/**
	 * Tear down.
	 */
	public function tear_down() {
		remove_filter( 'wp_redirect', array( $this, 'intercept_redirect' ) );
		remove_filter( 'send_auth_cookies', '__return_false' );
		$_REQUEST = array();
		wp_set_current_user( 0 );

		parent::tear_down();
	}

	/**
	 * Turn redirects into exceptions.
	 *
	 * @param string $location Redirect target.
	 * @throws Central_Connect_Test_Redirect Always.
	 */
	public function intercept_redirect( $location ) {
		throw new Central_Connect_Test_Redirect( $location );
	}

	/**
	 * Run authenticate() and return the redirect location.
	 *
	 * @param array $request Request parameters.
	 * @return string
	 */
	private function authenticate( $request ) {
		$_REQUEST = $request;
		$login    = new Central_Connect_Login();

		try {
			$login->authenticate();
		} catch ( Central_Connect_Test_Redirect $e ) {
			return $e->getMessage();
		}

		return '';
	}

	/**
	 * An environment token on an unconnected site never reaches Central.
	 */
	public function test_environment_token_on_unconnected_site_does_not_relay() {
		self::factory()->user->create( array( 'role' => 'administrator' ) );
		delete_option( 'boldgrid_api_key' );
		$this->central_accepts();

		$location = $this->authenticate( array( 'token' => 'abc123', 'environment_id' => 'env-1' ) );

		$this->assertSame( 'wp-login.php', $location );
		$this->assertCount( 0, $this->http_requests );
		$this->assertSame( 0, get_current_user_id() );
	}

	/**
	 * A site token (no environment id) on an unconnected site never reaches Central.
	 */
	public function test_site_token_on_unconnected_site_does_not_relay() {
		self::factory()->user->create( array( 'role' => 'administrator' ) );
		delete_option( 'boldgrid_api_key' );
		$this->http_response = $this->central_response( array( 'validated' => true ) );

		$location = $this->authenticate( array( 'token' => 'abc123' ) );

		$this->assertSame( 'wp-login.php', $location );
		$this->assertCount( 0, $this->http_requests );
	}

	/**
	 * Site-token validation is rate limited like the environment path.
	 */
	public function test_site_token_relay_is_rate_limited() {
		self::factory()->user->create( array( 'role' => 'administrator' ) );
		$this->connect_site();
		$this->http_response = $this->central_response( array( 'validated' => false ), 403 );

		$limit = \Central\Connect\Authentication\Token::rateLimits()['ip']['limit'];
		for ( $i = 0; $i < $limit; $i++ ) {
			$this->authenticate( array( 'token' => 'site-token-' . $i ) );
		}
		$this->assertCount( $limit, $this->http_requests );

		$this->authenticate( array( 'token' => 'site-token-final' ) );
		$this->assertCount( $limit, $this->http_requests, 'Rate-limited attempt must not reach Central.' );
	}

	/**
	 * Legitimate single sign-on: Central confirms the environment token and
	 * the administrator is logged in and redirected.
	 */
	public function test_confirmed_environment_token_logs_in_administrator() {
		$admin = self::factory()->user->create( array( 'role' => 'administrator' ) );
		$this->connect_site( 'site-key', 'env-1' );
		$this->central_accepts();

		$location = $this->authenticate( array( 'token' => 'good-token', 'environment_id' => 'env-1', 'redirect_url' => admin_url( 'plugins.php' ) ) );

		$this->assertSame( admin_url( 'plugins.php' ), $location );
		$this->assertSame( $admin, get_current_user_id() );
		$this->assertCount( 1, $this->http_requests );
		$this->assertSame( 'env-1', $this->sent_body()['environment_id'] );
	}

	/**
	 * The redirect target must stay on this host.
	 */
	public function test_redirect_url_is_restricted_to_local_host() {
		self::factory()->user->create( array( 'role' => 'administrator' ) );
		$this->connect_site( 'site-key', 'env-1' );
		$this->central_accepts();

		$location = $this->authenticate( array( 'token' => 'good-token', 'environment_id' => 'env-1', 'redirect_url' => 'https://evil.example/phish' ) );

		$this->assertSame( admin_url(), $location );
	}
}
