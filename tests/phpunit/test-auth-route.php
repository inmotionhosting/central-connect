<?php
/**
 * Tests for POST /bgc/v1/auth/.
 *
 * @package Central_Connect
 */

use Central\Connect\Authentication\RateLimiter;
use Central\Connect\Authentication\Token;
use Central\Connect\Option\Connect;

/**
 * Class: Test_Auth_Route
 */
class Test_Auth_Route extends Central_Connect_Test_Case {

	/**
	 * Route under test (WordPress strips the trailing slash on registration).
	 */
	const ROUTE = '/bgc/v1/auth';

	/**
	 * A plausible environment token.
	 */
	const TOKEN = 'env-token-0123456789abcdef';

	/**
	 * A plausible environment id.
	 */
	const ENV = 'a1b2c3d4-0000-4000-8000-000000000001';

	/**
	 * The route is registered without a WordPress session requirement.
	 */
	public function test_route_is_registered() {
		$routes = rest_get_server()->get_routes( 'bgc/v1' );

		$this->assertArrayHasKey( self::ROUTE, $routes );
		$this->assertSame( '__return_true', $routes[ self::ROUTE ][0]['permission_callback'] );
		$this->assertTrue( $routes[ self::ROUTE ][0]['args']['environment_id']['required'] );
	}

	/**
	 * A site with no Central API key is not connected and must never relay.
	 */
	public function test_unconnected_site_rejects_locally_without_contacting_central() {
		delete_option( 'boldgrid_api_key' );
		$this->central_accepts();

		$response = $this->rest(
			'POST',
			self::ROUTE,
			array(
				'token'          => self::TOKEN,
				'environment_id' => self::ENV,
			)
		);

		$this->assertSame( 403, $response->get_status() );
		$this->assertSame( 'central_connect_not_connected', $response->as_error()->get_error_code() );
		$this->assertCount( 0, $this->http_requests, 'No outbound request may be made for an unconnected site.' );
	}

	/**
	 * Malformed tokens are rejected before any relay.
	 *
	 * @dataProvider malformed_tokens
	 *
	 * @param string $token Token value.
	 */
	public function test_malformed_token_is_not_forwarded( $token ) {
		$this->connect_site();
		$this->central_accepts();

		$response = $this->rest(
			'POST',
			self::ROUTE,
			array(
				'token'          => $token,
				'environment_id' => self::ENV,
			)
		);

		$this->assertSame( 400, $response->get_status() );
		$this->assertCount( 0, $this->http_requests );
	}

	/**
	 * Token shapes that cannot be a Central token.
	 *
	 * @return array[]
	 */
	public function malformed_tokens() {
		return array(
			'whitespace inside' => array( 'abc def' ),
			'newline'           => array( "abc\ndef" ),
			'control char'      => array( "abc\x01def" ),
			'non-ascii'         => array( 'tökén' ),
			'too long'          => array( str_repeat( 'a', Token::MAX_TOKEN_LENGTH + 1 ) ),
		);
	}

	/**
	 * Malformed environment ids are rejected before any relay.
	 *
	 * @dataProvider malformed_environment_ids
	 *
	 * @param string $environment_id Environment id value.
	 */
	public function test_malformed_environment_id_is_not_forwarded( $environment_id ) {
		$this->connect_site();
		$this->central_accepts();

		$response = $this->rest(
			'POST',
			self::ROUTE,
			array(
				'token'          => self::TOKEN,
				'environment_id' => $environment_id,
			)
		);

		$this->assertSame( 400, $response->get_status() );
		$this->assertCount( 0, $this->http_requests );
	}

	/**
	 * Environment id shapes that cannot be a Central environment id.
	 *
	 * @return array[]
	 */
	public function malformed_environment_ids() {
		return array(
			'empty'     => array( '' ),
			'url'       => array( 'https://example.com/x' ),
			'query-ish' => array( 'a&key=b' ),
			'spaces'    => array( '1 2' ),
			'too long'  => array( str_repeat( '1', Token::MAX_ENVIRONMENT_ID_LENGTH + 1 ) ),
		);
	}

