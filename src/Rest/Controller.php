<?php
/**
 * File: Server.php
 *
 * Setup the Rest Router extension.
 *
 * @since      2.0.0
 * @package    BoldGrid\Connect\Rest
 * @author     InMotion Hosting <central-dev@inmotionhosting.com>
 * @link       https://boldgrid.com
 */

namespace Central\Connect\Rest;

/**
 * Class: Router
 *
 * Setup the Rest Server extension.
 *
 * @since 2.0.0
 */
class Controller extends \WP_REST_Controller {

	/**
	 * Namespace of the class.
	 *
	 * @since  2.0.0
	 * @access private
	 * @var    string
	 */
	protected $namespace = 'bgc/v1';

	public function permissionCheck() {
		return current_user_can( 'activate_plugins' );
	}
}
