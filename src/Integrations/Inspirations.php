<?php
/**
 * File: Installed.php
 *
 * Modifications to the installed plugin listing page.
 *
 * @since      2.0.0
 * @package    Central\Connect\Integrations
 * @author     InMotion Hosting <central-dev@inmotionhosting.com>
 * @link       https://boldgrid.com
 */

namespace Central\Connect\Integrations;

use Central\Connect\Option;
use Central\Connect\View\Central;

/**
 * Class: Installed
 *
 * Modifications to the installed plugin listing page.
 *
 * @since 2.0.0
 */
class Inspirations {

	/**
	 * Bind any hooks.
	 *
	 * @since 2.0.0
	 *
	 * @return void
	 */
	public function initialize() {
		add_action( 'add_meta_boxes_admin_page_my-inspiration', array( $this, 'addMetaBox' ), 99 );
	}

	/**
	 * Create the meta box for the inspirations dashboard informing the user that they need to deploy to production.
	 *
	 * @since 2.0.0
	 *
	 * @return void
	 */
	public function addMetaBox() {
		// Prompt currently only shows on cloud wordpress staging sites, should be updated to show on any dev environment.
		if ( ! defined( 'BOLDGRID_DEMO_VERSION' ) || ! Central\ConnectNotice::isConnected() ) {
			return;
		}

		$configs = get_option( 'bg_connect_configs', \Central_Connect_Service::get( 'configs' ) );
		$provider = get_option( 'boldgrid_connect_provider', 'BoldGrid' );

		add_meta_box(
			'publish_website',
			esc_html__( 'Publish Website', 'central-connect' ),
			function () use ( $configs, $provider ) {
				$productName = $configs['branding'][ $provider ]['productName'];
				$centralUrl = $configs['branding'][ $provider ]['central_url'];

				printf(
					'<p>%1$s</p><a target="_blank" href="' . esc_url( $centralUrl ) . '/projects?environment_id=' . esc_attr( Option\Connect::get( 'environment_id' ) ) .
						'" class="button button-primary">%2$s</a>',
					sprintf(
						/* translators: %s is product's name. */
						esc_html__(
							'You\'ve deployed this site on a development environment. To make this website public, you\'ll need to transfer to a production environment. Head back over to %s when you\'re done making changes to deploy your website.',
							'central-connect'
						),
						esc_html( $productName ),
					),
					esc_html__( 'Publish Site', 'central-connect' )
				);
			},
			'admin_page_my-inspiration',
			'container1'
		);
	}
}