	/**
	 * Missing environment_id is a 400 from the arg schema, not a relay.
	 */
	public function test_missing_environment_id_is_rejected() {
		$this->connect_site();
		$this->central_accepts();

		$response = $this->rest( 'POST', self::ROUTE, array( 'token' => self::TOKEN ) );

		$this->assertSame( 400, $response->get_status() );
		$this->assertCount( 0, $this->http_requests );
	}

	/**
	 * Legitimate flow: Central confirms the token and the site issues an
	 * access token, storing the environment id for later requests.
	 */
	public function test_valid_token_issues_access_token_and_stores_environment() {
		$admin = self::factory()->user->create( array( 'role' => 'administrator' ) );
		$this->connect_site( 'site-key-123' );
		$this->central_accepts();

		$response = $this->rest(
			'POST',
			self::ROUTE,
			array(
				'token'          => self::TOKEN,
				'environment_id' => self::ENV,
			)
		);
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertArrayHasKey( 'access_token', $data );
		$this->assertArrayNotHasKey( 'access_token_hash', $data );
		$this->assertSame( $admin, $data['wp_user_id'] );
		$this->assertSame( self::ENV, Connect::get( 'environment_id' ) );

		$this->assertCount( 1, $this->http_requests );
		$body = $this->sent_body();
		$this->assertSame( self::TOKEN, $body['token'] );
		$this->assertSame( self::ENV, $body['environment_id'] );
		$this->assertSame( 'site-key-123', $body['key'] );

		// The issued token must authenticate the admin through X-BGC-Auth.
		$token = new Token();
		$user  = $token->getAuthenticatedUser( 'Bearer ' . $data['access_token'] );
		$this->assertInstanceOf( 'WP_User', $user );
		$this->assertSame( $admin, $user->ID );
	}

	/**
	 * Verifies the reported concern: with no user_id, selectUser() falls
	 * back to an administrator, so a Central-confirmed token mints an
	 * administrator access token. This is the product's trust model and the
	 * test documents it so any change to it is deliberate.
	 */
	public function test_default_user_is_an_administrator() {
		self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$admin = self::factory()->user->create( array( 'role' => 'administrator' ) );
		$this->connect_site();
		$this->central_accepts();

		$response = $this->rest(
			'POST',
			self::ROUTE,
			array(
				'token'          => self::TOKEN,
				'environment_id' => self::ENV,
			)
		);

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( $admin, $response->get_data()['wp_user_id'] );
		$this->assertTrue( user_can( $response->get_data()['wp_user_id'], 'manage_options' ) );
	}

	/**
	 * An explicit administrator user_id is honoured (coherent with the default).
	 */
	public function test_explicit_administrator_user_id_is_honoured() {
		$default_admin = self::factory()->user->create(
			array(
				'role'       => 'administrator',
				'user_login' => 'zz-last-admin',
			)
		);
		$admin         = self::factory()->user->create(
			array(
				'role'       => 'administrator',
				'user_login' => 'aa-first-admin',
			)
		);
		$this->connect_site();
		$this->central_accepts();

		$response = $this->rest(
			'POST',
			self::ROUTE,
			array(
				'token'          => self::TOKEN,
				'environment_id' => self::ENV,
				'user_id'        => (string) $admin,
			)
		);

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( $admin, $response->get_data()['wp_user_id'] );
		$this->assertNotSame( $default_admin, $response->get_data()['wp_user_id'] );
	}

