<?php
/**
* File: Installer.php
*
* Theme install actions.
*
* @since      2.0.0
* @package    BoldGrid\Connect\Rest
* @author     BoldGrid <support@boldgrid.com>
* @link       https://boldgrid.com
*/

namespace Central\Connect\Theme;

/**
* Class: Installer
*
* Theme install actions.

* @since 2.0.0
*/
class Installer {

	/**
	 * Format the the WPTheme object into a standardize api response.
	 *
	 * @since 2.0.0
	 *
	 * @param Wp_Theme $wpTheme Theme to format.
	 * @return array Formatted theme.
	 */
	public function formatThemeResource( $wpTheme ) {
		include_once ABSPATH . 'wp-admin/includes/theme.php';

		$fields = [
			'Name',
			'ThemeURI',
			'Description',
			'Author',
			'AuthorURI',
			'Version',
			'Template',
			'Status',
			'Tags',
			'TextDomain',
			'DomainPath'
		];

		$formatted = [];
		foreach ( $fields as $field ) {
			$formatted[ $field ] = $wpTheme->get( $field );
		}
		$formatted['IsActive'] = $wpTheme->get_stylesheet() === get_stylesheet();
		$formatted['UpdateAvailable'] = (bool) \get_theme_update_available( $wpTheme );

		return $formatted;
	}

	/**
	 * List all installed themes for the WordPress.
	 *
	 * @since 2.0.0
	 *
	 * @return array List of themes.
	 */
	public function getCollection() {
		$themes = [];

		wp_cache_delete( 'themes', 'themes' );

		foreach( wp_get_themes() as $theme ) {
			$themes[] = $this->formatThemeResource( $theme );
		}

		return $themes;
	}

	/**
	 * Delete a list of themes.
	 *
	 * @since 2.0.0
	 *
	 * @param Stylesheets $stylesheets A list of stylesheets.
	 * @return void
	 */
	public function delete( $stylesheets ) {
		include_once ABSPATH . 'wp-admin/includes/misc.php';
		include_once ABSPATH . 'wp-admin/includes/theme.php';
		include_once CENTRAL_CONNECT_PATH . 'includes/class-central-connect-upgrader.php';

		$results = false;
		foreach( $stylesheets as $stylesheet ) {
			$directory = get_theme_root( $stylesheet ) . trailingslashit( "/$stylesheet" );
			$upgrader = new \Theme_Upgrader( new \Central_Connect_Upgrader_Skin() );
			$upgrader->init();
			$results[] = $upgrader->clear_destination( $directory );
		}

		return $results;
	}

	/**
	 * Install a theme.
	 *
	 * @since 2.0.0
	 */
	public function install( $themeZip ) {
		include_once ABSPATH . 'wp-admin/includes/misc.php';
		include_once ABSPATH . 'wp-admin/includes/theme.php';
		include_once CENTRAL_CONNECT_PATH . 'includes/class-central-connect-upgrader.php';

		add_filter( 'upgrader_package_options', function ( $options ) {
			$options['clear_destination'] = true;
			$options['clear_working'] = true;

			return $options;
		} );

		wp_cache_flush();

		$upgrader = new \Theme_Upgrader( new \Central_Connect_Upgrader_Skin() );
		$upgrader->install( $themeZip );

		return $upgrader->theme_info();
	}
}
