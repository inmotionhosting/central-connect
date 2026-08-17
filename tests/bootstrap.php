<?php
/**
 * WordPress-free bootstrap for Central Connect REST CORS tests.
 *
 * @package Central_Connect
 */

error_reporting( E_ALL );
ini_set( 'display_errors', '1' );

$GLOBALS['wp_recorded_filters']  = array();
$GLOBALS['wp_recorded_actions']  = array();
$GLOBALS['wp_recorded_removals'] = array();
$GLOBALS['wp_filters']           = array();
$GLOBALS['wp_filter_calls']      = array();
$GLOBALS['wp_did_actions']       = array();

if ( ! function_exists( 'add_filter' ) ) {
	/**
	 * Record and store a WordPress filter callback.
	 *
	 * @param string   $hook          Hook name.
	 * @param callable $callback      Callback.
	 * @param int      $priority      Priority.
	 * @param int      $accepted_args Accepted argument count.
	 * @return true
	 */
	function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
		$GLOBALS['wp_recorded_filters'][] = array(
			'hook'          => $hook,
			'callback'      => $callback,
			'priority'      => $priority,
			'accepted_args' => $accepted_args,
		);

		if ( ! isset( $GLOBALS['wp_filters'][ $hook ] ) ) {
			$GLOBALS['wp_filters'][ $hook ] = array();
		}
		if ( ! isset( $GLOBALS['wp_filters'][ $hook ][ $priority ] ) ) {
			$GLOBALS['wp_filters'][ $hook ][ $priority ] = array();
		}

		$GLOBALS['wp_filters'][ $hook ][ $priority ][] = array(
			'callback'      => $callback,
			'accepted_args' => $accepted_args,
		);

		return true;
	}
}

if ( ! function_exists( 'add_action' ) ) {
	/**
	 * Record and store a WordPress action callback.
	 *
	 * @param string   $hook          Hook name.
	 * @param callable $callback      Callback.
	 * @param int      $priority      Priority.
	 * @param int      $accepted_args Accepted argument count.
	 * @return true
	 */
	function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
		$GLOBALS['wp_recorded_actions'][] = array(
			'hook'          => $hook,
			'callback'      => $callback,
			'priority'      => $priority,
			'accepted_args' => $accepted_args,
		);

		return add_filter( $hook, $callback, $priority, $accepted_args );
	}
}

if ( ! function_exists( 'remove_filter' ) ) {
	/**
	 * Record a WordPress filter removal.
	 *
	 * @param string   $hook     Hook name.
	 * @param callable $callback Callback.
	 * @param int      $priority Priority.
	 * @return true
	 */
	function remove_filter( $hook, $callback, $priority = 10 ) {
		$GLOBALS['wp_recorded_removals'][] = array(
			'hook'     => $hook,
			'callback' => $callback,
			'priority' => $priority,
		);

		return true;
	}
}

if ( ! function_exists( 'apply_filters' ) ) {
	/**
	 * Apply recorded filter callbacks.
	 *
	 * @param string $tag   Filter name.
	 * @param mixed  $value Filtered value.
	 * @return mixed
	 */
	function apply_filters( $tag, $value ) {
		$GLOBALS['wp_filter_calls'][] = $tag;

		$args = array_slice( func_get_args(), 1 );
		if ( empty( $GLOBALS['wp_filters'][ $tag ] ) ) {
			return $value;
		}

		$priorities = $GLOBALS['wp_filters'][ $tag ];
		ksort( $priorities, SORT_NUMERIC );

		foreach ( $priorities as $callbacks ) {
			foreach ( $callbacks as $entry ) {
				$pass     = array_slice( $args, 0, $entry['accepted_args'] );
				$args[0]  = call_user_func_array( $entry['callback'], $pass );
			}
		}

		return $args[0];
	}
}

if ( ! function_exists( 'did_action' ) ) {
	/**
	 * Return how many times an action has been marked as done.
	 *
	 * @param string $tag Action name.
	 * @return int
	 */
	function did_action( $tag ) {
		if ( ! isset( $GLOBALS['wp_did_actions'][ $tag ] ) ) {
			return 0;
		}

		return (int) $GLOBALS['wp_did_actions'][ $tag ];
	}
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	/**
	 * Minimal sanitize_text_field stand-in.
	 *
	 * @param string $str Raw value.
	 * @return string
	 */
	function sanitize_text_field( $str ) {
		$str = is_string( $str ) ? $str : '';
		$str = preg_replace( '/[\r\n\t ]+/', ' ', $str );

		return trim( strip_tags( $str ) );
	}
}

if ( ! function_exists( 'wp_unslash' ) ) {
	/**
	 * Minimal wp_unslash stand-in.
	 *
	 * @param mixed $value Raw value.
	 * @return mixed
	 */
	function wp_unslash( $value ) {
		if ( is_string( $value ) ) {
			return stripslashes( $value );
		}

		return $value;
	}
}

if ( ! function_exists( 'get_http_origin' ) ) {
	/**
	 * Minimal get_http_origin stand-in.
	 *
	 * @return string
	 */
	function get_http_origin() {
		$origin = '';
		if ( ! empty( $_SERVER['HTTP_ORIGIN'] ) ) {
			$origin = $_SERVER['HTTP_ORIGIN'];
		}

		return apply_filters( 'http_origin', $origin );
	}
}

if ( ! class_exists( 'WP_REST_Request' ) ) {
	/**
	 * Test double for WP_REST_Request.
	 */
	class WP_REST_Request {
		/**
		 * REST route.
		 *
		 * @var string
		 */
		protected $route = '';

		/**
		 * Request headers, keyed lowercase.
		 *
		 * @var array
		 */
		protected $headers = array();

		/**
		 * Set the REST route.
		 *
		 * @param string $route Route.
		 * @return $this
		 */
		public function set_route( $route ) {
			$this->route = $route;

			return $this;
		}

		/**
		 * Get the REST route.
		 *
		 * @return string
		 */
		public function get_route() {
			return $this->route;
		}

		/**
		 * Set a request header.
		 *
		 * @param string $name  Header name.
		 * @param string $value Header value.
		 * @return $this
		 */
		public function set_header( $name, $value ) {
			$this->headers[ strtolower( $name ) ] = $value;

			return $this;
		}

		/**
		 * Get a request header.
		 *
		 * @param string $name Header name.
		 * @return string|null
		 */
		public function get_header( $name ) {
			$key = strtolower( $name );
			if ( ! isset( $this->headers[ $key ] ) ) {
				return null;
			}

			return $this->headers[ $key ];
		}
	}
}

if ( ! class_exists( 'WP_REST_Response' ) ) {
	/**
	 * Test double for WP_REST_Response.
	 */
	class WP_REST_Response {
	}
}

if ( ! class_exists( 'WP_REST_Server' ) ) {
	/**
	 * Test double for WP_REST_Server.
	 */
	class WP_REST_Server {
	}
}

require_once dirname( __DIR__ ) . '/autoload.php';
require_once __DIR__ . '/RecordingHeaderEmitter.php';
require_once __DIR__ . '/RestCorsTest.php';
