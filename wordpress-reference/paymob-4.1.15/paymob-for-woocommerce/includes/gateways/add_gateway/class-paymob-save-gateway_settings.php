<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Paymob_Save_Gateway_Settings {
	public static function save_paymob_add_gateway_settings() {
		global $current_section, $wpdb;

		if ( 'paymob_add_gateway' !== $current_section ) {
			return;
		}

		$paymob_options = get_option( 'woocommerce_paymob_settings' );
		$mainOptions = get_option('woocommerce_paymob-main_settings');
		$mode       = isset($mainOptions['mode']) ? $mainOptions['mode'] : 'test';

		$pub_key = isset( $paymob_options['pub_key'] ) ? $paymob_options['pub_key'] : '';
		$sec_key = isset( $paymob_options['sec_key'] ) ? $paymob_options['sec_key'] : '';
		$api_key = isset( $paymob_options['api_key'] ) ? $paymob_options['api_key'] : '';

		if ( empty( $pub_key ) || empty( $sec_key ) || empty( $api_key ) ) {
			WC_Admin_Settings::add_error( __( 'Please ensure you are entering API, public and secret keys in the main Paymob configuration.', 'paymob-for-woocommerce' ) );
		} else {
			$integration_id = Paymob::filterVar( 'integration_id', 'POST' ) ? sanitize_text_field( Paymob::filterVar( 'integration_id', 'POST' ) ) : '';
			$payment_enabled = Paymob::filterVar( 'payment_enabled', 'POST' ) ? 'yes' : 'no';
			$payment_integrations_type_raw = Paymob::filterVar( 'payment_integrations_type', 'POST' ) ? sanitize_text_field( Paymob::filterVar( 'payment_integrations_type', 'POST' ) ) : '';
			$checkout_title = Paymob::filterVar( 'checkout_title', 'POST' ) ? sanitize_text_field( Paymob::filterVar( 'checkout_title', 'POST' ) ) : '';
			$checkout_description = Paymob::filterVar( 'checkout_description', 'POST' ) ? sanitize_text_field( Paymob::filterVar( 'checkout_description', 'POST' ) ) : '';

			$payment_integrations_type = 'paymob-' . preg_replace( '/[^a-zA-Z0-9]+/', '-', strtolower( $payment_integrations_type_raw ) );
			$file_name = 'class-gateway-' .$payment_integrations_type. '.php';

			$gateway = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}paymob_gateways WHERE gateway_id = %s", $payment_integrations_type ), OBJECT );

			if ( ! $gateway && '' !== $payment_integrations_type_raw ) {
				$ordering = $wpdb->get_var( "SELECT max(ordering) FROM {$wpdb->prefix}paymob_gateways" );
				++$ordering;

				$inserted = $wpdb->insert(
					$wpdb->prefix . 'paymob_gateways',
					array(
						'gateway_id' => $payment_integrations_type,
						'file_name' => $file_name,
						'checkout_title' => sanitize_text_field( $checkout_title ),
						'checkout_description' => sanitize_text_field( $checkout_description ),
						'integration_id' => $integration_id,
						'is_manual' => '1',
						'ordering' => $ordering,
						'mode' => $mode
					)
				);

				if ( false !== $inserted ) {
					// Save default settings for the new gateway.
					$default_settings = array(
						'enabled' => $payment_enabled,
						'single_integration_id' => $integration_id,
						'title' => $checkout_title,
						'description' => $checkout_description,
					);
					update_option( 'woocommerce_' . $payment_integrations_type . '_settings', $default_settings );

					// Redirect to the list of gateways page.
					wp_safe_redirect( admin_url( 'admin.php?page=wc-settings&tab=checkout&section=paymob_list_gateways' ) );
					exit;
				} else {
					WC_Admin_Settings::add_error( __( 'Failed to insert gateway into database.', 'paymob-for-woocommerce' ) );
				}
			} else {
				WC_Admin_Settings::add_error( __( 'Gateway Already Exists.', 'paymob-for-woocommerce' ) );
			}
		}
	}

	/**
	 * Intercepts the Affordability Widget POST submission before WooCommerce's own settings save flow
	 * runs at `wp_loaded:10`. By short-circuiting here (with `exit;`) we guarantee that:
	 *  - When validation fails, the option is NOT updated and WC's "Your settings have been saved."
	 *    message never gets queued (because WC_Admin_Settings::save() is never called).
	 *  - When validation passes, we persist the option ourselves and redirect back with a clean URL.
	 */
	public static function intercept_widget_save() {
		if ( ! is_admin() ) {
			return;
		}
		if ( empty( $_POST['save'] ) ) {
			return;
		}
		if ( empty( $_REQUEST['page'] ) || 'wc-settings' !== $_REQUEST['page'] ) {
			return;
		}
		if ( empty( $_REQUEST['tab'] ) || 'checkout' !== $_REQUEST['tab'] ) {
			return;
		}
		if ( empty( $_REQUEST['section'] ) || 'widget' !== sanitize_title( wp_unslash( $_REQUEST['section'] ) ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		if ( empty( $_REQUEST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) ), 'woocommerce-settings' ) ) {
			return;
		}

		$enable_raw              = isset( $_POST['enable'] ) ? sanitize_text_field( wp_unslash( $_POST['enable'] ) ) : '';
		$payment_enabled         = ( '' !== (string) $enable_raw && 'no' !== $enable_raw ) ? 'yes' : 'no';

		$integration_id = '';
		if ( isset( $_POST['paymob_aw_integration_id'] ) ) {
			$integration_id = sanitize_text_field( wp_unslash( $_POST['paymob_aw_integration_id'] ) );
		} elseif ( isset( $_POST['integration_id'] ) ) {
			$integration_id = sanitize_text_field( wp_unslash( $_POST['integration_id'] ) );
		}
		if ( class_exists( 'Paymob_Widget_Settings' ) ) {
			$integration_id = Paymob_Widget_Settings::resolve_selected_integration_id( $integration_id );
		}

		$min_product_enabled_raw = isset( $_POST['min_product_enabled'] ) ? sanitize_text_field( wp_unslash( $_POST['min_product_enabled'] ) ) : '';
		$min_product_enabled     = ( '' !== (string) $min_product_enabled_raw && 'no' !== $min_product_enabled_raw ) ? 'yes' : 'no';
		$min_product_amount      = isset( $_POST['min_product_amount'] ) ? sanitize_text_field( wp_unslash( $_POST['min_product_amount'] ) ) : '';

		$min_cart_enabled_raw    = isset( $_POST['min_cart_enabled'] ) ? sanitize_text_field( wp_unslash( $_POST['min_cart_enabled'] ) ) : '';
		$min_cart_enabled        = ( '' !== (string) $min_cart_enabled_raw && 'no' !== $min_cart_enabled_raw ) ? 'yes' : 'no';
		$min_cart_amount         = isset( $_POST['min_cart_amount'] ) ? sanitize_text_field( wp_unslash( $_POST['min_cart_amount'] ) ) : '';

		$widget_theme            = isset( $_POST['widget_theme'] ) ? sanitize_key( wp_unslash( $_POST['widget_theme'] ) ) : 'primary';
		if ( ! in_array( $widget_theme, array( 'primary', 'light', 'dark' ), true ) ) {
			$widget_theme = 'primary';
		}

		$dark_mode = ( 'dark' === $widget_theme ) ? 'yes' : 'no';

		$blocking_errors = array();
		$field_warnings  = array();

		if ( 'yes' === $payment_enabled && '' === $integration_id ) {
			$blocking_errors[] = __( 'Please select an integration ID to enable the widget.', 'paymob-for-woocommerce' );
			$payment_enabled   = 'no';
		}

		if ( 'yes' === $min_product_enabled ) {
			$min_product_value = is_numeric( $min_product_amount ) ? (float) $min_product_amount : 0.0;
			if ( $min_product_value <= 0 ) {
				$field_warnings[]    = __( 'Please enter a minimum product amount.', 'paymob-for-woocommerce' );
				$min_product_enabled = 'no';
				$min_product_amount  = '';
			}
		} else {
			$min_product_amount = '';
		}

		if ( 'yes' === $min_cart_enabled ) {
			$min_cart_value = is_numeric( $min_cart_amount ) ? (float) $min_cart_amount : 0.0;
			if ( $min_cart_value <= 0 ) {
				$field_warnings[]  = __( 'Please enter a minimum cart amount.', 'paymob-for-woocommerce' );
				$min_cart_enabled  = 'no';
				$min_cart_amount   = '';
			}
		} else {
			$min_cart_amount = '';
		}

		// Discard any buffered output so the redirect header can actually be sent.
		while ( ob_get_level() > 0 ) {
			ob_end_clean();
		}
		nocache_headers();

		if ( ! empty( $blocking_errors ) ) {
			set_transient( 'paymob_aw_flash_errors', $blocking_errors, 60 );
			wp_safe_redirect( admin_url( 'admin.php?page=wc-settings&tab=checkout&section=widget' ) );
			exit;
		}

		$widget_settings = array(
			'enabled_widget'      => $payment_enabled,
			'integration_id'      => $integration_id,
			'min_product_enabled' => $min_product_enabled,
			'min_product_amount'  => $min_product_amount,
			'min_cart_enabled'    => $min_cart_enabled,
			'min_cart_amount'     => $min_cart_amount,
			'widget_theme'        => $widget_theme,
			'dark_mode'           => $dark_mode,
		);

		update_option( 'woocommerce_paymob_widget_settings', $widget_settings );

		delete_transient( 'paymob_aw_flash_errors' );
		delete_transient( 'paymob_aw_flash_success' );

		if ( ! empty( $field_warnings ) ) {
			set_transient( 'paymob_aw_flash_errors', $field_warnings, 60 );
		}

		set_transient( 'paymob_aw_flash_success', __( 'Your settings have been saved.', 'paymob-for-woocommerce' ), 60 );
		wp_safe_redirect( admin_url( 'admin.php?page=wc-settings&tab=checkout&section=widget&settings-updated=true' ) );
		exit;
	}

	/**
	 * Legacy entry point still wired through `woocommerce_update_options_checkout`.
	 *
	 * The real save is performed earlier in {@see Paymob_Save_Gateway_Settings::intercept_widget_save()}
	 * (hooked to `wp_loaded`). By the time WC's action chain runs, that handler has already
	 * exited the request on the widget section, so this method is intentionally a no-op kept
	 * for backwards compatibility with `add-paymob-gateway.php`.
	 */
	public static function save_paymob_widget_settings() {
		// Intentionally empty — see intercept_widget_save().
	}

	public static function save_paymob_subscription_settings() {
		global $current_section, $wpdb;

		if ( 'paymob_subscription' !== $current_section ) {
			return;
		}

		$mainOptions = get_option('woocommerce_paymob-main_settings');
		$mode       = isset($mainOptions['mode']) ? $mainOptions['mode'] : 'test';

		// Get subscription settings
		$subscription_settings = Paymob::filterVar('woocommerce_paymob-subscription_settings', 'POST');
		if ( ! is_array( $subscription_settings ) ) {
			$subscription_settings = array();
		}
		$enabled = (!empty($subscription_settings['enabled']) && $subscription_settings['enabled'] === '1') ? 'yes' : 'no';
		$title        = !empty($subscription_settings['title']) ? sanitize_text_field($subscription_settings['title']) : 'Paymob Subscription';
		$description  = !empty($subscription_settings['description']) ? sanitize_text_field($subscription_settings['description']) : 'Recurring payment via Paymob.';
		$moto_id      = isset( $subscription_settings['moto_integration_id'] ) ? sanitize_text_field( (string) $subscription_settings['moto_integration_id'] ) : '';
		$threeds_ids  = isset( $subscription_settings['ds3_integration_ids'] ) ? sanitize_text_field( (string) $subscription_settings['ds3_integration_ids'] ) : '';
		$allow_cancel = (!empty($subscription_settings['allow_cancel']) && $subscription_settings['allow_cancel'] === '1')
		? 'yes'
		: 'no';

		if ( '' === $moto_id || '' === $threeds_ids ) {
			WC_Admin_Settings::add_error(__('Please select both MOTO and 3DS Integration IDs.', 'paymob-for-woocommerce'));
			return;
		}

		$gateway_id = 'paymob-subscription';

		// Insert into custom DB table if needed
		$exists = $wpdb->get_var($wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->prefix}paymob_gateways WHERE gateway_id = %s",
			$gateway_id
		));

		if (!$exists) {
			$ordering = $wpdb->get_var("SELECT MAX(ordering) FROM {$wpdb->prefix}paymob_gateways");
			$ordering++;

			$wpdb->insert("{$wpdb->prefix}paymob_gateways", array(
				'gateway_id'        => $gateway_id,
				'class_name'        => 'Paymob_Subscription',
				'file_name'         => 'class-gateway-paymob-subscription.php',
				'checkout_title'    => sanitize_text_field($title),
				'checkout_description' => sanitize_text_field($description),
				'integration_id'    => implode(',', (array)$threeds_ids),
				'is_manual'         => '1',
				'ordering'          => $ordering,
				'mode'              => $mode,
			));
		}

		// Save the WooCommerce gateway settings
		$default_settings = array(
			'enabled'                  => $enabled,
			'title'                    => $title,
			'description'              => $description,
			'moto_integration_id'      => $moto_id,
			'ds3_integration_ids'      => $threeds_ids,
			'allow_cancel'             => $allow_cancel,
		);

		update_option('woocommerce_paymob-subscription_settings', $default_settings);

		// Redirect back to the same section with success message
		wp_safe_redirect(admin_url('admin.php?page=wc-settings&tab=checkout&section=paymob_subscription&settings-updated=true'));
		exit;
	}

	
}

