<?php
/**
 * Behavioral tests for Central Connect REST CORS.
 *
 * Expected production API (not defined in this harness):
 * - Central\Connect\Rest\Cors::__construct( $emitter, $allowlistCandidates = array() )
 * - Central\Connect\Rest\Cors::sendRestCorsHeaders( $served, $result, $request, $server )
 * - Central\Connect\Rest\Cors::sendHeadDiscoveryHeaders()
 *
 * $emitter is duck-typed with header( $header, $replace = true ) and header_remove( $name ).
 * $allowlistCandidates are extra origin strings merged with the built-in allowlist.
 *
 * @package Central_Connect
 */

/**
 * Class: RestCorsTest
 */
class RestCorsTest {

	/**
	 * Recording header emitter for the current test.
	 *
	 * @var RecordingHeaderEmitter
	 */
	protected $emitter;

	/**
	 * Reset hook recordings and request globals.
	 *
	 * @return void
	 */
	public function setUp() {
		$GLOBALS['wp_recorded_filters']  = array();
		$GLOBALS['wp_recorded_actions']  = array();
		$GLOBALS['wp_recorded_removals'] = array();
		$GLOBALS['wp_filters']           = array();
		$GLOBALS['wp_filter_calls']      = array();
		$GLOBALS['wp_did_actions']       = array();

		unset( $_SERVER['HTTP_ORIGIN'] );
		unset( $_SERVER['REQUEST_METHOD'] );

		$this->emitter = new RecordingHeaderEmitter();
	}

	/**
	 * Clear request globals after a test.
	 *
	 * @return void
	 */
	public function tearDown() {
		unset( $_SERVER['HTTP_ORIGIN'] );
		unset( $_SERVER['REQUEST_METHOD'] );
	}

	/**
	 * Server must register rest_pre_serve_request at PHP_INT_MAX with 4 accepted args.
	 *
	 * @return void
	 */
	public function testServerRegistersRestPreServeRequestAtPhpIntMaxWithFourArgs() {
		$this->registerServerCorsHooks();

		$registration = $this->findRecordedFilter( 'rest_pre_serve_request' );
		$this->assertTrue( null !== $registration, 'Server must register rest_pre_serve_request' );

		$parameter_count = $this->callbackParameterCount( $registration['callback'] );
		$problems        = array();
		if ( PHP_INT_MAX !== $registration['priority'] ) {
			$problems[] = 'priority must be PHP_INT_MAX, got ' . var_export( $registration['priority'], true );
		}
		if ( 4 !== $registration['accepted_args'] ) {
			$problems[] = 'accepted_args must be 4, got ' . var_export( $registration['accepted_args'], true );
		}
		if ( $parameter_count < 4 ) {
			$problems[] = 'callback must declare at least 4 parameters, got ' . $parameter_count;
		}
		$this->assertTrue( empty( $problems ), implode( '; ', $problems ) );
	}

	/**
	 * Server must not globally remove WordPress core rest_send_cors_headers.
	 *
	 * @return void
	 */
	public function testServerDoesNotRemoveCoreRestSendCorsHeaders() {
		$this->registerServerCorsHooks();

		foreach ( $GLOBALS['wp_recorded_removals'] as $removal ) {
			$callback = $removal['callback'];
			if ( 'rest_pre_serve_request' === $removal['hook'] && 'rest_send_cors_headers' === $callback ) {
				throw new Exception( 'Server must not globally remove rest_send_cors_headers' );
			}
		}
	}

	/**
	 * Server must still register the send_headers HEAD discovery action.
	 *
	 * @return void
	 */
	public function testServerRegistersSendHeadersAction() {
		$this->registerServerCorsHooks();

		$found = false;
		foreach ( $GLOBALS['wp_recorded_actions'] as $action ) {
			if ( 'send_headers' === $action['hook'] ) {
				$found = true;
				break;
			}
		}

		$this->assertTrue( $found, 'Server must register a send_headers action for HEAD discovery' );
	}

