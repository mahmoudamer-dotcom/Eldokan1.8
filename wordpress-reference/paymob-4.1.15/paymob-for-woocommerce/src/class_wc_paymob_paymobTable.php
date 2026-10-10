<?php
/**
 * Paymob Gateway Table
 */
class WC_Paymob_Tables {

	const TABLES_VERIFIED_TRANSIENT = 'paymob_tables_verified';

	public static function maybe_ensure_tables() {
		if ( get_transient( self::TABLES_VERIFIED_TRANSIENT ) ) {
			return;
		}

		self::create_paymob_gateways_table();
		self::update_paymob_gateways_table();
		self::create_paymob_pixel_table();

		set_transient( self::TABLES_VERIFIED_TRANSIENT, 1, DAY_IN_SECONDS );
	}

	public static function flush_tables_verified_cache() {
		delete_transient( self::TABLES_VERIFIED_TRANSIENT );
	}

	public static function create_paymob_gateways_table() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();
		$sql             = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}paymob_gateways (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            gateway_id varchar(100) NOT NULL,
            file_name varchar(100) DEFAULT '' NOT NULL,
            class_name varchar(100) DEFAULT '' NOT NULL,
            checkout_title varchar(100) DEFAULT '' NOT NULL,
            checkout_description LONGTEXT DEFAULT '' NOT NULL,
            integration_id varchar(3000) DEFAULT '' NOT NULL,
            is_manual varchar(56) DEFAULT '' NOT NULL,
			mode varchar(10) DEFAULT 'test' NOT NULL,
            ordering int(10) DEFAULT 0 NOT NULL,
            PRIMARY KEY (id),
            KEY gateway_id (gateway_id),
            UNIQUE (gateway_id)
        ) $charset_collate;";
		dbDelta( $sql ); 
		
		$sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}paymob_cards_token (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			user_id bigint(20) NOT NULL,
			token varchar(56) DEFAULT '' NOT NULL,
			masked_pan varchar(19) DEFAULT '' NOT NULL,
			card_subtype varchar(56) DEFAULT '' NOT NULL,
			PRIMARY KEY  (id),
			KEY user_id (user_id)
		) $charset_collate;";
		dbDelta( $sql );
	}

	public static function update_paymob_gateways_table() {
		global $wpdb;
 
		// Check if the column exists
		$column_exists = $wpdb->get_var("SHOW COLUMNS FROM `{$wpdb->prefix}paymob_gateways` LIKE 'mode'");
		
		if ( empty( $column_exists ) ) {
			// Add the column if it does not exist.
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- DDL migration; table name uses trusted $wpdb->prefix.
			$wpdb->query( "ALTER TABLE `{$wpdb->prefix}paymob_gateways` ADD `mode` VARCHAR(10) NOT NULL DEFAULT 'test'" );
		}
	}

	public static function create_paymob_pixel_table() {
		global $wpdb;
	
		$charset_collate = $wpdb->get_charset_collate();
		
		// SQL for creating the 'paymob_pixel_intentions' table
		$sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}paymob_pixel_intentions (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			pixel_identifier varchar(255) NOT NULL COMMENT 'Session ID with timestamp',
			merchant_order_id bigint(20) DEFAULT NULL COMMENT 'WooCommerce Order ID',
			response_cs varchar(255) NOT NULL COMMENT 'Response Create Intention Shared Key',
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Record creation timestamp',
			PRIMARY KEY (id),
			KEY pixel_identifier (pixel_identifier),
			KEY merchant_order_id (merchant_order_id),
			KEY created_at (created_at)
		) $charset_collate;";
		
		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
		dbDelta($sql);
	}
	
	
}

