<?php
if (!defined('ABSPATH')) { exit; }

/** Trusted WP-CLI process only. No Customer API registration or force path. */
final class ElDokan_Customer_API_Checkout_CLI {
    public static function register() {
        if (defined('WP_CLI') && WP_CLI && class_exists('WP_CLI')) {
            WP_CLI::add_command('eldokan checkout-attempt', __CLASS__);
        }
    }
    private function run($args,$assoc_args,$reconcile) {
        if (count($args)!==1 || $assoc_args) { WP_CLI::error('Provide exactly one chk_*; flags and force operations are not supported.'); return; }
        $result = $reconcile ? ElDokan_Customer_API_Checkout_Recovery::reconcile($args[0]) : ElDokan_Customer_API_Checkout_Recovery::inspect($args[0]);
        if (is_wp_error($result)) { WP_CLI::error($result->get_error_code().': '.$result->get_error_message()); return; }
        WP_CLI::line(wp_json_encode($result));
        if (!in_array($result['state'],['completed','recoverable'],true)) { WP_CLI::halt(2); }
    }
    /**
     * Read-only durable inspection.
     *
     * ## OPTIONS
     *
     * <attempt>
     * : Checkout attempt ID.
     */
    public function inspect($args,$assoc_args) { $this->run($args,$assoc_args,false); }
    /**
     * Reconcile only a proven committed Order; never create or replay.
     *
     * ## OPTIONS
     *
     * <attempt>
     * : Checkout attempt ID.
     */
    public function reconcile($args,$assoc_args) { $this->run($args,$assoc_args,true); }
}
