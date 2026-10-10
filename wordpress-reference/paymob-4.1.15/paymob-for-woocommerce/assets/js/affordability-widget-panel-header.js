/**
 * Paymob expanded panel — always-on storefront header (self-contained).
 */
( function () {
	'use strict';

	if ( window.paymobAwPanelHeaderBooted ) {
		return;
	}
	window.paymobAwPanelHeaderBooted = true;

	var HEADER_CLASS = 'paymob-aw-fallback-header';
	var settings     = window.paymobAwPanelLayout || {};

	function escapeHtml( value ) {
		return String( value )
			.replace( /&/g, '&amp;' )
			.replace( /</g, '&lt;' )
			.replace( />/g, '&gt;' )
			.replace( /"/g, '&quot;' );
	}

	function findModalRoot() {
		return document.getElementById( 'paymob-aw-expanded-modal' )
			|| document.querySelector( 'div[class*="modal_root"]' );
	}

	function getLogoSrc( modalRoot ) {
		var sdkLogo = modalRoot.querySelector( '[class*="modal_logo"]' );
		if ( sdkLogo && sdkLogo.src ) {
			return sdkLogo.src;
		}
		return settings.logoUrl || '';
	}

	function closeModal( modalRoot ) {
		var sdkClose = modalRoot.querySelector(
			'[class*="modal_closeFloatingDesktop"], [class*="modal_closeFloatingMobile"]'
		);
		if ( sdkClose ) {
			sdkClose.click();
			return;
		}
		var overlay = modalRoot.querySelector( '[class*="modal_overlay"]' );
		if ( overlay ) {
			overlay.click();
		}
	}

	function ensurePanelHeader( modalRoot ) {
		var panel     = modalRoot.querySelector( '[class*="modal_panel"]' );
		var dialog    = modalRoot.querySelector( '[role="dialog"]' );
		var sdkHeader = modalRoot.querySelector( '[class*="modal_header"]' );
		var sdkClose  = modalRoot.querySelector(
			'[class*="modal_closeFloatingDesktop"], [class*="modal_closeFloatingMobile"]'
		);
		var titleEl   = modalRoot.querySelector( '[class*="modal_title"]' );
		var fallback;
		var closeBtn;
		var titleText;
		var logoSrc;

		if ( ! panel ) {
			return;
		}

		modalRoot.id = 'paymob-aw-expanded-modal';

		panel.style.setProperty( 'display', 'flex', 'important' );
		panel.style.setProperty( 'flex-direction', 'column', 'important' );
		panel.style.setProperty( 'height', '100%', 'important' );
		panel.style.setProperty( 'max-height', '100%', 'important' );
		panel.style.setProperty( 'overflow', 'hidden', 'important' );

		if ( dialog ) {
			dialog.style.setProperty( 'display', 'flex', 'important' );
			dialog.style.setProperty( 'flex-direction', 'column', 'important' );
			dialog.style.setProperty( 'flex', '1 1 auto', 'important' );
			dialog.style.setProperty( 'min-height', '0', 'important' );
			dialog.style.setProperty( 'height', 'auto', 'important' );
			dialog.style.setProperty( 'max-height', '100%', 'important' );
			dialog.style.setProperty( 'overflow', 'hidden', 'important' );
			if ( ! dialog.getAttribute( 'data-theme' ) ) {
				dialog.setAttribute( 'data-theme', 'primary' );
			}
		}

		fallback = panel.querySelector( '.' + HEADER_CLASS );
		if ( ! fallback ) {
			titleText = ( titleEl && titleEl.textContent.trim() )
				|| settings.panelTitle
				|| 'Choose your plan to proceed';
			logoSrc = getLogoSrc( modalRoot );

			fallback = document.createElement( 'div' );
			fallback.className = HEADER_CLASS;
			fallback.setAttribute( 'role', 'banner' );
			fallback.innerHTML =
				'<span class="paymob-aw-fallback-header__title">' + escapeHtml( titleText ) + '</span>' +
				'<div class="paymob-aw-fallback-header__brand">' +
					'<span class="paymob-aw-fallback-header__label">Powered by</span>' +
					( logoSrc
						? '<img class="paymob-aw-fallback-header__logo" src="' + escapeHtml( logoSrc ) + '" alt="Paymob" />'
						: '<span class="paymob-aw-fallback-header__name">Paymob</span>'
					) +
				'</div>' +
				'<button type="button" class="paymob-aw-fallback-header__close" aria-label="Close modal">' +
					'<span aria-hidden="true">&times;</span>' +
				'</button>';

			panel.insertBefore( fallback, panel.firstChild );

			closeBtn = fallback.querySelector( '.paymob-aw-fallback-header__close' );
			if ( closeBtn ) {
				closeBtn.addEventListener( 'click', function () {
					closeModal( modalRoot );
				} );
			}
		}

		if ( sdkHeader ) {
			sdkHeader.style.setProperty( 'display', 'none', 'important' );
			sdkHeader.style.setProperty( 'height', '0', 'important' );
			sdkHeader.style.setProperty( 'min-height', '0', 'important' );
			sdkHeader.style.setProperty( 'padding', '0', 'important' );
			sdkHeader.style.setProperty( 'margin', '0', 'important' );
			sdkHeader.style.setProperty( 'overflow', 'hidden', 'important' );
			sdkHeader.setAttribute( 'aria-hidden', 'true' );
		}

		if ( sdkClose ) {
			sdkClose.style.setProperty( 'display', 'none', 'important' );
		}

		document.body.classList.add( 'paymob-aw-panel-open' );
	}

	function tick() {
		var modalRoot = findModalRoot();
		if ( modalRoot ) {
			ensurePanelHeader( modalRoot );
			if ( typeof window.paymobAwApplyExpandedPanelLayout === 'function' ) {
				window.paymobAwApplyExpandedPanelLayout();
			}
			return;
		}
		document.body.classList.remove( 'paymob-aw-panel-open' );
	}

	function boot() {
		tick();
		new MutationObserver( tick ).observe( document.body, {
			childList: true,
			subtree: true
		} );
		window.setInterval( function () {
			if ( findModalRoot() ) {
				tick();
			}
		}, 100 );
	}

	window.paymobAwEnsurePanelHeader = ensurePanelHeader;

	if ( 'loading' !== document.readyState ) {
		boot();
	} else {
		document.addEventListener( 'DOMContentLoaded', boot );
	}
} )();
