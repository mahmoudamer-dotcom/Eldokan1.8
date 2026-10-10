<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// var_dump($this->settings);die;
return array(
	'title'       => isset( $this->settings['title'] ) ? ucwords( $this->settings['title'] ) : '',
	'description' => isset( $this->settings['description'] ) ? $this->settings['description'] : '',
	'icon'        => isset( $this->settings['logo'] ) ? $this->settings['logo'] : plugins_url( PAYMOB_PLUGIN_NAME ) . '/assets/img/paymob.png',
);
