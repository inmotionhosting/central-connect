<?php
/**
 * File: Server.php
 *
 * Setup the Rest Router extension.
 *
 * @since      2.0.0
 * @package    Central\Connect\Rest
 * @author     InMotion Hosting <central-dev@inmotionhosting.com>
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
		$page = null;

		if ( isset( $_REQUEST['page'] ) ) {
			$page = sanitize_text_field( wp_unslash( $_REQUEST['page'] ) );
		}

		if ( 'central-connect' === $page ) {
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		}

		add_action( 'admin_post_boldgrid_connect_provider', array( $this, 'admin_post' ) );
		add_action( 'admin_print_footer_scripts-plugins.php', array( $this, 'printRestNonce' ) );
		add_action( 'admin_menu', array( $this, 'add_submenu' ) );
		add_action(
			'admin_init',
			function () {
				global $pagenow;

				if ( ! current_user_can( 'manage_options' ) && ! self::isConnected() ) {
					return;
				}

				if (
				( 'index.php' === $pagenow || 'plugins.php' === $pagenow )
				&& ! self::isConnected()
				&& current_user_can( 'manage_options' )
				) {
					add_action( 'admin_notices', array( $this, 'render' ) );
					add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
				}

				if (
				'options-general.php' === $pagenow
				&& current_user_can( 'manage_options' )
				) {
					$this->handleConnectRedirect();
				}
			}
		);
	}

	/**
	 * Print the rest nonce in the footer.
	 *
	 * @since 2.0.0
	 *
	 * @return void
	 */
	public function printRestNonce() {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
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
		return ! ! Option\Connect::get( 'environment_id' );
	}

	/**
	 * Enqueue Scripts.
	 *
	 * @since 2.0.0
	 */
	public function enqueue_scripts() {
		wp_enqueue_style( 'central-connect-styles', plugins_url( './assets/style/admin.css', CENTRAL_CONNECT_FILE ), array(), CENTRAL_CONNECT_VERSION );

		$configs = get_option( 'bg_connect_configs', \Central_Connect_Service::get( 'configs' ) );
		$provider = get_option( 'boldgrid_connect_provider', '' );

		global $_wp_admin_css_colors;

		$user_admin_color = get_user_meta( get_current_user_id(), 'admin_color', true );
		$color = $_wp_admin_css_colors[ $user_admin_color ]->colors[2];

		if ( ! empty( $provider ) && ! empty( $configs['branding'][ $provider ]['primaryColor'] ) ) {
			$color = $configs['branding'][ $provider ]['primaryColor'];
		}

		$custom_css = ".central-connect-prompt__attn { background-color: {$color}; }";

		wp_add_inline_style( 'central-connect-styles', $custom_css );

		wp_enqueue_script( 'central-connect-script', plugins_url( './assets/js/admin.js', CENTRAL_CONNECT_FILE ), array( 'jquery' ), CENTRAL_CONNECT_VERSION, true );
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
			__( 'Central Connection', 'central-connect' ),
			__( 'Central Connection', 'central-connect' ),
			'activate_plugins',
			'central-connect',
			function () {
				$configs = get_option( 'bg_connect_configs', \Central_Connect_Service::get( 'configs' ) );
				$centralUrl = $configs['central_url'] . '/projects?environment_id=' . Option\Connect::get( 'environment_id' );

				?>
				<div class="central-container"> 
				<?php
				if ( self::isConnected() ) {
					
					?>
						<div class="central-connect-active">
							<h2 class="central-connect-active__heading"><?php print esc_html__( 'Site Connected', 'central-connect' ); ?></h2>
							<p class="central-connect-active__sub-heading">
								<span class="dashicons dashicons-yes-alt"></span>
								<?php print esc_html__( 'This site\'s connection is working properly.', 'central-connect' ); ?></p>
							<p>
								<?php print esc_html__( 'Log into Central and access this site\'s controls. Manage your backups, SEO, page speed and more!', 'central-connect' ); ?>
							</p>
							<a target="_blank" class="button button-primary"
								href="<?php echo esc_url( $centralUrl ); ?>"><?php print esc_html__( 'Manage In Central', 'central-connect' ); ?></a>
						</div>
					<?php
				} else {
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
		$isRedirect = false;
		$page = false;

		if ( isset( $_GET['token_redirect'] ) ) {
			$isRedirect = sanitize_text_field( wp_unslash( $_GET['token_redirect'] ) );
		}

		if ( isset( $_GET['page'] ) ) {
			$page = sanitize_text_field( wp_unslash( $_GET['page'] ) );
		}

		if ( 'central-connect' === $page && $isRedirect ) {
			$authentication = new \Central\Connect\Authentication\Token();
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
				__( 'By clicking the <strong>Connect to %3$s</strong> button, you agree to our <a href="%1$s" target="_blank">Terms of Service</a> and our <a href="%2$s" target="_blank">privacy policy</a>.', 'central-connect' ),
				array(
					'a' => array(
						'href'   => array(),
						'target' => array(),
						'rel'    => array(),
					),
					'strong' => true,
				)
			),
			esc_url( $configs['branding'][ $provider ]['tos'] ),
			esc_url( $configs['branding'][ $provider ]['privacy'] ),
			esc_html( $configs['branding'][ $provider ]['productName'] )
		);
	}

	/**
	 * Get the url used for connecting a new site.
	 *
	 * @since 2.0.0
	 *
	 * @param string $token Central connect token.
	 *
	 * @return string
	 */
	public static function getConnectUrl( $token ) {
		$configs = get_option( 'bg_connect_configs', \Central_Connect_Service::get( 'configs' ) );
		$provider = get_option( 'boldgrid_connect_provider', 'BoldGrid' );

		$query = http_build_query(
			array(
				'url' => get_site_url(),
				'token' => $token,
				'site_title' => get_bloginfo( 'name' ),
			)
		);

		return trailingslashit( $configs['branding'][ $provider ]['central_url'] ) . 'connect/wordpress?' . $query;
	}

	/**
	 * Get the brand's logo to use.
	 *
	 * @since 2.0.0
	 *
	 * @return string $url The URL for the brand's logo.
	 */
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

	/**
	 * Get the HTML for the admin notice's body.
	 *
	 * @since 2.0.0
	 *
	 * @return void
	 */
	public static function getNoticeBody() {
		$configs = get_option( 'bg_connect_configs', \Central_Connect_Service::get( 'configs' ) );
		$provider = get_option( 'boldgrid_connect_provider', '' );
		$productName = '';

		if ( ! empty( $provider ) ) {
			$productName = $configs['branding'][ $provider ]['productName'];
		}

		$connectUrl = get_admin_url( null, 'options-general.php?page=central-connect&token_redirect=1' );

		if ( ! empty( $provider ) ) :
			?>
			<div class="central-connect-prompt__logo">
			<a href="<?php echo esc_url( $configs['branding'][ $provider ]['providerUrl'] ); ?>">
				<img src="<?php echo esc_url( self::getBrandLogo() ); ?>" alt="<?php esc_attr_e( 'Connect your site', 'central-connect' ); ?>" target="_blank" />
			</a>
			</div>
			<div class="central-connect-prompt__description">
				<h2><?php printf( /* translators: %s: Name of the product. */ esc_html__( 'Optimize your Workflow and Connect to %s', 'central-connect' ), esc_html( $productName ) ); ?></h2>
				<p><?php esc_html_e( 'Connect your site to Central for remote access to this install and any other WordPress installs you connect. Central makes it easy to set up your site if you\'re a beginner and fast if you\'re an expert. Our one-of-a-kind tools and services help you bring everything together.', 'central-connect' ); ?></p>
				<p><?php esc_html_e( 'Connecting to Central is completely free and includes a free WordPress environment that you can use for testing or staging changes.', 'central-connect' ); ?></p>
				<div class="central-connect-prompt__description__action">
					<a class="button-primary" target="_blank" href="<?php echo esc_url( $connectUrl ); ?>">
						<?php printf( /* translators: %s: Name of the product. */ esc_html__( 'Connect to %s', 'central-connect' ), esc_html( $productName ) ); ?>
					</a>
					<p><?php self::termsOfService(); ?></p>
				</div>
			</div>
			<?php
		else :
			if ( isset( $_SERVER['REQUEST_URI'] ) ) {
				$redirect = urlencode( remove_query_arg( 'provider', esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) ) );
				$redirect = urlencode( esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) );
			}

			?>
			<div class="central-connect-prompt__description">
				<h2><?php esc_html_e( 'Get Started by Choosing your Central Provider', 'central-connect' ); ?></h2>
				<p><?php esc_html_e( 'Connect your site to a Central provider for remote access to this install and any other WordPress installs you connect.  Central makes it easy to set up your site if you\'re a beginner and fast if you\'re an expert.  Our one-of-a-kind tools and services help you bring everything together.', 'central-connect' ); ?></p>
				<p><?php esc_html_e( 'Connecting to Central is completely free and includes a free WordPress environment that you can use for testing or staging changes.', 'central-connect' ); ?></p>
				<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
					<input type="hidden" name="action" value="boldgrid_connect_provider">
					<?php wp_nonce_field( 'boldgrid_connect_provider', 'boldgrid_connect_provider_nonce', false ); ?>
					<input type="hidden" name="_wp_http_referer" value="<?php echo esc_url( $redirect ); ?>">
				<?php
				foreach ( $configs['branding'] as $providerName => $settings ) {
					?>
						<input type="radio" id="<?php echo esc_attr( $providerName ); ?>" name="provider" value="<?php echo esc_attr( $providerName ); ?>">
						<label for="<?php echo esc_attr( $providerName ); ?>"><?php echo esc_html( $providerName ); ?></label><br>
					<?php } ?>
					<?php submit_button( __( 'Get Started', 'central-connect' ) ); ?>
				</form>
			</div>
			<?php
		endif;
	}

	/**
	 * Custom handling of $_GET and $_POST values.
	 *
	 * @since 2.0.0
	 *
	 * @return void
	 */
	public function admin_post() {
		// Validate nonce.
		if ( ! empty( $_POST['boldgrid_connect_provider_nonce'] ) && ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['boldgrid_connect_provider_nonce'] ) ), 'boldgrid_connect_provider' ) ) {
			die( 'Invalid nonce.' );
		}

		// Check and set option for provider on submission.
		$provider = get_site_option( 'boldgrid_connect_provider', 'InMotion Hosting' );

		if ( isset( $_POST['provider'] ) ) {
			$provider = sanitize_text_field( wp_unslash( $_POST['provider'] ) );
			update_option( 'boldgrid_connect_provider', $provider );
		}

		if ( ! isset( $_POST['_wp_http_referer'] ) ) {
			die( 'Missing target.' );
		}

		$url = add_query_arg( 'provider', $provider, urldecode( sanitize_text_field( wp_unslash( $_POST['_wp_http_referer'] ) ) ) );

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

		<div class="central-panel central-connect-prompt">
			<div class="central-connect-prompt__attn">
				<span class="dashicons dashicons-info"></span>
				<?php
				esc_html_e(
					'Finish setup by connecting to Central to unlock multiple WordPress environments,
					performance optimization, site protection and more!',
					'central-connect'
				);
				?>
			</div>
			<div class="central-connect-prompt__body">
				<?php self::getNoticeBody(); ?>
			</div>
		</div>

		<?php
	}
}
