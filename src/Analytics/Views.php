<?php
/**
 * Class: Views
 *
 * Simple page view counting.
 *
 * @since      2.0.0
 * @package    Central\Connect\Analytics
 * @author     InMotion Hosting <central-dev@inmotionhosting.com>
 * @link       https://boldgrid.com
 */

namespace Central\Connect\Analytics;

use Central\Connect\Analytics\Option;

/**
 * Class: Views
 *
 * Simple page view counting.
 *
 * @since      2.0.0
 */
class Views {

	/**
	 * Have we alreadt counted a page view?
	 *
	 * @since X.X.X
	 */
	protected $viewCounted = false;

	/**
	 * Status Code.
	 *
	 * @since X.X.X
	 */
	protected $statusCode;

	/**
	 * Bind the needed wp hooks.
	 */
	public function initialize() {
		// Grab the current status code.
		add_filter(
			'status_header',
			function ( $statusHeader, $code ) {
				$this->statusCode = $code;
				return $statusHeader;
			},
			10,
			3
		);

		add_action(
			'wp_print_footer_scripts',
			function () {
				if ( ! $this->viewCounted && 200 === $this->statusCode ) {
					$this->countView();
				}
			}
		);
	}

	/**
	 * Count a page view.
	 *
	 * @since 2.0.0
	 */
	public function countView() {
		$this->viewCounted = true;
		$date = $this->getDateFormat();
		$value = Option::get( $date );
		$value++;
		Option::update( $date, $value );
	}

	/**
	 * Get a list of stats based on page views.
	 *
	 * @since 2.0.0
	 *
	 * @return array Page Views Stats.
	 */
	public static function getStats() {
		return array(
			'dates' => get_option( 'boldgrid_connect_analytics', array() ),
		);
	}

	/**
	 * Get the date format for saving pages.
	 *
	 * @since 2.0.0
	 *
	 * @return string Date.
	 */
	public function getDateFormat() {
		return date( 'Y-m-d' );
	}
}