	/**
	 * An unknown user_id must not silently fall back to the administrator.
	 */
	public function test_unknown_user_id_does_not_fall_back_to_administrator() {
		self::factory()->user->create( array( 'role' => 'administrator' ) );
		$this->connect_site();
		$this->central_accepts();

		$response = $this->rest(
			'POST',
			self::ROUTE,
			array(
				'token'          => self::TOKEN,
				'environment_id' => self::ENV,
				'user_id'        => '999999',
			)
		);

		$this->assertSame( 400, $response->get_status() );
		$this->assertSame( 'user_not_found', $response->get_data()['errors']['name'] );
		$this->assertArrayNotHasKey( 'access_token', $response->get_data() );
		$this->assertEmpty( Connect::get( 'environment_id' ), 'Environment must only be stored once a token is issued.' );
	}

	/**
	 * Tokens are only issued for users who can manage the site.
	 */
	public function test_non_administrator_user_id_is_rejected() {
		$subscriber = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		self::factory()->user->create( array( 'role' => 'administrator' ) );
		$this->connect_site();
		$this->central_accepts();

		$response = $this->rest(
			'POST',
			self::ROUTE,
			array(
				'token'          => self::TOKEN,
				'environment_id' => self::ENV,
				'user_id'        => (string) $subscriber,
			)
		);

		$this->assertSame( 400, $response->get_status() );
		$this->assertSame( 'user_not_qualified', $response->get_data()['errors']['name'] );
		$this->assertEmpty( get_user_option( 'bgc_access_tokens', $subscriber ) );
		$this->assertEmpty( Connect::get( 'environment_id' ), 'Environment must only be stored once a token is issued.' );
	}

	/**
	 * A site with no administrator cannot issue a token and must not record
	 * the environment either.
	 */
	public function test_site_without_administrator_issues_nothing() {
		foreach ( get_users(
			array(
				'role'   => 'administrator',
				'fields' => 'ID',
			)
		) as $id ) {
			wp_delete_user( $id );
		}
		$this->connect_site();
		$this->central_accepts();

		$response = $this->rest(
			'POST',
			self::ROUTE,
			array(
				'token'          => self::TOKEN,
				'environment_id' => self::ENV,
			)
		);

		$this->assertSame( 400, $response->get_status() );
		$this->assertSame( 'user_not_found', $response->get_data()['errors']['name'] );
		$this->assertEmpty( Connect::get( 'environment_id' ) );
	}

	/**
	 * Central rejecting the token yields 403 immediately (no sleep).
	 */
	public function test_central_rejection_returns_403_without_delay() {
		self::factory()->user->create( array( 'role' => 'administrator' ) );
		$this->connect_site();
		$this->central_rejects();

		$start    = microtime( true );
		$response = $this->rest(
			'POST',
			self::ROUTE,
			array(
				'token'          => self::TOKEN,
				'environment_id' => self::ENV,
			)
		);
		$elapsed  = microtime( true ) - $start;

		$this->assertSame( 403, $response->get_status() );
		$this->assertSame( 'restx_logged_out', $response->as_error()->get_error_code() );
		$this->assertLessThan( 1.0, $elapsed, 'Failure path must not sleep.' );
		$this->assertEmpty( Connect::get( 'environment_id' ), 'Environment id must not be stored on failure.' );
	}

	/**
	 * Once the site knows its environment, the stored id is what is sent to
	 * Central; the caller's value is not forwarded when it matches.
	 */
	public function test_stored_environment_id_is_sent_upstream() {
		self::factory()->user->create( array( 'role' => 'administrator' ) );
		$this->connect_site( 'k', self::ENV );
		$this->central_accepts();

		$response = $this->rest(
			'POST',
			self::ROUTE,
			array(
				'token'          => self::TOKEN,
				'environment_id' => self::ENV,
			)
		);

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( self::ENV, $this->sent_body()['environment_id'] );
	}

