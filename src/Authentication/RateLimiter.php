<?php
/**
 * File: RateLimiter.php
 *
 * Transient-backed fixed-window rate limiter for authentication paths.
 *
 * @since      2.0.7
 * @package    Central\Connect\Authentication
 * @author     InMotion Hosting <central-dev@inmotionhosting.com>
 * @link       https://boldgrid.com
 */

namespace Central\Connect\Authentication;

/**
 * Class: RateLimiter
 *
 * Small fixed-window counter stored in transients. It bounds how often the
 * unauthenticated authentication paths may trigger expensive work (outbound
 * calls to Central, password-hash comparisons) per client and per site.
 *
 * Transients are used so the limiter works without a persistent object
 * cache; the counters are best-effort and reset when the window expires.
 *
 * @since 2.0.7
 */
class RateLimiter {

	/**
	 * Transient name prefix.
	 *
	 * @since 2.0.7
	 *
	 * @var string
	 */
	const TRANSIENT_PREFIX = 'central_connect_rl_';

	/**
	 * Consume one attempt from a bucket.
	 *
	 * @since 2.0.7
	 *
	 * @param string $scope  Bucket identifier (e.g. "remote_validate:ip:203.0.113.1").
	 * @param int    $limit  Maximum attempts allowed per window.
	 * @param int    $window Window length in seconds.
	 *
	 * @return bool True when the attempt is allowed, false when the bucket is exhausted.
	 */
	public function attempt( $scope, $limit, $window ) {
		$limit  = max( 1, (int) $limit );
		$window = max( 1, (int) $window );
		$now    = time();
		$key    = $this->key( $scope );
		$bucket = $this->bucket( $key, $now, $window );

		if ( $bucket['count'] >= $limit ) {
			return false;
		}

		++$bucket['count'];
		set_transient( $key, $bucket, max( 1, $bucket['reset'] - $now ) );

		return true;
	}

	/**
	 * Give back one previously consumed attempt.
	 *
	 * Used when an attempt turned out to be legitimate and should not count
	 * against the bucket. The window end is left unchanged.
	 *
	 * @since 2.0.7
	 *
	 * @param string $scope  Bucket identifier.
	 * @param int    $window Window length in seconds.
	 *
	 * @return void
	 */
	public function release( $scope, $window ) {
		$now    = time();
		$key    = $this->key( $scope );
		$bucket = $this->bucket( $key, $now, max( 1, (int) $window ) );

		if ( $bucket['count'] <= 1 ) {
			delete_transient( $key );
			return;
		}

		--$bucket['count'];
		set_transient( $key, $bucket, max( 1, $bucket['reset'] - $now ) );
	}

	/**
	 * Check whether a bucket is exhausted without consuming an attempt.
	 *
	 * @since 2.0.7
	 *
	 * @param string $scope  Bucket identifier.
	 * @param int    $limit  Maximum attempts allowed per window.
	 * @param int    $window Window length in seconds.
	 *
	 * @return bool True when no attempts remain in the current window.
	 */
	public function isLimited( $scope, $limit, $window ) {
		$bucket = $this->bucket( $this->key( $scope ), time(), max( 1, (int) $window ) );

		return $bucket['count'] >= max( 1, (int) $limit );
	}

	/**
	 * Seconds until the bucket resets.
	 *
	 * @since 2.0.7
	 *
	 * @param string $scope  Bucket identifier.
	 * @param int    $window Window length in seconds.
	 *
	 * @return int Seconds remaining (0 when the bucket is empty or expired).
	 */
	public function retryAfter( $scope, $window ) {
		$now    = time();
		$bucket = $this->bucket( $this->key( $scope ), $now, max( 1, (int) $window ) );

		if ( 0 === $bucket['count'] ) {
			return 0;
		}

		return max( 0, (int) $bucket['reset'] - $now );
	}

	/**
	 * Clear a bucket.
	 *
	 * @since 2.0.7
	 *
	 * @param string $scope Bucket identifier.
	 *
	 * @return void
	 */
	public function reset( $scope ) {
		delete_transient( $this->key( $scope ) );
	}

	/**
	 * Determine the client IP used for per-client buckets.
	 *
	 * Only REMOTE_ADDR is trusted. Sites behind a reverse proxy or CDN can
	 * supply the real client address with the `central_connect_client_ip`
	 * filter. Forwarded headers are deliberately not read here because a
	 * caller could spoof them to escape the per-client limit.
	 *
	 * @since 2.0.7
	 *
	 * @return string IP address, or "unknown" when none is available.
	 */
	public static function clientIp() {
		$ip = '';
		if ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
			$candidate = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
			if ( filter_var( $candidate, FILTER_VALIDATE_IP ) ) {
				$ip = $candidate;
			}
		}

		/**
		 * Filters the client IP used for authentication rate limiting.
		 *
		 * @since 2.0.7
		 *
		 * @param string $ip Client IP from REMOTE_ADDR (empty when unavailable).
		 */
		$filtered = apply_filters( 'central_connect_client_ip', $ip );
		if ( is_string( $filtered ) && filter_var( $filtered, FILTER_VALIDATE_IP ) ) {
			$ip = $filtered;
		}

		return '' !== $ip ? $ip : 'unknown';
	}

	/**
	 * Build the transient key for a scope.
	 *
	 * @since 2.0.7
	 *
	 * @param string $scope Bucket identifier.
	 *
	 * @return string
	 */
	private function key( $scope ) {
		return self::TRANSIENT_PREFIX . md5( (string) $scope );
	}

	/**
	 * Load the current bucket, starting a fresh window when expired.
	 *
	 * @since 2.0.7
	 *
	 * @param string $key    Transient key.
	 * @param int    $now    Current timestamp.
	 * @param int    $window Window length in seconds.
	 *
	 * @return array {
	 *     @type int $count Attempts consumed in the window.
	 *     @type int $reset Timestamp at which the window ends.
	 * }
	 */
	private function bucket( $key, $now, $window ) {
		$bucket = get_transient( $key );

		if ( ! is_array( $bucket ) || empty( $bucket['reset'] ) || (int) $bucket['reset'] <= $now ) {
			$bucket = array(
				'count' => 0,
				'reset' => $now + $window,
			);
		}

		$bucket['count'] = isset( $bucket['count'] ) ? (int) $bucket['count'] : 0;

		return $bucket;
	}
}
