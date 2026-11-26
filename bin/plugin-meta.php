<?php
/**
 * Print the plugin's release metadata as JSON for the conftest release policy.
 *
 * Usage: php bin/plugin-meta.php [plugin-root]
 *
 * @package Central_Connect
 */

// phpcs:disable WordPress.WP.AlternativeFunctions, WordPress.Security.EscapeOutput.OutputNotEscaped

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

$central_connect_root = isset( $argv[1] ) ? rtrim( $argv[1], '/' ) : dirname( __DIR__ );

/**
 * Read a "Name: value" header line from a file body.
 *
 * @param string $body File contents.
 * @param string $name Header name.
 * @return string|null Header value, or null when absent.
 */
function central_connect_meta_header( $body, $name ) {
	if ( preg_match( '/^[ \t\/*#@]*' . preg_quote( $name, '/' ) . ':[ \t]*(.+?)[ \t]*$/mi', $body, $match ) ) {
		return $match[1];
	}
	return null;
}

$central_connect_plugin = (string) file_get_contents( $central_connect_root . '/central-connect.php' );
$central_connect_readme = (string) file_get_contents( $central_connect_root . '/readme.txt' );

$central_connect_changelog = array();
$central_connect_section   = strstr( $central_connect_readme, '== Changelog ==' );
if ( false !== $central_connect_section && preg_match_all( '/^= ([^=]+?) =\s*$/m', $central_connect_section, $central_connect_matches ) ) {
	$central_connect_changelog = $central_connect_matches[1];
}

$central_connect_meta = array(
	'plugin' => array(
		'version'           => central_connect_meta_header( $central_connect_plugin, 'Version' ),
		'requires_php'      => central_connect_meta_header( $central_connect_plugin, 'Requires PHP' ),
		'requires_at_least' => central_connect_meta_header( $central_connect_plugin, 'Requires at least' ),
		'text_domain'       => central_connect_meta_header( $central_connect_plugin, 'Text Domain' ),
		'runtime_min_php'   => preg_match( "/Central_Connect_Version_Check::init\(\s*[^,]+,\s*'([^']+)',\s*'([^']+)'/", $central_connect_plugin, $central_connect_init ) ? $central_connect_init[2] : null,
		'runtime_min_wp'    => isset( $central_connect_init[1] ) ? $central_connect_init[1] : null,
	),
	'readme' => array(
		'stable_tag'        => central_connect_meta_header( $central_connect_readme, 'Stable tag' ),
		'requires_php'      => central_connect_meta_header( $central_connect_readme, 'Requires PHP' ),
		'requires_at_least' => central_connect_meta_header( $central_connect_readme, 'Requires at least' ),
		'tested_up_to'      => central_connect_meta_header( $central_connect_readme, 'Tested up to' ),
		'changelog'         => $central_connect_changelog,
	),
);

echo json_encode( $central_connect_meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . PHP_EOL;