// Intercept the Affordability Widget save before WC's own settings save flow runs (wp_loaded:10),
// so a failed validation never lets WC queue its "Your settings have been saved." message.
add_action( 'wp_loaded', array( 'Paymob_Save_Gateway_Settings', 'intercept_widget_save' ), 5 );

add_action('admin_notices', function () {
    $is_widget_page = isset( $_GET['page'], $_GET['tab'], $_GET['section'] )
        && 'wc-settings' === $_GET['page']
        && 'checkout' === $_GET['tab']
        && 'widget' === $_GET['section'];

    if ( $is_widget_page ) {
        $flash_errors = get_transient( 'paymob_aw_flash_errors' );
        if ( ! empty( $flash_errors ) && is_array( $flash_errors ) ) {
            foreach ( $flash_errors as $err ) {
                echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( $err ) . '</p></div>';
            }
            delete_transient( 'paymob_aw_flash_errors' );
        }

        $flash_success = get_transient( 'paymob_aw_flash_success' );
        if ( ! empty( $flash_success ) ) {
            echo '<div class="updated notice is-dismissible"><p>' . esc_html( $flash_success ) . '</p></div>';
            delete_transient( 'paymob_aw_flash_success' );
            return;
        }
    }

    if ( isset( $_GET['settings-updated'] ) && 'true' === $_GET['settings-updated'] ) {
        echo '<div class="updated notice is-dismissible"><p>' . esc_html__( 'Your settings have been saved.', 'paymob-for-woocommerce' ) . '</p></div>';
    }
});

