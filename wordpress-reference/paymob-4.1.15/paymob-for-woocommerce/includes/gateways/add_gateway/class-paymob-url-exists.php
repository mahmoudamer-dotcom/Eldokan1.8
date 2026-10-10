<?php

class Paymob_Url_Exists {

	public static function url_exists( $url ) {
		$headers = get_headers( $url, 1 ); // Avoid silencing errors, use 1 to fetch headers as an array.
		return $headers && false !== strpos( $headers[0], '200' );
	}
}