	/**
	 * Per-client rate limit: the sixth attempt in the window is refused
	 * locally and no outbound request is made.
	 */
	public function test_per_client_rate_limit_stops_relay() {
		$this->connect_site();
		$this->central_rejects();

		$limit = Token::rateLimits()['ip']['limit'];
		for ( $i = 0; $i < $limit; $i++ ) {
			$response = $this->rest(
				'POST',
				self::ROUTE,
				array(
					'token'          => self::TOKEN . $i,
					'environment_id' => self::ENV,
				)
			);
			$this->assertSame( 403, $response->get_status() );
		}
		$this->assertCount( $limit, $this->http_requests );

		$response = $this->rest(
			'POST',
			self::ROUTE,
			array(
				'token'          => self::TOKEN . 'x',
				'environment_id' => self::ENV,
			)
		);

		$this->assertSame( 429, $response->get_status() );
		$this->assertSame( 'central_connect_too_many_attempts', $response->as_error()->get_error_code() );
		$this->assertCount( $limit, $this->http_requests, 'A rate-limited request must not reach Central.' );

		// A different client still has its own budget.
		$_SERVER['REMOTE_ADDR'] = '203.0.113.11';
		$response               = $this->rest(
			'POST',
			self::ROUTE,
			array(
				'token'          => self::TOKEN,
				'environment_id' => self::ENV,
			)
		);
		$this->assertSame( 403, $response->get_status() );
		$this->assertCount( $limit + 1, $this->http_requests );
	}

	/**
	 * Site-wide rate limit bounds the total outbound calls regardless of
	 * how many client addresses are used.
	 */
	public function test_site_wide_rate_limit_bounds_total_relays() {
		$this->connect_site();
		$this->central_rejects();

		add_filter(
			'central_connect_auth_rate_limits',
			function ( $limits ) {
				$limits['ip']   = array(
					'limit'  => 100,
					'window' => 600,
				);
				$limits['site'] = array(
					'limit'  => 3,
					'window' => 600,
				);
				return $limits;
			}
		);

		for ( $i = 0; $i < 3; $i++ ) {
			$_SERVER['REMOTE_ADDR'] = '203.0.113.' . ( 20 + $i );
			$response               = $this->rest(
				'POST',
				self::ROUTE,
				array(
					'token'          => self::TOKEN,
					'environment_id' => self::ENV,
				)
			);
			$this->assertSame( 403, $response->get_status() );
		}

		$_SERVER['REMOTE_ADDR'] = '203.0.113.99';
		$response               = $this->rest(
			'POST',
			self::ROUTE,
			array(
				'token'          => self::TOKEN,
				'environment_id' => self::ENV,
			)
		);

		$this->assertSame( 429, $response->get_status() );
		$this->assertCount( 3, $this->http_requests );
	}

	/**
	 * A caller naming a different environment than the one stored goes
	 * through the much smaller re-pair bucket.
	 */
	public function test_environment_mismatch_uses_rebind_budget() {
		self::factory()->user->create( array( 'role' => 'administrator' ) );
		$this->connect_site( 'k', self::ENV );
		$this->central_rejects();

		$limit = Token::rateLimits()['rebind_ip']['limit'];
		for ( $i = 0; $i < $limit; $i++ ) {
			$response = $this->rest(
				'POST',
				self::ROUTE,
				array(
					'token'          => self::TOKEN,
					'environment_id' => 'other-env-' . $i,
				)
			);
			$this->assertSame( 403, $response->get_status() );
			$this->assertSame( 'other-env-' . $i, $this->sent_body( $i )['environment_id'] );
		}

		$response = $this->rest(
			'POST',
			self::ROUTE,
			array(
				'token'          => self::TOKEN,
				'environment_id' => 'other-env-z',
			)
		);
		$this->assertSame( 429, $response->get_status() );
		$this->assertCount( $limit, $this->http_requests );

		// The matching environment is still within its own budget.
		$response = $this->rest(
			'POST',
			self::ROUTE,
			array(
				'token'          => self::TOKEN,
				'environment_id' => self::ENV,
			)
		);
		$this->assertSame( 403, $response->get_status() );
		$this->assertCount( $limit + 1, $this->http_requests );
		$this->assertSame( self::ENV, Connect::get( 'environment_id' ), 'Stored environment must not change on a failed re-pair.' );
	}

