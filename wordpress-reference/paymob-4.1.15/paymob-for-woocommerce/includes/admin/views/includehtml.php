<?php

class MainHtmlInclude {

	public static function get_gateway_list_views() {

		$gatewaysViews = include_once __DIR__ . '/htmlsviews/html_gateway_list.php';
		return $gatewaysViews;
	}

	public static function get_save_card_confirmation_model() {
		$confirmation_model = include_once __DIR__ . '/htmlsviews/html_save_card_confirmation_model.php';
		return $confirmation_model;
	}

	public static function get_webhook_model() {
		$confirmation_model = include_once __DIR__ . '/htmlsviews/html_webhook_url_modal.php';
		return $confirmation_model;
	}

	public static function get_manual_setup_model() {
		$confirmation_model = include_once __DIR__ . '/htmlsviews/html_manual_setup_modal.php';
		return $confirmation_model;
	}

	public static function get_disconnect_model() {
		$confirmation_model = include_once __DIR__ . '/htmlsviews/html_disconnect_paymob.php';
		return $confirmation_model;
	}

	public static function get_change_mode_model() {
		$confirmation_model = include_once __DIR__ . '/htmlsviews/html_change_mode_model.php';
		return $confirmation_model;
	}
}
