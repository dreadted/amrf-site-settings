/**
 * "Reset to theme default" button next to Site Settings' color pickers.
 * Only changes the picker; the value is stored when the form is saved.
 */
( function () {
	'use strict';

	document.addEventListener( 'click', function ( event ) {
		var button = event.target.closest( '.amrf-color-field__reset' );
		if ( ! button ) {
			return;
		}

		var input = document.getElementById( button.dataset.target );
		if ( input ) {
			input.value = button.dataset.default;
			input.dispatchEvent( new Event( 'change', { bubbles: true } ) );
		}
	} );
} )();
