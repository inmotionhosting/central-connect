<?php
/**
* File: UpgraderSkin.php
*
* Remove feedback from upgrader.
*
* @since      2.0.0
* @package    BoldGrid\Connect\Rest
* @author     BoldGrid <support@boldgrid.com>
* @link       https://boldgrid.com
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
					'error' => \esc_html__( 'There was an error installing. Please try again.', 'boldgrid-connect' ),
				), 400 );
		}
	}

	/**
	 * Empty out the feedback method to prevent outputting HTML strings as the install
	 * is progressing.
	 *
	 * @since 2.0.0
	 *
	 * @param string $string The feedback string.
	 */
	public function feedback( $string, ...$args ) {}
}