	/**
	 * Re-pair requests refused by an exhausted rebind bucket must not drain
	 * the normal per-IP and per-site buckets.
	 */
	public function test_refused_rebind_requests_do_not_consume_normal_buckets() {
		self::factory()->user->create( array( 'role' => 'administrator' ) );
		$this->connect_site( 'k', self::ENV );
		$this->central_rejects();

		$limits       = Token::rateLimits();
		$rebind_limit = $limits['rebind_ip']['limit'];
		$ip_limit     = $limits['ip']['limit'];

		for ( $i = 0; $i < $rebind_limit + 10; $i++ ) {
			$this->rest(
				'POST',
				self::ROUTE,
				array(
					'token'          => self::TOKEN,
					'environment_id' => 'other-env-' . $i,
				)
			);
		}
		$this->assertCount( $rebind_limit, $this->http_requests );

		for ( $i = 0; $i < $ip_limit - $rebind_limit; $i++ ) {
			$response = $this->rest(
				'POST',
				self::ROUTE,
				array(
					'token'          => self::TOKEN . $i,
					'environment_id' => self::ENV,
				)
			);
			$this->assertSame( 403, $response->get_status(), 'Refused re-pair attempts must not have consumed the per-IP bucket.' );
		}
		$this->assertCount( $ip_limit, $this->http_requests );

		$response = $this->rest(
			'POST',
			self::ROUTE,
			array(
				'token'          => self::TOKEN,
				'environment_id' => self::ENV,
			)
		);
		$this->assertSame( 429, $response->get_status() );
	}

	/**
	 * Once the site-wide bucket is full, new client addresses do not get
	 * per-IP transients written for them.
	 */
	public function test_full_site_bucket_writes_no_per_ip_transient() {
		$this->connect_site();
		$this->central_rejects();

		add_filter(
			'central_connect_auth_rate_limits',
			function ( $limits ) {
				$limits['site'] = array(
					'limit'  => 1,
					'window' => 600,
				);
				return $limits;
			}
		);

		$this->rest(
			'POST',
			self::ROUTE,
			array(
				'token'          => self::TOKEN,
				'environment_id' => self::ENV,
			)
		);

		$_SERVER['REMOTE_ADDR'] = '203.0.113.200';
		$response               = $this->rest(
			'POST',
			self::ROUTE,
			array(
				'token'          => self::TOKEN,
				'environment_id' => self::ENV,
			)
		);

		$this->assertSame( 429, $response->get_status() );
		$this->assertFalse( get_transient( RateLimiter::TRANSIENT_PREFIX . md5( 'remote_validate:ip:203.0.113.200' ) ) );
	}

	/**
	 * Central-confirmed validations do not count against the per-IP and
	 * per-site buckets, so repeated legitimate connections are not refused.
	 */
	public function test_confirmed_validations_are_not_counted_against_ip_bucket() {
		self::factory()->user->create( array( 'role' => 'administrator' ) );
		$this->connect_site( 'k', self::ENV );
		$this->central_accepts();

		$attempts = Token::rateLimits()['ip']['limit'] + 3;
		for ( $i = 0; $i < $attempts; $i++ ) {
			$response = $this->rest(
				'POST',
				self::ROUTE,
				array(
					'token'          => self::TOKEN,
					'environment_id' => self::ENV,
				)
			);
			$this->assertSame( 200, $response->get_status() );
		}
		$this->assertCount( $attempts, $this->http_requests );
	}

