<?php
/**
* File: Server.php
*
* Setup the Rest Router extension.
*
* @since      2.0.0
* @package    BoldGrid\Connect\Rest
* @author     BoldGrid <support@boldgrid.com>
* @link       https://boldgrid.com
*/

namespace Central\Connect\View\Central;

use Central\Connect\Option;

/**
* Class: Router
*
* Setup the Rest Server extension.
*
* @since 2.0.0
*/
class ConnectNotice {

	/**
	 * Setup hooks for pages that show notice.
	 *
	 * @since 2.0.0
	 */
	public function initialize() {
		$page = isset( $_REQUEST['page'] ) ? sanitize_text_field( $_REQUEST['page'] ) : null;
		if ( $page === 'boldgrid-connect-central' ) {
			add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
		}

		add_action( 'admin_post_boldgrid_connect_provider', [ $this, 'admin_post' ] );
		add_action( 'admin_print_footer_scripts-plugins.php', [ $this, 'printRestNonce' ] );
		add_action( 'admin_menu', [ $this, 'add_submenu' ] );
		add_action( 'admin_init', function () {
			global $pagenow;

			if ( ! current_user_can( 'manage_options' ) && ! self::isConnected() ) {
				return;
			}

			if (
				( 'index.php' === $pagenow || 'plugins.php' === $pagenow )
				&& ! self::isConnected()
				&& current_user_can( 'manage_options' )
			) {
				add_action( 'admin_notices', [ $this, 'render'] );
				add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
			}

			if (
				'options-general.php' === $pagenow
				&& current_user_can( 'manage_options' )
			) {
				$this->handleConnectRedirect();
			}
		} );
	}

	/**
	 * Print the rest nonce in the footer.
	 *
	 * @since 2.0.0
	 *
	 * @return void
	 */
	public function printRestNonce() {
		print '<script id="bgc-wprest-nonce" type="application/json">{ "nonce": "' . wp_create_nonce( 'wp_rest' ) . '"}</script>';
	}

	/**
	 * Is this site connected.
	 *
	 * @since 2.0.0
	 *
	 * @return boolean
	 */
	public static function isConnected() {
		return !! Option\Connect::get( 'environment_id' );
	}

	/**
	 * Enqueue Scripts.
	 *
	 * @since 2.0.0
	 */
	public function enqueue_scripts() {
		wp_enqueue_style( 'bgc-connect-styles', plugins_url( './assets/style/admin.css', CENTRAL_CONNECT_FILE ), array(), CENTRAL_CONNECT_VERSION );

		$configs = get_option( 'bg_connect_configs', \Central_Connect_Service::get( 'configs' ) );
		$provider = get_option( 'boldgrid_connect_provider', '' );

		global $_wp_admin_css_colors;

		$user_admin_color = get_user_meta(get_current_user_id(), 'admin_color', true);
		$color = $_wp_admin_css_colors[$user_admin_color]->colors[2];

		if ( ! empty( $provider ) && ! empty( $configs['branding'][ $provider ]['primaryColor'] ) ) {
			$color = $configs['branding'][ $provider ]['primaryColor'];
		}

		$custom_css = ".bgc-connect-prompt__attn { background-color: {$color}; }";

		wp_add_inline_style( 'bgc-connect-styles', $custom_css );

		wp_enqueue_script( 'bgc-connect-script', plugins_url( './assets/js/admin.js', CENTRAL_CONNECT_FILE ), array( 'jquery' ), CENTRAL_CONNECT_VERSION, true );
	}

