<?php
/**
* File: Installed.php
*
* Modifications to the installed plugin listing page.
*
* @since      2.0.0
* @package    BoldGrid\Connect\Plugin
* @author     BoldGrid <support@boldgrid.com>
* @link       https://boldgrid.com
*/

namespace BoldGrid\Connect\Plugin;

/**
* Class: Installed
*
* Modifications to the installed plugin listing page.
*
* @since 2.0.0
*/
class Installed {

	/**
	 * Bind any hooks.
	 *
	 * @since 2.0.0
	 *
	 * @return void
	 */
	public function initialize() {
		$this->pluginRow();
	}

	/**
	 * Add links to the plugin row.
	 *
	 * @since 2.0.0
	 *
	 * @return void
	 */
	public function pluginRow() {
		add_filter( 'plugin_row_meta', function ( $meta, $slug ) {
			$pluginName = 'boldgrid-connect.php';
			$length = strlen( $pluginName );
			$hasPluginFilename = substr( $slug, -$length ) === $pluginName;
			if ( $hasPluginFilename ) {
				$meta[] = '<a href="' . admin_url( 'options-general.php?page=boldgrid-connect-central' ) . '">' . __( 'My Connection', 'boldgrid-connect' ) . '</a>';
			}

			return $meta;
		}, 10, 2 );
	}
}