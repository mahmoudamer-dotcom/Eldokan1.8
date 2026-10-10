/**
 * Checkout helper: pre-select the Bank Installments gateway after Affordability Widget Buy Now.
 * Supports classic checkout and WooCommerce Blocks checkout.
 */
( function ( $ ) {
	'use strict';

	if ( typeof window.paymobAffordabilityCheckout !== 'object' || ! window.paymobAffordabilityCheckout ) {
		return;
	}

	var config = window.paymobAffordabilityCheckout;
	if ( ! config.preselect || ! config.gatewayId ) {
		return;
	}

	var gatewayId = config.gatewayId;
	var userOverrodeSelection = false;
	var preselectEstablished = false;
	var initGracePeriod = true;
	var watchTimer = null;
	var watchObserver = null;
	var delayedSelectTimers = [];

	window.setTimeout( function () {
		initGracePeriod = false;
	}, 10000 );

	function isPlaceOrderButtonVisible() {
		var selectors = [
			'.wc-block-components-checkout-place-order-button',
			'.wc-block-checkout__actions .wc-block-components-button',
			'.wc-block-checkout__form .wc-block-components-button',
			'#place_order'
		];
		var i;
		var el;
		var rect;
		var style;

		for ( i = 0; i < selectors.length; i++ ) {
			el = document.querySelector( selectors[ i ] );
			if ( ! el ) {
				continue;
			}

			rect  = el.getBoundingClientRect();
			style = window.getComputedStyle( el );

			if (
				rect.height > 0
				&& rect.width > 0
				&& 'none' !== style.display
				&& 'hidden' !== style.visibility
				&& parseFloat( style.opacity ) > 0
			) {
				return true;
			}
		}

		return false;
	}

	function revealPlaceOrderButton() {
		var bankSelected = false;

		if ( isBlocksCheckout() ) {
			document.body.classList.add( 'paymob-aw-show-place-order' );
			bankSelected = isBlocksGatewaySelected();
			if ( ! bankSelected && window.wp && window.wp.data ) {
				try {
					bankSelected = window.wp.data.select( 'wc/store/payment' ).getActivePaymentMethod() === gatewayId;
				} catch ( err ) {
					bankSelected = false;
				}
			}
			if ( typeof window.startBlocksPlaceOrderGuard === 'function' ) {
				window.startBlocksPlaceOrderGuard();
			}
		} else if ( isClassicCheckout() ) {
			bankSelected = $( 'input[name="payment_method"][value="' + gatewayId + '"]' ).is( ':checked' );
		}

		if ( isBlocksCheckout() && config.preselect ) {
			if ( typeof window.hideLoadingIndicator === 'function' ) {
				window.hideLoadingIndicator();
			}
			if ( typeof window.setPlaceOrderButtonsVisible === 'function' ) {
				window.setPlaceOrderButtonsVisible( true );
			}
			if ( typeof window.enableBlocksPlaceOrderButton === 'function' ) {
				window.enableBlocksPlaceOrderButton();
			}
			if ( typeof window.bindBlocksPlaceOrderClickFallback === 'function' ) {
				window.bindBlocksPlaceOrderClickFallback();
			}
			if ( typeof window.updatePlaceOrderVisibility === 'function' ) {
				window.updatePlaceOrderVisibility();
			}
			return;
		}

		if ( bankSelected && typeof window.setPlaceOrderButtonsVisible === 'function' ) {
			window.setPlaceOrderButtonsVisible( true );
			return;
		}

		if ( typeof window.updatePlaceOrderVisibility === 'function' ) {
			window.updatePlaceOrderVisibility();
			return;
		}

		$( '.wc-block-checkout__form .wc-block-components-button, .wc-block-components-checkout-place-order-button, #place_order' )
			.show()
			.prop( 'disabled', false );
	}

	function keepPlaceOrderVisibleWhilePreselecting() {
		if ( userOverrodeSelection || ! config.preselect ) {
			return;
		}

		revealPlaceOrderButton();

		if ( isPlaceOrderButtonVisible() ) {
			stopPlaceOrderRevealLoop();
		}
	}

	var placeOrderRevealTimer = null;

	function startPlaceOrderRevealLoop() {
		if ( placeOrderRevealTimer ) {
			return;
		}

		keepPlaceOrderVisibleWhilePreselecting();
		placeOrderRevealTimer = window.setInterval( keepPlaceOrderVisibleWhilePreselecting, 250 );
		window.setTimeout( function () {
			stopPlaceOrderRevealLoop();
		}, 30000 );
	}

	function stopPlaceOrderRevealLoop() {
		if ( placeOrderRevealTimer ) {
			window.clearInterval( placeOrderRevealTimer );
			placeOrderRevealTimer = null;
		}
	}

	function isBlocksCheckout() {
		return document.querySelector( '.wc-block-checkout' ) !== null;
	}

	function isClassicCheckout() {
		return $( 'form.checkout, form.woocommerce-checkout' ).length > 0 && ! isBlocksCheckout();
	}

	function isBlocksGatewaySelected() {
		var $target = $( 'input[name="radio-control-wc-payment-method-options"][value="' + gatewayId + '"]' );
		if ( $target.length && $target.is( ':checked' ) ) {
			return true;
		}

		if ( window.wp && window.wp.data ) {
			try {
				return window.wp.data.select( 'wc/store/payment' ).getActivePaymentMethod() === gatewayId;
			} catch ( err ) {
				return false;
			}
		}

		return false;
	}

	function disablePreselectLocally() {
		config.preselect = false;
		window.paymobAffordabilityCheckout.preselect = false;
	}

	function clearWidgetSessionOnServer() {
		if ( ! config.ajaxUrl || ! config.nonce ) {
			return;
		}

		$.post( config.ajaxUrl, {
			action: 'paymob_aw_clear_plan',
			nonce: config.nonce,
		} );
	}

	function isIgnorableInitialMethod( selectedMethod ) {
		if ( ! selectedMethod || selectedMethod === gatewayId ) {
			return true;
		}

		// Blocks boots with paymob-pixel first; do not treat that as a manual override.
		if ( initGracePeriod && ! preselectEstablished && selectedMethod === 'paymob-pixel' ) {
			return true;
		}

		return false;
	}

	function handleManualPaymentSwitch( selectedMethod ) {
		if ( userOverrodeSelection || isIgnorableInitialMethod( selectedMethod ) ) {
			return;
		}

		userOverrodeSelection = true;
		disablePreselectLocally();
		clearWidgetSessionOnServer();
		stopWatching();
	}

	function stopWatching() {
		if ( watchTimer ) {
			window.clearInterval( watchTimer );
			watchTimer = null;
		}

		if ( watchObserver ) {
			watchObserver.disconnect();
			watchObserver = null;
		}

		stopPlaceOrderRevealLoop();

		delayedSelectTimers.forEach( function ( timerId ) {
			window.clearTimeout( timerId );
		} );
		delayedSelectTimers = [];
	}

	function selectBlocksGatewayStore() {
		if ( ! window.wp || ! window.wp.data ) {
			return false;
		}

		try {
			var dispatch = window.wp.data.dispatch( 'wc/store/payment' );
			if ( dispatch && typeof dispatch.__internalSetActivePaymentMethod === 'function' ) {
				dispatch.__internalSetActivePaymentMethod( gatewayId, {} );
				return true;
			}
		} catch ( err ) {
			return false;
		}

		return false;
	}

	function selectClassicGateway() {
		var $input = $( 'input[name="payment_method"][value="' + gatewayId + '"]' );

		if ( ! $input.length ) {
			return false;
		}

		if ( ! $input.is( ':checked' ) ) {
			$( 'input[name="payment_method"]' ).prop( 'checked', false );
			$input.prop( 'checked', true ).trigger( 'click' ).trigger( 'change' );
			$input.closest( 'li' ).find( '.payment_box' ).show();
		}

		return true;
	}

	/**
	 * Mirrors the proven ValU widget Blocks selection flow in valuWidget.js.
	 */
	function selectBlocksGatewayDom() {
		var $target = $( 'input[name="radio-control-wc-payment-method-options"][value="' + gatewayId + '"]' );

		if ( ! $target.length ) {
			return false;
		}

		if ( $target.is( ':checked' ) && isBlocksGatewaySelected() ) {
			return true;
		}

		$( 'input[name="radio-control-wc-payment-method-options"]' ).prop( 'checked', false ).removeAttr( 'checked' );
		$( '.wc-block-components-radio-control-accordion-option' )
			.removeClass( 'is-selected wc-block-components-radio-control-accordion-option--checked-option-highlighted' );
		$( '.wc-block-components-radio-control__option' ).removeClass( 'wc-block-components-radio-control__option-checked' );
		$( '.wc-block-components-radio-control-accordion-content' ).removeClass( 'is-open' ).hide();

		$target.prop( 'checked', true ).attr( 'checked', 'checked' );

		window.setTimeout( function () {
			$target.trigger( 'click' ).trigger( 'change' );
		}, 100 );

		$( '.wc-block-components-radio-control-accordion-content[data-payment-method="' + gatewayId + '"]' )
			.addClass( 'is-open' )
			.show();

		$target
			.closest( '.wc-block-components-radio-control-accordion-option' )
			.addClass( 'is-selected wc-block-components-radio-control-accordion-option--checked-option-highlighted' );

		$target
			.closest( '.wc-block-components-radio-control__option' )
			.addClass( 'wc-block-components-radio-control__option-checked' );

		selectBlocksGatewayStore();
		$( document.body ).trigger( 'wc-blocks-update-checkout' );
		return true;
	}

	function attemptSelect() {
		if ( userOverrodeSelection || ! config.preselect ) {
			return false;
		}

		var selected = false;

		if ( isBlocksCheckout() ) {
			if ( isBlocksGatewaySelected() ) {
				selected = true;
			} else {
				selected = selectBlocksGatewayDom() || selectBlocksGatewayStore();
			}
		} else if ( isClassicCheckout() ) {
			selected = selectClassicGateway();
		}

		if ( selected ) {
			preselectEstablished = true;
			revealPlaceOrderButton();
			startPlaceOrderRevealLoop();
		}

		return selected;
	}

	function scheduleDelayedSelect() {
		[ 500, 1200, 2500, 4000, 6000 ].forEach( function ( delay ) {
			delayedSelectTimers.push(
				window.setTimeout( function () {
					if ( ! userOverrodeSelection && config.preselect ) {
						attemptSelect();
					}
				}, delay )
			);
		} );
	}

	function bindManualSwitchListeners() {
		$( document ).on(
			'change',
			'input[name="payment_method"], input[name="radio-control-wc-payment-method-options"]',
			function () {
				var selected = $( this ).val() || '';
				if ( selected === gatewayId ) {
					preselectEstablished = true;
					revealPlaceOrderButton();
					startPlaceOrderRevealLoop();
					return;
				}
				if ( selected === 'paymob-pixel' ) {
					handleManualPaymentSwitch( selected );
					if ( typeof window.releaseWidgetPreselectForPixelSelection === 'function' ) {
						window.releaseWidgetPreselectForPixelSelection();
					}
					return;
				}
				handleManualPaymentSwitch( selected );
			}
		);

		if ( window.wp && window.wp.data && typeof window.wp.data.subscribe === 'function' ) {
			var previousMethod = '';

			window.wp.data.subscribe( function () {
				if ( userOverrodeSelection || ! config.preselect ) {
					return;
				}

				try {
					var activeMethod = window.wp.data.select( 'wc/store/payment' ).getActivePaymentMethod();
					if ( ! activeMethod || activeMethod === previousMethod ) {
						return;
					}

					previousMethod = activeMethod;
					if ( activeMethod === gatewayId ) {
						preselectEstablished = true;
						revealPlaceOrderButton();
						startPlaceOrderRevealLoop();
						return;
					}

					if ( isIgnorableInitialMethod( activeMethod ) ) {
						attemptSelect();
						return;
					}

					handleManualPaymentSwitch( activeMethod );
				} catch ( err ) {
					return;
				}
			}, 'wc/store/payment' );
		}
	}

	function startWatching() {
		var tries = 0;
		var maxTries = 80;

		watchTimer = window.setInterval( function () {
			tries++;
			if ( userOverrodeSelection || ! config.preselect ) {
				stopWatching();
				return;
			}
			if ( attemptSelect() || tries >= maxTries ) {
				if ( tries >= maxTries ) {
					window.clearInterval( watchTimer );
					watchTimer = null;
				} else {
					stopWatching();
				}
			}
		}, 500 );

		if ( isBlocksCheckout() && window.MutationObserver ) {
			var checkout = document.querySelector( '.wc-block-checkout' );
			if ( checkout ) {
				watchObserver = new MutationObserver( function () {
					if ( ! userOverrodeSelection && config.preselect ) {
						attemptSelect();
					}
				} );
				watchObserver.observe( checkout, { childList: true, subtree: true } );
				window.setTimeout( function () {
					if ( watchObserver ) {
						watchObserver.disconnect();
						watchObserver = null;
					}
				}, 30000 );
			}
		}

		$( document.body ).on( 'updated_checkout wc-blocks-update-checkout', function () {
			if ( ! userOverrodeSelection && config.preselect ) {
				attemptSelect();
			}
		} );

		scheduleDelayedSelect();
	}

	window.paymobAwAttemptBankPreselect = attemptSelect;

	bindManualSwitchListeners();
	startPlaceOrderRevealLoop();
	$( startWatching );
} )( jQuery );