	/**
	 * Add the submenu item labeled Central Connection.
	 *
	 * @since 2.0.0
	 *
	 * @return void
	 */
	public function add_submenu() {
		add_options_page(
			__( 'Central Connection', 'boldgrid-connect' ),
			__( 'Central Connection', 'boldgrid-connect' ),
			'activate_plugins',
			'boldgrid-connect-central',
			function () {
				$configs = get_option( 'bg_connect_configs', \Central_Connect_Service::get( 'configs' ) );
				$centralUrl = $configs['central_url'] . '/projects?environment_id=' . Option\Connect::get( 'environment_id' );

				?>
				<div class="bg-container"> <?php
					if ( self::isConnected() ) { ?>
						<div class="bgc-connect-active">
							<h2 class="bgc-connect-active__heading"><?php print esc_html__( 'Site Connected', 'boldgrid-connect' ) ?></h2>
							<p class="bgc-connect-active__sub-heading">
								<span class="dashicons dashicons-yes-alt"></span>
								<?php print esc_html__( 'This site\'s connection is working properly.', 'boldgrid-connect' ) ?></p>
							<p>
								<?php print esc_html__( 'Log into Central and access this site\'s controls. Manage your backups, SEO, page speed and more!', 'boldgrid-connect' ) ?>
							</p>
							<a target="_blank" class="button button-primary"
								href="<?php print $centralUrl; ?>"><?php print esc_html__( 'Manage In Central', 'boldgrid-connect' ) ?></a>
						</div>
					<?php } else {
						$this->render();
					}
				?>
				</div>
			<?php
			}
		);
	}

	/**
	 * Handle connect redirect.
	 *
	 * @return void
	 */
	public function handleConnectRedirect() {
		$isRedirect = isset( $_GET['token_redirect'] ) ? $_GET['token_redirect'] : false;
		$page = isset( $_GET['page'] ) ? $_GET['page'] : false;

		if ( 'boldgrid-connect-central' === $page && $isRedirect ) {
			$authentication = new \BoldGrid\Connect\Authentication\Token();
			$token = $authentication->create( wp_get_current_user(), '+5 minutes' );

			$url = self::getConnectUrl( $token['access_token'] );
			wp_redirect( $url );
			exit;
		}
	}

	/**
	* Prints a TOS blurb used throughout the connection prompts.
	*
	* @since 2.0.0
	*
	* @echo string
	*/
	public static function termsOfService() {
		$configs = get_option( 'bg_connect_configs', \Central_Connect_Service::get( 'configs' ) );
		$provider = get_option( 'boldgrid_connect_provider', 'BoldGrid' );

		printf(
			wp_kses(
				/* Translators: placeholders are links. */
				__( 'By clicking the <strong>Connect to %3$s</strong> button, you agree to our <a href="%1$s" target="_blank">Terms of Service</a> and our <a href="%2$s" target="_blank">privacy policy</a>.', 'boldgrid-connect' ),
				array(
					'a' => array(
						'href'   => array(),
						'target' => array(),
						'rel'    => array(),
					),
					'strong' => true,
				)
			),
			$configs['branding'][ $provider ]['tos'],
			$configs['branding'][ $provider ]['privacy'],
			$configs['branding'][ $provider ]['productName']
		);
	}

	/**
	 * Get the url used for connecting a new site.
	 *
	 * @since 2.0.0
	 *
	 * @return string
	 */
	public static function getConnectUrl( $token ) {
		$configs = get_option( 'bg_connect_configs', \Central_Connect_Service::get( 'configs' ) );
		$provider = get_option( 'boldgrid_connect_provider', 'BoldGrid' );

		$query = http_build_query( [
			'url' => get_site_url(),
			'token' => $token,
			'site_title' => get_bloginfo( 'name' )
		] );

		return trailingslashit( $configs['branding'][ $provider ]['central_url'] ) . 'connect/wordpress?' . $query;
	}

	public static function getBrandLogo() {
		$configs = \Central_Connect_Service::get( 'configs' );
		$provider = get_option( 'boldgrid_connect_provider', 'BoldGrid' );
		$url = $configs['branding'][ $provider ]['logo'];

		// Allows brands to provide external URL via config or load from local file.
		if ( substr( $configs['branding'][ $provider ]['logo'], 0, 4 ) !== 'http' ) {
			$url = plugins_url( $url, CENTRAL_CONNECT_FILE );
		}

		return $url;
	}

