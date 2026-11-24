<?php
/**
 * Tests for the transient-backed RateLimiter.
 *
 * @package Central_Connect
 */

use Central\Connect\Authentication\RateLimiter;

/**
 * Class: Test_Rate_Limiter
 */
class Test_Rate_Limiter extends WP_UnitTestCase {

	/**
	 * Attempts are allowed up to the limit, then refused until reset.
	 */
	public function test_attempts_are_counted_per_window() {
		$limiter = new RateLimiter();

		$this->assertFalse( $limiter->isLimited( 'scope-a', 3, 60 ) );
		$this->assertTrue( $limiter->attempt( 'scope-a', 3, 60 ) );
		$this->assertTrue( $limiter->attempt( 'scope-a', 3, 60 ) );
		$this->assertTrue( $limiter->attempt( 'scope-a', 3, 60 ) );
		$this->assertTrue( $limiter->isLimited( 'scope-a', 3, 60 ) );
		$this->assertFalse( $limiter->attempt( 'scope-a', 3, 60 ) );

		$retry = $limiter->retryAfter( 'scope-a', 60 );
		$this->assertGreaterThan( 0, $retry );
		$this->assertLessThanOrEqual( 60, $retry );

		$limiter->reset( 'scope-a' );
		$this->assertFalse( $limiter->isLimited( 'scope-a', 3, 60 ) );
		$this->assertSame( 0, $limiter->retryAfter( 'scope-a', 60 ) );
	}

	/**
	 * Scopes are independent.
	 */
	public function test_scopes_are_independent() {
		$limiter = new RateLimiter();

		$this->assertTrue( $limiter->attempt( 'scope-b', 1, 60 ) );
		$this->assertFalse( $limiter->attempt( 'scope-b', 1, 60 ) );
		$this->assertTrue( $limiter->attempt( 'scope-c', 1, 60 ) );
	}

	/**
	 * An expired window starts fresh.
	 */
	public function test_expired_window_starts_fresh() {
		$limiter = new RateLimiter();
		$this->assertTrue( $limiter->attempt( 'scope-d', 1, 60 ) );
		$this->assertFalse( $limiter->attempt( 'scope-d', 1, 60 ) );

		// Age the bucket past its reset time without waiting.
		$key = RateLimiter::TRANSIENT_PREFIX . md5( 'scope-d' );
		set_transient( $key, array( 'count' => 1, 'reset' => time() - 1 ), 60 );

		$this->assertFalse( $limiter->isLimited( 'scope-d', 1, 60 ) );
		$this->assertTrue( $limiter->attempt( 'scope-d', 1, 60 ) );
	}

	/**
	 * Only a valid REMOTE_ADDR is used; the filter may supply a better one.
	 */
	public function test_client_ip_resolution() {
		$_SERVER['REMOTE_ADDR'] = '203.0.113.9';
		$this->assertSame( '203.0.113.9', RateLimiter::clientIp() );

		$_SERVER['REMOTE_ADDR'] = '2001:db8::1';
		$this->assertSame( '2001:db8::1', RateLimiter::clientIp() );

		$_SERVER['REMOTE_ADDR'] = 'not-an-ip';
		$this->assertSame( 'unknown', RateLimiter::clientIp() );

		add_filter(
			'central_connect_client_ip',
			function () {
				return '198.51.100.7';
			}
		);
		$this->assertSame( '198.51.100.7', RateLimiter::clientIp() );

		add_filter(
			'central_connect_client_ip',
			function () {
				return 'garbage';
			},
			20
		);
		$this->assertSame( 'unknown', RateLimiter::clientIp(), 'An invalid filtered value falls back to the original.' );
	}
}
