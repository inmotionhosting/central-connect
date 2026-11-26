<?php
/**
 * Regression tests for WordPress Plugin Check findings in shipped files.
 *
 * @package Central_Connect
 */

/**
 * Class: Test_Plugin_Check
 */
class Test_Plugin_Check extends WP_UnitTestCase {

	/**
	 * Tokens allowed at the top level of a file without a direct access guard.
	 *
	 * @var int[]
	 */
	const DECLARATION_TOKENS = array(
		T_OPEN_TAG,
		T_CLOSE_TAG,
		T_INLINE_HTML,
		T_WHITESPACE,
		T_COMMENT,
		T_DOC_COMMENT,
	);

	/**
	 * Every shipped PHP file that runs code when loaded must stop direct requests.
	 */
	public function test_shipped_files_with_top_level_code_block_direct_access() {
		$unguarded = array();

		foreach ( $this->shipped_php_files() as $relative => $path ) {
			$source = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

			if ( ! $this->has_top_level_code( $source ) ) {
				continue;
			}

			if ( ! preg_match( "/if\s*\(\s*!\s*defined\(\s*'(ABSPATH|WPINC)'\s*\)\s*\)/", $source ) ) {
				$unguarded[] = $relative;
			}
		}

		$this->assertSame( array(), $unguarded, 'Shipped PHP files with top-level code must exit when ABSPATH/WPINC is not defined.' );
	}

	/**
	 * The plugin header must not point at a translation folder that does not exist.
	 */
	public function test_domain_path_header_points_to_existing_folder() {
		$header = get_file_data( CENTRAL_CONNECT_FILE, array( 'DomainPath' => 'Domain Path' ) );

		if ( '' === $header['DomainPath'] ) {
			$this->assertSame( '', $header['DomainPath'] );
			return;
		}

		$this->assertDirectoryExists( CENTRAL_CONNECT_PATH . ltrim( $header['DomainPath'], '/' ) );
	}

	/**
	 * Remote validation fires the prefixed action.
	 */
	public function test_remote_validate_fires_prefixed_action() {
		$before = did_action( 'central_connect_remote_validate' );

		\Central\Connect\Authentication\Token::doRemoteValidateAction();

		$this->assertSame( $before + 1, did_action( 'central_connect_remote_validate' ) );
	}

	/**
	 * The legacy action still fires for existing listeners and is reported as deprecated.
	 */
	public function test_legacy_remote_validate_action_still_fires_and_is_deprecated() {
		$this->setExpectedDeprecated( 'bgc_remote_validate' );

		$calls    = 0;
		$listener = function () use ( &$calls ) {
			++$calls;
		};
		add_action( 'bgc_remote_validate', $listener );

		\Central\Connect\Authentication\Token::doRemoteValidateAction();

		remove_action( 'bgc_remote_validate', $listener );
		$this->assertSame( 1, $calls );
	}

	/**
	 * Without a callback, a passing version check fires the prefixed init action.
	 */
	public function test_version_check_success_hook_fires_prefixed_action() {
		$before = did_action( 'central_connect_init' );

		Central_Connect_Version_Check::success_hook();

		$this->assertSame( $before + 1, did_action( 'central_connect_init' ) );
	}

	/**
	 * The legacy "{slug}:init" action still fires for existing listeners and is reported as deprecated.
	 */
	public function test_legacy_version_check_init_action_still_fires_and_is_deprecated() {
		$legacy = 'central-connect:init';
		$this->setExpectedDeprecated( $legacy );

		$calls    = 0;
		$listener = function () use ( &$calls ) {
			++$calls;
		};
		add_action( $legacy, $listener );

		Central_Connect_Version_Check::init( 'central-connect/central-connect.php', '5.0', '7.4', '__return_null' );
		Central_Connect_Version_Check::success_hook();
		Central_Connect_Version_Check::init( plugin_basename( CENTRAL_CONNECT_FILE ), '5.0', '7.4', '__return_null' );

		remove_action( $legacy, $listener );
		$this->assertSame( 1, $calls );
	}

	/**
	 * Shipped PHP files: the plugin root plus includes/ and src/.
	 *
	 * @return string[] Absolute paths keyed by path relative to the plugin root.
	 */
	private function shipped_php_files() {
		$files = array();
		foreach ( glob( CENTRAL_CONNECT_PATH . '*.php' ) as $path ) {
			$files[ basename( $path ) ] = $path;
		}

		foreach ( array( 'includes', 'src' ) as $dir ) {
			$iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( CENTRAL_CONNECT_PATH . $dir, FilesystemIterator::SKIP_DOTS ) );
			foreach ( $iterator as $file ) {
				if ( 'php' === $file->getExtension() ) {
					$files[ substr( $file->getPathname(), strlen( CENTRAL_CONNECT_PATH ) ) ] = $file->getPathname();
				}
			}
		}

		$this->assertNotEmpty( $files );
		ksort( $files );

		return $files;
	}

	/**
	 * Whether a file has statements outside namespace/use/class/function declarations.
	 *
	 * @param string $source PHP source.
	 *
	 * @return bool
	 */
	private function has_top_level_code( $source ) {
		$tokens = token_get_all( $source );
		$count  = count( $tokens );

		for ( $i = 0; $i < $count; $i++ ) {
			$id = is_array( $tokens[ $i ] ) ? $tokens[ $i ][0] : $tokens[ $i ];

			if ( in_array( $id, self::DECLARATION_TOKENS, true ) ) {
				continue;
			}

			if ( in_array( $id, array( T_NAMESPACE, T_USE, T_DECLARE ), true ) ) {
				$i = $this->skip_statement( $tokens, $i );
				continue;
			}

			if ( in_array( $id, array( T_ABSTRACT, T_FINAL ), true ) ) {
				continue;
			}

			if ( in_array( $id, array( T_CLASS, T_INTERFACE, T_TRAIT, T_FUNCTION ), true ) ) {
				$i = $this->skip_statement( $tokens, $i );
				continue;
			}

			return true;
		}

		return false;
	}

	/**
	 * Skip to the end of a `;`-terminated statement or a `{ ... }` block.
	 *
	 * @param array $tokens Tokens from token_get_all().
	 * @param int   $i      Index of the statement's first token.
	 *
	 * @return int Index of the statement's last token.
	 */
	private function skip_statement( array $tokens, $i ) {
		$count = count( $tokens );
		$depth = 0;

		for ( ; $i < $count; $i++ ) {
			$token = $tokens[ $i ];
			$text  = is_array( $token ) ? $token[1] : $token;

			if ( '{' === $text || ( is_array( $token ) && in_array( $token[0], array( T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES ), true ) ) ) {
				++$depth;
			} elseif ( '}' === $text ) {
				--$depth;
				if ( 0 === $depth ) {
					return $i;
				}
			} elseif ( ';' === $text && 0 === $depth ) {
				return $i;
			}
		}

		return $i;
	}
}
