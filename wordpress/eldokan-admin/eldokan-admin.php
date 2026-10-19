<?php
/**
 * Plugin Name: ElDokan Admin Staff
 * Description: Internal marketplace administration and operations for ElDokan.
 * Version: 1.10.0-rc.7
 * Text Domain: eldokan-admin
 * Domain Path: /languages
 * Author: ElDokan
 */
if (!defined('ABSPATH')) exit;

// Prevent a fatal collision when an older ElDokan Admin build is still active.
if (class_exists('ElDokan_Admin', false)) {
    add_action('admin_notices', function () {
        if (!current_user_can('activate_plugins')) return;
        echo '<div class="notice notice-error"><p><strong>ElDokan Admin:</strong> Another ElDokan Admin version is already active. Deactivate the older version, then keep only one ElDokan Admin version active.</p></div>';
    });
    return;
}

if (!defined('ELDOKAN_ADMIN_VERSION')) define('ELDOKAN_ADMIN_VERSION', '1.10.0-rc.7');
if (!defined('ELDOKAN_ADMIN_DIR')) define('ELDOKAN_ADMIN_DIR', __DIR__ . '/');
if (!defined('ELDOKAN_ADMIN_URL')) define('ELDOKAN_ADMIN_URL', plugin_dir_url(__FILE__));

// Always load files from this plugin's own directory. Do not trust a constant
// that may have been defined by another installed plugin version.
require_once __DIR__ . '/includes/class-eldokan-report-pdf.php';
require_once __DIR__ . '/includes/class-eldokan-backoffice-roles.php';
require_once __DIR__ . '/includes/class-eldokan-admin.php';
require_once __DIR__ . '/includes/class-eldokan-category-attributes.php';
require_once __DIR__ . '/includes/class-eldokan-home-management.php';
require_once __DIR__ . '/includes/class-eldokan-admin-ui.php';
ElDokan_Admin_UI::boot();

register_activation_hook(__FILE__, ['ElDokan_Admin', 'activate']);
register_activation_hook(__FILE__, ['ElDokan_Backoffice_Roles', 'activate']);

add_action('plugins_loaded', function () {
    new ElDokan_Backoffice_Roles();
    new ElDokan_Admin();
    new ElDokan_Admin_Category_Attributes();
    new ElDokan_Home_Management();
});

require_once __DIR__ . '/includes/class-eldokan-operational-modules.php';
ElDokan_Operational_Modules::boot();
