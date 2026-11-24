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
	 * A Central-confirmed environment id is remembered by the SSO path too,
	 * so a transferred site that only signs in through the browser does not
	 * stay in the small re-pair bucket forever.
	 */
	public function test_confirmed_sso_persists_environment_id() {
		self::factory()->user->create( array( 'role' => 'administrator' ) );
		$this->connect_site( 'site-key', 'old-env' );
		$this->central_accepts();

		$this->authenticate( array( 'token' => 'good-token', 'environment_id' => 'new-env' ) );
		$this->assertSame( 'new-env', \Central\Connect\Option\Connect::get( 'environment_id' ) );

		// Follow-up logins with the new id now use the normal bucket, not re-pair.
		$this->assertSame( 'new-env', $this->sent_body( 0 )['environment_id'] );
		$this->central_rejects();
		$rebind_limit = \Central\Connect\Authentication\Token::rateLimits()['rebind_ip']['limit'];
		for ( $i = 0; $i <= $rebind_limit; $i++ ) {
			wp_set_current_user( 0 );
			$this->authenticate( array( 'token' => 'stale-' . $i, 'environment_id' => 'new-env' ) );
		}
		$this->assertCount( $rebind_limit + 2, $this->http_requests );
	}

	/**
	 * A rejected SSO attempt does not change the stored environment id.
	 */
	public function test_rejected_sso_does_not_persist_environment_id() {
		self::factory()->user->create( array( 'role' => 'administrator' ) );
		$this->connect_site( 'site-key', 'old-env' );
		$this->central_rejects();

		$location = $this->authenticate( array( 'token' => 'bad-token', 'environment_id' => 'new-env' ) );

		$this->assertSame( 'wp-login.php', $location );
		$this->assertSame( 'old-env', \Central\Connect\Option\Connect::get( 'environment_id' ) );
	}

	/**
	 * has_access_token SSO logs the token's user in.
	 */
	public function test_access_token_sso_logs_in_token_owner() {
		$admin  = self::factory()->user->create( array( 'role' => 'administrator' ) );
		$issued = ( new \Central\Connect\Authentication\Token() )->create( get_user_by( 'id', $admin ) );
		$this->connect_site( 'site-key', 'env-1' );

		$location = $this->authenticate( array( 'token' => $issued['access_token'], 'environment_id' => 'env-1', 'has_access_token' => '1' ) );

		$this->assertSame( user_admin_url(), $location );
		$this->assertSame( $admin, get_current_user_id() );
		$this->assertCount( 0, $this->http_requests, 'Access-token SSO is local and must not contact Central.' );
	}

	/**
	 * has_access_token SSO shares the per-client failure limit with the
	 * X-BGC-Auth header, so issued tokens cannot be brute forced through
	 * wp-login.php either.
	 */
	public function test_access_token_sso_is_rate_limited_after_failures() {
		$admin  = self::factory()->user->create( array( 'role' => 'administrator' ) );
		$issued = ( new \Central\Connect\Authentication\Token() )->create( get_user_by( 'id', $admin ) );
		$this->connect_site( 'site-key', 'env-1' );
		$limit = \Central\Connect\Authentication\Token::accessTokenFailureLimits()['limit'];

		for ( $i = 0; $i < $limit; $i++ ) {
			$location = $this->authenticate( array( 'token' => base64_encode( $admin . ':guess-' . $i ), 'environment_id' => 'env-1', 'has_access_token' => '1' ) );
			$this->assertSame( 'wp-login.php', $location );
		}

		$location = $this->authenticate( array( 'token' => $issued['access_token'], 'environment_id' => 'env-1', 'has_access_token' => '1' ) );
		$this->assertSame( 'wp-login.php', $location, 'Exhausted client must be refused even with a valid token.' );
		$this->assertSame( 0, get_current_user_id() );

		$_SERVER['REMOTE_ADDR'] = '203.0.113.78';
		$location               = $this->authenticate( array( 'token' => $issued['access_token'], 'environment_id' => 'env-1', 'has_access_token' => '1' ) );
		$this->assertSame( user_admin_url(), $location );
		$this->assertSame( $admin, get_current_user_id() );
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
