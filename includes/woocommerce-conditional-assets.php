<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'hello_is_wc_page_request' ) ) {
	function hello_is_wc_page_request() {
		if ( ! function_exists( 'is_woocommerce' ) ) {
			return false;
		}

		return is_woocommerce() || is_cart() || is_checkout() || is_account_page();
	}
}

add_filter( 'woocommerce_enqueue_styles', function( $styles ) {
	if ( hello_is_wc_page_request() ) {
		return $styles;
	}

	return [];
}, 99 );

add_action( 'wp_enqueue_scripts', function() {
	if ( hello_is_wc_page_request() ) {
		return;
	}

	$style_handles = [
		'woocommerce-general',
		'woocommerce-layout',
		'woocommerce-smallscreen',
		'wc-blocks-style',
		'wc-blocks-vendors-style',
		'wc-blocks-packages-style',
	];

	foreach ( $style_handles as $handle ) {
		wp_dequeue_style( $handle );
		wp_deregister_style( $handle );
	}

	$script_handles = [
		'woocommerce',
		'wc-add-to-cart',
		'wc-cart-fragments',
		'js-cookie',
	];

	foreach ( $script_handles as $handle ) {
		wp_dequeue_script( $handle );
		wp_deregister_script( $handle );
	}
}, 99 );

