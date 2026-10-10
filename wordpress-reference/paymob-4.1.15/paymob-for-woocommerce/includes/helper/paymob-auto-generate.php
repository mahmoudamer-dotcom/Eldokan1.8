<?php
/**
 * Class PaymobAutoGenerate
 *
 * This file contains the PaymobAutoGenerate class, which provides functionality
 * for auto-generating Paymob gateway files and handling integration details
 * such as creating gateways, updating orders, and managing integration settings
 * in WooCommerce.
 *
 * @package PaymobWooCommerce
 * @subpackage Classes
 */

class PaymobAutoGenerate {
	/**
	 * Generates the required files for Paymob integration.
	 *
	 * @param array $file_data An associative array containing the following keys:
	 *                       - 'file_name': The name of the file to generate.
	 *                       - 'gateway_id': The ID of the gateway.
	 *                       - 'class_name': The class name for the gateway.
	 *                       - 'checkout_title': The title for the checkout page.
	 *                       - 'checkout_description': The description for the checkout page.
	 *
	 * @return void
	 */
	public $needle;

	/**
	 * Recreate generated gateway/block files from DB after a plugin zip update.
	 *
	 * WordPress deletes the whole plugin folder on update, so merchant-specific
	 * PHP classes disappear while wp_paymob_gateways rows remain.
	 *
	 * @return void
	 */
	public static function ensure_generated_gateway_files() {
		global $wpdb;

		$table = $wpdb->prefix . 'paymob_gateways';
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
			return;
		}

		$gateways = self::get_db_gateways_data();
		if ( empty( $gateways ) ) {
			return;
		}

