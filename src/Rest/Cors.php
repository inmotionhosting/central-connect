<?php
/**
 * File: Cors.php
 *
 * Restrict REST CORS to an explicit Central portal allowlist.
 *
 * @since      2.0.7
 * @package    Central\Connect\Rest
 * @author     InMotion Hosting <central-dev@inmotionhosting.com>
 * @link       https://boldgrid.com
 */

namespace Central\Connect\Rest;

/**
 * Class: Cors
 *
 * Emit CORS headers only for allowlisted Central portal origins.
 *
 * @since 2.0.7
 */
class Cors {

	/**
	 * Built-in production portal not always present in branding config.
	 *
	 * @since 2.0.7
	 * @var string
	 */
	const BUILTIN_V2_ORIGIN = 'https://v2.central.inmotionhosting.com';

	/**
	 * Injectable header emitter, or null to use PHP header()/header_remove().
	 *
	 * @since 2.0.7
	 * @var object|null
	 */
	protected $emitter;

	/**
	 * Extra allowlist candidates supplied by the caller.
	 *
	 * @since 2.0.7
	 * @var array
	 */
	protected $extraCandidates = array();

	/**
	 * Constructor.
	 *
	 * @since 2.0.7
	 *
	 * @param object|null $emitter              Duck-typed header($header, $replace) / header_remove($name).
	 * @param array       $allowlistCandidates Extra origin candidates.
	 */
	public function __construct( $emitter = null, $allowlistCandidates = array() ) {
		$this->emitter          = $emitter;
		$this->extraCandidates  = is_array( $allowlistCandidates ) ? $allowlistCandidates : array();
	}

	/**
	 * Apply CORS policy for an in-scope REST request.
	 *
	 * @since 2.0.7
	 *
	 * @param mixed             $served  Whether the request was already served.
	 * @param mixed             $result  REST result.
	 * @param \WP_REST_Request  $request Request.
	 * @param \WP_REST_Server   $server  Server.
	 * @return mixed Unchanged $served.
	 */
	public function sendRestCorsHeaders( $served, $result, $request, $server ) {
		$route = '';
		if ( is_object( $request ) && method_exists( $request, 'get_route' ) ) {
			$route = $request->get_route();
		}
		if ( ! $this->isInScopeRestRoute( $route ) ) {
			return $served;
		}

		$this->scrubCorsHeaders();

		$origin     = $this->requestOrigin( $request );
		$canonical  = $this->canonicalizeOrigin( $origin );
		$allowed    = $this->isAllowedOrigin( $canonical );

		$this->mergeVaryOrigin();

		if ( $allowed ) {
			$this->emitAllowedHeaders( $canonical );
		}

		return $served;
	}

