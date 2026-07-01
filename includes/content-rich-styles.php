<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'linsy_enqueue_rich_content_styles' ) ) {
	function linsy_enqueue_rich_content_styles() {
		if ( is_admin() ) {
			return;
		}

		$style_path = HELLO_THEME_STYLE_PATH . 'rich-content.css';
		$style_url  = HELLO_THEME_STYLE_URL . 'rich-content.css';
		$version    = file_exists( $style_path ) ? (string) filemtime( $style_path ) : HELLO_ELEMENTOR_VERSION;

		wp_enqueue_style(
			'linsy-rich-content',
			$style_url,
			[ 'hello-elementor-theme-style' ],
			$version
		);
	}
}
add_action( 'wp_enqueue_scripts', 'linsy_enqueue_rich_content_styles', 25 );
