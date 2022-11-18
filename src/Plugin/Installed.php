<?php
/**
 * File: Installed.php
 *
 * Modifications to the installed plugin listing page.
 *
 * @since      2.0.0
 * @package    Central\Connect\Plugin
 * @author     InMotion Hosting <central-dev@inmotionhosting.com>
 * @link       https://boldgrid.com
 */

namespace Central\Connect\Plugin;

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
		add_filter(
			'plugin_row_meta',
			function ( $meta, $slug ) {
				$pluginName = 'central-connect.php';
				$length = strlen( $pluginName );
				$hasPluginFilename = substr( $slug, -$length ) === $pluginName;
				if ( $hasPluginFilename ) {
					$meta[] = '<a href="' . admin_url( 'options-general.php?page=central-connect' ) . '">' . __( 'My Connection', 'central-connect' ) . '</a>';
				}

				return $meta;
			},
			10,
			2
		);
	}
}
