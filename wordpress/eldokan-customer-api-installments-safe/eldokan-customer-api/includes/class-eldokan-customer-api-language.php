<?php
if (!defined('ABSPATH')) {
    exit;
}

interface ElDokan_Customer_API_Translation_Adapter {

    public function name();

    public function current_language();

    public function can_switch();

    public function run($language, $callback);

    public function object_id($object_id, $object_type, $language);

    public function product_language_sql($post_alias);
}

final class ElDokan_Customer_API_Native_Translation_Adapter implements ElDokan_Customer_API_Translation_Adapter {

    public function name() {
        return 'native';
    }

    public function current_language() {
        return determine_locale();
    }

    public function can_switch() {
        return false;
    }

    public function run($language, $callback) {
        return call_user_func($callback);
    }

    public function object_id($object_id, $object_type, $language) {
        return absint($object_id);
    }

    public function product_language_sql($post_alias) {
        return '';
    }
}

final class ElDokan_Customer_API_WPML_Translation_Adapter implements ElDokan_Customer_API_Translation_Adapter {

    public static function available() {
        return defined('ICL_SITEPRESS_VERSION')
            || has_filter('wpml_current_language')
            || has_filter('wpml_object_id')
            || has_action('wpml_switch_language');
    }

    public function name() {
        return 'wpml';
    }

    public function current_language() {
        if (has_filter('wpml_current_language')) {
            return (string) apply_filters('wpml_current_language', null);
        }

        return determine_locale();
    }

    public function can_switch() {
        return has_action('wpml_switch_language');
    }

    public function run($language, $callback) {
        $previous = ElDokan_Customer_API_Language::normalize($this->current_language());
        $switched = $language && $this->can_switch();

        if ($switched) {
            do_action('wpml_switch_language', $language);
        }

        try {
            return call_user_func($callback);
        } finally {
            if ($switched) {
                do_action('wpml_switch_language', $previous);
            }
        }
    }

    public function object_id($object_id, $object_type, $language) {
        if (!$language || !has_filter('wpml_object_id')) {
            return absint($object_id);
        }

        return absint(apply_filters(
            'wpml_object_id',
            absint($object_id),
            sanitize_key($object_type),
            false,
            sanitize_key($language)
        ));
    }

    public function product_language_sql($post_alias) {
        global $wpdb;

        $post_alias = preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', (string) $post_alias)
            ? (string) $post_alias
            : 'p';
        $language = ElDokan_Customer_API_Language::normalize($this->current_language());
        $translations = $wpdb->prefix . 'icl_translations';

        if (
            !$language
            || $wpdb->get_var($wpdb->prepare(
                'SHOW TABLES LIKE %s',
                $wpdb->esc_like($translations)
            )) !== $translations
        ) {
            return '';
        }

        return $wpdb->prepare("AND EXISTS (
            SELECT 1 FROM {$translations} AS tr
            WHERE tr.element_id = {$post_alias}.ID
                AND tr.element_type = 'post_product'
                AND tr.language_code = %s
        )", $language);
    }
}

final class ElDokan_Customer_API_Language {

    private static $adapter = null;
    private static $effective_language = null;

    public static function requested(WP_REST_Request $request) {
        return ElDokan_Customer_API_Utils::requested_language($request);
    }

    public static function normalize($language) {
        $language = strtolower(str_replace('_', '-', trim((string) $language)));

        if (strpos($language, 'ar') === 0) {
            return 'ar';
        }
        if (strpos($language, 'en') === 0) {
            return 'en';
        }

        return null;
    }

    public static function provider() {
        return self::adapter()->name();
    }

    public static function current() {
        return self::normalize(self::adapter()->current_language()) ?: 'en';
    }

    public static function effective(WP_REST_Request $request = null) {
        if (self::$effective_language) {
            return self::$effective_language;
        }

        $requested = $request ? self::requested($request) : null;
        if ($requested && self::adapter()->can_switch()) {
            return $requested;
        }

        return self::current();
    }

    public static function cache_identity(WP_REST_Request $request) {
        return [
            'requested' => self::requested($request),
            'effective' => self::effective($request),
            'provider' => self::provider(),
        ];
    }

    public static function run(WP_REST_Request $request, $callback) {
        $requested = self::requested($request);
        $previous_effective = self::$effective_language;
        self::$effective_language = ($requested && self::adapter()->can_switch())
            ? $requested
            : self::current();

        try {
            return self::adapter()->run($requested, $callback);
        } finally {
            self::$effective_language = $previous_effective;
        }
    }

    public static function object_id($object_id, $object_type, $language = null) {
        return self::adapter()->object_id(
            $object_id,
            $object_type,
            self::normalize($language)
        );
    }

    public static function product_language_sql($post_alias = 'p') {
        return self::adapter()->product_language_sql($post_alias);
    }

    private static function adapter() {
        if (self::$adapter === null) {
            self::$adapter = ElDokan_Customer_API_WPML_Translation_Adapter::available()
                ? new ElDokan_Customer_API_WPML_Translation_Adapter()
                : new ElDokan_Customer_API_Native_Translation_Adapter();
        }

        return self::$adapter;
    }
}
