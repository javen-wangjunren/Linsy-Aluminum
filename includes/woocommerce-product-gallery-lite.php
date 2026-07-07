<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'after_setup_theme', function() {
	remove_theme_support( 'wc-product-gallery-zoom' );
	remove_theme_support( 'wc-product-gallery-lightbox' );
	remove_theme_support( 'wc-product-gallery-slider' );
}, 20 );

add_filter( 'woocommerce_single_product_flexslider_enabled', function( $enabled ) {
	if ( function_exists( 'hello_is_wc_product_page_request' ) && hello_is_wc_product_page_request() ) {
		return false;
	}

	return $enabled;
}, 99 );

add_filter( 'woocommerce_gallery_image_html_attachment_image_params', function( $params ) {
	if ( ! function_exists( 'hello_is_wc_product_page_request' ) || ! hello_is_wc_product_page_request() ) {
		return $params;
	}

	$params['decoding'] = 'async';
	$params['loading']  = empty( $params['class'] ) || false === strpos( $params['class'], 'wp-post-image' ) ? 'lazy' : 'eager';

	if ( 'eager' === $params['loading'] ) {
		$params['fetchpriority'] = 'high';
	}

	return $params;
}, 20 );

add_action( 'wp_enqueue_scripts', function() {
	if ( ! function_exists( 'hello_is_wc_product_page_request' ) || ! hello_is_wc_product_page_request() ) {
		return;
	}

	$style_path = HELLO_THEME_STYLE_PATH . 'woocommerce-product-gallery-lite.css';
	$script_path = HELLO_THEME_SCRIPTS_PATH . 'woocommerce-product-gallery-lite.js';
	$version = HELLO_ELEMENTOR_VERSION;

	if ( file_exists( $style_path ) ) {
		$version = (string) filemtime( $style_path );
	}

	wp_enqueue_style(
		'linsy-woocommerce-product-gallery-lite',
		HELLO_THEME_STYLE_URL . 'woocommerce-product-gallery-lite.css',
		[],
		$version
	);

	wp_enqueue_script(
		'linsy-woocommerce-product-gallery-lite',
		HELLO_THEME_SCRIPTS_URL . 'woocommerce-product-gallery-lite.js',
		[],
		file_exists( $script_path ) ? (string) filemtime( $script_path ) : $version,
		true
	);
}, 30 );

add_filter( 'script_loader_tag', function( $tag, $handle, $src ) {
	if ( 'linsy-woocommerce-product-gallery-lite' !== $handle ) {
		return $tag;
	}

	return sprintf(
		'<script nowprocket src="%s" id="%s-js"></script>',
		esc_url( $src ),
		esc_attr( $handle )
	);
}, 10, 3 );
