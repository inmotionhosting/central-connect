<?php

/**
 * BoldGrid Source Code
 *
 * @package Boldgrid_Connect
 * @copyright BoldGrid.com
 * @version 2.0.0
 * @author BoldGrid.com <wpb@boldgrod.com>
 */

/**
 * Boldgrid Upgrade Class
 *
 * Responsible for performing any upgrade methods that
 * are version specific needs.
 *
 * @since 2.0.0
 */
class Central_Connect_Upgrade {

	/**
	 * Prefix string used in plugin.
	 *
	 * @var string
	 *
	 * @access protected
	 *
	 * @since 2.0.0
	 */
	protected $prefix;

	/**
	 * Constructor.
	 *
	 * @access public
	 *
	 * @since 2.0.0
	 */
	public function __construct() {
		$this->prefix = 'central_connect';
	}

	/**
	 * Checks the DB for current version number, and compares to version set by version constant.
	 *
	 * If there's a method upgrade_to_MAJOR_MINOR_SUBMINOR() then that method
	 * will be executed if the method's specified version is less than/equal to the
	 * current version constant, and greater than the stored version in the DB.
	 *
	 * Since we didn't need any upgrade methods initially, we will set the default
	 * version in the DB to 1.0.0 and run any upgrade methods required from then
	 * on.  All additional upgrade methods in the future should be added here in
	 * the same format to be automatically managed and handled.
	 *
	 * @access public
	 *
	 * @since 2.0.0
	 */
	public function upgrade_db_check() {
		$this->set_option( '1.0.0' );

		// Set the default version in db if no version is set.
		if ( ! $this->get_option() ) {
            $this->set_option( '1.0.0' );
        }

		// Get current version from constant set in plugin.
		$version = CENTRAL_CONNECT_VERSION;

		// If the db version doesn't match the config version then run upgrade methods.
		if ( $this->get_option() !== $version ) {
			$methods = $this->get_upgrade_methods();

			// Format found methods to versions.
			foreach( $methods as $method ) {
				$ver = substr( $method, 11 );
				$ver = str_replace( '_', '.', $ver );

				// Gives precedence to minor version specific upgrades over subminors.
				$verHigh = str_replace( 'x', '9999', $ver );
				$verLow = str_replace( 'x', '0', $ver );

				// If upgrade method version is greater than stored DB version.
				if ( version_compare( $verHigh, $this->get_option(), 'gt' )  &&
                
					// The config version is less than or equal to upgrade method versions.
					version_compare( $verLow, $version, 'le' ) ) {
						if ( is_callable( array( $this, $method ) ) ) {
                            $this->$method();
                        }
				}
			}

			// Once done with method calls, update the version number from constant.
			$this->set_option( CENTRAL_CONNECT_VERSION );
		}
	}

	/**
	 * Gets an array of upgrade methods.
	 *
	 * This checks __CLASS__ to see what methods are available
	 * as class
	 *
	 * @access public
	 *
	 * @since 2.0.0
	 *
	 * @return array $methods List of available upgrade methods.
	 */
	public function get_upgrade_methods() {
		$methods = get_class_methods( $this );
		$methods = array_filter( $methods, function( $key ) {
			return strpos( $key, 'upgrade_to_' ) !== false;
		} );

		return $methods;
	}

	/**
	 * Get option.
	 *
	 * This checks if option has been set in db.
	 *
	 * @access public
	 *
	 * @since 2.0.0
	 *
	 * @return mixed Version as a string or false.
	 */
	public function get_option() {
		return get_site_option( "{$this->prefix}_version" );
	}

	/**
	 * Set option for version.
	 *
	 * This sets the version option in the db.
	 *
	 * @access public
	 *
	 * @since 2.0.0
	 */
	public function set_option( $version ) {
		update_site_option( "{$this->prefix}_version", $version );
	}

	/**
	 * Upgrade to version 2.0.0
	 *
	 * This will perform upgrade tasks for 2.0.0
	 *
	 * @link https://codex.wordpress.org/Class_Reference/wpdb#UPDATE_rows
	 *
	 * @access public
	 *
	 * @since 2.0.0
	 */
	public function upgrade_to_2_0_0() {
        // If option isn't set in DB already, update it with IMH as default provider.
        if ( empty( get_site_option( "{$this->prefix}_provider", '' ) ) ) {
            update_site_option( "{$this->prefix}_provider", 'InMotion Hosting' );
        }
	}
}