	/**
	 * Access-Control-Allow-Origin must never be *.
	 *
	 * @return void
	 */
	public function testNeverSendsWildcardAllowOrigin() {
		$cors = $this->newCors( array( 'https://central.inmotionhosting.com', '*' ) );

		$this->serveRest( $cors, '/bgc/v1', '*' );
		$this->assertNoWildcardAcao( $this->emitter );

		$this->emitter = new RecordingHeaderEmitter();
		$cors          = $this->newCors( array( 'https://central.inmotionhosting.com' ) );
		$this->serveRest( $cors, '/bgc/v1', 'https://central.inmotionhosting.com' );
		$this->assertNoWildcardAcao( $this->emitter );

		$this->emitter = new RecordingHeaderEmitter();
		$cors          = $this->newCors( array( 'https://central.inmotionhosting.com' ) );
		$this->serveRest( $cors, '/bgc/v1', 'https://evil.example' );
		$this->assertNoWildcardAcao( $this->emitter );
	}

	/**
	 * Access-Control-Allow-Credentials must never be sent.
	 *
	 * @return void
	 */
	public function testNeverSendsAllowCredentials() {
		$cors = $this->newCors( array( 'https://central.inmotionhosting.com' ) );

		$this->serveRest( $cors, '/bgc/v1', 'https://central.inmotionhosting.com' );
		$this->assertNoCredentials( $this->emitter );

		$this->emitter = new RecordingHeaderEmitter();
		$cors          = $this->newCors( array( 'https://central.inmotionhosting.com' ) );
		$this->serveRest( $cors, '/bgc/v1', 'https://evil.example' );
		$this->assertNoCredentials( $this->emitter );

		$this->emitter = new RecordingHeaderEmitter();
		$cors          = $this->newCors( array( 'https://central.inmotionhosting.com' ) );
		$_SERVER['REQUEST_METHOD'] = 'HEAD';
		$this->setOrigin( 'https://central.inmotionhosting.com' );
		$cors->sendHeadDiscoveryHeaders();
		$this->assertNoCredentials( $this->emitter );
	}

	/**
	 * Attacker origin https://evil.example must not receive ACAO on in-scope REST.
	 *
	 * @return void
	 */
	public function testAttackerOriginEvilExampleGetsNoAcao() {
		$cors = $this->newCors( array( 'https://central.inmotionhosting.com' ) );
		$this->serveRest( $cors, '/bgc/v1', 'https://evil.example' );

		$this->assertNoAcao( $this->emitter );
		$this->assertNoCredentials( $this->emitter );
		$this->assertNoWildcardAcao( $this->emitter );
		$this->assertVaryIncludesOrigin( $this->emitter );
	}

	/**
	 * Attacker origin http://localhost must not receive ACAO on in-scope REST.
	 *
	 * @return void
	 */
	public function testAttackerOriginLocalhostGetsNoAcao() {
		$cors = $this->newCors( array( 'https://central.inmotionhosting.com' ) );
		$this->serveRest( $cors, '/bgc/v1/plugins', 'http://localhost' );

		$this->assertNoAcao( $this->emitter );
		$this->assertNoCredentials( $this->emitter );
		$this->assertVaryIncludesOrigin( $this->emitter );
	}

	/**
	 * Attacker origin https://192.168.1.1 must not receive ACAO on in-scope REST.
	 *
	 * @return void
	 */
	public function testAttackerOriginPrivateIpGetsNoAcao() {
		$cors = $this->newCors( array( 'https://central.inmotionhosting.com' ) );
		$this->serveRest( $cors, '/', 'https://192.168.1.1' );

		$this->assertNoAcao( $this->emitter );
		$this->assertNoCredentials( $this->emitter );
		$this->assertVaryIncludesOrigin( $this->emitter );
	}

	/**
	 * Origin null must not receive ACAO on in-scope REST.
	 *
	 * @return void
	 */
	public function testNullOriginGetsNoAcao() {
		$cors = $this->newCors( array( 'https://central.inmotionhosting.com' ) );
		$this->serveRest( $cors, '/bgc/v1', 'null' );

		$this->assertNoAcao( $this->emitter );
		$this->assertNoCredentials( $this->emitter );
		$this->assertVaryIncludesOrigin( $this->emitter );
	}

