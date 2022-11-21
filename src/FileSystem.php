<?php
/**
 * Class: FileSystem
 *
 * Functions for interacting with WordPress Filesystem.
 *
 * @since      2.0.0
 * @package    Central\Connect
 * @author     InMotion Hosting <central-dev@inmotionhosting.com>
 * @link       https://boldgrid.com
 */

namespace Central\Connect;

/**
 * Class: FileSystem
 *
 * Functions for interacting with WordPress Filesystem.
 *
 * @since      2.0.0
 */
class FileSystem {

	/**
	 * Initialize the class and set class properties.
	 *
	 * @since    1.0.0
	 */
	public function __construct() {
		$this->wpFilesystem = $this->init();

		return $this;
	}

	/**
	 * Accessor.
	 *
	 * @since 2.0.0
	 *
	 * @return wp_filesystem Wordpress global.
	 */
	public function get_wp_filesystem() {
		return $this->wpFilesystem;
	}

	/**
	 * Initialize the WP_Filesystem.
	 *
	 * @since 2.0.0
	 * @global $wp_filesystem WordPress Filesystem global.
	 */
	public function init() {
		global $wp_filesystem;
		if ( empty( $wp_filesystem ) ) {
			require_once ABSPATH . '/wp-admin/includes/file.php';
			WP_Filesystem();
		}

		return $wp_filesystem;
	}
}
