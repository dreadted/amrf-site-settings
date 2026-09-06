/**
 * Drives the "Discourage search engines" toggle on the Site Settings SEO
 * tab (Provider::renderDiscourageSearchEnginesField()). Unlike every other
 * field on this tab, it isn't part of the Settings API form at all — it
 * writes straight to WordPress's own blog_public option via admin-ajax.php
 * the moment it's flipped, then locks or unlocks every other field on the
 * tab to match, so the toggle's effect is visible immediately instead of
 * only after a Save + reload.
 */
( function ( $ ) {
	'use strict';

	$( function () {
		var $toggle = $( '#amrf-discourage-search-engines' );
		if ( ! $toggle.length || typeof amrfSearchVisibility === 'undefined' ) {
			return;
		}

		var $form = $toggle.closest( 'form' );
		var $table = $toggle.closest( 'table.form-table' );

		function setLocked( locked ) {
			$form
				.find( 'input, textarea, select, button' )
				.not( $toggle )
				.prop( 'disabled', locked );
			$table.toggleClass( 'amrf-seo-locked', locked );
		}

		setLocked( $toggle.is( ':checked' ) );

		$toggle.on( 'change', function () {
			var discourage = $toggle.is( ':checked' );

			$toggle.prop( 'disabled', true );

			$.post( ajaxurl, {
				action: amrfSearchVisibility.action,
				nonce: amrfSearchVisibility.nonce,
				discourage: discourage ? '1' : '0',
			} )
				.done( function ( response ) {
					if ( response && response.success ) {
						setLocked( response.data.discourage );
					} else {
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
