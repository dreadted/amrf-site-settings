/**
 * Media library picker for Site Settings' "media" field type. Uses
 * attachment.url (the original/full-size file), not a cropped WP subsize.
 */
( function ( $ ) {
	'use strict';

	function initMediaField( $wrapper ) {
		var $input = $wrapper.find( '.amrf-media-field__input' );
		var $preview = $wrapper.find( '.amrf-media-field__preview' );
		var $choose = $wrapper.find( '.amrf-media-field__choose' );
		var $remove = $wrapper.find( '.amrf-media-field__remove' );
		var frame;

		$choose.on( 'click', function ( event ) {
			event.preventDefault();

			if ( frame ) {
				frame.open();
				return;
			}

			frame = wp.media( {
				title: $choose.data( 'title' ),
				button: { text: $choose.data( 'button' ) },
				multiple: false,
				library: { type: 'image' },
			} );

			frame.on( 'select', function () {
				var attachment = frame.state().get( 'selection' ).first().toJSON();

				$input.val( attachment.url ).trigger( 'change' );
				$preview.attr( 'src', attachment.url ).show();
				$remove.show();
			} );

			frame.open();
		} );

		$remove.on( 'click', function ( event ) {
			event.preventDefault();

			$input.val( '' ).trigger( 'change' );
			$preview.attr( 'src', '' ).hide();
			$remove.hide();
		} );
	}

	$( function () {
		$( '.amrf-media-field' ).each( function () {
			initMediaField( $( this ) );
		} );
	} );
} )( jQuery );
