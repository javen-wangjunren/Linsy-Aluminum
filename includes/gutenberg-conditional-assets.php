<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'hello_is_product_page_request' ) ) {
	function hello_is_product_page_request() {
		return function_exists( 'is_product' ) && is_product();
	}
}

add_action( 'wp_enqueue_scripts', function() {
	if ( is_admin() || ! hello_is_product_page_request() ) {
		return;
	}

	$style_handles = [
		'wp-block-library',
		'wp-block-library-theme',
	];

	foreach ( $style_handles as $handle ) {
		wp_dequeue_style( $handle );
		wp_deregister_style( $handle );
	}
}, 99 );