	/**
	 * Allowlisted portal origin on REST index / gets ACAO, no credentials, Vary Origin.
	 *
	 * @return void
	 */
	public function testAllowlistedPortalOriginOnRestIndex() {
		$cors = $this->newCors( array( 'https://central.inmotionhosting.com' ) );
		$this->serveRest( $cors, '/', 'https://central.inmotionhosting.com' );

		$this->assertSame(
			'https://central.inmotionhosting.com',
			$this->emitter->headerValue( 'Access-Control-Allow-Origin' ),
			'ACAO must equal the allowlisted origin on /'
		);
		$this->assertNoCredentials( $this->emitter );
		$this->assertNoWildcardAcao( $this->emitter );
		$this->assertVaryIncludesOrigin( $this->emitter );
	}

	/**
	 * Allowlisted portal origin on /bgc/v1 gets ACAO, no credentials, Vary Origin.
	 *
	 * @return void
	 */
	public function testAllowlistedPortalOriginOnBgcV1() {
		$cors = $this->newCors( array( 'https://central.inmotionhosting.com' ) );
		$this->serveRest( $cors, '/bgc/v1', 'https://central.inmotionhosting.com' );

		$this->assertSame(
			'https://central.inmotionhosting.com',
			$this->emitter->headerValue( 'Access-Control-Allow-Origin' ),
			'ACAO must equal the allowlisted origin on /bgc/v1'
		);
		$this->assertNoCredentials( $this->emitter );
		$this->assertVaryIncludesOrigin( $this->emitter );
	}

	/**
	 * Allowlisted portal origin on /bgc/v1/plugins gets ACAO, no credentials, Vary Origin.
	 *
	 * @return void
	 */
	public function testAllowlistedPortalOriginOnPluginsRoute() {
		$cors = $this->newCors( array( 'https://central.inmotionhosting.com' ) );
		$this->serveRest( $cors, '/bgc/v1/plugins', 'https://central.inmotionhosting.com' );

		$this->assertSame(
			'https://central.inmotionhosting.com',
			$this->emitter->headerValue( 'Access-Control-Allow-Origin' ),
			'ACAO must equal the allowlisted origin on /bgc/v1/plugins'
		);
		$this->assertNoCredentials( $this->emitter );
		$this->assertVaryIncludesOrigin( $this->emitter );
	}

	/**
	 * Allowed in-scope responses expose Link and the shipped method/header lists.
	 *
	 * @return void
	 */
	public function testAllowlistedInScopeEmitsMethodsHeadersAndExpose() {
		$cors = $this->newCors( array( 'https://central.inmotionhosting.com' ) );
		$this->serveRest( $cors, '/bgc/v1', 'https://central.inmotionhosting.com' );

		$expose = $this->emitter->headerValue( 'Access-Control-Expose-Headers' );
		$this->assertTrue( null !== $expose && false !== stripos( $expose, 'Link' ), 'must expose Link' );

		$methods = $this->headerTokens( $this->emitter, 'Access-Control-Allow-Methods' );
		foreach ( array( 'HEAD', 'OPTIONS', 'GET', 'POST', 'PUT', 'PATCH', 'DELETE' ) as $method ) {
			$this->assertTrue(
				in_array( strtolower( $method ), $methods, true ),
				'Allow-Methods must include ' . $method
			);
		}

		$headers = $this->headerTokens( $this->emitter, 'Access-Control-Allow-Headers' );
		foreach ( array( 'Authorization', 'X-WP-Nonce', 'Content-Type', 'Content-Disposition', 'Content-MD5', 'X-BGC-Auth' ) as $header ) {
			$this->assertTrue(
				in_array( strtolower( $header ), $headers, true ),
				'Allow-Headers must include ' . $header
			);
		}
	}

	/**
	 * Denied in-scope origins scrub pre-existing ACAO/ACAC and still set Vary Origin.
	 *
	 * @return void
	 */
	public function testDeniedInScopeScrubsPreexistingCorsAndSetsVary() {
		$this->seedPreexistingCors();
		$cors = $this->newCors( array( 'https://central.inmotionhosting.com' ) );
		$this->serveRest( $cors, '/bgc/v1', 'https://evil.example' );

		$this->assertScrubbedCorsHeaders( $this->emitter );
		$this->assertNoAcao( $this->emitter );
		$this->assertNoCredentials( $this->emitter );
		$this->assertNoCorsMethodHeaders( $this->emitter );
		$this->assertVaryIncludesOrigin( $this->emitter );
	}