	public static function getNoticeBody() {
		$configs = get_option( 'bg_connect_configs', \Central_Connect_Service::get( 'configs' ) );
		$provider = get_option( 'boldgrid_connect_provider', '' );
		$productName = '';

		if ( ! empty( $provider ) ) {
			$productName = $configs['branding'][ $provider ]['productName'];
		}

		$connectUrl = get_admin_url( null, 'options-general.php?page=boldgrid-connect-central&token_redirect=1' );

		if ( ! empty( $provider ) ) : ?>
			<div class="bgc-connect-prompt__logo">
			<a href="<?php echo $configs['branding'][ $provider ]['providerUrl']; ?>">
				<img src="<?php echo self::getBrandLogo(); ?>" alt="<?php esc_attr_e( 'Connect your site', 'boldgrid-connect' ); ?>" target="_blank" />
			</a>
			</div>
			<div class="bgc-connect-prompt__description">
				<h2><?php esc_html_e( "Optimize your Workflow and Connect to $productName" ); ?></h2>
				<p><?php esc_html_e( 'Connect your site to Central for remote access to this install and any other WordPress installs you connect. Central makes it easy to set up your site if you\'re a beginner and fast if you\'re an expert. Our one-of-a-kind tools and services help you bring everything together.' ); ?></p>
				<p><?php esc_html_e( 'Connecting to Central is completely free and includes a free WordPress environment that you can use for testing or staging changes.'); ?></p>
				<div class="bgc-connect-prompt__description__action">
					<a class="button-primary" target="_blank" href="<?php echo $connectUrl ?>"><?php
						esc_html_e( "Connect to $productName" ); ?></a> <?php echo self::termsOfService() ?>
				</div>
			</div>
		<?php else :
			$redirect = urlencode( remove_query_arg( 'provider', $_SERVER['REQUEST_URI'] ) );
			$redirect = urlencode( $_SERVER['REQUEST_URI'] );
			?>
			<div class="bgc-connect-prompt__description">
				<h2><?php esc_html_e( 'Get Started by Choosing your Central Provider' ); ?></h2>
				<p><?php esc_html_e( 'Connect your site to a Central provider for remote access to this install and any other WordPress installs you connect.  Central makes it easy to set up your site if you\'re a beginner and fast if you\'re an expert.  Our one-of-a-kind tools and services help you bring everything together.' ); ?></p>
				<p><?php esc_html_e( 'Connecting to Central is completely free and includes a free WordPress environment that you can use for testing or staging changes.'); ?></p>
				<form action="<?php echo admin_url( 'admin-post.php' ); ?>" method="post">
					<input type="hidden" name="action" value="boldgrid_connect_provider">
					<?php wp_nonce_field( 'boldgrid_connect_provider', 'boldgrid_connect_provider_nonce', FALSE ); ?>
					<input type="hidden" name="_wp_http_referer" value="<?php echo $redirect; ?>">
				<?php
					foreach( $configs['branding'] as $providerName => $settings ) { ?>
						<input type="radio" id="<?php echo $providerName; ?>" name="provider" value="<?php echo $providerName; ?>">
						<label for="<?php echo $providerName; ?>"><?php echo $providerName; ?></label><br>
					<?php } ?>
					<?php submit_button( 'Get Started' ); ?>
				</form>
			</div>
		<?php endif;
	}

	public function admin_post() {
		// Validate nonce.
		if ( ! wp_verify_nonce( $_POST['boldgrid_connect_provider_nonce'], 'boldgrid_connect_provider' ) ) {
			die( 'Invalid nonce.' . var_export( $_POST, true ) );
		}

		// Check and set option for provider on submission.
		if ( isset ( $_POST['provider'] ) ) {
			update_option( 'boldgrid_connect_provider', $_POST['provider'] );
		}

		if ( ! isset ( $_POST['_wp_http_referer'] ) ) {
			die( 'Missing target.' );
		}

		$url = add_query_arg( 'provider', $msg, urldecode( $_POST['_wp_http_referer'] ) );

		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * Render the connection notice.
	 *
	 * @since 2.0.0
	 */
	public function render() {
		?>

		<div class="bgc-panel bgc-connect-prompt">
			<div class="bgc-connect-prompt__attn">
				<span class="dashicons dashicons-info"></span>
				<?php esc_html_e( 'Finish setup by connecting to Central to unlock multiple WordPress environments,
					performance optimization, site protection and more!' ); ?>
				<!--- <span class="notice-dismiss" title="Dismiss this notice"></span> -->
			</div>
			<div class="bgc-connect-prompt__body">
				<?php self::getNoticeBody(); ?>
			</div>
		</div>

		<?php
	}
}
