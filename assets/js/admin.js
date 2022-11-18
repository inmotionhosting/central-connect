/* global jQuery */
( function( $ ) {
	var	notice = $( '.central-connect-prompt' ),
	wpWelcomeNotice = $( '#welcome-panel, .wp-header-end' );

	// Move the banner below the WP Welcome notice on the dashboard
	$( window ).on( 'load', function() {
		wpWelcomeNotice.after( notice );
	} );
} )( jQuery );
