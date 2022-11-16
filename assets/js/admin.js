/* global jQuery */
console.log( 'fdfd' );
( function( $ ) {
	var	notice = $( '.bgc-connect-prompt' ),
	wpWelcomeNotice = $( '#welcome-panel, .wp-header-end' ),
	dismiss = $( '.bgc-connect-prompt .notice-dismiss' );

	// Move the banner below the WP Welcome notice on the dashboard
	$( window ).on( 'load', function() {
		wpWelcomeNotice.after( notice );
	} );

	// Dismiss the connection banner via AJAX
	dismiss.on( 'click', function() {
		alert( 'Under Development');return;

		$( notice ).hide();

		var data = {
			action: 'jetpack_connection_banner',
			nonce: jp_banner.connectionBannerNonce,
			dismissBanner: true,
		};

		$.post( jp_banner.ajax_url, data, function( response ) {
			if ( true !== response.success ) {
				$( notice ).show();
			}
		} );
	} );

	/**
	 * Full-screen connection prompt
	 */
	dismiss.on( 'click', function() {
		$( notice ).hide();
	} );
} )( jQuery );
