<?php
/**
 * Paymob stored error logs (admin UI + persistence).
 *
 * Path: wp-content/plugins/paymob-for-woocommerce/includes/admin/paymob-error-logs/
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Paymob_Error_Logs {

	const OPTION_KEY = 'paymob_error_logs';
	const MAX_ITEMS  = 300;
	const MENU_SLUG  = 'paymob-error-logs';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
		add_action( 'wp_ajax_paymob_log_error', array( __CLASS__, 'ajax_log_error' ) );
		add_action( 'admin_post_paymob_clear_error_logs', array( __CLASS__, 'clear_logs' ) );
		add_action( 'admin_post_paymob_export_error_logs', array( __CLASS__, 'export_logs' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
	}

	public static function add( $message, $context = 'general', $source = 'system', $meta = array() ) {
		$message = sanitize_text_field( (string) $message );
		if ( '' === $message ) {
			return;
		}

		$logs = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $logs ) ) {
			$logs = array();
		}

		$clean_meta = array();
		if ( is_array( $meta ) ) {
			foreach ( $meta as $k => $v ) {
				$key = sanitize_key( (string) $k );
				if ( 'response_raw' === $key ) {
					$txt = sanitize_textarea_field( (string) $v );
					if ( function_exists( 'mb_substr' ) ) {
						$txt = mb_substr( $txt, 0, 24000 );
					} else {
						$txt = substr( $txt, 0, 24000 );
					}
					$clean_meta[ $key ] = $txt;
				} else {
					$clean_meta[ $key ] = sanitize_text_field( (string) $v );
				}
			}
		}

		$logs[] = array(
			'time'    => current_time( 'mysql' ),
			'message' => $message,
			'context' => sanitize_key( $context ),
			'source'  => sanitize_key( $source ),
			'meta'    => $clean_meta,
		);

		if ( count( $logs ) > self::MAX_ITEMS ) {
			$logs = array_slice( $logs, - self::MAX_ITEMS );
		}

		update_option( self::OPTION_KEY, $logs, false );
	}

	public static function get_logs() {
		$logs = get_option( self::OPTION_KEY, array() );
		return is_array( $logs ) ? $logs : array();
	}

	public static function get_filters_from_request() {
		return array(
			'source'    => isset( $_GET['source'] ) ? sanitize_key( wp_unslash( $_GET['source'] ) ) : '',
			'context'   => isset( $_GET['context'] ) ? sanitize_key( wp_unslash( $_GET['context'] ) ) : '',
			'http_code' => isset( $_GET['http_code'] ) ? sanitize_text_field( wp_unslash( $_GET['http_code'] ) ) : '',
			'search'    => isset( $_GET['search'] ) ? sanitize_text_field( wp_unslash( $_GET['search'] ) ) : '',
			'date_from' => isset( $_GET['date_from'] ) ? sanitize_text_field( wp_unslash( $_GET['date_from'] ) ) : '',
			'date_to'   => isset( $_GET['date_to'] ) ? sanitize_text_field( wp_unslash( $_GET['date_to'] ) ) : '',
		);
	}

	public static function get_distinct_values( $field ) {
		$values = array();
		foreach ( self::get_logs() as $log ) {
			if ( isset( $log[ $field ] ) && '' !== (string) $log[ $field ] ) {
				$values[ (string) $log[ $field ] ] = (string) $log[ $field ];
			}
		}
		ksort( $values );
		return array_values( $values );
	}

	public static function get_filtered_logs( $filters ) {
		$logs      = self::get_logs();
		$search    = strtolower( (string) $filters['search'] );
		$date_from = ! empty( $filters['date_from'] ) ? strtotime( $filters['date_from'] . ' 00:00:00' ) : 0;
		$date_to   = ! empty( $filters['date_to'] ) ? strtotime( $filters['date_to'] . ' 23:59:59' ) : 0;
		$filtered  = array();

		foreach ( $logs as $log ) {
			$meta      = isset( $log['meta'] ) && is_array( $log['meta'] ) ? $log['meta'] : array();
			$time      = isset( $log['time'] ) ? (string) $log['time'] : '';
			$source    = isset( $log['source'] ) ? (string) $log['source'] : '';
			$context   = isset( $log['context'] ) ? (string) $log['context'] : '';
			$http_code = isset( $meta['http_code'] ) ? (string) $meta['http_code'] : '';

			if ( '' !== $filters['source'] && $source !== $filters['source'] ) {
				continue;
			}
			if ( '' !== $filters['context'] && $context !== $filters['context'] ) {
				continue;
			}
			if ( '' !== $filters['http_code'] && $http_code !== $filters['http_code'] ) {
				continue;
			}
			if ( $date_from || $date_to ) {
				$created_ts = strtotime( $time );
				if ( $date_from && $created_ts < $date_from ) {
					continue;
				}
				if ( $date_to && $created_ts > $date_to ) {
					continue;
				}
			}
			if ( '' !== $search ) {
				$encoded  = wp_json_encode( $log );
				$haystack = strtolower( false === $encoded ? '' : $encoded );
				if ( false === strpos( $haystack, $search ) ) {
					continue;
				}
			}

			$filtered[] = $log;
		}

		return $filtered;
	}

	/**
	 * Log a Paymob API HTTP outcome with the raw curl body (before JSON decode).
	 *
	 * @param string $summary    Short summary for the card title.
	 * @param string $api_path   Request URL/path.
	 * @param string $method     HTTP verb.
	 * @param int    $http_code  Response code from curl.
	 * @param string $raw_body   Exact body returned by curl_exec().
	 */
	public static function log_http_raw_response( $summary, $api_path, $method, $http_code, $raw_body ) {
		self::add(
			$summary,
			'api_http',
			'http',
			array(
				'request_url'    => (string) $api_path,
				'request_method' => (string) $method,
				'http_code'      => (string) (int) $http_code,
				'response_raw'   => (string) $raw_body,
			)
		);
	}

	public static function register_menu() {
		add_submenu_page(
			'woocommerce',
			__( 'Paymob Error Logs', 'paymob-for-woocommerce' ),
			__( 'Paymob Error Logs', 'paymob-for-woocommerce' ),
			'manage_woocommerce',
			self::MENU_SLUG,
			array( __CLASS__, 'render_page' )
		);
	}

	public static function enqueue_assets( $hook ) {
		if ( 'woocommerce_page_' . self::MENU_SLUG !== $hook ) {
			return;
		}

		$asset_path = PAYMOB_PLUGIN_PATH . 'assets/css/paymob-error-logs.css';
		wp_enqueue_style(
			'paymob-error-logs-style',
			plugins_url( PAYMOB_PLUGIN_NAME . '/assets/css/paymob-error-logs.css' ),
			array(),
			file_exists( $asset_path ) ? PAYMOB_VERSION . '-' . filemtime( $asset_path ) : PAYMOB_VERSION
		);
	}

	public static function render_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$filters  = self::get_filters_from_request();
		$logs     = array_reverse( self::get_filtered_logs( $filters ) );
		$sources  = self::get_distinct_values( 'source' );
		$contexts = self::get_distinct_values( 'context' );
		include __DIR__ . '/views/view-paymob-error-logs.php';
	}

	public static function ajax_log_error() {
		check_ajax_referer( 'paymob_log_error_nonce', 'nonce' );
		if ( ! isset( $_POST['message'] ) ) {
			wp_send_json_error( array( 'message' => 'missing message' ) );
		}

		$message = sanitize_text_field( wp_unslash( $_POST['message'] ) );
		$context = isset( $_POST['context'] ) ? sanitize_text_field( wp_unslash( $_POST['context'] ) ) : 'frontend';
		self::add( $message, $context, 'frontend' );
		wp_send_json_success();
	}

	public static function clear_logs() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Permission denied', 'paymob-for-woocommerce' ) );
		}

		check_admin_referer( 'paymob_clear_error_logs' );
		delete_option( self::OPTION_KEY );
		wp_safe_redirect( admin_url( 'admin.php?page=' . self::MENU_SLUG . '&cleared=1' ) );
		exit;
	}

	public static function export_logs() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Permission denied', 'paymob-for-woocommerce' ) );
		}

		check_admin_referer( 'paymob_export_error_logs' );

		$filters  = self::get_filters_from_request();
		$logs     = array_reverse( self::get_filtered_logs( $filters ) );
		$filename = 'paymob-error-logs-' . gmdate( 'Ymd-His' ) . '.csv';

		nocache_headers();
		header( 'Content-Type: text/csv; charset=UTF-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );

		$output = fopen( 'php://output', 'w' );
		fputcsv( $output, array( 'time', 'source', 'context', 'message', 'request_method', 'http_code', 'request_url', 'response_raw' ), ',', '"', '\\' );

		foreach ( $logs as $log ) {
			$meta = isset( $log['meta'] ) && is_array( $log['meta'] ) ? $log['meta'] : array();
			fputcsv(
				$output,
				array(
					isset( $log['time'] ) ? $log['time'] : '',
					isset( $log['source'] ) ? $log['source'] : '',
					isset( $log['context'] ) ? $log['context'] : '',
					isset( $log['message'] ) ? $log['message'] : '',
					isset( $meta['request_method'] ) ? $meta['request_method'] : '',
					isset( $meta['http_code'] ) ? $meta['http_code'] : '',
					isset( $meta['request_url'] ) ? $meta['request_url'] : '',
					isset( $meta['response_raw'] ) ? $meta['response_raw'] : '',
				),
				',',
				'"',
				'\\'
			);
		}

		fclose( $output ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- php://output stream for CSV export.
		exit;
	}
}

Paymob_Error_Logs::init();
