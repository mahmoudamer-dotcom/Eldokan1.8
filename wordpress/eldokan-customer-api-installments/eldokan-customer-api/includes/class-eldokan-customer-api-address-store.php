<?php
if (!defined('ABSPATH')) {
    exit;
}

/** Private address books. Each customer book is one atomic compare-and-swap row. */
final class ElDokan_Customer_API_Address_Store {
    const SCHEMA_VERSION = '1';
    const SCHEMA_OPTION = 'eldokan_customer_api_address_schema';
    const MAX_ATTEMPTS = 8;

    private static function table() {
        global $wpdb;
        return $wpdb->prefix . 'eldokan_customer_address_books';
    }

    public static function maybe_upgrade() {
        if ((string) get_option(self::SCHEMA_OPTION, '') !== self::SCHEMA_VERSION) {
            self::install();
        }
    }

    public static function install() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $table = self::table();
        $charset = $wpdb->get_charset_collate();
        // No drop, truncate, foreign key to Orders, or migration of existing user metadata.
        dbDelta("CREATE TABLE {$table} (
            customer_id bigint(20) unsigned NOT NULL,
            revision bigint(20) unsigned NOT NULL DEFAULT 0,
            addresses_json longtext NOT NULL,
            PRIMARY KEY  (customer_id)
        ) ENGINE=InnoDB {$charset};");
        $columns = $wpdb->get_col("SHOW COLUMNS FROM `{$table}`", 0);
        $indexes = $wpdb->get_results("SHOW INDEX FROM `{$table}`", ARRAY_A);
        $primary = array_values(array_filter((array) $indexes, static function ($index) {
            return ($index['Key_name'] ?? '') === 'PRIMARY';
        }));
        if (!array_diff(['customer_id', 'revision', 'addresses_json'], (array) $columns)
            && count($primary) === 1 && ($primary[0]['Column_name'] ?? '') === 'customer_id'
            && (int) ($primary[0]['Non_unique'] ?? 1) === 0 && empty($primary[0]['Sub_part'])) {
            update_option(self::SCHEMA_OPTION, self::SCHEMA_VERSION, false);
        }
    }

    private static function unavailable() {
        return new WP_Error('address_storage_unavailable', 'The Address Book is temporarily unavailable.', ['status' => 503]);
    }

    public static function read($customer_id) {
        global $wpdb;
        if ((string) get_option(self::SCHEMA_OPTION, '') !== self::SCHEMA_VERSION) {
            return self::unavailable();
        }
        $previous = $wpdb->suppress_errors(true);
        try {
            $row = $wpdb->get_row($wpdb->prepare(
                'SELECT revision, addresses_json FROM ' . self::table() . ' WHERE customer_id = %d',
                $customer_id
            ), ARRAY_A);
            if ($wpdb->last_error) {
                return self::unavailable();
            }
            if (!$row) {
                return ['revision' => 0, 'items' => []];
            }
            $items = json_decode($row['addresses_json'], true);
            if (!is_array($items) || !is_array(json_decode($row['addresses_json'])) || array_values($items) !== $items) {
                return self::unavailable();
            }
            $ids = [];
            $defaults = 0;
            foreach ($items as $item) {
                if (!is_array($item) || array_diff(array_merge(['id'], ElDokan_Customer_API_Addresses::FIELDS), array_keys($item))
                    || !is_string($item['id']) || !preg_match('/^adr_[a-f0-9]{64}$/D', $item['id'])
                    || !is_bool($item['is_default']) || isset($ids[$item['id']])) {
                    return self::unavailable();
                }
                $ids[$item['id']] = true;
                $defaults += $item['is_default'] ? 1 : 0;
            }
            if ($defaults > 1 || count($items) > ElDokan_Customer_API_Addresses::MAX_ADDRESSES) {
                return self::unavailable();
            }
            return ['revision' => (int) $row['revision'], 'items' => $items];
        } finally {
            $wpdb->suppress_errors($previous);
        }
    }

    public static function mutate($customer_id, $callback) {
        global $wpdb;
        if ((string) get_option(self::SCHEMA_OPTION, '') !== self::SCHEMA_VERSION) {
            return self::unavailable();
        }
        $previous = $wpdb->suppress_errors(true);
        try {
            $initialized = $wpdb->query($wpdb->prepare(
                'INSERT IGNORE INTO ' . self::table() . ' (customer_id, revision, addresses_json) VALUES (%d, 0, %s)',
                $customer_id, '[]'
            ));
            if ($initialized === false) {
                return self::unavailable();
            }
            for ($attempt = 0; $attempt < self::MAX_ATTEMPTS; $attempt++) {
                $book = self::read($customer_id);
                if (is_wp_error($book)) {
                    return $book;
                }
                // Re-apply the operation to a fresh row if another request won the race.
                $result = $callback($book['items']);
                if (is_wp_error($result)) {
                    return $result;
                }
                $json = wp_json_encode(array_values($result['items']));
                if ($json === false) {
                    return self::unavailable();
                }
                $changed = $wpdb->query($wpdb->prepare(
                    'UPDATE ' . self::table() . ' SET addresses_json = %s, revision = revision + 1 WHERE customer_id = %d AND revision = %d',
                    $json, $customer_id, $book['revision']
                ));
                if ($changed === false) {
                    return self::unavailable();
                }
                if ($changed === 1) {
                    return $result['response'];
                }
            }
            return new WP_Error('address_book_busy', 'The Address Book changed concurrently. Please retry.', ['status' => 409]);
        } finally {
            $wpdb->suppress_errors($previous);
        }
    }
}
