<?php
/**
 * Paymob Loading Data
 */
class WC_Paymob_Row_Meta {

	public static function add_row_meta( $links, $file ) {
		if ( PAYMOB_PLUGIN === $file ) {
			$row_meta = array(
				'apidocs' => '<a href="' . esc_url( 'https://docs.paymob.com' ) . '" aria-label="' . esc_attr__( 'API documentation', 'paymob-for-woocommerce' ) . '">' . esc_html__( 'API docs', 'paymob-for-woocommerce' ) . '</a>',
				'support' => '<a href="' . esc_url( 'https://support.paymob.com/support/home' ) . '" aria-label="' . esc_attr__( 'Customer support', 'paymob-for-woocommerce' ) . '">' . esc_html__( 'Customer support', 'paymob-for-woocommerce' ) . '</a>',
			);
			return array_merge( $links, $row_meta );
		}
		return (array) $links;
	}
}
