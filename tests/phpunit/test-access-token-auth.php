<?php
/**
 * Tests for X-BGC-Auth access-token authentication.
 *
 * @package Central_Connect
 */

use Central\Connect\Authentication\Central;
use Central\Connect\Authentication\Token;

/**
 * Class: Test_Access_Token_Auth
 */
class Test_Access_Token_Auth extends Central_Connect_Test_Case {

	/**
	 * Run the REST authentication filter with a given X-BGC-Auth header.
	 *
	 * @param string $header Header value.
	 * @return mixed Filter result (null, true or WP_Error).
	 */
	private function authenticate_with( $header ) {
		rest_get_server();
		$_SERVER['HTTP_X_BGC_AUTH'] = $header;
		wp_set_current_user( 0 );

		return apply_filters( 'rest_authentication_errors', null );
	}

	/**
	 * A valid access token authenticates its user.
	 */
	public function test_valid_access_token_authenticates_user() {
		$admin = self::factory()->user->create( array( 'role' => 'administrator' ) );
		$token = new Token();
		$issued = $token->create( get_user_by( 'id', $admin ) );

		$result = $this->authenticate_with( 'Bearer ' . $issued['access_token'] );

		$this->assertTrue( $result );
		$this->assertSame( $admin, get_current_user_id() );
	}

	/**
	 * An invalid token is refused immediately (no sleep) with 401.
	 */
	public function test_invalid_access_token_fails_fast() {
		$admin = self::factory()->user->create( array( 'role' => 'administrator' ) );

		$start  = microtime( true );
		$result = $this->authenticate_with( base64_encode( $admin . ':not-the-token' ) );

		$this->assertLessThan( 1.0, microtime( true ) - $start );
		$this->assertWPError( $result );
		$this->assertSame( 401, $result->get_error_data()['status'] );
		$this->assertSame( 0, get_current_user_id() );
	}

	/**
	 * Repeated failures from one client are cut off with 429 before the
	 * token lookup runs.
	 */
	public function test_repeated_failures_are_rate_limited() {
		$admin  = self::factory()->user->create( array( 'role' => 'administrator' ) );
		$limits = Central::failureLimits();

		for ( $i = 0; $i < $limits['limit']; $i++ ) {
			$result = $this->authenticate_with( base64_encode( $admin . ':bad-' . $i ) );
			$this->assertSame( 401, $result->get_error_data()['status'] );
		}

		$result = $this->authenticate_with( base64_encode( $admin . ':bad-final' ) );
		$this->assertWPError( $result );
		$this->assertSame( 429, $result->get_error_data()['status'] );
		$this->assertSame( 'central_connect_too_many_attempts', $result->get_error_code() );

		// A valid token from the same client is also held back until the window resets.
		$issued = ( new Token() )->create( get_user_by( 'id', $admin ) );
		$result = $this->authenticate_with( 'Bearer ' . $issued['access_token'] );
		$this->assertSame( 429, $result->get_error_data()['status'] );

		// Other clients are unaffected.
		$_SERVER['REMOTE_ADDR'] = '203.0.113.77';
		$result                 = $this->authenticate_with( 'Bearer ' . $issued['access_token'] );
		$this->assertTrue( $result );
	}

	/**
	 * Non-Central bearer tokens are left for other authentication handlers.
	 *
	 * Core's cookie handler (priority 100) turns the untouched null into
	 * true for an anonymous request, so the observable result is "no error,
	 * nobody logged in".
	 */
	public function test_foreign_tokens_are_ignored() {
		$result = $this->authenticate_with( 'Bearer some-jwt-looking-thing' );

		$this->assertNotWPError( $result );
		$this->assertSame( 0, get_current_user_id() );
	}

	/**
	 * Expired tokens do not authenticate.
	 */
	public function test_expired_token_is_rejected() {
		$admin  = self::factory()->user->create( array( 'role' => 'administrator' ) );
		$issued = ( new Token() )->create( get_user_by( 'id', $admin ), '-1 minute' );

		$result = $this->authenticate_with( 'Bearer ' . $issued['access_token'] );

		$this->assertWPError( $result );
		$this->assertSame( 401, $result->get_error_data()['status'] );
	}
}