	/**
	 * Emit allowlist CORS for HEAD discovery before rest_api_init. Never scrubs.
	 *
	 * @since 2.0.7
	 *
	 * @return void
	 */
	public function sendHeadDiscoveryHeaders() {
		if ( did_action( 'rest_api_init' ) ) {
			return;
		}

		$method = '';
		if ( ! empty( $_SERVER['REQUEST_METHOD'] ) ) {
			$method = sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) );
		}
		if ( 'HEAD' !== $method ) {
			return;
		}

		$canonical = $this->canonicalizeOrigin( $this->requestOrigin( null ) );
		if ( ! $this->isAllowedOrigin( $canonical ) ) {
			return;
		}

		$this->emitAllowedHeaders( $canonical );
	}

	/**
	 * Whether a REST route is the index or bgc/v1 (boundary-safe).
	 *
	 * @since 2.0.7
	 *
	 * @param string $route Route from WP_REST_Request::get_route().
	 * @return bool
	 */
	protected function isInScopeRestRoute( $route ) {
		if ( ! is_string( $route ) || '' === $route ) {
			return false;
		}
		if ( '/' !== substr( $route, 0, 1 ) ) {
			$route = '/' . $route;
		}

		return ( '/' === $route || '/bgc/v1' === $route || 0 === strpos( $route, '/bgc/v1/' ) );
	}

	/**
	 * Read the request Origin from the REST request or $_SERVER.
	 *
	 * @since 2.0.7
	 *
	 * @param object|null $request REST request.
	 * @return string
	 */
	protected function requestOrigin( $request ) {
		if ( is_object( $request ) && method_exists( $request, 'get_header' ) ) {
			$header = $request->get_header( 'Origin' );
			if ( is_string( $header ) && '' !== $header ) {
				return $header;
			}
		}
		if ( ! empty( $_SERVER['HTTP_ORIGIN'] ) && is_string( $_SERVER['HTTP_ORIGIN'] ) ) {
			return wp_unslash( $_SERVER['HTTP_ORIGIN'] );
		}

		return '';
	}

	/**
	 * Canonicalize a request Origin. Paths are rejected (browsers do not send them).
	 *
	 * @since 2.0.7
	 *
	 * @param string $origin Raw Origin.
	 * @return string
	 */
	protected function canonicalizeOrigin( $origin ) {
		return $this->parseHttpsOrigin( $origin, false );
	}

	/**
	 * Parse a config/filter URL into an origin, ignoring path (e.g. /wordpress).
	 *
	 * @since 2.0.7
	 *
	 * @param string $url Candidate URL.
	 * @return string
	 */
	protected function originFromCandidateUrl( $url ) {
		return $this->parseHttpsOrigin( $url, true );
	}

	/**
	 * Parse an HTTPS origin from a URL or Origin header.
	 *
	 * @since 2.0.7
	 *
	 * @param string $value      Raw URL or Origin.
	 * @param bool   $ignorePath When true, drop path (allowlist candidates).
	 * @return string
	 */
	protected function parseHttpsOrigin( $value, $ignorePath ) {
		if ( ! is_string( $value ) || '' === $value ) {
			return '';
		}
		$value = trim( $value );
		if ( '' === $value || '*' === $value || 0 === strcasecmp( $value, 'null' ) ) {
			return '';
		}

		$parts = parse_url( $value );
		if ( ! is_array( $parts ) ) {
			return '';
		}
		if ( empty( $parts['scheme'] ) || empty( $parts['host'] ) ) {
			return '';
		}
		if ( isset( $parts['user'] ) || isset( $parts['pass'] ) ) {
			return '';
		}
		if ( isset( $parts['query'] ) || isset( $parts['fragment'] ) ) {
			return '';
		}
		if ( ! $ignorePath && isset( $parts['path'] ) && '' !== $parts['path'] && '/' !== $parts['path'] ) {
			return '';
		}

		$scheme = strtolower( $parts['scheme'] );
		if ( 'https' !== $scheme ) {
			return '';
		}

		$host = strtolower( $parts['host'] );
		if ( '' === $host || $this->isIpLiteralHost( $host ) ) {
			return '';
		}

		$port = '';
		if ( isset( $parts['port'] ) && 443 !== (int) $parts['port'] ) {
			$port = ':' . (int) $parts['port'];
		}

		return $scheme . '://' . $host . $port;
	}

	/**
	 * Whether a host is an IPv4 or IPv6 literal.
	 *
	 * @since 2.0.7
	 *
	 * @param string $host Host.
	 * @return bool
	 */
	protected function isIpLiteralHost( $host ) {
		if ( '[' === substr( $host, 0, 1 ) && ']' === substr( $host, -1 ) ) {
			$host = substr( $host, 1, -1 );
		}

		return false !== filter_var( $host, FILTER_VALIDATE_IP );
	}

	/**
	 * Whether a canonical origin is on the allowlist.
	 *
	 * @since 2.0.7
	 *
	 * @param string $canonical Canonical origin.
	 * @return bool
	 */
	protected function isAllowedOrigin( $canonical ) {
		if ( ! is_string( $canonical ) || '' === $canonical ) {
			return false;
		}

		$allowlist = $this->allowedOrigins();
		foreach ( $allowlist as $allowed ) {
			if ( is_string( $allowed ) && '' !== $allowed && hash_equals( $allowed, $canonical ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Build the validated allowlist.
	 *
	 * @since 2.0.7
	 *
	 * @return string[]
	 */
	protected function allowedOrigins() {
		$candidates = array( self::BUILTIN_V2_ORIGIN );
		$candidates = array_merge( $candidates, $this->configOriginCandidates() );
		$candidates = array_merge( $candidates, $this->extraCandidates );

		$filtered = apply_filters( 'central_connect_allowed_cors_origins', $candidates );
		if ( is_array( $filtered ) ) {
			$candidates = $filtered;
		}

		$allowed = array();
		foreach ( $candidates as $candidate ) {
			$canonical = $this->originFromCandidateUrl( $candidate );
			if ( '' === $canonical ) {
				continue;
			}
			$already = false;
			foreach ( $allowed as $existing ) {
				if ( hash_equals( $existing, $canonical ) ) {
					$already = true;
					break;
				}
			}
			if ( ! $already ) {
				$allowed[] = $canonical;
			}
		}

		return $allowed;
	}

	/**
	 * Origins parsed from plugin config central_url and branding.
	 *
	 * @since 2.0.7
	 *
	 * @return string[]
	 */
	protected function configOriginCandidates() {
		$urls = array();
		if ( ! class_exists( 'Central_Connect_Service' ) ) {
			return $urls;
		}
		if ( ! method_exists( 'Central_Connect_Service', 'get' ) ) {
			return $urls;
		}

		$configs = null;
		try {
			$configs = \Central_Connect_Service::get( 'configs' );
		} catch ( \Exception $e ) {
			return $urls;
		}
		if ( ! is_array( $configs ) ) {
			return $urls;
		}

		if ( ! empty( $configs['central_url'] ) && is_string( $configs['central_url'] ) ) {
			$urls[] = $configs['central_url'];
		}
		if ( ! empty( $configs['branding'] ) && is_array( $configs['branding'] ) ) {
			foreach ( $configs['branding'] as $brand ) {
				if ( is_array( $brand ) && ! empty( $brand['central_url'] ) && is_string( $brand['central_url'] ) ) {
					$urls[] = $brand['central_url'];
				}
			}
		}

		return $urls;
	}

	/**
	 * Remove already-sent CORS headers on in-scope REST routes.
	 *
	 * @since 2.0.7
	 *
	 * @return void
	 */
	protected function scrubCorsHeaders() {
		$names = array(
			'Access-Control-Allow-Origin',
			'Access-Control-Allow-Credentials',
			'Access-Control-Allow-Methods',
			'Access-Control-Allow-Headers',
			'Access-Control-Expose-Headers',
		);
		foreach ( $names as $name ) {
			$this->headerRemove( $name );
		}
	}

	/**
	 * Merge Origin into Vary without replacing existing values.
	 *
	 * @since 2.0.7
	 *
	 * @return void
	 */
	protected function mergeVaryOrigin() {
		$this->sendHeader( 'Vary: Origin', false );
	}

	/**
	 * Emit allowlisted CORS headers (never credentials).
	 *
	 * @since 2.0.7
	 *
	 * @param string $canonical Canonical origin.
	 * @return void
	 */
	protected function emitAllowedHeaders( $canonical ) {
		$this->sendHeader( 'Access-Control-Allow-Origin: ' . $canonical );
		$this->sendHeader( 'Access-Control-Expose-Headers: Link' );
		$this->sendHeader( 'Access-Control-Allow-Methods: HEAD, OPTIONS, GET, POST, PUT, PATCH, DELETE' );
		$this->sendHeader( 'Access-Control-Allow-Headers: Authorization, X-WP-Nonce, Content-Type, Content-Disposition, Content-MD5, X-BGC-Auth' );
	}

	/**
	 * Send a header via the emitter or PHP.
	 *
	 * @since 2.0.7
	 *
	 * @param string $header  Header line.
	 * @param bool   $replace Replace existing.
	 * @return void
	 */
	protected function sendHeader( $header, $replace = true ) {
		if ( is_object( $this->emitter ) && method_exists( $this->emitter, 'header' ) ) {
			$this->emitter->header( $header, $replace );
			return;
		}
		header( $header, $replace );
	}

	/**
	 * Remove a header via the emitter or PHP.
	 *
	 * @since 2.0.7
	 *
	 * @param string $name Header name.
	 * @return void
	 */
	protected function headerRemove( $name ) {
		if ( is_object( $this->emitter ) && method_exists( $this->emitter, 'header_remove' ) ) {
			$this->emitter->header_remove( $name );
			return;
		}
		header_remove( $name );
	}
}
