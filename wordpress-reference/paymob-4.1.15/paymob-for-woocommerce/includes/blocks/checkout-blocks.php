<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Checkout_Blocks {

	/** @var bool */
	private static $block_files_loaded = false;

	/** @var bool */
	private static $registration_done = false;

	public function __construct() {
		add_action( 'woocommerce_blocks_loaded', array( $this, 'load_block_integration_files' ) );
		// Primary hook — also acts as fallback if blocks_loaded already ran (4.1.10+ load-order edge case).
		add_action( 'woocommerce_blocks_payment_method_type_registration', array( $this, 'register_payment_method_types' ), 5, 1 );
	}

	/**
	 * Load Paymob *-block.php integration classes once.
	 */
	public function load_block_integration_files() {
		if ( self::$block_files_loaded ) {
			return;
		}
		self::$block_files_loaded = true;

		$files = glob( PAYMOB_PLUGIN_PATH . 'includes/blocks/*-block.php' );
		if ( ! is_array( $files ) ) {
			return;
		}

		foreach ( $files as $filename ) {
			if ( is_readable( $filename ) ) {
				require_once $filename;
			}
		}

		$gateway_blocks = PAYMOB_PLUGIN_PATH . 'includes/blocks/gateway-blocks.php';
		if ( is_readable( $gateway_blocks ) ) {
			require_once $gateway_blocks;
		}
	}

	/**
	 * Register Paymob gateways with WooCommerce Blocks checkout.
	 *
	 * @param Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry Registry.
	 */
	public function register_payment_method_types( $payment_method_registry ) {
		if ( self::$registration_done ) {
			return;
		}

		if ( ! class_exists( 'Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' )
			|| ! class_exists( 'Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry' ) ) {
			return;
		}

		$this->load_block_integration_files();

		$registered = array();

		foreach ( $this->get_gateway_block_classes() as $gateway_class ) {
			if ( isset( $registered[ $gateway_class ] ) ) {
				continue;
			}

			if ( ! class_exists( $gateway_class ) ) {
				continue;
			}

			try {
				$instance = new $gateway_class();
				if ( ! $instance instanceof Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType ) {
					continue;
				}
				$payment_method_registry->register( $instance );
				$registered[ $gateway_class ] = true;
			} catch ( \Throwable $e ) {
				continue;
			}
		}

		self::$registration_done = ! empty( $registered );
	}

	/**
	 * Block class names from DB + bundled static integrations.
	 *
	 * @return string[]
	 */
	private function get_gateway_block_classes() {
		global $wpdb;

		$classes   = array();
		$table     = $wpdb->prefix . 'paymob_gateways';
		$has_table = ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table );

		if ( $has_table ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$gateways = $wpdb->get_results( "SELECT class_name FROM {$wpdb->prefix}paymob_gateways", OBJECT );
			if ( is_array( $gateways ) ) {
				foreach ( $gateways as $gateway ) {
					if ( empty( $gateway->class_name ) ) {
						continue;
					}
					$classes[] = 'WC_' . $gateway->class_name . '_Blocks';
				}
			}
		}

		// Always attempt bundled integrations (is_active() still respects enabled settings).
		$classes = array_merge(
			$classes,
			array(
				'WC_Paymob_Blocks',
				'WC_Paymob_Pixel_Blocks',
				'WC_Paymob_Subscription_Blocks',
			)
		);

		return array_values( array_unique( $classes ) );
	}
}

new Checkout_Blocks();
