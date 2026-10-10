/**
 * Paymob Affordability Widget — storefront bootstrap.
 *
 * Wraps the official Paymob Widget SDK (`paymob-widget-alpha`) loaded from the
 * jsDelivr CDN. For every `[data-paymob-aw-widget]` host on the page we:
 *
 *   1. Pull the merchant configuration (publicKey, integrationId, amount in cents, currency, theme)
 *      out of `data-*` attributes and the localized `paymobAffordabilityWidget` config.
 *   2. Instantiate the SDK in selectable mode so the customer can pick a plan and Buy Now.
 *   3. When the SDK fires `onSubmit({ id, tenure, amount })`, we POST the chosen plan to
 *      WordPress so it is persisted in the WooCommerce session (the gateway later forwards
 *      the plan ID to Paymob's Intention API and pre-selects the Bank Installments method
 *      on the checkout page).
 *   4. Redirect the shopper to the WooCommerce checkout — adding the current product to the
 *      cart first on a product page via `?add-to-cart={id}`.
 *
 * Per the SDK README, the SDK script must be loaded as an ES module; PHP handles that.
 * This file is a classic IIFE so it works on every theme without bundling.
 */
( function () {
	'use strict';

	if ( typeof window.paymobAffordabilityWidget !== 'object' || ! window.paymobAffordabilityWidget ) {
		return;
	}

	var config       = window.paymobAffordabilityWidget;
	var MAX_RETRIES  = 60;          // ~12s with the 200ms back-off
	var RETRY_DELAY  = 200;
	var initialized  = false;

	function getCentsMultiplier() {
		var multiplier = parseInt( config.centsMultiplier, 10 );
		return ( ! isNaN( multiplier ) && multiplier > 0 ) ? multiplier : 100;
	}

	function getAmountDecimals() {
		var decimals = parseInt( config.amountDecimals, 10 );
		return ( ! isNaN( decimals ) && decimals >= 0 ) ? decimals : 2;
	}

	function majorAmountToCents( amount ) {
		var major = parseFloat( amount );
		if ( isNaN( major ) || major <= 0 ) {
			return 0;
		}

		var factor = Math.pow( 10, getAmountDecimals() );
		return Math.round( major * factor ) / factor * getCentsMultiplier();
	}

	function normalizeAmountCents( majorAmount, amountCents ) {
		var major = parseFloat( majorAmount );
		var cents = parseInt( amountCents, 10 );

		if ( isNaN( major ) || major <= 0 ) {
			return isNaN( cents ) || cents <= 0 ? 0 : cents;
		}

		var expected = majorAmountToCents( major );
		if ( expected <= 0 ) {
			return isNaN( cents ) || cents <= 0 ? 0 : cents;
		}

		if ( isNaN( cents ) || cents <= 0 ) {
			return expected;
		}

		// Some block carts expose major units in amount_cents (e.g. 10 instead of 1000).
		if ( cents === Math.round( major ) || Math.abs( cents - major ) < 0.001 ) {
			return expected;
		}

		return cents;
	}

	function getMajorAmountFromCartTotals( totals, items ) {
		var minorUnit = typeof totals.currency_minor_unit !== 'undefined'
			? parseInt( totals.currency_minor_unit, 10 )
			: getAmountDecimals();

		if ( isNaN( minorUnit ) || minorUnit < 0 ) {
			minorUnit = getAmountDecimals();
		}

		var rawTotal = parseFloat( totals.total_price );
		if ( isNaN( rawTotal ) || rawTotal <= 0 ) {
			return 0;
		}

		var divisor = Math.pow( 10, minorUnit );
		var majorFromTotal = rawTotal / divisor;

		if ( Array.isArray( items ) && items.length ) {
			var lineMinorTotal = 0;
			var i;
			var item;
			var lineMinor;

			for ( i = 0; i < items.length; i++ ) {
				item = items[ i ];
				lineMinor = 0;

				if ( item.totals && typeof item.totals.line_total !== 'undefined' ) {
					lineMinor = parseInt( item.totals.line_total, 10 );
				} else if ( typeof item.line_total !== 'undefined' ) {
					lineMinor = parseInt( item.line_total, 10 );
				}

				if ( ! isNaN( lineMinor ) && lineMinor > 0 ) {
					lineMinorTotal += lineMinor;
				}
			}

			if ( lineMinorTotal > 0 ) {
				return lineMinorTotal / divisor;
			}
		}

		// Fallback when total_price is already major units (10 EGP stored as 10, not 1000).
		if ( majorFromTotal > 0 && majorFromTotal < 1 && rawTotal >= 1 ) {
			return rawTotal;
		}

		return majorFromTotal;
	}

	function passesCartThreshold( amount ) {
		if ( ! config.minCartEnabled ) {
			return true;
		}

		var threshold = parseFloat( config.minCartAmount );
		var major     = parseFloat( amount );

		if ( isNaN( threshold ) || threshold <= 0 ) {
			return true;
		}
		if ( isNaN( major ) || major <= 0 ) {
			return false;
		}

		return major >= threshold;
	}

	function passesProductThreshold( amount ) {
		if ( ! config.minProductEnabled ) {
			return true;
		}

		var threshold = parseFloat( config.minProductAmount );
		var major     = parseFloat( amount );

		if ( isNaN( threshold ) || threshold <= 0 ) {
			return true;
		}
		if ( isNaN( major ) || major <= 0 ) {
			return false;
		}

		return major >= threshold;
	}

	function passesContextThreshold( amount, context ) {
		if ( context === 'product' ) {
			return passesProductThreshold( amount );
		}
		if ( context === 'cart' ) {
			return passesCartThreshold( amount );
		}
		return true;
	}

	function buildHiddenCartPayload( currency ) {
		return {
			visible:      false,
			amount:       0,
			amount_cents: 0,
			currency:     currency || config.currency || 'EGP'
		};
	}

	function normalizeCartWidgetPayload( payload, context ) {
		if ( ! payload ) {
			return buildHiddenCartPayload();
		}

		var amount = parseFloat( payload.amount );
		if ( isNaN( amount ) || amount <= 0 ) {
			return buildHiddenCartPayload( payload.currency );
		}

		if ( ! passesContextThreshold( amount, context || 'cart' ) ) {
			return buildHiddenCartPayload( payload.currency );
		}

		var amountCents = normalizeAmountCents( amount, payload.amount_cents );
		if ( amountCents <= 0 ) {
			return buildHiddenCartPayload( payload.currency );
		}

		return {
			visible:      true,
			amount:       amount,
			amount_cents: amountCents,
			currency:     payload.currency || config.currency || 'EGP'
		};
	}
	var cartReloadBound = false;
	var cartReloadTimer = null;
	var cartSyncInFlight = false;
	var cartSyncQueued = false;
	var blockCartTotalCache = null;
	var blockCartItemCache = 0;
	var blockCartTotalsReady = false;
	var cartSyncWatcher = null;
	var cartSyncAttempts = 0;
	var cartMutationObserver = null;
	var lastAppliedCartCents = null;
	var cartPageSyncStarted = false;
	var MAX_CART_SYNC_ATTEMPTS = 80;

	function waitForSdk( retriesLeft ) {
		if ( typeof window.PaymobWidget === 'function' ) {
			init();
			return;
		}
		if ( retriesLeft <= 0 ) {
			if ( window.console && console.warn ) {
				console.warn( 'Paymob Affordability Widget: SDK did not load in time.' );
			}
			return;
		}
		setTimeout( function () {
			waitForSdk( retriesLeft - 1 );
		}, RETRY_DELAY );
	}

	function init() {
		if ( initialized ) {
			return;
		}
		initialized = true;

		if ( ! config.publicKey ) {
			return;
		}

		bootstrapWidgets();
		setupCartWidgetReload();
	}

	function getCartWidgetHosts() {
		return document.querySelectorAll( '.paymob-aw-widget-host--cart[data-paymob-aw-widget]' );
	}

	function getPlacedCartWidgetHosts() {
		var placed = [];
		Array.prototype.forEach.call( getCartWidgetHosts(), function ( host ) {
			if ( ! host.closest( '#paymob-aw-cart-widget-source' ) ) {
				placed.push( host );
			}
		} );
		return placed;
	}

	function dedupeCartWidgetHosts() {
		var placed = getPlacedCartWidgetHosts();
		for ( var i = 1; i < placed.length; i++ ) {
			placed[ i ].parentNode.removeChild( placed[ i ] );
		}
	}

	function getPrimaryCartWidgetHost() {
		var placed = getPlacedCartWidgetHosts();
		return placed.length ? placed[ 0 ] : null;
	}

	function isCartWidgetMounted() {
		var host = getPrimaryCartWidgetHost();
		return !! host && host.getAttribute( 'data-paymob-aw-mounted' ) === '1';
	}

	function getBlockCartItemCount() {
		if ( ! window.wp || ! window.wp.data ) {
			return 0;
		}

		try {
			var store = window.wp.data.select( 'wc/store/cart' );
			if ( ! store || typeof store.getCartItems !== 'function' ) {
				return 0;
			}
			var items = store.getCartItems();
			return Array.isArray( items ) ? items.length : 0;
		} catch ( err ) {
			return 0;
		}
	}

	function cartShouldShowWidget() {
		var payload = getBlockCartPayloadFromStore();
		return !!( payload && payload.visible && payload.amount_cents );
	}

	function getBlockCartPayloadFromStore() {
		if ( ! window.wp || ! window.wp.data ) {
			return null;
		}

		try {
			var store = window.wp.data.select( 'wc/store/cart' );
			if ( ! store || typeof store.getCartTotals !== 'function' ) {
				return null;
			}

			var totals = store.getCartTotals();
			var items  = typeof store.getCartItems === 'function' ? store.getCartItems() : [];

			if ( ! totals || typeof totals.total_price === 'undefined' ) {
				return null;
			}

			if ( Array.isArray( items ) && ! items.length && ( ! totals.total_price || '0' === String( totals.total_price ) ) ) {
				return null;
			}

			var minorUnit = typeof totals.currency_minor_unit !== 'undefined' ? parseInt( totals.currency_minor_unit, 10 ) : getAmountDecimals();
			if ( isNaN( minorUnit ) || minorUnit < 0 ) {
				minorUnit = getAmountDecimals();
			}

			var amount  = getMajorAmountFromCartTotals( totals, items );
			if ( isNaN( amount ) || amount <= 0 ) {
				return null;
			}

			var amountCents = majorAmountToCents( amount );

			return normalizeCartWidgetPayload( {
				visible:      true,
				amount:       amount,
				amount_cents: amountCents,
				currency:     totals.currency_code || config.currency || 'EGP'
			}, 'cart' );
		} catch ( err ) {
			return null;
		}
	}

	function placeBlockCartWidget() {
		var source = document.getElementById( 'paymob-aw-cart-widget-source' );
		if ( ! source ) {
			return getPlacedCartWidgetHosts().length > 0;
		}

		var template = source.querySelector( '[data-paymob-aw-widget]' );
		if ( ! template ) {
			return false;
		}

		var existingPlaced = getPrimaryCartWidgetHost();
		if ( existingPlaced ) {
			return true;
		}

		var selectors = [
			'.wp-block-woocommerce-proceed-to-checkout-block',
			'.wc-block-cart__submit-container',
			'.wc-block-cart__sidebar .wc-block-components-totals-wrapper',
			'.wp-block-woocommerce-cart-order-summary-block .wc-block-components-totals-footer-item',
			'.wp-block-woocommerce-cart-order-summary-block',
			'.wc-block-cart__main'
		];

		var anchor = null;
		for ( var i = 0; i < selectors.length; i++ ) {
			var node = document.querySelector( selectors[ i ] );
			if ( node ) {
				anchor = node;
				break;
			}
		}

		if ( ! anchor || ! anchor.parentNode ) {
			return false;
		}

		var placedHost = template.cloneNode( true );
		placedHost.style.display = '';
		placedHost.removeAttribute( 'hidden' );
		placedHost.removeAttribute( 'data-paymob-aw-mounted' );

		anchor.parentNode.insertBefore( placedHost, anchor );
		return true;
	}

	function ensureBlockCartWidgetPlaced( callback ) {
		if ( placeBlockCartWidget() ) {
			if ( typeof callback === 'function' ) {
				callback();
			}
			return;
		}

		var tries = 0;
		var maxTries = 40;
		var timer = window.setInterval( function () {
			tries++;
			if ( placeBlockCartWidget() || tries >= maxTries ) {
				window.clearInterval( timer );
				if ( typeof callback === 'function' ) {
					callback();
				}
			}
		}, 250 );
	}

	function mountVisibleCartWidgetHosts() {
		dedupeCartWidgetHosts();
		var host = getPrimaryCartWidgetHost();
		if ( host ) {
			mountWidget( host );
		}
	}

	function mountAllWidgets() {
		var hosts = document.querySelectorAll( '[data-paymob-aw-widget]' );
		Array.prototype.forEach.call( hosts, function ( host ) {
			if ( host.getAttribute( 'data-context' ) === 'cart' ) {
				return;
			}
			mountWidget( host );
		} );
		mountVisibleCartWidgetHosts();
	}

	function bootstrapWidgets() {
		var needsBlockPlacement = config.isBlockCart
			|| document.getElementById( 'paymob-aw-cart-widget-source' )
			|| document.querySelector( '.wc-block-cart' );

		if ( ! needsBlockPlacement ) {
			mountAllWidgets();
			return;
		}

		startCartPageSync();
	}

	function disconnectCartMutationObserver() {
		if ( cartMutationObserver ) {
			cartMutationObserver.disconnect();
			cartMutationObserver = null;
		}
	}

	function startCartMutationObserver() {
		if ( cartMutationObserver || ! window.MutationObserver ) {
			return;
		}

		var root = document.querySelector( '.wc-block-cart, .woocommerce-cart-form, .cart-collaterals' ) || document.body;
		cartMutationObserver = new MutationObserver( function () {
			if ( isCartWidgetMounted() ) {
				disconnectCartMutationObserver();
				return;
			}
			if ( cartShouldShowWidget() ) {
				scheduleCartWidgetReload();
			}
		} );
		cartMutationObserver.observe( root, { childList: true, subtree: true } );
	}

	function startCartSyncWatcher() {
		if ( cartSyncWatcher ) {
			return;
		}

		cartSyncAttempts = 0;
		cartSyncWatcher = window.setInterval( function () {
			cartSyncAttempts++;

			if ( isCartWidgetMounted() ) {
				window.clearInterval( cartSyncWatcher );
				cartSyncWatcher = null;
				disconnectCartMutationObserver();
				return;
			}

			if ( cartShouldShowWidget() ) {
				syncCartWidget();
			}

			if ( cartSyncAttempts >= MAX_CART_SYNC_ATTEMPTS ) {
				window.clearInterval( cartSyncWatcher );
				cartSyncWatcher = null;
			}
		}, 400 );
	}

	function startCartPageSync() {
		if ( cartPageSyncStarted ) {
			scheduleCartWidgetReload();
			return;
		}

		cartPageSyncStarted = true;
		startCartMutationObserver();
		startCartSyncWatcher();
		scheduleCartWidgetReload();
	}

	function resolveAmountInCents( host ) {
		var amountCents = parseInt( host.getAttribute( 'data-amount-cents' ), 10 );
		var amount      = parseFloat( host.getAttribute( 'data-amount' ) );

		if ( ! isNaN( amountCents ) && amountCents > 0 ) {
			return normalizeAmountCents( amount, amountCents );
		}

		if ( isNaN( amount ) || amount <= 0 ) {
			return 0;
		}

		return majorAmountToCents( amount );
	}

	function mountWidget( host ) {
		if ( host.getAttribute( 'data-paymob-aw-mounted' ) === '1' ) {
			return;
		}
		if ( host.closest( '#paymob-aw-cart-widget-source' ) ) {
			return;
		}
		var mount = host.querySelector( '.paymob-aw-widget-mount' );
		if ( ! mount || ! mount.id ) {
			return;
		}

		var amountCents   = resolveAmountInCents( host );
		var currency      = host.getAttribute( 'data-currency' ) || config.currency || 'EGP';
		var integrationId = host.getAttribute( 'data-integration-id' );
		var theme         = host.getAttribute( 'data-theme' ) || config.theme || 'primary';
		var context       = host.getAttribute( 'data-context' ) || '';
		var productId     = host.getAttribute( 'data-product-id' ) || '';
		var majorAmount   = parseFloat( host.getAttribute( 'data-amount' ) );

		if ( ! passesContextThreshold( majorAmount, context ) ) {
			return;
		}

		if ( ! amountCents || amountCents <= 0 ) {
			return;
		}

		var options = {
			publicKey:         config.publicKey,
			elementId:         mount.id,
			amount:            amountCents,
			currency:          currency,
			theme:             theme,
			customerCanSelect: true,
			onSubmit: function ( plan ) {
				handleBuyNow( plan, integrationId, context, productId );
			}
		};

		var parsedIntegrationId = integrationId ? parseInt( integrationId, 10 ) : NaN;
		if ( ! isNaN( parsedIntegrationId ) && parsedIntegrationId > 0 ) {
			options.integrationId = parsedIntegrationId;
		}

		try {
			new window.PaymobWidget( options );
			host.setAttribute( 'data-paymob-aw-mounted', '1' );
		} catch ( err ) {
			if ( window.console && console.error ) {
				console.error( 'Paymob Affordability Widget init error:', err );
			}
		}
	}

	function rebuildMountElement( host ) {
		var mount = host.querySelector( '.paymob-aw-widget-mount' );
		if ( ! mount ) {
			return null;
		}

		var freshMount = document.createElement( 'div' );
		freshMount.id = mount.id;
		freshMount.className = 'paymob-aw-widget-mount';
		mount.parentNode.replaceChild( freshMount, mount );
		return freshMount;
	}

	function unmountWidget( host ) {
		rebuildMountElement( host );
		host.removeAttribute( 'data-paymob-aw-mounted' );
	}

	function formatMajorAmount( amount ) {
		var decimals = parseInt( config.amountDecimals, 10 );
		if ( isNaN( decimals ) ) {
			decimals = 2;
		}
		return Number( amount ).toFixed( decimals );
	}

	function reloadCartWidgetHost( host, payload ) {
		if ( ! payload ) {
			return;
		}

		if ( ! payload.visible || ! payload.amount_cents ) {
			unmountWidget( host );
			host.style.display = 'none';
			host.setAttribute( 'data-amount-cents', '0' );
			host.setAttribute( 'data-amount', formatMajorAmount( 0 ) );
			return;
		}

		var currentCents = parseInt( host.getAttribute( 'data-amount-cents' ), 10 );
		var isMounted    = host.getAttribute( 'data-paymob-aw-mounted' ) === '1';
		var nextCents    = normalizeAmountCents( payload.amount, payload.amount_cents );

		if ( isMounted && currentCents === nextCents && 'none' !== host.style.display ) {
			return;
		}

		host.style.display = '';
		host.setAttribute( 'data-amount-cents', String( nextCents ) );
		host.setAttribute( 'data-amount', formatMajorAmount( payload.amount ) );
		if ( payload.currency ) {
			host.setAttribute( 'data-currency', payload.currency );
		}

		unmountWidget( host );
		mountWidget( host );
	}

	function syncCartWidgetHostsFromPayload( payload ) {
		dedupeCartWidgetHosts();
		var primary = getPrimaryCartWidgetHost();
		if ( primary ) {
			reloadCartWidgetHost( primary, payload );
			return;
		}

		var source = document.getElementById( 'paymob-aw-cart-widget-source' );
		if ( source ) {
			var template = source.querySelector( '[data-paymob-aw-widget]' );
			if ( template ) {
				reloadCartWidgetHost( template, payload );
			}
		}
	}

	function resolveCartWidgetPayload( ajaxPayload ) {
		var storePayload = getBlockCartPayloadFromStore();
		var resolved     = null;

		if ( ajaxPayload && typeof ajaxPayload.amount !== 'undefined' ) {
			resolved = ajaxPayload;
		} else if ( storePayload ) {
			resolved = storePayload;
		} else {
			resolved = buildHiddenCartPayload();
		}

		return normalizeCartWidgetPayload( resolved, 'cart' );
	}

	function fetchCartWidgetPayload() {
		if ( ! window.fetch || typeof FormData === 'undefined' ) {
			return Promise.resolve( resolveCartWidgetPayload( null ) );
		}

		var body = new FormData();
		body.append( 'action', 'paymob_aw_cart_amount' );
		body.append( 'nonce', config.nonce );

		return window.fetch( config.ajaxUrl, {
			method:      'POST',
			body:        body,
			credentials: 'same-origin'
		} ).then( function ( response ) {
			return response.json().catch( function () { return null; } );
		} ).then( function ( json ) {
			var ajaxPayload = json && json.success && json.data ? json.data : null;
			return resolveCartWidgetPayload( ajaxPayload );
		} ).catch( function () {
			return resolveCartWidgetPayload( null );
		} );
	}

	function applyVisibleCartWidgetPayload( payload, done ) {
		var finish = typeof done === 'function' ? done : function () {};

		payload = normalizeCartWidgetPayload( payload, 'cart' );

		if ( ! payload.visible || ! payload.amount_cents ) {
			var host = getPrimaryCartWidgetHost();
			if ( host ) {
				reloadCartWidgetHost( host, payload );
			}
			lastAppliedCartCents = null;
			finish();
			return;
		}

		var nextCents = payload.amount_cents;
		if ( isCartWidgetMounted() && lastAppliedCartCents === nextCents ) {
			finish();
			return;
		}

		ensureBlockCartWidgetPlaced( function () {
			syncCartWidgetHostsFromPayload( payload );
			mountVisibleCartWidgetHosts();
			if ( isCartWidgetMounted() ) {
				lastAppliedCartCents = nextCents;
			}
			finish();
		} );
	}

	function syncCartWidget() {
		if ( cartSyncInFlight ) {
			cartSyncQueued = true;
			return;
		}

		cartSyncInFlight = true;

		fetchCartWidgetPayload().then( function ( payload ) {
			applyVisibleCartWidgetPayload( payload, function () {
				cartSyncInFlight = false;
				if ( cartSyncQueued ) {
					cartSyncQueued = false;
					scheduleCartWidgetReload();
				}
			} );
		} );
	}

	function scheduleCartWidgetReload() {
		window.clearTimeout( cartReloadTimer );
		cartReloadTimer = window.setTimeout( syncCartWidget, 350 );
	}

	function setupCartWidgetReload() {
		if ( cartReloadBound ) {
			return;
		}

		var onCartPage = config.isCart
			|| config.isBlockCart
			|| document.querySelector( '.paymob-aw-widget-host--cart[data-paymob-aw-widget]' )
			|| document.getElementById( 'paymob-aw-cart-widget-source' );

		if ( ! onCartPage ) {
			return;
		}

		cartReloadBound = true;

		if ( window.jQuery ) {
			window.jQuery( document.body ).on(
				'updated_cart_totals updated_wc_div wc_fragments_refreshed item_removed_from_classic_cart applied_coupon removed_coupon wc_cart_emptied',
				scheduleCartWidgetReload
			);
		}

		document.addEventListener( 'wc-blocks_added_to_cart', scheduleCartWidgetReload );
		document.addEventListener( 'wc-blocks_removed_from_cart', scheduleCartWidgetReload );

		document.addEventListener( 'change', function ( event ) {
			if ( ! event || ! event.target ) {
				return;
			}
			if ( event.target.matches && event.target.matches( '.woocommerce-cart-form input.qty' ) ) {
				scheduleCartWidgetReload();
			}
		}, true );

		if ( window.wp && window.wp.data && typeof window.wp.data.subscribe === 'function' ) {
			window.wp.data.subscribe( function () {
				var store = window.wp.data.select( 'wc/store/cart' );
				if ( ! store || typeof store.getCartTotals !== 'function' ) {
					return;
				}

				var totals = store.getCartTotals();
				if ( ! totals || typeof totals.total_price === 'undefined' ) {
					return;
				}

				var nextTotal = String( totals.total_price );
				var nextItems = getBlockCartItemCount();

				if ( ! blockCartTotalsReady ) {
					blockCartTotalCache = nextTotal;
					blockCartItemCache  = nextItems;
					blockCartTotalsReady = true;

					if ( config.isBlockCart && nextItems === 0 && ( '' === nextTotal || '0' === nextTotal ) ) {
						return;
					}

					scheduleCartWidgetReload();
					return;
				}

				if ( nextTotal === blockCartTotalCache && nextItems === blockCartItemCache ) {
					return;
				}

				blockCartTotalCache = nextTotal;
				blockCartItemCache  = nextItems;
				scheduleCartWidgetReload();
			} );
		}
	}

	function handleBuyNow( plan, integrationId, context, productId ) {
		if ( ! plan || ! plan.id ) {
			return;
		}

		// Default redirect built client-side, mirrors the server-side builder. Used only as a
		// fallback when fetch/FormData are unavailable or the AJAX call fails outright.
		var fallbackUrl = config.checkoutUrl || '';
		if ( 'product' === context && productId ) {
			fallbackUrl = appendQuery( fallbackUrl, 'add-to-cart', productId );
		}
		if ( fallbackUrl ) {
			fallbackUrl = appendQuery( fallbackUrl, 'paymob_aw_preselect', '1' );
		}

		var navigateTo = function ( url ) {
			var target = url || fallbackUrl;
			if ( target ) {
				window.location.assign( target );
			}
		};

		if ( ! window.fetch || typeof FormData === 'undefined' ) {
			navigateTo( '' );
			return;
		}

		var body = new FormData();
		body.append( 'action', 'paymob_aw_select_plan' );
		body.append( 'nonce', config.nonce );
		body.append( 'plan_id', plan.id );
		body.append( 'integration_id', integrationId || config.integrationId || '' );
		body.append( 'context', context || '' );
		if ( productId ) {
			body.append( 'product_id', productId );
		}
		if ( plan.tenure ) {
			body.append( 'plan_tenure', plan.tenure );
		}
		if ( typeof plan.amount !== 'undefined' && plan.amount !== null ) {
			body.append( 'plan_amount', plan.amount );
		}

		window.fetch( config.ajaxUrl, {
			method:      'POST',
			body:        body,
			credentials: 'same-origin'
		} ).then( function ( response ) {
			return response.json().catch( function () { return null; } );
		} ).then( function ( json ) {
			var serverUrl = json && json.success && json.data && json.data.redirect_url ? json.data.redirect_url : '';
			navigateTo( serverUrl );
		}, function () {
			navigateTo( '' );
		} );
	}

	function appendQuery( url, key, value ) {
		if ( ! url ) {
			return '';
		}
		var sep = url.indexOf( '?' ) === -1 ? '?' : '&';
		return url + sep + encodeURIComponent( key ) + '=' + encodeURIComponent( value );
	}

	if ( 'loading' !== document.readyState ) {
		waitForSdk( MAX_RETRIES );
	} else {
		document.addEventListener( 'DOMContentLoaded', function () {
			waitForSdk( MAX_RETRIES );
		} );
	}
} )();