	/**
	 * Allowed in-scope origins scrub pre-existing ACAO/ACAC before emitting policy.
	 *
	 * @return void
	 */
	public function testAllowedInScopeScrubsPreexistingThenEmitsPolicy() {
		$this->seedPreexistingCors();
		$cors = $this->newCors( array( 'https://central.inmotionhosting.com' ) );
		$this->serveRest( $cors, '/bgc/v1', 'https://central.inmotionhosting.com' );

		$this->assertScrubbedCorsHeaders( $this->emitter );
		$this->assertSame(
			'https://central.inmotionhosting.com',
			$this->emitter->headerValue( 'Access-Control-Allow-Origin' ),
			'ACAO must be the allowlisted origin after scrub'
		);
		$this->assertNoCredentials( $this->emitter );
		$this->assertTrue(
			'https://preexisting.example' !== $this->emitter->headerValue( 'Access-Control-Allow-Origin' ),
			'pre-existing ACAO must not remain'
		);
	}

	/**
	 * Missing Origin on an in-scope route still sets Vary Origin and does not emit ACAO.
	 *
	 * @return void
	 */
	public function testMissingOriginInScopeSetsVaryWithoutAcao() {
		$cors = $this->newCors( array( 'https://central.inmotionhosting.com' ) );
		$this->serveRest( $cors, '/bgc/v1', null );

		$this->assertNoAcao( $this->emitter );
		$this->assertNoCredentials( $this->emitter );
		$this->assertVaryIncludesOrigin( $this->emitter );
	}

	/**
	 * Out-of-scope /wp/v2/posts must not scrub or emit CORS headers.
	 *
	 * @return void
	 */
	public function testOutOfScopeWpV2PostsDoesNotTouchCorsHeaders() {
		$this->seedPreexistingCors();
		$snapshot = $this->emitter->lines;
		$cors     = $this->newCors( array( 'https://central.inmotionhosting.com' ) );
		$this->serveRest( $cors, '/wp/v2/posts', 'https://central.inmotionhosting.com' );

		$this->assertSame( $snapshot, $this->emitter->lines, 'out-of-scope /wp/v2/posts must not change headers' );
		$this->assertTrue( empty( $this->emitter->removed ), 'out-of-scope /wp/v2/posts must not header_remove' );
	}

	/**
	 * Out-of-scope /bgc/v1evil must not scrub or emit CORS headers.
	 *
	 * @return void
	 */
	public function testOutOfScopeBgcV1evilDoesNotTouchCorsHeaders() {
		$this->seedPreexistingCors();
		$snapshot = $this->emitter->lines;
		$cors     = $this->newCors( array( 'https://central.inmotionhosting.com' ) );
		$this->serveRest( $cors, '/bgc/v1evil', 'https://evil.example' );

		$this->assertSame( $snapshot, $this->emitter->lines, 'out-of-scope /bgc/v1evil must not change headers' );
		$this->assertTrue( empty( $this->emitter->removed ), 'out-of-scope /bgc/v1evil must not header_remove' );
	}

	/**
	 * HEAD discovery emits ACAO only for an allowlisted Origin and never scrubs.
	 *
	 * @return void
	 */
	public function testHeadDiscoveryAllowlistedEmitsAcaoWithoutScrub() {
		$_SERVER['REQUEST_METHOD'] = 'HEAD';
		$this->setOrigin( 'https://central.inmotionhosting.com' );
		$cors = $this->newCors( array( 'https://central.inmotionhosting.com' ) );
		$cors->sendHeadDiscoveryHeaders();

		$this->assertSame(
			'https://central.inmotionhosting.com',
			$this->emitter->headerValue( 'Access-Control-Allow-Origin' ),
			'HEAD discovery must emit ACAO for an allowlisted Origin'
		);
		$this->assertNoCredentials( $this->emitter );
		$this->assertNoWildcardAcao( $this->emitter );
		$this->assertTrue( empty( $this->emitter->removed ), 'HEAD discovery must never header_remove' );
	}

