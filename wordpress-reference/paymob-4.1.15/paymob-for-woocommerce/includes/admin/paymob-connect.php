<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
return array(
	'config_note'       => array(
		'title'       => __( 'Connect Paymob account', 'paymob-for-woocommerce' ),
		'description' => include __DIR__ . '/views/htmlsviews/html_connect_paymob.php',
		'type'        => 'title',
	)
);
