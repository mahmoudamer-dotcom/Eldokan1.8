/**
 * Paymob Error Logs admin interactions: copy-to-clipboard, expand/collapse all.
 */
( function () {
	'use strict';

	function showToast( message ) {
		var toast = document.querySelector( '[data-paymob-log-toast]' );
		if ( ! toast ) {
			return;
		}
		toast.textContent = message;
		toast.hidden = false;
		window.clearTimeout( showToast._timer );
		showToast._timer = window.setTimeout( function () {
			toast.hidden = true;
		}, 2200 );
	}

	function copyText( text ) {
		if ( navigator.clipboard && navigator.clipboard.writeText ) {
			return navigator.clipboard.writeText( text );
		}

		return new Promise( function ( resolve, reject ) {
			var textarea = document.createElement( 'textarea' );
			textarea.value = text;
			textarea.setAttribute( 'readonly', '' );
			textarea.style.position = 'absolute';
			textarea.style.left = '-9999px';
			document.body.appendChild( textarea );
			textarea.select();
			try {
				document.execCommand( 'copy' );
				resolve();
			} catch ( err ) {
				reject( err );
			}
			document.body.removeChild( textarea );
		} );
	}

	function onReady( fn ) {
		if ( 'loading' !== document.readyState ) {
			fn();
		} else {
			document.addEventListener( 'DOMContentLoaded', fn );
		}
	}

	onReady( function () {
		var page = document.querySelector( '.paymob-error-logs-page' );
		if ( ! page ) {
			return;
		}

		var copiedLabel = ( window.paymobErrorLogs && window.paymobErrorLogs.copied ) || 'Copied to clipboard';

		page.addEventListener( 'click', function ( event ) {
			var copyBtn = event.target.closest( '[data-paymob-log-copy]' );
			if ( copyBtn ) {
				var text = copyBtn.getAttribute( 'data-copy-text' ) || '';
				if ( ! text ) {
					return;
				}
				copyText( text ).then( function () {
					showToast( copiedLabel );
				} );
				return;
			}

			if ( event.target.closest( '[data-paymob-log-expand-all]' ) ) {
				page.querySelectorAll( '[data-paymob-log-details]' ).forEach( function ( el ) {
					el.open = true;
				} );
				return;
			}

			if ( event.target.closest( '[data-paymob-log-collapse-all]' ) ) {
				page.querySelectorAll( '[data-paymob-log-details]' ).forEach( function ( el ) {
					el.open = false;
				} );
			}
		} );
	} );
} )();
