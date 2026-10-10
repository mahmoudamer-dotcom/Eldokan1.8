<?php
/**
 * Affordability Widget settings fields registration.
 *
 * Returns the WooCommerce settings array used by `Paymob_Widget_Settings`.
 * The custom field type `paymob_affordability_widget_ui` is rendered through the
 * action hook registered in `class-paymob-widget-section.php`.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$tabs = include __DIR__ . '/paymob-admin-tabs.php';

return array(
	array(
		'name' => '',
		'type' => 'title',
		'desc' => $tabs,
		'id'   => 'paymob_affordability_widget_tabs',
	),
	array(
		'type' => 'paymob_affordability_widget_ui',
		'id'   => 'paymob_affordability_widget_ui',
	),
	array(
		'type' => 'sectionend',
		'id'   => 'paymob_affordability_widget_end',
	),
);
