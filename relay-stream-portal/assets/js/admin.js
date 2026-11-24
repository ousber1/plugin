/* Relay Stream Portal — admin helpers */
( function ( $ ) {
	'use strict';
	// Ask for an audit reason before sensitive actions.
	$( document ).on( 'submit', 'form[data-rsp-reason]', function ( e ) {
		var reason = window.prompt( 'Reason for this action (saved in the audit log):' );
		if ( ! reason ) { e.preventDefault(); return false; }
		$( this ).find( 'input[name="reason"]' ).val( reason );
		return true;
	} );
	// Media picker for the logo.
	$( document ).on( 'click', '[data-rsp-media]', function ( e ) {
		e.preventDefault();
		var frame = wp.media( { title: 'Logo', multiple: false, library: { type: 'image' } } );
		frame.on( 'select', function () {
			var a = frame.state().get( 'selection' ).first().toJSON();
			$( '[data-rsp-media-id]' ).val( a.id );
			$( '[data-rsp-media-preview]' ).attr( 'src', ( a.sizes && a.sizes.medium ? a.sizes.medium.url : a.url ) ).show();
		} );
		frame.open();
	} );
	$( document ).on( 'click', '[data-rsp-media-clear]', function ( e ) {
		e.preventDefault();
		$( '[data-rsp-media-id]' ).val( 0 );
		$( '[data-rsp-media-preview]' ).hide();
	} );
}( jQuery ) );
