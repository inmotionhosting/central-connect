<?php
/**
 * File: UpgraderSkin.php
 *
 * Remove feedback from upgrader.
 *
 * @link       https://central.inmotionhosting.com
 * @since      1.0.0
 *
 * @package    Central_Connect
 * @subpackage Central_Connect/includes
 * @copyright  InMotionHosting.com
 * @version    $Id$
 * @author     InMotion Hosting <central-dev@inmotionhosting.com>
 */

/**
 * Include core WordPress upgrader file.
 */
include_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

/**
 * Class: UpgraderSkin
 *
 * Remove feedback from upgrader.
 *
 * @since 2.0.0
 */
class Central_Connect_Upgrader_Skin extends \WP_Upgrader_Skin {


	/**
	 * Empty out the header of its HTML content and only check to see if it has
	 * been performed or not.
	 *
	 * @since 2.0.0
	 */
	public function header() {}

	/**
	 * Empty out the footer of its HTML contents.
	 *
	 * @since 2.0.0
	 */
	public function footer() {}

	/**
	 * Instead of outputting HTML for errors, json_encode the errors and send them
	 * back to the Ajax script for processing.
	 *
	 * @since 2.0.0
	 *
	 * @param array $errors Array of errors with the install process.
	 */
	public function error( $errors ) {
		if ( ! empty( $errors ) ) {
			\wp_send_json_error(
				array(
					'error' => \esc_html__( 'There was an error installing. Please try again.', 'central-connect' ),
				),
				400
			);
		}
	}

	/**
	 * Empty out the feedback method to prevent outputting HTML strings as the install
	 * is progressing.
	 *
	 * @since 2.0.0
	 *
	 * @param string $string  The feedback string.
	 * @param mixed  ...$args Additional arguments to pass.
	 */
	public function feedback( $string, ...$args ) {}
}
