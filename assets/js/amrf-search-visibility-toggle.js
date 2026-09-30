/**
 * Drives the "Discourage search engines" toggle on the Site Settings SEO tab.
 * Writes straight to WordPress's blog_public option via admin-ajax.php the
 * moment it's flipped.
 */
( function ( $ ) {
	'use strict';

	$( function () {
		var $toggle = $( '#amrf-discourage-search-engines' );
		if ( ! $toggle.length || typeof amrfSearchVisibility === 'undefined' ) {
			return;
		}

		$toggle.on( 'change', function () {
			var discourage = $toggle.is( ':checked' );

			$toggle.prop( 'disabled', true );

			$.post( ajaxurl, {
				action: amrfSearchVisibility.action,
				nonce: amrfSearchVisibility.nonce,
				discourage: discourage ? '1' : '0',
			} )
				.done( function ( response ) {
					if ( ! response || ! response.success ) {
						$toggle.prop( 'checked', ! discourage );
					}
				} )
				.fail( function () {
					$toggle.prop( 'checked', ! discourage );
				} )
				.always( function () {
					$toggle.prop( 'disabled', false );
				} );
		} );
	} );
} )( jQuery );