	/**
	 * HEAD discovery with an attacker Origin must not emit ACAO and must not scrub.
	 *
	 * @return void
	 */
	public function testHeadDiscoveryDeniedDoesNotEmitOrScrub() {
		$this->seedPreexistingCors();
		$snapshot                  = $this->emitter->lines;
		$_SERVER['REQUEST_METHOD'] = 'HEAD';
		$this->setOrigin( 'https://evil.example' );
		$cors = $this->newCors( array( 'https://central.inmotionhosting.com' ) );
		$cors->sendHeadDiscoveryHeaders();

		$this->assertSame( $snapshot, $this->emitter->lines, 'denied HEAD discovery must not change headers' );
		$this->assertTrue( empty( $this->emitter->removed ), 'HEAD discovery must never header_remove' );
		$this->assertTrue(
			'https://evil.example' !== $this->emitter->headerValue( 'Access-Control-Allow-Origin' ),
			'HEAD discovery must not reflect an unvalidated Origin'
		);
	}

	/**
	 * HEAD discovery must not emit after rest_api_init has already run.
	 *
	 * @return void
	 */
	public function testHeadDiscoveryDoesNotEmitAfterRestApiInit() {
		$GLOBALS['wp_did_actions']['rest_api_init'] = 1;
		$_SERVER['REQUEST_METHOD']                    = 'HEAD';
		$this->setOrigin( 'https://central.inmotionhosting.com' );
		$cors = $this->newCors( array( 'https://central.inmotionhosting.com' ) );
		$cors->sendHeadDiscoveryHeaders();

		$this->assertNoAcao( $this->emitter );
		$this->assertTrue( empty( $this->emitter->removed ), 'HEAD discovery must never header_remove' );
	}

	/**
	 * Invalid allowlist candidates are discarded and never used as ACAO.
	 *
	 * @return void
	 */
	public function testInvalidAllowlistCandidatesAreDiscarded() {
		$candidates = array(
			'*',
			'null',
			'http://central.inmotionhosting.com',
			'https://192.168.1.1',
			'https://evil.example/path',
			'https://user:pass@central.inmotionhosting.com',
			'https://central.inmotionhosting.com',
		);
		$cors = $this->newCors( $candidates );

		$this->emitter->reset();
		$this->serveRest( $cors, '/bgc/v1', '*' );
		$this->assertNoAcao( $this->emitter );
		$this->assertNoWildcardAcao( $this->emitter );

		$this->emitter->reset();
		$this->serveRest( $cors, '/bgc/v1', 'null' );
		$this->assertNoAcao( $this->emitter );

		$this->emitter->reset();
		$this->serveRest( $cors, '/bgc/v1', 'http://central.inmotionhosting.com' );
		$this->assertNoAcao( $this->emitter );

		$this->emitter->reset();
		$this->serveRest( $cors, '/bgc/v1', 'https://192.168.1.1' );
		$this->assertNoAcao( $this->emitter );

		$this->emitter->reset();
		$this->serveRest( $cors, '/bgc/v1', 'https://evil.example/path' );
		$this->assertNoAcao( $this->emitter );

		$this->emitter->reset();
		$this->serveRest( $cors, '/bgc/v1', 'https://user:pass@central.inmotionhosting.com' );
		$this->assertNoAcao( $this->emitter );

		$this->emitter->reset();
		$this->serveRest( $cors, '/bgc/v1', 'https://central.inmotionhosting.com' );
		$this->assertSame(
			'https://central.inmotionhosting.com',
			$this->emitter->headerValue( 'Access-Control-Allow-Origin' ),
			'valid allowlist candidate must still be accepted'
		);
		$this->assertNoCredentials( $this->emitter );
	}

	/**
	 * Built-in production v2 portal origin is allowlisted without being injected.
	 *
	 * @return void
	 */
	public function testBuiltInV2PortalOriginIsAllowlisted() {
		$cors = $this->newCors( array() );
		$this->serveRest( $cors, '/bgc/v1', 'https://v2.central.inmotionhosting.com' );

		$this->assertSame(
			'https://v2.central.inmotionhosting.com',
			$this->emitter->headerValue( 'Access-Control-Allow-Origin' ),
			'built-in v2 portal origin must be allowlisted'
		);
		$this->assertNoCredentials( $this->emitter );
	}

