/**
 * Paymob Affordability Widget — expanded panel layout fix (standalone).
 */
( function () {
	'use strict';

	var MODAL_ID          = 'paymob-aw-expanded-modal';
	var panelFixStyleEl   = null;
	var panelFixTimer     = null;
	var panelRafId        = null;
	var settings          = window.paymobAwPanelLayout || {};

	function readAdminBarHeight() {
		var html = document.documentElement;
		var body = document.body;
		var value;
		var adminBar;
		var rect;

		if ( html ) {
			value = parseInt( window.getComputedStyle( html ).getPropertyValue( '--wp-admin--admin-bar--height' ), 10 );
			if ( ! isNaN( value ) && value > 0 ) {
				return value;
			}
			value = parseInt( window.getComputedStyle( html ).marginTop, 10 );
			if ( ! isNaN( value ) && value > 0 ) {
				return value;
			}
		}

		if ( body && body.classList.contains( 'admin-bar' ) ) {
			adminBar = document.getElementById( 'wpadminbar' );
			if ( adminBar ) {
				rect = adminBar.getBoundingClientRect();
				if ( rect.height > 0 ) {
					return Math.ceil( rect.bottom );
				}
			}
			if ( settings.adminBarHeight ) {
				return settings.adminBarHeight;
			}
			return window.innerWidth > 782 ? 32 : 46;
		}

		return 0;
	}

	function measureSiteHeaderOffset() {
		var maxBottom = readAdminBarHeight();
		var probePoints = [
			[ 24, 1 ],
			[ 80, 1 ],
			[ 120, 1 ],
			[ 24, 24 ],
			[ 80, 48 ],
			[ 160, 24 ]
		];
		var headerSelectors = [
			'#wpadminbar',
			'header',
			'nav',
			'[role="banner"]',
			'.site-header',
			'#masthead',
			'#site-header',
			'.header-wrapper',
			'.primary-navigation',
			'.wp-block-template-part',
			'.wc-blocks-pattern-header',
			'.woocommerce-store-header',
			'.wc-block-sticky-header',
			'.elementor-location-header',
			'.ast-primary-header-bar',
			'.ast-main-header-wrap',
			'.et-fixed-header',
			'.main-header',
			'.navbar-fixed-top',
			'header.fixed-top',
			'.sticky-header',
			'.wp-block-navigation'
		];
		var i;
		var point;
		var stack;
		var j;
		var el;
		var rect;
		var style;
		var position;

		function considerElement( candidate ) {
			if ( ! candidate || candidate === document.documentElement || candidate === document.body ) {
				return;
			}
			if ( candidate.closest( '[class*="modal_"]' ) || MODAL_ID === candidate.id ) {
				return;
			}

			style = window.getComputedStyle( candidate );
			if ( 'none' === style.display || 'hidden' === style.visibility ) {
				return;
			}

			rect = candidate.getBoundingClientRect();
			if ( rect.height < 4 || rect.width < 40 || rect.bottom <= 0 || rect.top > 260 ) {
				return;
			}

			position = style.position;
			if ( 'fixed' === position || 'sticky' === position ) {
				if ( rect.top <= 4 ) {
					maxBottom = Math.max( maxBottom, Math.ceil( rect.bottom ) );
				}
				return;
			}

			if ( rect.top < 220 ) {
				maxBottom = Math.max( maxBottom, Math.ceil( rect.bottom ) );
			}
		}

		for ( i = 0; i < probePoints.length; i++ ) {
			point = probePoints[ i ];
			if ( ! document.elementsFromPoint ) {
				break;
			}
			stack = document.elementsFromPoint( point[ 0 ], point[ 1 ] );
			if ( ! stack ) {
				continue;
			}
			for ( j = 0; j < stack.length; j++ ) {
				considerElement( stack[ j ] );
			}
		}

		headerSelectors.forEach( function ( selector ) {
			document.querySelectorAll( selector ).forEach( considerElement );
		} );

		return Math.ceil( maxBottom );
	}

	function buildPanelFixCss( offset ) {
		var rootSelector = 'html body #' + MODAL_ID + ', html body div[class*="modal_root"]';

		return [
			':root { --paymob-aw-expanded-modal-top: ' + offset + 'px; }',
			rootSelector + ' {',
			'  position: fixed !important;',
			'  top: var(--paymob-aw-expanded-modal-top, ' + offset + 'px) !important;',
			'  right: 0 !important;',
			'  bottom: 0 !important;',
			'  left: 0 !important;',
			'  width: auto !important;',
			'  height: auto !important;',
			'  inset: auto !important;',
			'  transform: none !important;',
			'  z-index: 1000001 !important;',
			'}',
			rootSelector + ' [class*="modal_panelDesktop"] {',
			'  position: absolute !important;',
			'  top: 0 !important;',
			'  right: 0 !important;',
			'  bottom: 0 !important;',
			'  left: auto !important;',
			'  width: min(550px, 100vw) !important;',
			'  height: 100% !important;',
			'  max-height: 100% !important;',
			'  margin: 0 !important;',
			'  z-index: 1000001 !important;',
			'  display: flex !important;',
			'  flex-direction: column !important;',
			'  overflow: hidden !important;',
			'}',
			rootSelector + ' [class*="modal_dialog"],',
			rootSelector + ' [class*="modal_dialog__"] {',
			'  height: 100% !important;',
			'  max-height: 100% !important;',
			'  display: flex !important;',
			'  flex-direction: column !important;',
			'  overflow: hidden !important;',
			'}',
			rootSelector + ' [class*="modal_dialogDesktop"] {',
			'  overflow: visible !important;',
			'}',
			rootSelector + ' [class*="modal_header"],',
			rootSelector + ' [class*="modal_header__"] {',
			'  display: flex !important;',
			'  align-items: center !important;',
			'  justify-content: space-between !important;',
			'  visibility: visible !important;',
			'  opacity: 1 !important;',
			'  min-height: 56px !important;',
			'  flex: 0 0 auto !important;',
			'  width: 100% !important;',
			'  box-sizing: border-box !important;',
			'  position: relative !important;',
			'  z-index: 5 !important;',
			'  overflow: visible !important;',
			'  background-color: #144dff !important;',
			'  color: #ffffff !important;',
			'  padding: 12px !important;',
			'}',
			rootSelector + ' [class*="modal_closeFloatingDesktop"],',
			rootSelector + ' [class*="modal_closeFloatingMobile"] {',
			'  display: flex !important;',
			'  visibility: visible !important;',
			'  opacity: 1 !important;',
			'}',
			rootSelector + ' [class*="modal_closeFloatingDesktop"],',
			rootSelector + ' [class*="modal_closeFloatingMobile"] {',
			'  position: absolute !important;',
			'  top: 50% !important;',
			'  right: 12px !important;',
			'  left: auto !important;',
			'  transform: translateY(-50%) !important;',
			'  z-index: 1000003 !important;',
			'}',
			rootSelector + ' [class*="modal_title"],',
			rootSelector + ' [class*="modal_title__"] {',
			'  display: block !important;',
			'  visibility: visible !important;',
			'  opacity: 1 !important;',
			'  flex: 1 1 auto !important;',
			'  min-width: 0 !important;',
			'  font-size: 14px !important;',
			'  font-weight: 600 !important;',
			'  line-height: 1.4 !important;',
			'  padding-right: 52px !important;',
			'  color: #ffffff !important;',
			'  -webkit-text-fill-color: #ffffff !important;',
			'  background: none !important;',
			'  background-image: none !important;',
			'  background-clip: border-box !important;',
			'  -webkit-background-clip: border-box !important;',
			'}',
			rootSelector + ' [class*="modal_poweredRow"],',
			rootSelector + ' [class*="modal_poweredRow__"] {',
			'  display: flex !important;',
			'  align-items: center !important;',
			'  visibility: visible !important;',
			'  opacity: 1 !important;',
			'  flex: 0 0 auto !important;',
			'  margin-right: 48px !important;',
			'}',
			rootSelector + ' [class*="modal_poweredLabel"],',
			rootSelector + ' [class*="modal_logo"] {',
			'  display: block !important;',
			'  visibility: visible !important;',
			'  opacity: 1 !important;',
			'}',
			rootSelector + ' [class*="modal_poweredLabel"] {',
			'  color: #d0d5dd !important;',
			'  -webkit-text-fill-color: #d0d5dd !important;',
			'  background: none !important;',
			'  background-image: none !important;',
			'}',
			rootSelector + ' [class*="modal_body"],',
			rootSelector + ' [class*="modal_body__"] {',
			'  position: relative !important;',
			'  top: auto !important;',
			'  flex: 1 1 auto !important;',
			'  min-height: 0 !important;',
			'  overflow-y: auto !important;',
			'}',
			rootSelector + ' .paymob-aw-fallback-header,',
			'html body .paymob-aw-fallback-header {',
			'  display: flex !important;',
			'  align-items: center !important;',
			'  justify-content: space-between !important;',
			'  gap: 12px !important;',
			'  min-height: 56px !important;',
			'  padding: 12px 48px 12px 12px !important;',
			'  background-color: #144dff !important;',
			'  color: #ffffff !important;',
			'  box-sizing: border-box !important;',
			'  flex: 0 0 auto !important;',
			'  position: relative !important;',
			'  z-index: 6 !important;',
			'}',
			rootSelector + ' .paymob-aw-fallback-header__title,',
			'html body .paymob-aw-fallback-header__title {',
			'  flex: 1 1 auto !important;',
			'  min-width: 0 !important;',
			'  font-size: 14px !important;',
			'  font-weight: 600 !important;',
			'  line-height: 1.4 !important;',
			'  color: #ffffff !important;',
			'  -webkit-text-fill-color: #ffffff !important;',
			'}',
			rootSelector + ' .paymob-aw-fallback-header__brand,',
			'html body .paymob-aw-fallback-header__brand {',
			'  display: flex !important;',
			'  align-items: center !important;',
			'  gap: 4px !important;',
			'  flex: 0 0 auto !important;',
			'}',
			rootSelector + ' .paymob-aw-fallback-header__label,',
			'html body .paymob-aw-fallback-header__label {',
			'  font-size: 12px !important;',
			'  line-height: 1 !important;',
			'  color: #d0d5dd !important;',
			'  -webkit-text-fill-color: #d0d5dd !important;',
			'}',
			rootSelector + ' .paymob-aw-fallback-header__logo,',
			'html body .paymob-aw-fallback-header__logo {',
			'  display: block !important;',
			'  height: 17px !important;',
			'  width: auto !important;',
			'}',
			rootSelector + ' .paymob-aw-fallback-header__close,',
			'html body .paymob-aw-fallback-header__close {',
			'  position: absolute !important;',
			'  top: 50% !important;',
			'  right: 12px !important;',
			'  transform: translateY(-50%) !important;',
			'  display: flex !important;',
			'  align-items: center !important;',
			'  justify-content: center !important;',
			'  width: 40px !important;',
			'  height: 40px !important;',
			'  border: none !important;',
			'  border-radius: 50% !important;',
			'  background: #ffffff !important;',
			'  color: #101828 !important;',
			'  cursor: pointer !important;',
			'  box-shadow: 0 6px 16px rgba(0, 0, 0, 0.18) !important;',
			'  font-size: 24px !important;',
			'  line-height: 1 !important;',
			'  padding: 0 !important;',
			'}',
			rootSelector + '.paymob-aw-fallback-header-present [class*="modal_header"],',
			'html body .paymob-aw-fallback-header-present [class*="modal_header"] {',
			'  display: none !important;',
			'}'
		].join( '\n' );
	}

	function updatePanelFixStylesheet( offset ) {
		if ( ! panelFixStyleEl ) {
			panelFixStyleEl = document.getElementById( 'paymob-aw-panel-fix' );
			if ( ! panelFixStyleEl ) {
				panelFixStyleEl = document.createElement( 'style' );
				panelFixStyleEl.id = 'paymob-aw-panel-fix';
				document.head.appendChild( panelFixStyleEl );
			}
		}
		panelFixStyleEl.textContent = buildPanelFixCss( offset );
	}

	function findModalRoot() {
		return document.getElementById( MODAL_ID ) || document.querySelector( 'div[class*="modal_root"]' );
	}

	function escapeHtml( value ) {
		return String( value )
			.replace( /&/g, '&amp;' )
			.replace( /</g, '&lt;' )
			.replace( />/g, '&gt;' )
			.replace( /"/g, '&quot;' );
	}

	function ensureFallbackHeader( modalRoot ) {
		if ( typeof window.paymobAwEnsurePanelHeader === 'function' ) {
			window.paymobAwEnsurePanelHeader( modalRoot );
			return;
		}

		var panel     = modalRoot.querySelector( '[class*="modal_panel"]' );
		var sdkHeader = modalRoot.querySelector( '[class*="modal_header"]' );
		var titleEl   = modalRoot.querySelector( '[class*="modal_title"]' );
		var sdkClose  = modalRoot.querySelector( '[class*="modal_closeFloatingDesktop"], [class*="modal_closeFloatingMobile"]' );
		var fallback;
		var closeBtn;
		var titleText;
		var logoSrc;

		if ( ! panel ) {
			return;
		}

		panel.style.setProperty( 'display', 'flex', 'important' );
		panel.style.setProperty( 'flex-direction', 'column', 'important' );
		panel.style.setProperty( 'height', '100%', 'important' );

		fallback = panel.querySelector( '.paymob-aw-fallback-header' );
		if ( ! fallback ) {
			titleText = ( titleEl && titleEl.textContent.trim() ) || settings.panelTitle || 'Choose your plan to proceed';
			logoSrc   = settings.logoUrl || '';
			if ( modalRoot.querySelector( '[class*="modal_logo"]' ) ) {
				logoSrc = modalRoot.querySelector( '[class*="modal_logo"]' ).src || logoSrc;
			}

			fallback = document.createElement( 'div' );
			fallback.className = 'paymob-aw-fallback-header';
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
					if ( sdkClose ) {
						sdkClose.click();
						return;
					}
					var overlay = modalRoot.querySelector( '[class*="modal_overlay"]' );
					if ( overlay ) {
						overlay.click();
					}
				} );
			}
		}

		if ( sdkHeader ) {
			sdkHeader.style.setProperty( 'display', 'none', 'important' );
			sdkHeader.style.setProperty( 'height', '0', 'important' );
			sdkHeader.style.setProperty( 'min-height', '0', 'important' );
			sdkHeader.style.setProperty( 'overflow', 'hidden', 'important' );
		}

		if ( sdkClose ) {
			sdkClose.style.setProperty( 'display', 'none', 'important' );
		}
	}

	function applyExpandedPanelStyles( offset ) {
		var modalRoot = findModalRoot();
		var panel;
		var dialog;
		var body;

		if ( ! modalRoot ) {
			return false;
		}

		if ( 'number' !== typeof offset ) {
			offset = measureSiteHeaderOffset();
		}

		if ( MODAL_ID !== modalRoot.id ) {
			modalRoot.id = MODAL_ID;
		}

		document.documentElement.style.setProperty( '--paymob-aw-expanded-modal-top', offset + 'px' );
		document.documentElement.style.setProperty( '--paymob-aw-header-offset', offset + 'px' );
		updatePanelFixStylesheet( offset );

		modalRoot.style.setProperty( 'position', 'fixed', 'important' );
		modalRoot.style.setProperty( 'top', offset + 'px', 'important' );
		modalRoot.style.setProperty( 'right', '0', 'important' );
		modalRoot.style.setProperty( 'bottom', '0', 'important' );
		modalRoot.style.setProperty( 'left', '0', 'important' );
		modalRoot.style.setProperty( 'inset', 'auto', 'important' );
		modalRoot.style.setProperty( 'transform', 'none', 'important' );
		modalRoot.style.setProperty( 'z-index', '1000001', 'important' );

		panel = modalRoot.querySelector( '[class*="modal_panelDesktop"]' );
		if ( panel ) {
			panel.style.setProperty( 'position', 'absolute', 'important' );
			panel.style.setProperty( 'top', '0', 'important' );
			panel.style.setProperty( 'right', '0', 'important' );
			panel.style.setProperty( 'bottom', '0', 'important' );
			panel.style.setProperty( 'left', 'auto', 'important' );
			panel.style.setProperty( 'height', '100%', 'important' );
			panel.style.setProperty( 'max-height', '100%', 'important' );
			panel.style.setProperty( 'z-index', '1000001', 'important' );
		}

		panel = modalRoot.querySelector( '[class*="modal_panelMobile"]' );
		if ( panel ) {
			panel.style.setProperty( 'max-height', 'min(80vh, calc(100vh - ' + offset + 'px))', 'important' );
			panel.style.setProperty( 'height', 'min(80vh, calc(100vh - ' + offset + 'px))', 'important' );
		}

		dialog = modalRoot.querySelector( '[class*="modal_dialog"]' );
		if ( dialog ) {
			dialog.style.setProperty( 'display', 'flex', 'important' );
			dialog.style.setProperty( 'flex-direction', 'column', 'important' );
			dialog.style.setProperty( 'flex', '1 1 auto', 'important' );
			dialog.style.setProperty( 'min-height', '0', 'important' );
			dialog.style.setProperty( 'height', 'auto', 'important' );
			dialog.style.setProperty( 'max-height', '100%', 'important' );
			dialog.style.setProperty( 'overflow', 'hidden', 'important' );
		}

		ensureFallbackHeader( modalRoot );

		body = modalRoot.querySelector( '[class*="modal_body"]' );
		if ( body ) {
			body.style.setProperty( 'position', 'relative', 'important' );
			body.style.setProperty( 'top', 'auto', 'important' );
			body.style.setProperty( 'flex', '1 1 auto', 'important' );
			body.style.setProperty( 'min-height', '0', 'important' );
		}

		return true;
	}

	function stopPanelFixLoop() {
		if ( panelFixTimer ) {
			window.clearInterval( panelFixTimer );
			panelFixTimer = null;
		}
		if ( panelRafId ) {
			window.cancelAnimationFrame( panelRafId );
			panelRafId = null;
		}
	}

	function startPanelFixLoop() {
		if ( panelFixTimer ) {
			return;
		}

		panelFixTimer = window.setInterval( function () {
			if ( ! findModalRoot() ) {
				stopPanelFixLoop();
				if ( document.body ) {
					document.body.classList.remove( 'paymob-aw-panel-open' );
				}
				return;
			}
			applyExpandedPanelStyles();
		}, 16 );

		function rafTick() {
			if ( findModalRoot() ) {
				applyExpandedPanelStyles();
				panelRafId = window.requestAnimationFrame( rafTick );
			} else {
				panelRafId = null;
			}
		}

		panelRafId = window.requestAnimationFrame( rafTick );
	}

	function syncExpandedPanelState() {
		var modalRoot = findModalRoot();

		if ( modalRoot ) {
			document.body.classList.add( 'paymob-aw-panel-open' );
			applyExpandedPanelStyles();
			startPanelFixLoop();

			if ( ! modalRoot.getAttribute( 'data-paymob-aw-watched' ) ) {
				modalRoot.setAttribute( 'data-paymob-aw-watched', '1' );
				new MutationObserver( function () {
					applyExpandedPanelStyles();
				} ).observe( modalRoot, {
					attributes: true,
					attributeFilter: [ 'style', 'class', 'id' ],
					childList: true,
					subtree: true
				} );
			}
		} else if ( document.body ) {
			document.body.classList.remove( 'paymob-aw-panel-open' );
			stopPanelFixLoop();
		}
	}

	function boot() {
		if ( ! document.body ) {
			return;
		}

		applyExpandedPanelStyles( measureSiteHeaderOffset() );

		new MutationObserver( syncExpandedPanelState ).observe( document.body, {
			childList: true,
			subtree: true
		} );

		window.addEventListener( 'resize', function () {
			applyExpandedPanelStyles();
		} );
		window.addEventListener( 'scroll', function () {
			if ( findModalRoot() ) {
				applyExpandedPanelStyles();
			}
		}, { passive: true } );

		syncExpandedPanelState();
	}

	window.paymobAwApplyExpandedPanelLayout = applyExpandedPanelStyles;

	if ( 'loading' !== document.readyState ) {
		boot();
	} else {
		document.addEventListener( 'DOMContentLoaded', boot );
	}
} )();
