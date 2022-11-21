<?php
/**
 * File: Router.php
 *
 * Setup the Router.
 *
 * @since      2.0.0
 * @package    BoldGrid\Connect\Rest
 * @author     BoldGrid <support@boldgrid.com>
 * @link       https://boldgrid.com
 */

namespace BoldGrid\Connect\Health;

/**
 * Class: Router
 *
 * Setup the Router.
 *
 * @since 2.0.0
 */
class Stats {

	/**
	 * Get the results from the site heatlth page.
	 *
	 * @since 2.0.0
	 *
	 * @return array Site Health Results.
	 */
	public function getSiteHealth() {
		include_once ABSPATH . 'wp-admin/includes/class-wp-site-health.php';
		include_once ABSPATH . 'wp-admin/includes/class-wp-screen.php';
		include_once ABSPATH . 'wp-admin/includes/screen.php';
		include_once ABSPATH . 'wp-admin/includes/update.php';
		include_once ABSPATH . 'wp-admin/includes/plugin.php';
		include_once ABSPATH . 'wp-admin/includes/misc.php';

		// Disable Rest Test & async tests.
		add_filter(
			'site_status_tests',
			function ( $tests ) {
				$tests['async'] = array();
				unset( $tests['direct']['rest_availability'] );

				return $tests;
			}
		);

		// Capture all the results as they run.
		$allTests = array();
		add_filter(
			'site_status_test_result',
			function ( $result ) use ( &$allTests ) {
				$allTests[] = $result;
				return $result;
			}
		);

		set_current_screen( 'site-health' );
		$siteHealth = new \WP_Site_Health();
		$siteHealth->enqueue_scripts();

		return $allTests;
	}
}
