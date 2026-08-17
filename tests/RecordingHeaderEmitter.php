<?php
/**
 * Records header() / header_remove() calls for CORS tests.
 *
 * @package Central_Connect
 */

/**
 * Class: RecordingHeaderEmitter
 *
 * Duck-typed stand-in for the injectable Cors header emitter.
 */
class RecordingHeaderEmitter {

	/**
	 * Current header lines (final state after replace/remove).
	 *
	 * @var string[]
	 */
	public $lines = array();

	/**
	 * Names passed to header_remove(), in call order.
	 *
	 * @var string[]
	 */
	public $removed = array();

	/**
	 * Send or replace a header line.
	 *
	 * @param string $header  Header line, e.g. "Vary: Origin".
	 * @param bool   $replace Whether to replace existing headers of the same name.
	 * @return void
	 */
	public function header( $header, $replace = true ) {
		$name = $this->nameOf( $header );
		if ( $replace ) {
			$this->removeFromLines( $name );
		}
		$this->lines[] = $header;
	}

	/**
	 * Remove a header by name.
	 *
	 * @param string $name Header name.
	 * @return void
	 */
	public function header_remove( $name ) {
		$this->removed[] = $name;
		$this->removeFromLines( $name );
	}

	/**
	 * Clear recorded header state without replacing this instance.
	 *
	 * @return void
	 */
	public function reset() {
		$this->lines   = array();
		$this->removed = array();
	}

	/**
	 * Return all values for a header name.
	 *
	 * @param string $name Header name.
	 * @return string[]
	 */
	public function headerValues( $name ) {
		$values = array();
		foreach ( $this->lines as $line ) {
			if ( 0 === strcasecmp( $this->nameOf( $line ), $name ) ) {
				$values[] = $this->valueOf( $line );
			}
		}

		return $values;
	}

	/**
	 * Return the last value for a header name, or null.
	 *
	 * @param string $name Header name.
	 * @return string|null
	 */
	public function headerValue( $name ) {
		$values = $this->headerValues( $name );
		if ( empty( $values ) ) {
			return null;
		}

		return $values[ count( $values ) - 1 ];
	}

	/**
	 * Whether a header name was passed to header_remove().
	 *
	 * @param string $name Header name.
	 * @return bool
	 */
	public function didRemove( $name ) {
		foreach ( $this->removed as $removed ) {
			if ( 0 === strcasecmp( $removed, $name ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Extract the header name from a header line.
	 *
	 * @param string $header Header line.
	 * @return string
	 */
	protected function nameOf( $header ) {
		$parts = explode( ':', $header, 2 );

		return trim( $parts[0] );
	}

	/**
	 * Extract the header value from a header line.
	 *
	 * @param string $header Header line.
	 * @return string
	 */
	protected function valueOf( $header ) {
		$parts = explode( ':', $header, 2 );
		if ( ! isset( $parts[1] ) ) {
			return '';
		}

		return trim( $parts[1] );
	}

	/**
	 * Drop current lines matching a header name.
	 *
	 * @param string $name Header name.
	 * @return void
	 */
	protected function removeFromLines( $name ) {
		$kept = array();
		foreach ( $this->lines as $line ) {
			if ( 0 !== strcasecmp( $this->nameOf( $line ), $name ) ) {
				$kept[] = $line;
			}
		}
		$this->lines = $kept;
	}
}