	/**
	 * The `total` bucket still bounds confirmed validations.
	 */
	public function test_total_bucket_bounds_confirmed_validations() {
		self::factory()->user->create( array( 'role' => 'administrator' ) );
		$this->connect_site( 'k', self::ENV );
		$this->central_accepts();

		add_filter(
			'central_connect_auth_rate_limits',
			function ( $limits ) {
				$limits['total'] = array(
					'limit'  => 3,
					'window' => 600,
				);
				return $limits;
			}
		);

		for ( $i = 0; $i < 3; $i++ ) {
			$this->assertSame(
				200,
				$this->rest(
					'POST',
					self::ROUTE,
					array(
						'token'          => self::TOKEN,
						'environment_id' => self::ENV,
					)
				)->get_status()
			);
		}

		$response = $this->rest(
			'POST',
			self::ROUTE,
			array(
				'token'          => self::TOKEN,
				'environment_id' => self::ENV,
			)
		);
		$this->assertSame( 429, $response->get_status() );
		$this->assertCount( 3, $this->http_requests );
	}

	/**
	 * A filter returning a non-array falls back to the defaults.
	 */
	public function test_non_array_rate_limit_filter_uses_defaults() {
		add_filter( 'central_connect_auth_rate_limits', '__return_false' );

		$limits = Token::rateLimits();

		$this->assertSame( 5, $limits['ip']['limit'] );
		$this->assertSame( 20, $limits['site']['limit'] );
		$this->assertArrayHasKey( 'total', $limits );
	}

	/**
	 * A legitimate re-pair (Central confirms the new environment) updates
	 * the stored environment id.
	 */
	public function test_confirmed_rebind_updates_stored_environment() {
		self::factory()->user->create( array( 'role' => 'administrator' ) );
		$this->connect_site( 'k', self::ENV );
		$this->central_accepts();

		$response = $this->rest(
			'POST',
			self::ROUTE,
			array(
				'token'          => self::TOKEN,
				'environment_id' => 'new-env-1',
			)
		);

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'new-env-1', Connect::get( 'environment_id' ) );
	}

	/**
	 * Opt-in caller allowlist refuses other clients before any relay.
	 */
	public function test_caller_allowlist_blocks_unlisted_clients() {
		self::factory()->user->create( array( 'role' => 'administrator' ) );
		$this->connect_site();
		$this->central_accepts();

		add_filter(
			'central_connect_auth_allowed_ips',
			function () {
				return array( '198.51.100.5' );
			}
		);

		$response = $this->rest(
			'POST',
			self::ROUTE,
			array(
				'token'          => self::TOKEN,
				'environment_id' => self::ENV,
			)
		);
		$this->assertSame( 403, $response->get_status() );
		$this->assertSame( 'central_connect_caller_not_allowed', $response->as_error()->get_error_code() );
		$this->assertCount( 0, $this->http_requests );

		$_SERVER['REMOTE_ADDR'] = '198.51.100.5';
		$response               = $this->rest(
			'POST',
			self::ROUTE,
			array(
				'token'          => self::TOKEN,
				'environment_id' => self::ENV,
			)
		);
		$this->assertSame( 200, $response->get_status() );
		$this->assertCount( 1, $this->http_requests );
	}

	/**
	 * Class guard: no blocking sleep() may remain in plugin code.
	 */
	public function test_no_sleep_calls_remain_in_plugin_source() {
		$files = array_merge(
			glob( CENTRAL_CONNECT_PATH . 'src/*.php' ),
			glob( CENTRAL_CONNECT_PATH . 'src/*/*.php' ),
			glob( CENTRAL_CONNECT_PATH . 'src/*/*/*.php' ),
			glob( CENTRAL_CONNECT_PATH . 'includes/*.php' )
		);

		$this->assertNotEmpty( $files );
		foreach ( $files as $file ) {
			// php_strip_whitespace() drops comments so only real calls are matched.
			$this->assertSame(
				0,
				preg_match( '/(?<![a-zA-Z0-9_>$])u?sleep\s*\(/', php_strip_whitespace( $file ) ),
				basename( $file ) . ' must not call sleep()/usleep().'
			);
		}
	}
}
