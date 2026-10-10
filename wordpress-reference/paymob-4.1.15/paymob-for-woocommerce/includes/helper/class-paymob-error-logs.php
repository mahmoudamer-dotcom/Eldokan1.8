<?php
/**
 * Backward-compatible loader for Paymob_Error_Logs.
 *
 * Canonical implementation lives under includes/admin/paymob-error-logs/.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Paymob_Error_Logs' ) ) {
	require_once dirname( __DIR__ ) . '/admin/paymob-error-logs/class-paymob-error-logs.php';
}