	/**
	 * Extra origins from central_connect_allowed_cors_origins are honored after validation.
	 *
	 * @return void
	 */
	public function testFilterCanAddExtraAllowedOrigin() {
		add_filter(
			'central_connect_allowed_cors_origins',
			array( $this, 'addExtraPortalOrigin' ),
			10,
			1
		);

		$cors = $this->newCors( array() );
		$this->serveRest( $cors, '/bgc/v1', 'https://portal.example' );

		$this->assertTrue(
			in_array( 'central_connect_allowed_cors_origins', $GLOBALS['wp_filter_calls'], true ),
			'must apply central_connect_allowed_cors_origins'
		);
		$this->assertSame(
			'https://portal.example',
			$this->emitter->headerValue( 'Access-Control-Allow-Origin' ),
			'filter-provided origin must be allowlisted after validation'
		);
		$this->assertNoCredentials( $this->emitter );
	}

	/**
	 * Config/branding URLs with a path still allowlist the portal origin.
	 *
	 * @return void
	 */
	public function testConfigUrlWithPathAllowlistsPortalOrigin() {
		$cors = $this->newCors( array( 'https://central.inmotionhosting.com/wordpress' ) );
		$this->serveRest( $cors, '/bgc/v1', 'https://central.inmotionhosting.com' );

		$this->assertSame(
			'https://central.inmotionhosting.com',
			$this->emitter->headerValue( 'Access-Control-Allow-Origin' ),
			'config URL path must be ignored when building the allowlist'
		);
		$this->assertNoCredentials( $this->emitter );

		$this->emitter->reset();
		$this->serveRest( $cors, '/bgc/v1', 'https://central.inmotionhosting.com/wordpress' );
		$this->assertNoAcao( $this->emitter );
	}

	/**
	 * Default HTTPS port :443 is stripped so the canonical origin matches.
	 *
	 * @return void
	 */
	public function testCanonicalPort443MatchesAllowlistedOrigin() {
		$cors = $this->newCors( array( 'https://central.inmotionhosting.com' ) );
		$this->serveRest( $cors, '/bgc/v1', 'https://central.inmotionhosting.com:443' );

		$this->assertSame(
			'https://central.inmotionhosting.com',
			$this->emitter->headerValue( 'Access-Control-Allow-Origin' ),
			'ACAO must be the canonical origin without default port 443'
		);
		$this->assertNoCredentials( $this->emitter );
	}

	/**
	 * Existing Vary values are preserved when Origin is merged on in-scope routes.
	 *
	 * @return void
	 */
	public function testVaryPreservesExistingValuesOnInScopeRoutes() {
		$this->emitter->header( 'Vary: Accept-Encoding' );
		$cors = $this->newCors( array( 'https://central.inmotionhosting.com' ) );
		$this->serveRest( $cors, '/bgc/v1', 'https://evil.example' );

		$tokens = $this->headerTokens( $this->emitter, 'Vary' );
		$this->assertTrue( in_array( 'origin', $tokens, true ), 'Vary must include Origin' );
		$this->assertTrue( in_array( 'accept-encoding', $tokens, true ), 'Vary must preserve Accept-Encoding' );
	}

	/**
	 * rest_pre_serve_request callback must return the incoming $served value.
	 *
	 * @return void
	 */
	public function testRestCallbackReturnsServedUnchanged() {
		$cors     = $this->newCors( array( 'https://central.inmotionhosting.com' ) );
		$request  = $this->requestWithOrigin( '/bgc/v1', 'https://central.inmotionhosting.com' );
		$returned = $cors->sendRestCorsHeaders( true, new WP_REST_Response(), $request, new WP_REST_Server() );

		$this->assertSame( true, $returned, 'sendRestCorsHeaders must return the incoming $served value' );
	}

	/**
	 * Filter callback that appends an extra portal origin.
	 *
	 * @param array $origins Origin list.
	 * @return array
	 */
	public function addExtraPortalOrigin( $origins ) {
		if ( ! is_array( $origins ) ) {
			$origins = array();
		}
		$origins[] = 'https://portal.example';

		return $origins;
	}

