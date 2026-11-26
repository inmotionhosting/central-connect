<?php
/**
 * Shared test case for Central Connect.
 *
 * Provides a mocked Central HTTP backend and REST helpers so tests can
 * assert exactly what the plugin sends upstream and when it refuses to.
 *
 * @package Central_Connect
 */

/**
 * Class: Central_Connect_Test_Case
 */
abstract class Central_Connect_Test_Case extends WP_UnitTestCase {

	/**
	 * Outbound HTTP requests captured during the test.
	 *
	 * @var array[] Each entry: array( 'url' => string, 'args' => array ).
	 */
	protected $http_requests = array();

	/**
	 * Canned response for the next outbound HTTP requests.
	 *
	 * @var array|WP_Error
	 */
	protected $http_response;

	/**
	 * Set up a mocked Central and a deterministic client address.
	 */
	public function set_up() {
		parent::set_up();

		$this->http_requests = array();
		$this->http_response = $this->central_response( array( 'is_valid' => false ) );

		add_filter( 'pre_http_request', array( $this, 'capture_http_request' ), 10, 3 );

		$_SERVER['REMOTE_ADDR']    = '203.0.113.10';
		$GLOBALS['wp_rest_server'] = null;
	}

	/**
	 * Tear down.
	 */
	public function tear_down() {
		remove_filter( 'pre_http_request', array( $this, 'capture_http_request' ), 10 );
		unset( $_SERVER['HTTP_X_BGC_AUTH'] );
		$GLOBALS['wp_rest_server'] = null;

		parent::tear_down();
	}

	/**
	 * Record an outbound request and return the canned response.
	 *
	 * @param false|array|WP_Error $preempt Preempt value.
	 * @param array                $args    Request args.
	 * @param string               $url     Request URL.
	 * @return array|WP_Error
	 */
	public function capture_http_request( $preempt, $args, $url ) {
		$this->http_requests[] = array(
			'url'  => $url,
			'args' => $args,
		);

		return $this->http_response;
	}

	/**
	 * Build a fake Central HTTP response.
	 *
	 * @param array $body JSON body.
	 * @param int   $code HTTP status.
	 * @return array
	 */
	protected function central_response( $body, $code = 200 ) {
		return array(
			'headers'  => array(),
			'body'     => wp_json_encode( $body ),
			'response' => array(
				'code'    => $code,
				'message' => 'OK',
			),
			'cookies'  => array(),
			'filename' => null,
		);
	}

	/**
	 * Tell the mocked Central to accept the next token(s).
	 */
	protected function central_accepts() {
		$this->http_response = $this->central_response( array( 'is_valid' => true ) );
	}

	/**
	 * Tell the mocked Central to reject the next token(s).
	 */
	protected function central_rejects() {
		$this->http_response = $this->central_response( array( 'is_valid' => false ), 403 );
	}

	/**
	 * Mark the site as connected to Central.
	 *
	 * @param string      $key            API key.
	 * @param string|null $environment_id Stored environment id, if any.
	 */
	protected function connect_site( $key = 'test-site-key', $environment_id = null ) {
		update_option( 'boldgrid_api_key', $key );
		if ( null !== $environment_id ) {
			\Central\Connect\Option\Connect::update( 'environment_id', $environment_id );
		}
	}

	/**
	 * Dispatch a REST request against the plugin's namespace.
	 *
	 * @param string $method HTTP method.
	 * @param string $route  Route, e.g. '/bgc/v1/auth/'.
	 * @param array  $params Body params.
	 * @param array  $headers Request headers.
	 * @return WP_REST_Response
	 */
	protected function rest( $method, $route, $params = array(), $headers = array() ) {
		$request = new WP_REST_Request( $method, $route );
		foreach ( $params as $key => $value ) {
			$request->set_param( $key, $value );
		}
		foreach ( $headers as $key => $value ) {
			$request->set_header( $key, $value );
		}

		return rest_get_server()->dispatch( $request );
	}

	/**
	 * Build the body array the plugin sent to Central on the n-th request.
	 *
	 * @param int $index Request index.
	 * @return array
	 */
	protected function sent_body( $index = 0 ) {
		$this->assertArrayHasKey( $index, $this->http_requests, 'Expected an outbound request at index ' . $index );
		$body = $this->http_requests[ $index ]['args']['body'];
		if ( is_string( $body ) ) {
			parse_str( $body, $body );
		}

		return $body;
	}
}