		foreach ( $gateways as $gateway ) {
			if ( empty( $gateway->gateway_id ) || empty( $gateway->class_name ) ) {
				continue;
			}

			self::generate_files(
				array(
					'class_name'           => $gateway->class_name,
					'gateway_id'           => $gateway->gateway_id,
					'checkout_title'       => isset( $gateway->checkout_title ) ? $gateway->checkout_title : '',
					'checkout_description' => isset( $gateway->checkout_description ) ? $gateway->checkout_description : '',
					'file_name'            => ! empty( $gateway->file_name ) ? $gateway->file_name : ( 'class-gateway-' . $gateway->gateway_id . '.php' ),
				)
			);
		}
	}

	/**
	 * Write a file into the plugin directory (survives WP_Filesystem credential failures).
	 *
	 * @param string $path     Absolute path.
	 * @param string $contents File contents.
	 * @return bool
	 */
	private static function write_plugin_file( $path, $contents ) {
		$dir = dirname( $path );
		if ( ! is_dir( $dir ) && ! wp_mkdir_p( $dir ) ) {
			return false;
		}

		return false !== file_put_contents( $path, $contents, LOCK_EX );
	}

	public static function generate_files( $file_data ) {
		if ( empty( $file_data['file_name'] ) || empty( $file_data['gateway_id'] ) || empty( $file_data['class_name'] ) ) {
			return;
		}

		$gateway_file_path       = PAYMOB_PLUGIN_PATH . 'includes/gateways/' . $file_data['file_name'];
		$gateway_block_file_path = PAYMOB_PLUGIN_PATH . 'includes/blocks/' . $file_data['gateway_id'] . '-block.php';
		$gateway_js_file_path    = PAYMOB_PLUGIN_PATH . 'assets/js/blocks/' . $file_data['gateway_id'] . '_block.js';

		if ( file_exists( $gateway_file_path ) && file_exists( $gateway_block_file_path ) && file_exists( $gateway_js_file_path ) ) {
			return;
		}

		$template_gateway_file       = PAYMOB_PLUGIN_PATH . 'templates/gateway.php';
		$template_gateway_block_file = PAYMOB_PLUGIN_PATH . 'templates/gateway_block.php';
		$template_gateway_js_file    = PAYMOB_PLUGIN_PATH . 'templates/gateway_block.js';

		if ( ! file_exists( $template_gateway_file ) || ! file_exists( $template_gateway_block_file ) || ! file_exists( $template_gateway_js_file ) ) {
			return;
		}

		$content = str_replace(
			array( 'class_name', 'gateway_id', 'checkout_title', 'checkout_description' ),
			array(
				$file_data['class_name'],
				$file_data['gateway_id'],
				isset( $file_data['checkout_title'] ) ? $file_data['checkout_title'] : '',
				isset( $file_data['checkout_description'] ) ? $file_data['checkout_description'] : '',
			),
			(string) file_get_contents( $template_gateway_file )
		);
		self::write_plugin_file( $gateway_file_path, $content );

		$cb_content = str_replace(
			array( 'class_name', 'gateway_id' ),
			array( $file_data['class_name'], $file_data['gateway_id'] ),
			(string) file_get_contents( $template_gateway_block_file )
		);
		self::write_plugin_file( $gateway_block_file_path, $cb_content );

		$jb_content = str_replace(
			array( 'checkout_title', 'gateway_id' ),
			array(
				isset( $file_data['checkout_title'] ) ? $file_data['checkout_title'] : '',
				$file_data['gateway_id'],
			),
			(string) file_get_contents( $template_gateway_js_file )
		);
		if ( false !== strpos( $file_data['gateway_id'], 'apple-pay' ) ) {
			$jb_content = str_replace( '//check_a_pay', 'if (typeof window.ApplePaySession !== "undefined")', $jb_content );
		} else {
			$jb_content = str_replace( '//check_a_pay', '', $jb_content );
		}
		self::write_plugin_file( $gateway_js_file_path, $jb_content );
	}
	/**
	 * Creates and updates Paymob gateways based on the provided data.
	 *
	 * @param array $result Result data containing integration IDs and other information.
	 * @param int   $paymob_setting Paymob setting status (e.g., 1 for enabled, 0 for disabled).
	 * @param array $gateway_data An associative array containing gateway-specific details.
	 *
	 * @return void
	 */
	public static function create_gateways( $result, $paymob_setting, $gateway_data ) {
		global $wpdb;
		$ids = array();
		$paymob_ids=array();
		// $paymobOptions = get_option('woocommerce_paymob-main_settings');
		// $paymob_mode = isset($paymobOptions['mode']) ? $paymobOptions['mode'] : '';
		// echo $paymob_mode;exit;
		if ( isset( $result['integrationIDs'] ) ) {
			foreach ( $result['integrationIDs'] as $value ) {
				$title                     = empty( $value['name'] ) ? $value['type'] : $value['name'];
				$ids[]                     = trim( $value['id'] );
				// if (isset($value['mode']) && $value['mode'] == $paymob_mode) {
				// 	$paymob_ids[]=trim( $value['id']);
				// }
				$payment_integrations_type = $value['id'] . ' ' . $title . ' ' . $value['gateway_type'] . ' ' . $value['currency'];
				$checkout_title            = isset( $gateway_data[ strtolower( $value['gateway_type'] ) ]['title'] ) ? $gateway_data[ strtolower( $value['gateway_type'] ) ]['title'] : $title;
				$checkout_desc             = isset( $gateway_data[ strtolower( $value['gateway_type'] ) ]['desc'] ) ? $gateway_data[ strtolower( $value['gateway_type'] ) ]['desc'] : $title;
				$class_name                = 'Paymob_' . preg_replace( '/[^a-zA-Z0-9]+/', '_', ucwords( $payment_integrations_type ) );
				$payment_integrations_type = 'paymob-' . preg_replace( '/[^a-zA-Z0-9]+/', '-', strtolower( $payment_integrations_type ) );
				$file_name                 = 'class-gateway-' .$payment_integrations_type. '.php';
				$logo                      = file_exists( PAYMOB_PLUGIN_PATH . '/assets/img/' . strtolower( $value['gateway_type'] ) . '.png' ) ?
					plugins_url( PAYMOB_PLUGIN_NAME ) . '/assets/img/' . strtolower( $value['gateway_type'] ) . '.png'
					: plugins_url( PAYMOB_PLUGIN_NAME ) . '/assets/img/paymob.png';

				$ordering = 2;
				$mode       = isset($value['mode'])?$value['mode'] :'test';
				// in case Apple Pay / Google Pay / Installment
				$applePay = strpos( $payment_integrations_type, 'apple-pay' );
				if ( false !== $applePay ) {
					$checkout_title = __( 'Apple Pay', 'paymob-for-woocommerce' );
					$checkout_desc  = __( 'Secure Payment Via Paymob Checkout', 'paymob-for-woocommerce' );
					$logo           = plugins_url( PAYMOB_PLUGIN_NAME ) . '/assets/img/applepay.png';
				}

				$googlePay = strpos( $payment_integrations_type, 'google-pay' );
				if ( false !== $googlePay ) {
					$checkout_title = __( 'Google Pay', 'paymob-for-woocommerce' );
					$checkout_desc  = __( 'Secure Payment Via Paymob Checkout', 'paymob-for-woocommerce' );
					$logo           = plugins_url( PAYMOB_PLUGIN_NAME ) . '/assets/img/googlepay.png';
				}

				$bankInstallments = strpos( $payment_integrations_type, 'bank-installments' );
				if ( false !== $bankInstallments ) {
					$checkout_title = __( 'Bank Installments', 'paymob-for-woocommerce' );
					$checkout_desc  = __( 'Split your payment into monthly installments', 'paymob-for-woocommerce' );
				}
				// End Apple Pay / Google Pay / Installment

				if ( false !== stripos( strtolower( $checkout_title ), 'card' ) ) {
					$ordering = 1;
				}

				if ( false !== stripos( strtolower( $checkout_title ), 'kiosk' ) ) {
					$ordering = 29;
				}

				$row_count = $wpdb->get_var(
					$wpdb->prepare(
						"SELECT COUNT(*) FROM {$wpdb->prefix}paymob_gateways WHERE gateway_id = %s",
						$payment_integrations_type
					)
				);

				if ( '0' === $row_count ) {
					$f_array = array(
						'class_name'           => $class_name,
						'gateway_id'           => $payment_integrations_type,
						'checkout_title'       => $checkout_title,
						'checkout_description' => $checkout_desc,
						'file_name'            => $file_name,
					);
					self::generate_files( $f_array );
					$wpdb->insert(
						$wpdb->prefix . 'paymob_gateways',
						array(
							'gateway_id'           => $payment_integrations_type,
							'file_name'            => $file_name,
							'class_name'           => sanitize_text_field( $class_name ),
							'checkout_title'       => sanitize_text_field( $checkout_title ),
							'checkout_description' => sanitize_text_field( $checkout_desc ),
							'integration_id'       => sanitize_text_field( $value['id'] ),
							'is_manual'            => '0',
							'ordering'             => $ordering,
							'mode'                 =>$mode
						)
					);

					$enabled = 'no'; // Default to 'no'.

					if ( 1 == $paymob_setting && $value['currency'] === get_woocommerce_currency() ) {
						$enabled = 'yes';
					} elseif ( 1 != $paymob_setting && self::check_enabled( $value['id'], $value['currency'] ) ) {
						$enabled = 'yes';
					}

					// Save default settings for the new gateway.
					$default_settings = array(
						'enabled'               => $enabled,
						'single_integration_id' => $value['id'],
						'title'                 => $checkout_title,
						'description'           => $checkout_desc,
						'logo'                  => $logo,
						'mode'                 =>$mode,
						
					);
					update_option( 'woocommerce_' . $payment_integrations_type . '_settings', $default_settings );
				}
			}

			// $existing_paymob_gateway = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}paymob_gateways WHERE gateway_id = %s", 'paymob' ), OBJECT );
			// // $integration_ids         = implode( ', ', array_unique( $ids ) ); // Combine unique IDs.
			// $integration_ids         = implode( ', ', array_unique($paymob_ids ) ); // Combine unique IDs.
			
			// if ( empty( $existing_paymob_gateway ) ) {
			// 	// Insert Paymob gateway only if it does not exist.
			// 	$wpdb->insert(
			// 		$wpdb->prefix . 'paymob_gateways',
			// 		array(
			// 			'gateway_id'           => 'paymob',
			// 			'file_name'            => 'class-gateway-paymob.php',
			// 			'class_name'           => 'Paymob',
			// 			'checkout_title'       => 'Pay with Paymob',
			// 			'checkout_description' => 'Pay with Paymob',
			// 			'integration_id'       => $integration_ids,
			// 			'is_manual'            => '0',
			// 			'ordering'             => 30,
			// 			'mode'                 =>$paymob_mode
			// 		)
			// 	);
			// } elseif ( $existing_paymob_gateway[0]->integration_id !== $integration_ids ) {
			// 		$wpdb->update(
			// 			$wpdb->prefix . 'paymob_gateways',
			// 			array(
			// 				'integration_id' => $integration_ids,
			// 				'ordering'       => 30,
			// 			),
			// 			array( 'gateway_id' => 'paymob' )
			// 		);
			// }
		}

		// Update the paymob_gateway_order option according to the ordering.
		self::update_paymob_gateway_order();
	}
	/**
	 * Updates the paymob_gateway_order option with the sorted gateway IDs.
	 */
	public static function update_paymob_gateway_order() {
		global $wpdb;
		// Fetch all gateways sorted by their ordering value.
		$gateways = $wpdb->get_results( "SELECT gateway_id FROM {$wpdb->prefix}paymob_gateways ORDER BY ordering ASC", ARRAY_A );
		// Extract gateway IDs.
		$gateway_order = array_column( $gateways, 'gateway_id' );
		// Update the paymob_gateway_order option.
		update_option( 'paymob_gateway_order', $gateway_order );
	}
	/**
	 * Checks if the given integration ID is enabled for the current currency.
	 *
	 * @param int    $id       The integration ID to check.
	 * @param string $currency The currency to check against.
	 *
	 * @return bool True if the integration ID is enabled, false otherwise.
	 */
	public static function check_enabled( $id, $currency ) {
		$options                 = get_option( 'woocommerce_paymob_settings', array() );
		$selected_integration_id = isset( $options['integration_id'] ) ? $options['integration_id'] : '';
		if ( ! empty( $selected_integration_id ) ) {
			foreach ( $selected_integration_id as $integration_id ) {
				if ( $id == $integration_id && get_woocommerce_currency() === $currency ) {
					return true;
				}
			}
		}
		// If none of the IDs match, return false
		return false;
	}
	/**
	 * Returns an array of integration IDs.
	 *
	 * @return array
	 */
	public static function get_integration_ids() {
		$integration_ids = array();
		if ( ( Paymob::filterVar( 'section' ) ) && Paymob::filterVar( 'section' ) !== 'paymob' ) {
			$integration_ids = array(
				'' => __( 'Select an Integration ID', 'paymob-for-woocommerce' ),
			);
		}
		$paymob_options = get_option( 'woocommerce_paymob_settings' );
		$mainOptions = get_option('woocommerce_paymob-main_settings');
		$paymobReq = new Paymob( Paymob::debug_flag(), Paymob::log_dir() . 'paymob-auth.log' );
		$mode = $paymobReq->getMode( $paymob_options['sec_key'] );

		if ( isset( $paymob_options['integration_id_hidden'] ) && ! empty( $paymob_options['integration_id_hidden'] ) ) {
			$integration_id_hidden = explode( ',', $paymob_options['integration_id_hidden'] );
			foreach ( $integration_id_hidden as $entry ) {
				if (stripos($entry, $mode) !== false) {
					$parts = explode( ':', $entry );
					// Check if parts are set correctly.
					if ( count( $parts ) < 3 ) {
						continue; // Skip this entry if it doesn't have enough parts.
					}
					$id = trim( $parts[0] );
					if ( ( Paymob::filterVar( 'section' ) ) && 'paymob' === Paymob::filterVar( 'section' ) ) {
						if ( ! empty( trim( $entry ) ) ) {
							list($id, $label)               = explode( ' : ', trim( $entry ), 2 );
							$integration_ids[ trim( $id ) ] = trim( $id . ' : ' . $label );
						}
					} else {
						$integration_ids[ $id ] = $id;
					}
				}
			}
		}
		return $integration_ids;
	}

	public static function get_valu_integration_ids() {
		global $wpdb;
		$integration_ids = array();
	
		if ((Paymob::filterVar('section')) && Paymob::filterVar('section') !== 'paymob') {
			$integration_ids = array(
				'' => __('Select an Integration ID', 'paymob-for-woocommerce'),
			);
		}
	
		$paymob_options = get_option('woocommerce_paymob_settings');
		$paymobReq = new Paymob( Paymob::debug_flag(), Paymob::log_dir() . 'paymob-auth.log' );
		$mode = $paymobReq->getMode($paymob_options['sec_key']); // Get the current mode (live/test)
	
		if (isset($paymob_options['integration_id_hidden']) && !empty($paymob_options['integration_id_hidden'])) {
			$integration_id_hidden = explode(',', $paymob_options['integration_id_hidden']);
			$integration_ids = []; // Initialize array
	
			// ✅ Fetch all ValU options once
			$valuOptions = $wpdb->get_results("SELECT option_value FROM {$wpdb->options} WHERE option_name LIKE '%-valu%'");
	
			$enabled_integration_ids = [];
	
			// ✅ Process ValU settings first
			foreach ($valuOptions as $option) {
				$settings = maybe_unserialize($option->option_value);
	
				if (
					isset($settings['enabled']) && $settings['enabled'] === 'yes' &&  // Check if enabled
					isset($settings['single_integration_id']) &&                      // Check if integration ID exists
					isset($settings['mode']) && strtolower($settings['mode']) === strtolower($mode) // Match mode
				) {
					$enabled_integration_ids[] = (string) $settings['single_integration_id']; // Store as string
				}
			}
	
			// ✅ Now loop over integration_id_hidden and match enabled IDs & mode
			foreach ($integration_id_hidden as $entry) {
				$parts = explode(' : ', $entry);
	
				if (count($parts) < 3) {
					continue; // Skip invalid entries
				}
	
				$id = trim($parts[0]); // Extract integration ID
				$label = trim($parts[1]); // Extract label
	
				// Ensure closing parenthesis
				if (substr($label, -1) !== ')') {
					$label .= ' )'; // Append ")" if not present
				}
	
				// ✅ Check if the integration ID exists in enabled ValU options & matches mode
				if (in_array($id, $enabled_integration_ids, true)) {
					$integration_ids[$id] = $id . ' : ' . $label; // Store in associative array
				}
			}
		}
	
		return $integration_ids;
	}
	
	
	/**
	 * Returns the count of enabled gateways and updates the Paymob settings with the new title.
	 *
	 * @param array $gateways The array of gateways.
	 *
	 * @return bool True if the option is updated, false otherwise.
	 */
	public static function enabled_gateways_count( $gateways ) {
		$title = null;
		foreach ( $gateways as $gateway ) {
			$options = get_option( 'woocommerce_' . $gateway->gateway_id . '_settings', array() );
			if($options)
			{
				if ( 'yes' === $options['enabled'] && isset( $options['title'] ) ) {
					$title .= ucwords( $options['title'] ) . ', ';
				}
			}
			
		}

		if ( empty( $title ) ) {
			$title = 'At least one Payment Method should be enabled below Paymob.';
		} else {
			$title = 'Payment Methods (' . substr( $title, 0, -2 ) . ')';
		}
		$paymob_settings = get_option( 'woocommerce_paymob-main_settings', array() );
		// Merge the new title with the existing settings.
		$paymob_settings['title'] = $title;
		// Update the Paymob settings with the new title.
		return update_option( 'woocommerce_paymob-main_settings', $paymob_settings );
	}
	/** @var array|null Request-level cache for paymob_gateways rows. */
	private static $db_gateways_cache = null;

	/**
	 * Retrieves the gateways data from the database.
	 *
	 * @return array The gateways data.
	 */
	public static function get_db_gateways_data() {
		if ( null !== self::$db_gateways_cache ) {
			return self::$db_gateways_cache;
		}

		global $wpdb;
		self::$db_gateways_cache = $wpdb->get_results( 'SELECT * FROM ' . $wpdb->prefix . 'paymob_gateways', OBJECT );

		if ( ! is_array( self::$db_gateways_cache ) ) {
			self::$db_gateways_cache = array();
		}

		return self::$db_gateways_cache;
	}

	/**
	 * Clear cached gateway rows after DB writes (add/delete/toggle gateway).
	 */
	public static function flush_db_gateways_cache() {
		self::$db_gateways_cache = null;
	}
	/**
	 * Disables the single integration ID field based on the gateway settings.
	 *
	 * @return array The field settings.
	 */
	public static function disable_single_integration_id_field() {
		global $current_section, $wpdb;

		// Fetch the result.
		$existing_paymob_gateway = $wpdb->get_results(
			$wpdb->prepare( "SELECT is_manual FROM {$wpdb->prefix}paymob_gateways WHERE gateway_id = %s", $current_section ),
			OBJECT
		);

		// Check if the result is not empty and determine the type and custom attributes.
		if ( isset( $existing_paymob_gateway[0]->is_manual ) && ! empty( $existing_paymob_gateway ) && '0' === $existing_paymob_gateway[0]->is_manual ) {
			$title             = '';
			$type              = 'hidden';
			$custom_attributes = array( 'required' => 'required' );
		} else {
			$title             = __( 'Paymob Integration ID', 'paymob-for-woocommerce' );
			$type              = 'select';
			$custom_attributes = array( 'required' => 'required' );
		}

		// Return an array with the type and custom attributes.
		return array(
			'title'             => $title,
			'type'              => $type,
			'custom_attributes' => $custom_attributes,
		);
	}
	/**
	 * Registers the framework with Paymob.
	 *
	 * @param array $ids The IDs of the integrations to register.
	 */
	public static function register_framework( $ids ) {
		try {
			$paymob_options = get_option( 'woocommerce_paymob-main_settings' );
			$debug          = isset( $paymob_options['debug'] ) ? sanitize_text_field( $paymob_options['debug'] ) : '0';

				$debug   = 'yes' === $debug ? '1' : '0';
				$sec_key = isset( $paymob_options['sec_key'] ) ? $paymob_options['sec_key'] : '';
				$add_log = Paymob::log_dir() . 'paymob-auth.log';
				// Re-index after array_unique so JSON encodes a list [], not a dict {}.
				// Paymob rejects: Expected a list of items but got type "dict".
				$unique_ids = array_values(
					array_map(
						'intval',
						array_filter(
							(array) $ids,
							static function ( $id ) {
								return '' !== $id && null !== $id;
							}
						)
					)
				);
				$unique_ids = array_values( array_unique( $unique_ids ) );
				$paymob_req = new Paymob( $debug, Paymob::log_dir() . 'paymob-auth.log' );
				$data       = array(
					'url'                   => get_site_url(),
					'is_ssl'                => is_ssl(),
					'platform'              => 'WORDPRESS',
					'platform_version'      => defined( 'WC_VERSION' ) ? constant( 'WC_VERSION' ) : ( function_exists( 'WC' ) && WC() ? WC()->version : '' ),
					'plugin_version'        => PAYMOB_VERSION,
					'selected_integrations' => $unique_ids, // JSON list of ints.
					'info'                  => array(),
				);
				Paymob::addLogs( $debug, $add_log, 'Register Framework Request Data ', $data );
				$paymob_req->registerFramework( $sec_key, $data );
		} catch ( \Exception $e ) {
			WC_Admin_Settings::add_error( $e->getMessage() );
		}
	}
	/**
	 * Customize the Gateways Method Title.
	 */
	public static function gateways_method_title( $method_title, $setting, $single_integration_id = null ) {
		if ( ! empty( $single_integration_id ) ) {
			echo '<h2>' . esc_html__( 'Edit Payment Method - ', 'paymob-for-woocommerce' ) . esc_html( $method_title ) . ' ( ' . esc_html( $single_integration_id ) . ' ) ' .
			'<small class="wc-admin-breadcrumb"><a href="' . esc_url( admin_url( 'admin.php?page=wc-settings&tab=checkout' ) ) . '" aria-label="' . esc_attr__( 'Return to payments', 'paymob-for-woocommerce' ) . '">⤴︎</a></small></h2>';
		} else {
			echo '<h2>' . esc_html__( 'Edit Payment Method - ', 'paymob-for-woocommerce' ) . esc_html( $method_title ) .
			'<small class="wc-admin-breadcrumb"><a href="' . esc_url( admin_url( 'admin.php?page=wc-settings&tab=checkout' ) ) . '" aria-label="' . esc_attr__( 'Return to payments', 'paymob-for-woocommerce' ) . '">⤴︎</a></small></h2>';
		}
		echo '<table class="form-table">';
		$setting->generate_settings_html();
		echo '</table>';
	}
	/**
	 * Pixel Gateway Integration IDs.
	 */
	public static function get_pixel_integration_ids($name) {
		$integration_ids = array();
		if (isset($name) &&(stripos($name, 'apple') !== false || stripos($name, 'google') !== false )) {
			$integration_ids = array(
				'' => __( 'Select an Integration ID', 'paymob-for-woocommerce' ),
			);
			
		}
		$paymob_options = get_option('woocommerce_paymob_settings');
		$mainOptions = get_option('woocommerce_paymob-main_settings');
		$mode       = !empty($mainOptions['mode'])?$mainOptions['mode'] : 'test';

		if (isset($paymob_options['integration_id_hidden']) && !empty($paymob_options['integration_id_hidden'])) {
			$integration_id_hidden = explode(',', $paymob_options['integration_id_hidden']);

			foreach ($integration_id_hidden as $entry) {
				if ((stripos($entry, $mode) !== false) ) {
					$parts = explode(':', $entry);
					// Check if parts are set correctly.
					if (count($parts) < 3) {
						continue; // Skip this entry if it doesn't have enough parts.
					}
						$id = trim($parts[0]);
						$label = trim($parts[1]);
						$currency = trim($parts[2]);
						$mode = !empty($parts[3])?trim($parts[3]):$mode;
						if (strpos(strtolower($label), strtolower($name)) !== false && strpos(strtolower($label), 'bank-installments') === false &&  strpos(strtolower($label), 'apple_pay') === false &&  strpos(strtolower($label), 'google-pay') === false) {
							$integration_ids[strtolower($id)] = strtolower($id) . ' : ' . strtolower($label).' : '. $currency.' : '.$mode;
						}

						if (isset($name) &&(stripos($name, 'apple') !== false || stripos($name, 'google') !== false )  && strpos($label, $name) !== false) {
							$integration_ids = array(
								'' => __( 'Select an Integration ID', 'paymob-for-woocommerce' ),
							);
							$integration_ids[ $id ] = $id;
						}
				}
			}
		}
		return $integration_ids;
	}

	/**
	 * Bank installment integration IDs for Paymob Pixel settings.
	 *
	 * @param bool $include_placeholder Whether to prepend an empty "Select an Integration ID" option.
	 * @return array<string, string>
	 */
	public static function get_pixel_bank_installment_integration_ids( $include_placeholder = false ) {
		$integration_ids = array();
		if ( $include_placeholder ) {
			$integration_ids[''] = __( 'Select an Integration ID', 'paymob-for-woocommerce' );
		}

		$paymob_options = get_option( 'woocommerce_paymob_settings' );
		$main_options   = get_option( 'woocommerce_paymob-main_settings' );
		$mode           = ! empty( $main_options['mode'] ) ? $main_options['mode'] : 'test';

		if ( empty( $paymob_options['integration_id_hidden'] ) ) {
			return $integration_ids;
		}

		foreach ( explode( ',', (string) $paymob_options['integration_id_hidden'] ) as $entry ) {
			if ( stripos( $entry, $mode ) === false ) {
				continue;
			}

			$parts = explode( ':', $entry );
			if ( count( $parts ) < 3 ) {
				continue;
			}

			$id         = trim( $parts[0] );
			$label      = trim( $parts[1] );
			$currency   = trim( $parts[2] );
			$entry_mode = ! empty( $parts[3] ) ? trim( $parts[3] ) : $mode;

			if ( '' === $id || false === stripos( $label, 'bank-installments' ) ) {
				continue;
			}

			$integration_ids[ $id ] = $id . ' : ' . strtolower( $label ) . ' : ' . $currency . ' : ' . $entry_mode;
		}

		return $integration_ids;
	}

	/**
	 * Whether the connected Paymob account is Egypt.
	 *
	 * @return bool
	 */
	public static function is_paymob_egypt_merchant() {
		$country = strtolower( (string) get_option( 'woocommerce_paymob_country', '' ) );
		if ( 'egy' === $country ) {
			return true;
		}

		$main_options = get_option( 'woocommerce_paymob-main_settings', array() );
		$sec_key      = isset( $main_options['sec_key'] ) ? (string) $main_options['sec_key'] : '';
		$pub_key      = isset( $main_options['pub_key'] ) ? (string) $main_options['pub_key'] : '';
		$key          = '' !== $sec_key ? $sec_key : $pub_key;

		if ( '' === $key || ! class_exists( 'Paymob' ) ) {
			return false;
		}

		return 'egy' === strtolower( (string) Paymob::getCountryCode( $key ) );
	}

	public static function get_pixel_integration_ids_all($name) {
		$integration_ids = array();
	
		// Default option for Apple Pay and Google Pay
		if (isset($name) && (strtolower($name) == 'apple_pay' || strtolower($name) == 'google-pay')) {
			$integration_ids = array(
				'' => __('Select an Integration ID', 'paymob-for-woocommerce'),
			);
		}
	
		$paymob_options = get_option('woocommerce_paymob-main_settings');
	
		if (isset($paymob_options['integration_id_hidden']) && !empty($paymob_options['integration_id_hidden'])) {
			$integration_id_hidden = explode(',', $paymob_options['integration_id_hidden']);
	
			foreach ($integration_id_hidden as $entry) {
				$parts = explode(':', $entry);
	
				// Ensure the entry has enough parts
				if (count($parts) < 3) {
					continue;
				}
	
				$id = trim($parts[0]);
				$label = trim($parts[1]);
				$currency = trim($parts[2]);
	
				// Match the name and exclude specific cases
				if (strpos(strtolower($label), strtolower($name)) !== false && 
					strpos(strtolower($label), 'bank-installments') === false && 
					strpos(strtolower($label), 'apple_pay') === false && 
					strpos(strtolower($label), 'google-pay') === false) {
					$integration_ids[strtolower($id)] = strtolower($id) . ' : ' . strtolower($label) . ' : ' . $currency;
				}
	
				// Special case for Apple Pay and Google Pay
				if (isset($name) && (strtolower($name) == 'apple_pay' || strtolower($name) == 'google-pay') && strpos($label, $name) !== false) {
					$integration_ids = array(
						'' => __('Select an Integration ID', 'paymob-for-woocommerce'),
					);
					$integration_ids[$id] = $id;
				}
			}
		}
	
		return $integration_ids;
	}

		public static function get_moto_integration_ids() {
		return self::filter_integration_ids('MOTO');
	}

	public static function get_ds3_integration_ids() {
		return self::filter_integration_ids('3DS');
	}

	private static function filter_integration_ids($type) {
		$integration_ids = array(
			'' => __( 'Select an Integration ID', 'paymob-for-woocommerce' ),
		);

		$paymob_options = get_option('woocommerce_paymob_settings');
		$mainOptions = get_option('woocommerce_paymob-main_settings');
		$mode       = !empty($mainOptions['mode']) ? $mainOptions['mode'] : 'test';

		if (!empty($paymob_options['integration_id_hidden'])) {
			$entries = explode(',', $paymob_options['integration_id_hidden']);

			foreach ($entries as $entry) {
				if (stripos($entry, $mode) === false) {
					continue;
				}

				$parts = explode(':', $entry, 2);
				if (count($parts) < 2) {
					continue;
				}

				$id    = trim($parts[0]);
				$label = trim($parts[1]);

				// ✅ Fix: Get the last ( ) match, not the first one
				preg_match_all('/\((.*?)\)/', $label, $matches_all);
				$match_string = end($matches_all[1]);

				if (!$match_string) {
					continue;
				}

				$details = array_map('trim', explode(':', $match_string));

				if (count($details) < 5) {
					continue;
				}

				list($type_label, $currency, $entry_mode, $moto, $three_ds) = array_map('strtolower', $details);

				if (
					($type == 'MOTO' && $moto == 'yes') ||
					($type == '3DS'  && $three_ds == 'yes')
				) {
					// Capitalize each detail part
					$formatted_type   = ucwords($type_label);
					$formatted_curr   = strtoupper($currency); // Currency typically all caps
					$formatted_mode   = ucfirst($entry_mode);

					$formatted_label = "$id : ($formatted_type : $formatted_curr : $formatted_mode)";
					$integration_ids[$id] = $formatted_label;
				}
			}
		}

		if (count($integration_ids) == 2) {
			unset($integration_ids['']);
			return $integration_ids;
		} else {
			return $integration_ids;
		}
	}
	
}