	/**
	 * Register Server CORS hooks via the private registerCorsHooks method.
	 *
	 * @return void
	 */
	protected function registerServerCorsHooks() {
		$server = new Central\Connect\Rest\Server();
		$method = new ReflectionMethod( $server, 'registerCorsHooks' );
		$method->setAccessible( true );
		$method->invoke( $server );
	}

	/**
	 * Create a Cors instance with the recording emitter.
	 *
	 * @param array $candidates Extra allowlist candidates.
	 * @return \Central\Connect\Rest\Cors
	 */
	protected function newCors( $candidates ) {
		$this->requireCors();

		return new Central\Connect\Rest\Cors( $this->emitter, $candidates );
	}

	/**
	 * Fail when the production Cors class is missing.
	 *
	 * @return void
	 */
	protected function requireCors() {
		if ( ! class_exists( 'Central\\Connect\\Rest\\Cors' ) ) {
			throw new Exception( 'Central\\Connect\\Rest\\Cors does not exist yet' );
		}
	}

	/**
	 * Drive sendRestCorsHeaders for a route and Origin.
	 *
	 * @param object      $cors   Cors instance.
	 * @param string      $route  REST route.
	 * @param string|null $origin Origin header value, or null when absent.
	 * @return mixed
	 */
	protected function serveRest( $cors, $route, $origin ) {
		$request = $this->requestWithOrigin( $route, $origin );

		return $cors->sendRestCorsHeaders( true, new WP_REST_Response(), $request, new WP_REST_Server() );
	}

	/**
	 * Build a REST request and populate Origin sources.
	 *
	 * @param string      $route  REST route.
	 * @param string|null $origin Origin header value, or null when absent.
	 * @return WP_REST_Request
	 */
	protected function requestWithOrigin( $route, $origin ) {
		if ( null === $origin ) {
			unset( $_SERVER['HTTP_ORIGIN'] );
		} else {
			$this->setOrigin( $origin );
		}

		$request = new WP_REST_Request();
		$request->set_route( $route );
		if ( null !== $origin ) {
			$request->set_header( 'Origin', $origin );
		}

		return $request;
	}

	/**
	 * Set the request Origin.
	 *
	 * @param string $origin Origin value.
	 * @return void
	 */
	protected function setOrigin( $origin ) {
		$_SERVER['HTTP_ORIGIN'] = $origin;
	}

	/**
	 * Seed ACAO/ACAC as if core CORS already ran.
	 *
	 * @return void
	 */
	protected function seedPreexistingCors() {
		$this->emitter->header( 'Access-Control-Allow-Origin: https://preexisting.example' );
		$this->emitter->header( 'Access-Control-Allow-Credentials: true' );
		$this->emitter->header( 'Access-Control-Allow-Methods: GET' );
		$this->emitter->header( 'Access-Control-Allow-Headers: Authorization' );
		$this->emitter->header( 'Access-Control-Expose-Headers: X-Preexisting' );
	}

	/**
	 * Find the rest_pre_serve_request registration recorded from Server.
	 *
	 * @param string $hook Hook name.
	 * @return array|null
	 */
	protected function findRecordedFilter( $hook ) {
		$match = null;
		foreach ( $GLOBALS['wp_recorded_filters'] as $entry ) {
			if ( $hook === $entry['hook'] ) {
				$match = $entry;
			}
		}

		return $match;
	}

	/**
	 * Count parameters on a recorded callback.
	 *
	 * @param callable $callback Callback.
	 * @return int
	 */
	protected function callbackParameterCount( $callback ) {
		if ( is_array( $callback ) ) {
			$reflection = new ReflectionMethod( $callback[0], $callback[1] );
		} elseif ( is_string( $callback ) && false !== strpos( $callback, '::' ) ) {
			$parts      = explode( '::', $callback, 2 );
			$reflection = new ReflectionMethod( $parts[0], $parts[1] );
		} else {
			$reflection = new ReflectionFunction( $callback );
		}

		return $reflection->getNumberOfParameters();
	}

	/**
	 * Assert ACAO is absent.
	 *
	 * @param RecordingHeaderEmitter $emitter Emitter.
	 * @return void
	 */
	protected function assertNoAcao( $emitter ) {
		$this->assertSame(
			null,
			$emitter->headerValue( 'Access-Control-Allow-Origin' ),
			'must not send Access-Control-Allow-Origin; headers=' . implode( '; ', $emitter->lines )
		);
	}

