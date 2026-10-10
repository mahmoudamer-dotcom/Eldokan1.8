<?php
/**
 * Paymob Affordability Widget runtime.
 *
 * Renders the Bank Installments widget on product and cart pages when configured,
 * exposes AJAX endpoints to fetch installment plans and persist the shopper's
 * choice, and integrates the chosen plan with the Paymob Intention API + WC
 * gateway pre-selection on checkout.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Paymob_Affordability_Widget {

	const OPTION_KEY        = 'woocommerce_paymob_widget_settings';
	const SESSION_PLAN_KEY      = 'paymob_aw_plan_id';
	const SESSION_INTEGR_ID     = 'paymob_aw_integration_id';
	const SESSION_PRESELECT_KEY = 'paymob_aw_preselect';
	const SESSION_BUY_NOW_KEY   = 'paymob_aw_buy_now';
	const NONCE_ACTION          = 'paymob_aw_widget_nonce';

	/**
	 * Prevent rendering the cart widget twice on pages that fire multiple hooks.
	 *
	 * @var bool
	 */
	protected static $cart_widget_rendered = false;

	public static function init() {
		add_action( 'woocommerce_single_product_summary', array( __CLASS__, 'render_on_product' ), 25 );

		// Classic cart: show the widget just above the Proceed to Checkout button.
		add_action( 'woocommerce_proceed_to_checkout', array( __CLASS__, 'render_on_cart' ), 5 );

		// Cart Block is hydrated client-side; output a hidden source container and let JS place it.
		add_action( 'wp_footer', array( __CLASS__, 'render_cart_block_widget_source' ), 5 );

		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'maybe_enqueue_assets' ) );
		add_action( 'wp_footer', array( __CLASS__, 'render_panel_layout_footer_fix' ), 99999 );

		// Plan persistence and "buy now" forwarding. Plan discovery itself happens inside the
		// Paymob Widget SDK (which calls Paymob directly using the merchant public key), so the
		// legacy `paymob_aw_fetch_plans` endpoint is no longer registered here.
		add_action( 'wp_ajax_paymob_aw_select_plan', array( __CLASS__, 'ajax_select_plan' ) );
		add_action( 'wp_ajax_nopriv_paymob_aw_select_plan', array( __CLASS__, 'ajax_select_plan' ) );

		add_action( 'wp_ajax_paymob_aw_clear_plan', array( __CLASS__, 'ajax_clear_plan' ) );
		add_action( 'wp_ajax_nopriv_paymob_aw_clear_plan', array( __CLASS__, 'ajax_clear_plan' ) );

		add_action( 'wp_ajax_paymob_aw_cart_amount', array( __CLASS__, 'ajax_cart_amount' ) );
		add_action( 'wp_ajax_nopriv_paymob_aw_cart_amount', array( __CLASS__, 'ajax_cart_amount' ) );

		add_filter( 'paymob_intention_data', array( __CLASS__, 'inject_into_intention' ), 10, 2 );
		add_filter( 'woocommerce_available_payment_gateways', array( __CLASS__, 'preselect_bank_installment_gateway' ), 99 );
		add_filter( 'woocommerce_available_payment_gateways', array( __CLASS__, 'deprioritize_bank_installments_on_standard_checkout' ), 101 );
		add_filter( 'default_checkout_payment_method', array( __CLASS__, 'override_default_payment_method' ) );
		add_action( 'template_redirect', array( __CLASS__, 'maybe_restore_buy_now_from_redirect' ), 3 );
		add_action( 'template_redirect', array( __CLASS__, 'maybe_clear_widget_session_on_standard_checkout' ), 4 );
		add_action( 'template_redirect', array( __CLASS__, 'maybe_clear_widget_session_on_cart' ), 4 );
		add_action( 'woocommerce_checkout_init', array( __CLASS__, 'reset_standard_checkout_payment_method' ), 1 );
		add_action( 'template_redirect', array( __CLASS__, 'maybe_lock_payment_method_on_checkout' ), 5 );
		add_action( 'wp_head', array( __CLASS__, 'print_checkout_preselect_bootstrap' ), 1 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'maybe_localize_checkout_preselect_config' ), 19 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'maybe_enqueue_checkout_assets' ), 20 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'maybe_enqueue_standard_checkout_fix' ), 25 );
		add_action( 'woocommerce_checkout_update_order_review', array( __CLASS__, 'track_manual_payment_switch' ), 10, 1 );
		add_action( 'woocommerce_store_api_checkout_update_order_from_request', array( __CLASS__, 'sync_widget_session_with_order_payment' ), 10, 2 );
	}

	public static function get_settings() {
		$settings = get_option( self::OPTION_KEY, array() );
		return is_array( $settings ) ? $settings : array();
	}

	public static function is_enabled() {
		$settings = self::get_settings();
		if ( empty( $settings['enabled_widget'] ) || 'yes' !== $settings['enabled_widget'] ) {
			return false;
		}
		if ( '' === self::get_integration_id() ) {
			return false;
		}
		// The SDK refuses to render without a merchant public key, so don't even emit the host
		// container in that case — it would otherwise sit empty on the storefront.
		return '' !== self::get_public_key();
	}

	public static function get_public_key() {
		$main_settings = get_option( 'woocommerce_paymob-main_settings', array() );
		return isset( $main_settings['pub_key'] ) ? (string) $main_settings['pub_key'] : '';
	}

	public static function get_integration_id() {
		$settings       = self::get_settings();
		$stored_value   = isset( $settings['integration_id'] ) ? trim( (string) $settings['integration_id'] ) : '';

		if ( class_exists( 'Paymob_Widget_Settings' ) ) {
			return Paymob_Widget_Settings::resolve_selected_integration_id( $stored_value );
		}

		return $stored_value;
	}

	public static function get_theme() {
		$settings = self::get_settings();
		$theme    = isset( $settings['widget_theme'] ) ? (string) $settings['widget_theme'] : 'primary';
		return in_array( $theme, array( 'primary', 'light', 'dark' ), true ) ? $theme : 'primary';
	}

	public static function get_currency_meta() {
		$currency = function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : 'EGP';
		$country  = 'egy';
		$round    = 2;
		$cents    = 100;

		if ( class_exists( 'Paymob' ) ) {
			$main_settings = get_option( 'woocommerce_paymob-main_settings', array() );
			$sec_key       = isset( $main_settings['sec_key'] ) ? (string) $main_settings['sec_key'] : '';
			if ( '' !== $sec_key ) {
				$country = Paymob::getCountryCode( $sec_key );
			}
		}

		if ( 'omn' === $country ) {
			$round = 3;
			$cents = 1000;
		}

		return array(
			'currency' => $currency,
			'cents'    => $cents,
			'round'    => $round,
		);
	}

	/**
	 * Convert a WooCommerce major-unit amount to Paymob cents (or baisa for OMR).
	 *
	 * @param float $amount Major currency units (e.g. 10.00 EGP).
	 * @return int
	 */
	public static function amount_to_cents( $amount ) {
		$meta   = self::get_currency_meta();
		$amount = (float) $amount;

		if ( $amount <= 0 ) {
			return 0;
		}

		return (int) round( $amount, (int) $meta['round'] ) * (int) $meta['cents'];
	}

	protected static function passes_product_threshold( $amount ) {
		$settings = self::get_settings();
		if ( empty( $settings['min_product_enabled'] ) || 'yes' !== $settings['min_product_enabled'] ) {
			return true;
		}
		$threshold = isset( $settings['min_product_amount'] ) ? (float) $settings['min_product_amount'] : 0.0;
		return $threshold <= 0 || (float) $amount >= $threshold;
	}

	protected static function passes_cart_threshold( $amount ) {
		$settings = self::get_settings();
		if ( empty( $settings['min_cart_enabled'] ) || 'yes' !== $settings['min_cart_enabled'] ) {
			return true;
		}
		$threshold = isset( $settings['min_cart_amount'] ) ? (float) $settings['min_cart_amount'] : 0.0;
		return $threshold <= 0 || (float) $amount >= $threshold;
	}

	public static function is_cart_minimum_enabled() {
		$settings = self::get_settings();
		return ! empty( $settings['min_cart_enabled'] ) && 'yes' === $settings['min_cart_enabled'];
	}

	public static function get_cart_minimum_amount() {
		$settings = self::get_settings();
		return isset( $settings['min_cart_amount'] ) ? (float) $settings['min_cart_amount'] : 0.0;
	}

	public static function is_product_minimum_enabled() {
		$settings = self::get_settings();
		return ! empty( $settings['min_product_enabled'] ) && 'yes' === $settings['min_product_enabled'];
	}

	public static function get_product_minimum_amount() {
		$settings = self::get_settings();
		return isset( $settings['min_product_amount'] ) ? (float) $settings['min_product_amount'] : 0.0;
	}

	/**
	 * Whether the current storefront request can show the affordability widget.
	 *
	 * @return bool
	 */
	protected static function is_widget_context_page() {
		if ( function_exists( 'is_product' ) && is_product() ) {
			return true;
		}
		if ( function_exists( 'is_cart' ) && is_cart() ) {
			return true;
		}
		return false;
	}

	/**
	 * Panel header runtime config for localized scripts.
	 *
	 * @return array<string, mixed>
	 */
	protected static function get_panel_layout_script_config() {
		return array(
			'adminBarHeight' => ( function_exists( 'is_admin_bar_showing' ) && is_admin_bar_showing() )
				? ( wp_is_mobile() ? 46 : 32 )
				: 0,
			'logoUrl'        => plugins_url( PAYMOB_PLUGIN_NAME . '/assets/img/paymob.png' ),
			'panelTitle'     => __( 'Choose your plan to proceed', 'paymob-for-woocommerce' ),
		);
	}

	public static function render_on_product() {
		if ( ! self::is_enabled() || ! function_exists( 'is_product' ) || ! is_product() ) {
			return;
		}
		global $product;
		if ( ! $product instanceof WC_Product ) {
			return;
		}
		$price = (float) wc_get_price_to_display( $product );
		if ( $price <= 0 ) {
			return;
		}
		if ( ! self::passes_product_threshold( $price ) ) {
			return;
		}
		self::render_container( $price, 'product', $product->get_id() );
	}

	public static function render_on_cart() {
		if ( self::$cart_widget_rendered || self::is_block_cart_page() ) {
			return;
		}
		if ( ! self::is_enabled() ) {
			return;
		}

		$total  = self::get_cart_widget_eligible_amount();
		$hidden = $total <= 0;

		self::$cart_widget_rendered = true;
		self::render_container( $hidden ? 0.0 : $total, 'cart', 0, $hidden );
	}

	/**
	 * Hidden mount source for the WooCommerce Cart Block (client-rendered).
	 */
	public static function render_cart_block_widget_source() {
		if ( ! self::is_block_cart_page() || ! self::is_enabled() ) {
			return;
		}

		$total  = self::get_cart_widget_eligible_amount();
		$hidden = $total <= 0;
		?>
		<div id="paymob-aw-cart-widget-source" class="paymob-aw-cart-widget-source" hidden aria-hidden="true">
			<?php self::render_container( $hidden ? 0.0 : $total, 'cart', 0, $hidden ); ?>
		</div>
		<?php
	}

	/**
	 * Whether the current cart page uses the WooCommerce Cart Block.
	 *
	 * @return bool
	 */
	protected static function is_block_cart_page() {
		if ( ! function_exists( 'is_cart' ) || ! is_cart() ) {
			return false;
		}

		if ( self::is_classic_cart_page() ) {
			return false;
		}

		if ( class_exists( '\Automattic\WooCommerce\Blocks\Utils\CartCheckoutUtils' ) ) {
			if ( \Automattic\WooCommerce\Blocks\Utils\CartCheckoutUtils::is_cart_block_default() ) {
				return true;
			}
		}

		if ( function_exists( 'has_block' ) && has_block( 'woocommerce/cart' ) ) {
			return true;
		}

		$cart_page_id = function_exists( 'wc_get_page_id' ) ? (int) wc_get_page_id( 'cart' ) : 0;
		if ( $cart_page_id > 0 ) {
			$content = (string) get_post_field( 'post_content', $cart_page_id );
			if ( '' !== $content && false !== strpos( $content, 'woocommerce/cart' ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Whether the cart page still uses the legacy WooCommerce cart shortcode.
	 *
	 * @return bool
	 */
	protected static function is_classic_cart_page() {
		$cart_page_id = function_exists( 'wc_get_page_id' ) ? (int) wc_get_page_id( 'cart' ) : 0;
		if ( $cart_page_id <= 0 ) {
			return false;
		}

		$content = (string) get_post_field( 'post_content', $cart_page_id );
		if ( '' === $content ) {
			return false;
		}

		if ( false !== strpos( $content, '[woocommerce_cart]' ) ) {
			return true;
		}

		if (
			class_exists( '\Automattic\WooCommerce\Blocks\Utils\CartCheckoutUtils' )
			&& \Automattic\WooCommerce\Blocks\Utils\CartCheckoutUtils::has_block_variation(
				'woocommerce/classic-shortcode',
				'shortcode',
				'cart',
				$content
			)
		) {
			return true;
		}

		return false;
	}

	/**
	 * Cart amount eligible for the widget, or 0 when it should not render.
	 *
	 * @param bool $require_cart_route When false, skip the `is_cart()` check (used by AJAX refresh).
	 * @return float
	 */
	protected static function get_cart_widget_eligible_amount( $require_cart_route = true ) {
		if ( ! self::is_enabled() ) {
			return 0.0;
		}
		if ( $require_cart_route && ( ! function_exists( 'is_cart' ) || ! is_cart() ) ) {
			return 0.0;
		}
		if ( ! function_exists( 'WC' ) || ! WC()->cart || WC()->cart->is_empty() ) {
			return 0.0;
		}

		$total = self::get_cart_amount();
		if ( $total <= 0 || ! self::passes_cart_threshold( $total ) ) {
			return 0.0;
		}

		return $total;
	}

	/**
	 * Cart total used by the Affordability Widget (matches checkout total basis).
	 *
	 * @return float
	 */
	protected static function get_cart_amount() {
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return 0.0;
		}

		$total = (float) WC()->cart->get_total( 'edit' );
		if ( $total > 0 ) {
			return $total;
		}

		// Fallback when the cart total is not yet calculated.
		return (float) WC()->cart->get_cart_contents_total();
	}

	/**
	 * Outputs a mounting point for the Paymob Widget SDK.
	 *
	 * The Paymob Widget SDK is loaded as an ES module from the CDN and renders its UI inside
	 * a Shadow DOM rooted at the element whose ID is passed in `elementId`. We therefore only
	 * emit a host `<div>` carrying configuration via `data-*` attributes plus an empty mount
	 * `<div id="paymob-widget-{context}">` that the SDK takes over.
	 */
	protected static function render_container( $amount, $context, $product_id = 0, $hidden = false ) {
		$meta         = self::get_currency_meta();
		$amount_cents = $amount > 0 ? self::amount_to_cents( $amount ) : 0;
		$theme        = self::get_theme();
		$instance_id  = 'paymob-widget-' . sanitize_html_class( $context );
		$host_class   = 'paymob-aw-widget-host';
		if ( 'cart' === $context ) {
			$host_class .= ' paymob-aw-widget-host--cart';
		}
		?>
		<div class="<?php echo esc_attr( $host_class ); ?>"
			data-paymob-aw-widget
			data-context="<?php echo esc_attr( $context ); ?>"
			data-product-id="<?php echo esc_attr( (string) $product_id ); ?>"
			data-amount="<?php echo esc_attr( number_format( max( 0, (float) $amount ), (int) $meta['round'], '.', '' ) ); ?>"
			data-amount-cents="<?php echo esc_attr( (string) $amount_cents ); ?>"
			data-currency="<?php echo esc_attr( $meta['currency'] ); ?>"
			data-integration-id="<?php echo esc_attr( self::get_integration_id() ); ?>"
			data-theme="<?php echo esc_attr( $theme ); ?>"<?php if ( $hidden ) : ?> style="display:none"<?php endif; ?>>
			<div id="<?php echo esc_attr( $instance_id ); ?>" class="paymob-aw-widget-mount"></div>
		</div>
		<?php
	}

	public static function maybe_enqueue_assets() {
		if ( ! self::is_enabled() ) {
			return;
		}
		if ( ! self::is_widget_context_page() ) {
			return;
		}

		$public_key = self::get_public_key();

		// `is_enabled()` already ensures the merchant has a public key, but the storefront could
		// still call this hook on a non-affordability page; bail defensively.
		if ( '' === $public_key ) {
			return;
		}

		$css_path         = PAYMOB_PLUGIN_PATH . 'assets/css/affordability-widget-front.css';
		$js_path          = PAYMOB_PLUGIN_PATH . 'assets/js/affordability-widget.js';
		$panel_js_path    = PAYMOB_PLUGIN_PATH . 'assets/js/affordability-widget-panel-layout.js';
		$panel_header_path = PAYMOB_PLUGIN_PATH . 'assets/js/affordability-widget-panel-header.js';

		wp_enqueue_style(
			'paymob-affordability-widget-front',
			plugins_url( PAYMOB_PLUGIN_NAME ) . '/assets/css/affordability-widget-front.css',
			array(),
			file_exists( $css_path ) ? PAYMOB_VERSION . '-' . filemtime( $css_path ) : PAYMOB_VERSION
		);

		wp_add_inline_style(
			'paymob-affordability-widget-front',
			self::get_panel_layout_critical_css()
		);

		wp_enqueue_script(
			'paymob-affordability-widget-panel-layout',
			plugins_url( PAYMOB_PLUGIN_NAME ) . '/assets/js/affordability-widget-panel-layout.js',
			array(),
			file_exists( $panel_js_path ) ? PAYMOB_VERSION . '-' . filemtime( $panel_js_path ) : PAYMOB_VERSION,
			true
		);

		wp_enqueue_script(
			'paymob-affordability-widget-panel-header',
			plugins_url( PAYMOB_PLUGIN_NAME ) . '/assets/js/affordability-widget-panel-header.js',
			array(),
			file_exists( $panel_header_path ) ? PAYMOB_VERSION . '-' . filemtime( $panel_header_path ) : PAYMOB_VERSION,
			true
		);

		wp_localize_script(
			'paymob-affordability-widget-panel-header',
			'paymobAwPanelLayout',
			self::get_panel_layout_script_config()
		);

		wp_localize_script(
			'paymob-affordability-widget-panel-layout',
			'paymobAwPanelLayout',
			self::get_panel_layout_script_config()
		);

		// Pull the official Paymob Widget SDK directly from jsDelivr. It exposes a global
		// `PaymobWidget` class once loaded; per its README it must be loaded as a module.
		$sdk_url = apply_filters(
			'paymob_aw_sdk_url',
			'https://cdn.jsdelivr.net/npm/paymob-widget@latest/main.js'
		);

		wp_enqueue_script(
			'paymob-widget-sdk',
			$sdk_url,
			array(),
			null,
			true
		);

		// Promote that script tag to an ES module exactly once.
		add_filter( 'script_loader_tag', array( __CLASS__, 'mark_sdk_script_as_module' ), 10, 3 );

		wp_enqueue_script(
			'paymob-affordability-widget',
			plugins_url( PAYMOB_PLUGIN_NAME ) . '/assets/js/affordability-widget.js',
			array( 'paymob-widget-sdk' ),
			file_exists( $js_path ) ? PAYMOB_VERSION . '-' . filemtime( $js_path ) : PAYMOB_VERSION,
			true
		);

		$currency_meta = self::get_currency_meta();

		wp_localize_script(
			'paymob-affordability-widget',
			'paymobAffordabilityWidget',
			array(
				'ajaxUrl'            => admin_url( 'admin-ajax.php' ),
				'nonce'              => wp_create_nonce( self::NONCE_ACTION ),
				'publicKey'          => $public_key,
				'integrationId'        => self::get_integration_id(),
				'currency'             => $currency_meta['currency'],
				'centsMultiplier'      => $currency_meta['cents'],
				'amountDecimals'       => $currency_meta['round'],
				'theme'                => self::get_theme(),
				'checkoutUrl'          => function_exists( 'wc_get_checkout_url' ) ? wc_get_checkout_url() : '',
				'cartUrl'              => function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : '',
				'isBlockCart'          => self::is_block_cart_page(),
				'isCart'               => function_exists( 'is_cart' ) && is_cart(),
				'minCartEnabled'       => self::is_cart_minimum_enabled(),
				'minCartAmount'        => self::get_cart_minimum_amount(),
				'minProductEnabled'    => self::is_product_minimum_enabled(),
				'minProductAmount'     => self::get_product_minimum_amount(),
			)
		);
	}

	/**
	 * Re-assert the Buy Now flow when the shopper lands on checkout via the widget redirect.
	 *
	 * Must run before reset_standard_checkout_payment_method() so session keys are not cleared
	 * while WooCommerce Blocks is still booting with paymob-pixel as the interim default.
	 */
	public static function maybe_restore_buy_now_from_redirect() {
		if ( empty( $_GET['paymob_aw_preselect'] ) ) {
			return;
		}
		if ( ! function_exists( 'is_checkout' ) || ! is_checkout() || is_order_received_page() ) {
			return;
		}
		if ( ! function_exists( 'WC' ) || ! WC()->session ) {
			return;
		}

		$plan_id        = (string) WC()->session->get( self::SESSION_PLAN_KEY, '' );
		$integration_id = (string) WC()->session->get( self::SESSION_INTEGR_ID, '' );
		if ( '' === $plan_id || '' === $integration_id ) {
			return;
		}

		WC()->session->set( self::SESSION_PRESELECT_KEY, '1' );
		WC()->session->set( self::SESSION_BUY_NOW_KEY, '1' );
	}

	/**
	 * Print widget preselect config in <head> before Pixel/Blocks scripts boot.
	 *
	 * Merchant themes and caches may strip checkout query args; session-backed config must
	 * still be available when paymob-pixel_block.js first evaluates Place Order visibility.
	 *
	 * @return void
	 */
	public static function print_checkout_preselect_bootstrap() {
		if ( ! function_exists( 'is_checkout' ) || ! is_checkout() || is_order_received_page() ) {
			return;
		}

		$config = self::get_checkout_preselect_config();
		if ( empty( $config ) ) {
			return;
		}

		echo '<script>window.paymobAffordabilityCheckout = window.paymobAffordabilityCheckout || ' . wp_json_encode( $config ) . ';</script>' . "\n";
	}

	/**
	 * Localize checkout preselect config for Blocks before the pixel script boots.
	 *
	 * @return void
	 */
	public static function maybe_localize_checkout_preselect_config() {
		if ( ! function_exists( 'is_checkout' ) || ! is_checkout() || is_order_received_page() ) {
			return;
		}
		if ( ! function_exists( 'WC' ) || ! WC()->session ) {
			return;
		}

		$config = self::get_checkout_preselect_config();
		if ( empty( $config ) ) {
			return;
		}

		$handles = array( 'paymob-pixel-checkout', 'paymob-pixel-blocks-integration' );
		foreach ( $handles as $handle ) {
			if ( ! wp_script_is( $handle, 'registered' ) ) {
				continue;
			}

			wp_add_inline_script(
				$handle,
				'window.paymobAffordabilityCheckout = window.paymobAffordabilityCheckout || ' . wp_json_encode( $config ) . ';',
				'before'
			);
		}
	}

	/**
	 * Build the checkout preselect payload when a widget plan is active.
	 *
	 * @return array<string, mixed>
	 */
	protected static function get_checkout_preselect_config() {
		if ( ! function_exists( 'WC' ) || ! WC()->session ) {
			return array();
		}

		$plan_id        = (string) WC()->session->get( self::SESSION_PLAN_KEY, '' );
		$integration_id = (string) WC()->session->get( self::SESSION_INTEGR_ID, '' );
		if ( '' === $plan_id || '' === $integration_id ) {
			return array();
		}

		$session_preselect = '1' === (string) WC()->session->get( self::SESSION_PRESELECT_KEY, '' );
		if ( ! self::should_preselect_payment_method() && empty( $_GET['paymob_aw_preselect'] ) && ! $session_preselect ) {
			return array();
		}

		$bank_gateway_id = self::find_matching_bank_gateway_id( $integration_id );
		if ( '' === $bank_gateway_id ) {
			return array();
		}

		return array(
			'gatewayId' => $bank_gateway_id,
			'preselect' => true,
			'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
			'nonce'     => wp_create_nonce( self::NONCE_ACTION ),
		);
	}

	/**
	 * Enqueue a small checkout helper that programmatically selects the Bank Installments
	 * gateway when the shopper arrives from the widget Buy Now flow.
	 */
	public static function maybe_enqueue_checkout_assets() {
		if ( ! function_exists( 'is_checkout' ) || ! is_checkout() || is_order_received_page() ) {
			return;
		}

		$config = self::get_checkout_preselect_config();
		if ( empty( $config ) ) {
			return;
		}

		if ( ! wp_style_is( 'paymob-pixel-checkout-front', 'enqueued' ) ) {
			wp_enqueue_style(
				'paymob-pixel-checkout-front',
				plugins_url( PAYMOB_PLUGIN_NAME ) . '/assets/css/pixel-checkout-front.css',
				array(),
				defined( 'PAYMOB_VERSION' ) ? PAYMOB_VERSION : '1.0.0'
			);
		}

		$js_path = PAYMOB_PLUGIN_PATH . 'assets/js/affordability-widget-checkout.js';
		$deps    = array( 'jquery' );
		if ( wp_script_is( 'paymob-pixel-checkout', 'registered' ) ) {
			$deps[] = 'paymob-pixel-checkout';
		}
		wp_enqueue_script(
			'paymob-affordability-widget-checkout',
			plugins_url( PAYMOB_PLUGIN_NAME ) . '/assets/js/affordability-widget-checkout.js',
			$deps,
			file_exists( $js_path ) ? PAYMOB_VERSION . '-' . filemtime( $js_path ) : PAYMOB_VERSION,
			true
		);

		wp_localize_script(
			'paymob-affordability-widget-checkout',
			'paymobAffordabilityCheckout',
			$config
		);
	}

	/**
	 * Classic checkout fallback: switch away from auto-selected Bank Installments when the
	 * shopper used Proceed to Checkout instead of widget Buy Now.
	 */
	public static function maybe_enqueue_standard_checkout_fix() {
		if ( ! function_exists( 'is_checkout' ) || ! is_checkout() || is_order_received_page() ) {
			return;
		}
		if ( self::should_preselect_payment_method() ) {
			return;
		}

		$js_path = PAYMOB_PLUGIN_PATH . 'assets/js/affordability-widget-standard-checkout.js';
		$deps    = array( 'jquery' );
		if ( wp_script_is( 'paymob-pixel-checkout', 'registered' ) ) {
			$deps[] = 'paymob-pixel-checkout';
		}
		wp_enqueue_script(
			'paymob-affordability-widget-standard-checkout',
			plugins_url( PAYMOB_PLUGIN_NAME ) . '/assets/js/affordability-widget-standard-checkout.js',
			$deps,
			file_exists( $js_path ) ? PAYMOB_VERSION . '-' . filemtime( $js_path ) : PAYMOB_VERSION,
			true
		);
	}

	/**
	 * Stop forcing the bank gateway once the shopper manually picks a different method.
	 *
	 * @param string $post_data Serialized checkout form data from `update_order_review`.
	 */
	public static function track_manual_payment_switch( $post_data ) {
		if ( ! function_exists( 'WC' ) || ! WC()->session ) {
			return;
		}

		$integration_id = (string) WC()->session->get( self::SESSION_INTEGR_ID, '' );
		if ( '' === $integration_id ) {
			return;
		}

		$bank_gateway_id = self::find_matching_bank_gateway_id( $integration_id );
		if ( '' === $bank_gateway_id ) {
			return;
		}

		$parsed = array();
		parse_str( (string) $post_data, $parsed );
		if ( empty( $parsed['payment_method'] ) ) {
			return;
		}

		if ( (string) $parsed['payment_method'] !== $bank_gateway_id ) {
			self::clear_widget_session();
		}
	}

	/**
	 * Blocks checkout: clear stale widget plan data when the placed order uses a different gateway.
	 *
	 * @param \WC_Order          $order   Checkout order.
	 * @param \WP_REST_Request   $request Store API request.
	 */
	public static function sync_widget_session_with_order_payment( $order, $request ) {
		unset( $request );

		if ( ! function_exists( 'WC' ) || ! WC()->session || ! is_a( $order, 'WC_Order' ) ) {
			return;
		}

		$integration_id = (string) WC()->session->get( self::SESSION_INTEGR_ID, '' );
		if ( '' === $integration_id ) {
			return;
		}

		$bank_gateway_id  = self::find_matching_bank_gateway_id( $integration_id );
		$order_payment_id = (string) $order->get_payment_method();

		if ( '' !== $bank_gateway_id && $order_payment_id !== $bank_gateway_id ) {
			self::clear_widget_session();
		}
	}

	/**
	 * Whether the checkout should auto-select the bank installment gateway.
	 *
	 * Only true after the shopper clicked Buy Now in the widget (not Proceed to Checkout).
	 *
	 * @return bool
	 */
	protected static function should_preselect_payment_method() {
		if ( ! function_exists( 'WC' ) || ! WC()->session ) {
			return false;
		}

		if ( '' === (string) WC()->session->get( self::SESSION_PLAN_KEY, '' ) ) {
			return false;
		}

		return '1' === (string) WC()->session->get( self::SESSION_BUY_NOW_KEY, '' );
	}

	/**
	 * Remove widget session keys when the shopper reaches checkout without the Buy Now flow.
	 */
	public static function maybe_clear_widget_session_on_standard_checkout() {
		self::reset_standard_checkout_payment_method();
	}

	/**
	 * Clear stale widget session data on the cart page when the shopper has not used Buy Now.
	 */
	public static function maybe_clear_widget_session_on_cart() {
		if ( ! function_exists( 'is_cart' ) || ! is_cart() ) {
			return;
		}

		self::clear_widget_session();
	}

	/**
	 * Ensure standard checkout does not keep or default to Bank Installments without a widget plan.
	 */
	public static function reset_standard_checkout_payment_method() {
		if ( ! function_exists( 'is_checkout' ) || ! is_checkout() || is_order_received_page() ) {
			return;
		}
		if ( self::should_preselect_payment_method() ) {
			return;
		}
		if ( ! function_exists( 'WC' ) || ! WC()->session ) {
			return;
		}

		self::clear_widget_session();

		$chosen = (string) WC()->session->get( 'chosen_payment_method', '' );
		if ( '' !== $chosen && self::is_bank_installment_gateway( $chosen ) ) {
			WC()->session->__unset( 'chosen_payment_method' );
		}

		$default_gateway = self::get_standard_checkout_default_gateway();
		if ( '' !== $default_gateway ) {
			WC()->session->set( 'chosen_payment_method', $default_gateway );
		}
	}

	/**
	 * @param string $gateway_id Gateway slug.
	 * @return bool
	 */
	protected static function is_bank_installment_gateway( $gateway_id ) {
		return is_string( $gateway_id ) && false !== stripos( $gateway_id, 'bank-installments' );
	}

	/**
	 * Preferred checkout gateway when the shopper did not choose a widget installment plan.
	 *
	 * Uses registered gateways only — never calls get_available_payment_gateways() here,
	 * because that filter runs during checkout bootstrap and would recurse.
	 *
	 * @return string
	 */
	protected static function get_standard_checkout_default_gateway() {
		if ( ! function_exists( 'WC' ) || ! WC()->payment_gateways() ) {
			return '';
		}

		$all_gateways = WC()->payment_gateways()->payment_gateways();
		if ( empty( $all_gateways ) || ! is_array( $all_gateways ) ) {
			return '';
		}

		if ( isset( $all_gateways['paymob-pixel'] ) && 'yes' === $all_gateways['paymob-pixel']->enabled ) {
			return 'paymob-pixel';
		}

		foreach ( $all_gateways as $gateway_id => $gateway ) {
			if ( ! is_object( $gateway ) || 'yes' !== $gateway->enabled ) {
				continue;
			}
			if ( ! self::is_bank_installment_gateway( $gateway_id ) ) {
				return (string) $gateway_id;
			}
		}

		return '';
	}

	/**
	 * @return void
	 */
	protected static function clear_widget_session() {
		if ( ! function_exists( 'WC' ) || ! WC()->session ) {
			return;
		}

		WC()->session->__unset( self::SESSION_PLAN_KEY );
		WC()->session->__unset( self::SESSION_INTEGR_ID );
		WC()->session->__unset( self::SESSION_PRESELECT_KEY );
		WC()->session->__unset( self::SESSION_BUY_NOW_KEY );
		WC()->session->__unset( 'paymob_aw_plan_tenure' );
		WC()->session->__unset( 'paymob_aw_plan_amount' );
	}

	/**
	 * Move bank installment gateways to the end of the list on standard checkout so WooCommerce
	 * does not auto-select the first radio button as Bank Installments.
	 *
	 * @param array $available_gateways Available gateways.
	 * @return array
	 */
	public static function deprioritize_bank_installments_on_standard_checkout( $available_gateways ) {
		if ( ! is_array( $available_gateways ) || empty( $available_gateways ) ) {
			return $available_gateways;
		}
		if ( ! function_exists( 'is_checkout' ) || ! is_checkout() || is_order_received_page() ) {
			return $available_gateways;
		}
		if ( self::should_preselect_payment_method() ) {
			return $available_gateways;
		}

		$preferred = array();
		$banks     = array();

		foreach ( $available_gateways as $gateway_id => $gateway ) {
			if ( self::is_bank_installment_gateway( $gateway_id ) ) {
				$banks[ $gateway_id ] = $gateway;
			} else {
				$preferred[ $gateway_id ] = $gateway;
			}
		}

		return array_merge( $preferred, $banks );
	}

	public static function get_selected_plan_id() {
		if ( ! function_exists( 'WC' ) || ! WC()->session ) {
			return '';
		}
		return (string) WC()->session->get( self::SESSION_PLAN_KEY, '' );
	}

	public static function ajax_fetch_plans() {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );

		if ( ! self::is_enabled() ) {
			wp_send_json_error( array( 'reason' => 'widget_disabled' ), 400 );
		}

		$amount_cents   = isset( $_POST['amount_cents'] ) ? (int) $_POST['amount_cents'] : 0;
		$integration_id = isset( $_POST['integration_id'] ) ? sanitize_text_field( wp_unslash( $_POST['integration_id'] ) ) : '';
		$currency       = isset( $_POST['currency'] ) ? sanitize_text_field( wp_unslash( $_POST['currency'] ) ) : self::get_currency_meta()['currency'];

		if ( $amount_cents <= 0 || '' === $integration_id ) {
			wp_send_json_error( array( 'reason' => 'invalid_params' ), 400 );
		}

		if ( (string) $integration_id !== self::get_integration_id() ) {
			wp_send_json_error( array( 'reason' => 'integration_mismatch' ), 400 );
		}

		$plans = self::fetch_installment_plans( $integration_id, $amount_cents, $currency );

		if ( empty( $plans ) ) {
			wp_send_json_success( array( 'plans' => array() ) );
		}

		wp_send_json_success( array( 'plans' => $plans ) );
	}

	public static function ajax_select_plan() {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );

		$plan_id        = isset( $_POST['plan_id'] ) ? sanitize_text_field( wp_unslash( $_POST['plan_id'] ) ) : '';
		$integration_id = isset( $_POST['integration_id'] ) ? sanitize_text_field( wp_unslash( $_POST['integration_id'] ) ) : '';
		$plan_tenure    = isset( $_POST['plan_tenure'] ) ? (int) $_POST['plan_tenure'] : 0;
		$plan_amount    = isset( $_POST['plan_amount'] ) ? sanitize_text_field( wp_unslash( $_POST['plan_amount'] ) ) : '';
		$product_id     = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
		$context        = isset( $_POST['context'] ) ? sanitize_key( wp_unslash( $_POST['context'] ) ) : '';

		if ( '' === $plan_id ) {
			wp_send_json_error( array( 'reason' => 'missing_plan_id' ), 400 );
		}

		// Default the persisted integration ID to whatever the merchant configured if the SDK
		// onSubmit callback doesn't echo one back (it does not in selectable mode).
		if ( '' === $integration_id ) {
			$integration_id = self::get_integration_id();
		}

		if ( ! function_exists( 'WC' ) || ! WC()->session ) {
			wp_send_json_error( array( 'reason' => 'no_session' ), 400 );
		}

		WC()->session->set( self::SESSION_PLAN_KEY, $plan_id );
		WC()->session->set( self::SESSION_INTEGR_ID, $integration_id );
		WC()->session->set( 'paymob_aw_plan_tenure', $plan_tenure );
		WC()->session->set( 'paymob_aw_plan_amount', $plan_amount );
		WC()->session->set( self::SESSION_PRESELECT_KEY, '1' );
		WC()->session->set( self::SESSION_BUY_NOW_KEY, '1' );

		// Add the product to the cart server-side so the checkout redirect does not need
		// `?add-to-cart=` (which can reset the session payment method before our hooks run).
		if ( 'product' === $context && $product_id > 0 && function_exists( 'WC' ) && WC()->cart ) {
			$cart_item_key = WC()->cart->find_product_in_cart(
				WC()->cart->generate_cart_id( $product_id )
			);
			if ( ! $cart_item_key ) {
				WC()->cart->add_to_cart( $product_id, 1 );
			}
		}

		$bank_gateway_id = self::find_matching_bank_gateway_id( $integration_id );
		if ( '' !== $bank_gateway_id ) {
			WC()->session->set( 'chosen_payment_method', $bank_gateway_id );
		}

		if ( method_exists( WC()->session, 'save_data' ) ) {
			WC()->session->save_data();
		}

		$redirect_url = function_exists( 'wc_get_checkout_url' ) ? wc_get_checkout_url() : '';
		if ( $redirect_url ) {
			$redirect_url = add_query_arg( 'paymob_aw_preselect', '1', $redirect_url );
		}

		wp_send_json_success(
			array(
				'stored'       => true,
				'gateway'      => $bank_gateway_id,
				'redirect_url' => $redirect_url,
			)
		);
	}

	public static function ajax_clear_plan() {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );

		self::clear_widget_session();

		wp_send_json_success( array( 'cleared' => true ) );
	}

	/**
	 * Return the live cart total so the Affordability Widget can reload after cart updates.
	 */
	public static function ajax_cart_amount() {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );

		if ( ! self::is_enabled() ) {
			wp_send_json_error( array( 'reason' => 'widget_disabled' ), 400 );
		}

		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			wp_send_json_error( array( 'reason' => 'no_cart' ), 400 );
		}

		if ( WC()->cart->is_empty() && WC()->session ) {
			$session_cart = WC()->session->get( 'cart', null );
			if ( is_array( $session_cart ) && ! empty( $session_cart ) ) {
				WC()->cart->get_cart_from_session();
			}
		}

		if ( ! WC()->cart->is_empty() ) {
			WC()->cart->calculate_totals();
		}

		$meta   = self::get_currency_meta();
		$amount = self::get_cart_amount();
		$total  = self::get_cart_widget_eligible_amount( false );

		if ( $total <= 0 ) {
			wp_send_json_success(
				array(
					'visible'      => false,
					'amount'       => max( 0, (float) $amount ),
					'amount_cents' => 0,
					'currency'     => $meta['currency'],
				)
			);
		}

		wp_send_json_success(
			array(
				'visible'      => true,
				'amount'       => (float) $total,
				'amount_cents' => self::amount_to_cents( $total ),
				'currency'     => $meta['currency'],
			)
		);
	}

	protected static function fetch_installment_plans( $integration_id, $amount_cents, $currency ) {
		$main_settings = get_option( 'woocommerce_paymob-main_settings', array() );
		$sec_key       = isset( $main_settings['sec_key'] ) ? (string) $main_settings['sec_key'] : '';

		if ( '' === $sec_key || ! class_exists( 'Paymob' ) ) {
			return array();
		}

		$plans = array();

		try {
			$api_base = Paymob::getApiUrl( Paymob::getCountryCode( $sec_key ) );

			$endpoint = apply_filters(
				'paymob_aw_plans_endpoint',
				$api_base . 'api/acceptance/installment/plans',
				$integration_id,
				$amount_cents,
				$currency
			);

			$body = apply_filters(
				'paymob_aw_plans_request_body',
				array(
					'integration_id' => (int) $integration_id,
					'amount_cents'   => (int) $amount_cents,
					'currency'       => $currency,
				),
				$integration_id,
				$amount_cents,
				$currency
			);

			$method = apply_filters( 'paymob_aw_plans_request_method', 'POST' );

			$paymobReq = new Paymob( false, null );
			$header    = array( 'Content-Type: application/json', 'Authorization: Token ' . $sec_key );
			$response  = $paymobReq->HttpRequest( $endpoint, $method, $header, $body );

			$response_array = is_object( $response ) ? json_decode( wp_json_encode( $response ), true ) : (array) $response;

			$candidates = array();
			if ( isset( $response_array['plans'] ) && is_array( $response_array['plans'] ) ) {
				$candidates = $response_array['plans'];
			} elseif ( isset( $response_array['results'] ) && is_array( $response_array['results'] ) ) {
				$candidates = $response_array['results'];
			} elseif ( isset( $response_array['data'] ) && is_array( $response_array['data'] ) ) {
				$candidates = $response_array['data'];
			} elseif ( is_array( $response_array ) && isset( $response_array[0] ) ) {
				$candidates = $response_array;
			}

			foreach ( $candidates as $candidate ) {
				$candidate = (array) $candidate;
				$id        = isset( $candidate['id'] ) ? (string) $candidate['id'] : ( isset( $candidate['plan_id'] ) ? (string) $candidate['plan_id'] : '' );
				if ( '' === $id ) {
					continue;
				}
				$months           = isset( $candidate['months'] ) ? (int) $candidate['months'] : ( isset( $candidate['tenor'] ) ? (int) $candidate['tenor'] : 0 );
				$monthly_cents    = isset( $candidate['monthly_installment'] ) ? (int) $candidate['monthly_installment'] : ( isset( $candidate['monthly_amount_cents'] ) ? (int) $candidate['monthly_amount_cents'] : 0 );
				$interest_percent = isset( $candidate['interest_rate'] ) ? (float) $candidate['interest_rate'] : ( isset( $candidate['interest_percent'] ) ? (float) $candidate['interest_percent'] : 0 );
				$bank_label       = isset( $candidate['bank_name'] ) ? (string) $candidate['bank_name'] : ( isset( $candidate['bank'] ) ? (string) $candidate['bank'] : '' );

				if ( 0 === $monthly_cents && $months > 0 ) {
					$monthly_cents = (int) round( $amount_cents / $months );
				}

				$plans[] = array(
					'id'              => $id,
					'months'          => $months,
					'monthly_cents'   => $monthly_cents,
					'interest'        => $interest_percent,
					'bank'            => $bank_label,
					'currency'        => $currency,
				);
			}
		} catch ( Exception $e ) {
			if ( class_exists( 'Paymob_Error_Logs' ) ) {
				Paymob_Error_Logs::add( 'Affordability widget: failed to fetch installment plans — ' . $e->getMessage(), 'affordability_widget', 'gateway' );
			}
		}

		return apply_filters( 'paymob_aw_plans_normalised', $plans, $integration_id, $amount_cents, $currency );
	}

	public static function inject_into_intention( $data, $context = array() ) {
		if ( ! is_array( $data ) ) {
			return $data;
		}
		if ( ! function_exists( 'WC' ) || ! WC()->session ) {
			return $data;
		}

		$plan_id        = (string) WC()->session->get( self::SESSION_PLAN_KEY, '' );
		$integration_id = (string) WC()->session->get( self::SESSION_INTEGR_ID, '' );

		if ( '' === $plan_id || '' === $integration_id ) {
			return $data;
		}

		if ( ! self::should_preselect_payment_method() ) {
			return $data;
		}

		if ( ! self::should_attach_plan_to_intention( $context, $integration_id ) ) {
			return $data;
		}

		$plan_id_int = absint( $plan_id );
		if ( $plan_id_int <= 0 ) {
			return $data;
		}

		// Paymob Intention API: pre-select the installment plan on hosted checkout.
		$data['pre_selected_plan'] = $plan_id_int;

		$data['extras'] = isset( $data['extras'] ) && is_array( $data['extras'] ) ? $data['extras'] : array();
		$data['extras']['affordability_widget_plan_id'] = $plan_id;

		if ( ! empty( $context['order_id'] ) && function_exists( 'wc_get_order' ) ) {
			$order = wc_get_order( absint( $context['order_id'] ) );
			if ( $order ) {
				$order->update_meta_data( 'paymob_aw_plan_id', $plan_id );
				$order->update_meta_data( 'paymob_aw_integration_id', $integration_id );
				$order->save();
			}
		}

		return $data;
	}

	/**
	 * Whether the selected widget plan should be forwarded to the Intention API.
	 *
	 * @param array  $context        Filter context (order_id, etc.).
	 * @param string $integration_id Bank integration ID from the widget session.
	 * @return bool
	 */
	protected static function should_attach_plan_to_intention( $context, $integration_id ) {
		$order_payment_method = '';

		if ( ! empty( $context['order_id'] ) && function_exists( 'wc_get_order' ) ) {
			$order = wc_get_order( absint( $context['order_id'] ) );
			if ( $order ) {
				$order_payment_method = (string) $order->get_payment_method();
			}
		}

		if ( '' === $order_payment_method && WC()->session ) {
			$order_payment_method = (string) WC()->session->get( 'chosen_payment_method', '' );
		}

		if ( '' === $order_payment_method ) {
			return self::should_preselect_payment_method();
		}

		$bank_gateway_id = self::find_matching_bank_gateway_id( $integration_id );
		if ( $bank_gateway_id && $order_payment_method === $bank_gateway_id ) {
			return true;
		}

		return false;
	}

	/**
	 * Pre-selects the Bank Installments WooCommerce gateway when the shopper has just clicked
	 * "Buy Now" inside the Affordability Widget and lands on the checkout page.
	 *
	 * Selection logic:
	 *  - When the redirected URL carries `?paymob_aw_preselect=1` we treat it as a fresh arrival
	 *    from the widget and forcibly assign `chosen_payment_method` to the matching bank gateway.
	 *  - On subsequent checkout renders (including `update_order_review` AJAX calls) we DO NOT
	 *    override `chosen_payment_method` — that way the shopper can still freely switch gateways.
	 *  - Re-ordering and pre-selection only run for the explicit widget Buy Now flow.
	 */
	public static function preselect_bank_installment_gateway( $available_gateways ) {
		if ( ! is_array( $available_gateways ) || empty( $available_gateways ) ) {
			return $available_gateways;
		}
		if ( ! function_exists( 'WC' ) || ! WC()->session ) {
			return $available_gateways;
		}

		if ( ! self::should_preselect_payment_method() ) {
			return $available_gateways;
		}

		$plan_id        = (string) WC()->session->get( self::SESSION_PLAN_KEY, '' );
		$integration_id = (string) WC()->session->get( self::SESSION_INTEGR_ID, '' );
		if ( '' === $plan_id || '' === $integration_id ) {
			return $available_gateways;
		}

		$bank_gateway_id = self::find_matching_bank_gateway_id( $integration_id );
		if ( '' === $bank_gateway_id || ! isset( $available_gateways[ $bank_gateway_id ] ) ) {
			return $available_gateways;
		}

		WC()->session->set( 'chosen_payment_method', $bank_gateway_id );

		$preselected = array( $bank_gateway_id => $available_gateways[ $bank_gateway_id ] );
		foreach ( $available_gateways as $key => $gateway ) {
			if ( $key !== $bank_gateway_id ) {
				$preselected[ $key ] = $gateway;
			}
		}

		return $preselected;
	}

	/**
	 * Detects whether the shopper just arrived on the checkout from the Affordability Widget's
	 * Buy Now button (we add `paymob_aw_preselect=1` to the redirect URL).
	 *
	 * The flag is also propagated through `update_order_review` AJAX calls so the bank gateway
	 * isn't "un-selected" the moment WooCommerce re-renders the order review fragment after the
	 * first checkout load.
	 */
	protected static function just_arrived_from_widget() {
		if ( ! empty( $_GET['paymob_aw_preselect'] ) ) {
			return true;
		}

		if ( function_exists( 'WC' ) && WC()->session && '1' === (string) WC()->session->get( self::SESSION_PRESELECT_KEY, '' ) ) {
			return true;
		}

		if ( ! empty( $_REQUEST['post_data'] ) ) {
			$post_data = sanitize_text_field( wp_unslash( $_REQUEST['post_data'] ) );
			if ( is_string( $post_data ) && false !== strpos( $post_data, 'paymob_aw_preselect=1' ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Resolve the WooCommerce gateway ID for a Bank Installments integration ID.
	 *
	 * Mirrors the admin widget dropdown rules: current mode, enabled gateway, then matches
	 * against the DB integration_id column, gateway settings, or the ID embedded in gateway_id.
	 *
	 * @param string $integration_id Paymob integration ID from the widget settings / SDK.
	 * @return string Gateway ID slug or empty string.
	 */
	protected static function find_matching_bank_gateway_id( $integration_id ) {
		if ( '' === (string) $integration_id || ! class_exists( 'PaymobAutoGenerate' ) ) {
			return '';
		}

		$gateways     = PaymobAutoGenerate::get_db_gateways_data();
		if ( ! is_array( $gateways ) ) {
			return '';
		}

		$main_options = get_option( 'woocommerce_paymob-main_settings', array() );
		$current_mode = ! empty( $main_options['mode'] ) ? strtolower( (string) $main_options['mode'] ) : 'test';
		$target_id    = (string) $integration_id;

		foreach ( $gateways as $gateway ) {
			if ( empty( $gateway->gateway_id ) || false === stripos( $gateway->gateway_id, 'bank-installments' ) ) {
				continue;
			}

			$gateway_mode = isset( $gateway->mode ) ? strtolower( (string) $gateway->mode ) : $current_mode;
			if ( $gateway_mode !== $current_mode ) {
				continue;
			}

			$gateway_options = get_option( 'woocommerce_' . $gateway->gateway_id . '_settings', array() );
			if ( empty( $gateway_options['enabled'] ) || 'yes' !== $gateway_options['enabled'] ) {
				continue;
			}

			$candidate_ids = array();
			if ( ! empty( $gateway->integration_id ) ) {
				$candidate_ids[] = (string) $gateway->integration_id;
			}
			if ( ! empty( $gateway_options['single_integration_id'] ) ) {
				$candidate_ids[] = (string) $gateway_options['single_integration_id'];
			}
			if ( preg_match( '/^paymob-(\d+)-bank-installments/i', (string) $gateway->gateway_id, $matches ) ) {
				$candidate_ids[] = (string) $matches[1];
			}

			foreach ( array_unique( $candidate_ids ) as $candidate_id ) {
				if ( $candidate_id === $target_id ) {
					return (string) $gateway->gateway_id;
				}
			}
		}

		if ( class_exists( 'Paymob_Widget_Settings' ) && Paymob_Widget_Settings::is_integration_in_multi_app_bank_installments( $target_id ) ) {
			return 'paymob';
		}

		return '';
	}

	/**
	 * Hooked to `default_checkout_payment_method`.
	 *
	 * Runs before the gateway list is built. When the shopper has just picked an installment
	 * plan via the widget, we surface the matching Bank Installments gateway as the checkout's
	 * default — so the very first paint shows the right option already selected, with no
	 * flicker from a competing default.
	 */
	public static function override_default_payment_method( $default ) {
		if ( ! function_exists( 'WC' ) || ! WC()->session ) {
			return $default;
		}

		if ( self::should_preselect_payment_method() ) {
			$plan_id        = (string) WC()->session->get( self::SESSION_PLAN_KEY, '' );
			$integration_id = (string) WC()->session->get( self::SESSION_INTEGR_ID, '' );
			if ( '' === $plan_id || '' === $integration_id ) {
				return $default;
			}
			$bank_gateway_id = self::find_matching_bank_gateway_id( $integration_id );
			return '' !== $bank_gateway_id ? $bank_gateway_id : $default;
		}

		if ( is_string( $default ) && self::is_bank_installment_gateway( $default ) ) {
			$default = '';
		}

		$standard_default = self::get_standard_checkout_default_gateway();
		return '' !== $standard_default ? $standard_default : $default;
	}

	/**
	 * On the checkout page, eagerly assert the Bank Installments gateway as the shopper's
	 * chosen payment method whenever a widget plan is in the session. This covers the case
	 * where the page is reached via `?paymob_aw=1` (right after Buy Now) and also any later
	 * refresh while the session still holds a plan.
	 */
	public static function maybe_lock_payment_method_on_checkout() {
		if ( ! function_exists( 'is_checkout' ) || ! is_checkout() || is_order_received_page() ) {
			return;
		}
		if ( ! self::should_preselect_payment_method() ) {
			return;
		}
		if ( ! function_exists( 'WC' ) || ! WC()->session ) {
			return;
		}

		$integration_id = (string) WC()->session->get( self::SESSION_INTEGR_ID, '' );
		if ( '' === $integration_id ) {
			return;
		}

		$bank_gateway_id = self::find_matching_bank_gateway_id( $integration_id );
		if ( '' === $bank_gateway_id ) {
			return;
		}

		WC()->session->set( 'chosen_payment_method', $bank_gateway_id );
	}

	/**
	 * Critical CSS for the Paymob expanded plans panel so it clears site chrome before JS runs.
	 *
	 * @return string
	 */
	protected static function get_panel_layout_critical_css() {
		return '
:root {
	--paymob-aw-expanded-modal-top: 0px;
	--paymob-aw-header-offset: 0px;
}
html body.admin-bar {
	--paymob-aw-expanded-modal-top: var(--wp-admin--admin-bar--height, 32px);
	--paymob-aw-header-offset: var(--wp-admin--admin-bar--height, 32px);
}
@media screen and (max-width: 782px) {
	html body.admin-bar {
		--paymob-aw-expanded-modal-top: var(--wp-admin--admin-bar--height, 46px);
		--paymob-aw-header-offset: var(--wp-admin--admin-bar--height, 46px);
	}
}
html body div[class*="modal_root"],
html body #paymob-aw-expanded-modal {
	position: fixed !important;
	inset: auto !important;
	top: var(--paymob-aw-expanded-modal-top, 0px) !important;
	right: 0 !important;
	bottom: 0 !important;
	left: 0 !important;
	width: auto !important;
	height: auto !important;
	transform: none !important;
	z-index: 1000001 !important;
}
html body div[class*="modal_root"] [class*="modal_panelDesktop"],
html body #paymob-aw-expanded-modal [class*="modal_panelDesktop"] {
	position: absolute !important;
	top: 0 !important;
	right: 0 !important;
	bottom: 0 !important;
	left: auto !important;
	width: min(550px, 100vw) !important;
	height: 100% !important;
	max-height: 100% !important;
	margin: 0 !important;
	z-index: 1000001 !important;
	display: flex !important;
	flex-direction: column !important;
	overflow: hidden !important;
}
html body div[class*="modal_root"] [class*="modal_dialog"],
html body #paymob-aw-expanded-modal [class*="modal_dialog"] {
	display: flex !important;
	flex-direction: column !important;
	flex: 1 1 auto !important;
	min-height: 0 !important;
	height: auto !important;
	max-height: 100% !important;
	overflow: hidden !important;
}
html body div[class*="modal_root"] [class*="modal_dialogDesktop"],
html body #paymob-aw-expanded-modal [class*="modal_dialogDesktop"] {
	overflow: visible !important;
}
html body div[class*="modal_root"] [class*="modal_header"],
html body #paymob-aw-expanded-modal [class*="modal_header"] {
	display: flex !important;
	align-items: center !important;
	justify-content: space-between !important;
	visibility: visible !important;
	opacity: 1 !important;
	min-height: 56px !important;
	flex: 0 0 auto !important;
	width: 100% !important;
	box-sizing: border-box !important;
	position: relative !important;
	z-index: 5 !important;
	overflow: visible !important;
	background-color: #144dff !important;
	color: #ffffff !important;
	padding: 12px !important;
}
html body div[class*="modal_root"] [class*="modal_title"],
html body #paymob-aw-expanded-modal [class*="modal_title"] {
	display: block !important;
	visibility: visible !important;
	opacity: 1 !important;
	flex: 1 1 auto !important;
	min-width: 0 !important;
	font-size: 14px !important;
	font-weight: 600 !important;
	line-height: 1.4 !important;
	padding-right: 48px !important;
	color: #ffffff !important;
	-webkit-text-fill-color: #ffffff !important;
	background: none !important;
	background-image: none !important;
	background-clip: border-box !important;
	-webkit-background-clip: border-box !important;
}
html body div[class*="modal_root"] [class*="modal_poweredRow"],
html body #paymob-aw-expanded-modal [class*="modal_poweredRow"] {
	display: flex !important;
	align-items: center !important;
	visibility: visible !important;
	opacity: 1 !important;
	flex: 0 0 auto !important;
	gap: 4px !important;
	margin-right: 40px !important;
}
html body div[class*="modal_root"] [class*="modal_poweredLabel"],
html body #paymob-aw-expanded-modal [class*="modal_poweredLabel"] {
	display: flex !important;
	visibility: visible !important;
	opacity: 1 !important;
	color: #d0d5dd !important;
	-webkit-text-fill-color: #d0d5dd !important;
	background: none !important;
	background-image: none !important;
}
html body div[class*="modal_root"] [class*="modal_logo"],
html body #paymob-aw-expanded-modal [class*="modal_logo"] {
	display: block !important;
	visibility: visible !important;
	opacity: 1 !important;
	height: 17px !important;
	width: auto !important;
}
html body div[class*="modal_root"] [class*="modal_body"],
html body #paymob-aw-expanded-modal [class*="modal_body"] {
	position: relative !important;
	top: auto !important;
	flex: 1 1 auto !important;
	min-height: 0 !important;
	overflow-y: auto !important;
}
html body .paymob-aw-fallback-header {
	display: flex !important;
	align-items: center !important;
	justify-content: space-between !important;
	gap: 12px !important;
	min-height: 56px !important;
	padding: 12px 48px 12px 12px !important;
	background-color: #144dff !important;
	color: #ffffff !important;
	box-sizing: border-box !important;
	flex: 0 0 auto !important;
	position: relative !important;
	z-index: 6 !important;
	width: 100% !important;
	font-family: Inter, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif !important;
}
html body .paymob-aw-fallback-header__title {
	flex: 1 1 auto !important;
	min-width: 0 !important;
	font-size: 14px !important;
	font-weight: 600 !important;
	line-height: 1.4 !important;
	color: #ffffff !important;
	-webkit-text-fill-color: #ffffff !important;
	background: none !important;
}
html body .paymob-aw-fallback-header__brand {
	display: flex !important;
	align-items: center !important;
	gap: 4px !important;
	flex: 0 0 auto !important;
}
html body .paymob-aw-fallback-header__label {
	font-size: 12px !important;
	line-height: 1 !important;
	color: #d0d5dd !important;
	-webkit-text-fill-color: #d0d5dd !important;
}
html body .paymob-aw-fallback-header__logo {
	display: block !important;
	height: 17px !important;
	width: auto !important;
}
html body .paymob-aw-fallback-header__close {
	position: absolute !important;
	top: 50% !important;
	right: 12px !important;
	transform: translateY(-50%) !important;
	display: flex !important;
	align-items: center !important;
	justify-content: center !important;
	width: 40px !important;
	height: 40px !important;
	border: none !important;
	border-radius: 50% !important;
	background: #ffffff !important;
	color: #101828 !important;
	cursor: pointer !important;
	box-shadow: 0 6px 16px rgba(0, 0, 0, 0.18) !important;
	font-size: 24px !important;
	line-height: 1 !important;
	padding: 0 !important;
}
html body .paymob-aw-fallback-header-present [class*="modal_header"],
html body [class*="modal_panel"] > .paymob-aw-fallback-header ~ [role="dialog"] [class*="modal_header"] {
	display: none !important;
	height: 0 !important;
	min-height: 0 !important;
	padding: 0 !important;
	margin: 0 !important;
	overflow: hidden !important;
}
html body div[class*="modal_root"] [class*="modal_closeFloatingDesktop"],
html body div[class*="modal_root"] [class*="modal_closeFloatingMobile"],
html body #paymob-aw-expanded-modal [class*="modal_closeFloatingDesktop"],
html body #paymob-aw-expanded-modal [class*="modal_closeFloatingMobile"] {
	display: flex !important;
	visibility: visible !important;
	opacity: 1 !important;
	position: absolute !important;
	top: 50% !important;
	right: 12px !important;
	left: auto !important;
	transform: translateY(-50%) !important;
	z-index: 5 !important;
}
';
	}

	/**
	 * Late footer fallback so expanded panel header styles win over SDK resets.
	 *
	 * @return void
	 */
	public static function render_panel_layout_footer_fix() {
		if ( ! self::is_enabled() ) {
			return;
		}
		if ( ! self::is_widget_context_page() ) {
			return;
		}

		$panel_config   = wp_json_encode( self::get_panel_layout_script_config() );
		$header_js_path = PAYMOB_PLUGIN_PATH . 'assets/js/affordability-widget-panel-header.js';
		$header_js      = file_exists( $header_js_path ) ? file_get_contents( $header_js_path ) : '';

		echo '<style id="paymob-aw-panel-footer-critical">' . wp_kses( self::get_panel_layout_critical_css(), array() ) . '</style>';
		?>
		<script id="paymob-aw-panel-layout-config">
		window.paymobAwPanelLayout = window.paymobAwPanelLayout || <?php echo $panel_config; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>;
		</script>
		<?php if ( '' !== $header_js ) : ?>
		<script id="paymob-aw-panel-header-inline">
		<?php echo $header_js; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</script>
		<?php endif; ?>
		<script id="paymob-aw-panel-footer-fix">
		( function() {
			function run() {
				var modal = document.querySelector( '[class*="modal_root"], #paymob-aw-expanded-modal' );
				if ( typeof window.paymobAwEnsurePanelHeader === 'function' && modal ) {
					window.paymobAwEnsurePanelHeader( modal );
				}
				if ( typeof window.paymobAwApplyExpandedPanelLayout === 'function' ) {
					window.paymobAwApplyExpandedPanelLayout();
				}
			}
			run();
			document.addEventListener( 'DOMContentLoaded', run );
			window.addEventListener( 'load', run );
			window.setInterval( function() {
				if ( document.querySelector( '[class*="modal_root"], #paymob-aw-expanded-modal' ) ) {
					run();
				}
			}, 100 );
		} )();
		</script>
		<?php
	}

	/**
	 * Promote the Paymob Widget SDK script tag to a real ES module. The SDK README explicitly
	 * requires `type="module"` and `wp_enqueue_script` cannot do that on its own.
	 */
	public static function mark_sdk_script_as_module( $tag, $handle, $src ) {
		if ( 'paymob-widget-sdk' === $handle ) {
			// phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript -- script_loader_tag filter; SDK requires type="module".
			$tag = '<script type="module" src="' . esc_url( $src ) . '" id="' . esc_attr( $handle ) . '-js"></script>' . "\n";
		}
		return $tag;
	}
}

Paymob_Affordability_Widget::init();
