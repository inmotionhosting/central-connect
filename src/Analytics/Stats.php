<?php
/**
 * Class: Stats
 *
 * @since      2.0.0
 * @package    BoldGrid\Connect
 * @author     InMotion Hosting <central-dev@inmotionhosting.com>
 * @link       https://boldgrid.com
 */

namespace Central\Connect\Analytics;

use Central\Connect\Health;

/**
 * Class: Stats
 *
 * Get all stats for a WP.
 *
 * @since      2.0.0
 */
class Stats {

	/**
	 * Get WordPress stats.
	 *
	 * @since 2.0.0
	 */
	public function getData() {
		$healthStats = new Health\Stats();

		return array(
			'debug' => self::getDebugInfo(),
			'views' => Views::getStats(),
			'health' => $healthStats->getSiteHealth(),
		);
	}

	/**
	 * Get debug data.
	 *
	 * @since 2.0.0
	 *
	 * @return array Debug Data.
	 */
	public static function getDebugInfo() {
		if ( ! class_exists( 'WP_Debug_Data' ) ) {
			require_once( ABSPATH . 'wp-admin/includes/class-wp-debug-data.php' );
		}

		require_once ABSPATH . 'wp-admin/includes/misc.php';
		require_once ABSPATH . 'wp-admin/includes/update.php';
		require_once ABSPATH . 'wp-admin/includes/plugin.php';

		return \WP_Debug_Data::debug_data();
	}
}