	/**
	 * Assert ACAC is absent.
	 *
	 * @param RecordingHeaderEmitter $emitter Emitter.
	 * @return void
	 */
	protected function assertNoCredentials( $emitter ) {
		$this->assertSame(
			null,
			$emitter->headerValue( 'Access-Control-Allow-Credentials' ),
			'must not send Access-Control-Allow-Credentials; headers=' . implode( '; ', $emitter->lines )
		);
	}

	/**
	 * Assert ACAO is never *.
	 *
	 * @param RecordingHeaderEmitter $emitter Emitter.
	 * @return void
	 */
	protected function assertNoWildcardAcao( $emitter ) {
		foreach ( $emitter->headerValues( 'Access-Control-Allow-Origin' ) as $value ) {
			if ( '*' === trim( $value ) ) {
				throw new Exception( 'must never send Access-Control-Allow-Origin: *' );
			}
		}
	}

	/**
	 * Assert Vary includes Origin.
	 *
	 * @param RecordingHeaderEmitter $emitter Emitter.
	 * @return void
	 */
	protected function assertVaryIncludesOrigin( $emitter ) {
		$tokens = $this->headerTokens( $emitter, 'Vary' );
		$this->assertTrue(
			in_array( 'origin', $tokens, true ),
			'Vary must include Origin; headers=' . implode( '; ', $emitter->lines )
		);
	}

	/**
	 * Assert in-scope scrubbing removed the core CORS headers.
	 *
	 * @param RecordingHeaderEmitter $emitter Emitter.
	 * @return void
	 */
	protected function assertScrubbedCorsHeaders( $emitter ) {
		$names = array(
			'Access-Control-Allow-Origin',
			'Access-Control-Allow-Credentials',
			'Access-Control-Allow-Methods',
			'Access-Control-Allow-Headers',
			'Access-Control-Expose-Headers',
		);
		foreach ( $names as $name ) {
			$this->assertTrue(
				$emitter->didRemove( $name ),
				'must header_remove ' . $name . ' on in-scope routes'
			);
		}
	}

	/**
	 * Assert denied in-scope responses do not re-emit method/header CORS lists.
	 *
	 * @param RecordingHeaderEmitter $emitter Emitter.
	 * @return void
	 */
	protected function assertNoCorsMethodHeaders( $emitter ) {
		$this->assertSame( null, $emitter->headerValue( 'Access-Control-Allow-Methods' ), 'denied in-scope must not send Allow-Methods' );
		$this->assertSame( null, $emitter->headerValue( 'Access-Control-Allow-Headers' ), 'denied in-scope must not send Allow-Headers' );
		$this->assertSame( null, $emitter->headerValue( 'Access-Control-Expose-Headers' ), 'denied in-scope must not send Expose-Headers' );
	}

	/**
	 * Split a header's values into lowercase tokens.
	 *
	 * @param RecordingHeaderEmitter $emitter Emitter.
	 * @param string                 $name    Header name.
	 * @return string[]
	 */
	protected function headerTokens( $emitter, $name ) {
		$tokens = array();
		foreach ( $emitter->headerValues( $name ) as $value ) {
			$parts = explode( ',', $value );
			foreach ( $parts as $part ) {
				$token = strtolower( trim( $part ) );
				if ( '' !== $token ) {
					$tokens[] = $token;
				}
			}
		}

		return $tokens;
	}

	/**
	 * Assert a condition is true.
	 *
	 * @param bool   $condition Condition.
	 * @param string $message   Failure message.
	 * @return void
	 */
	protected function assertTrue( $condition, $message ) {
		if ( ! $condition ) {
			throw new Exception( $message );
		}
	}

	/**
	 * Assert two values are identical.
	 *
	 * @param mixed  $expected Expected value.
	 * @param mixed  $actual   Actual value.
	 * @param string $message  Failure message.
	 * @return void
	 */
	protected function assertSame( $expected, $actual, $message ) {
		if ( $expected !== $actual ) {
			throw new Exception(
				$message . ' (expected ' . var_export( $expected, true ) . ', got ' . var_export( $actual, true ) . ')'
			);
		}
	}
}
