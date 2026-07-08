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
		$script_path = HELLO_THEME_SCRIPTS_PATH . 'product-table-scroll.js';
		$script_url  = HELLO_THEME_SCRIPTS_URL . 'product-table-scroll.js';
		$toc_style_path = HELLO_THEME_STYLE_PATH . 'blog-toc.css';
		$toc_style_url  = HELLO_THEME_STYLE_URL . 'blog-toc.css';
		$toc_script_path = HELLO_THEME_SCRIPTS_PATH . 'blog-toc.js';
		$toc_script_url  = HELLO_THEME_SCRIPTS_URL . 'blog-toc.js';

		wp_enqueue_style(
			'linsy-rich-content',
			$style_url,
			[ 'hello-elementor-theme-style' ],
			$version
		);

		wp_enqueue_script(
			'linsy-product-table-scroll',
			$script_url,
			[],
			file_exists( $script_path ) ? (string) filemtime( $script_path ) : $version,
			true
		);

		if ( is_singular( 'post' ) ) {
			wp_enqueue_style(
				'linsy-blog-toc',
				$toc_style_url,
				[ 'linsy-rich-content' ],
				file_exists( $toc_style_path ) ? (string) filemtime( $toc_style_path ) : $version
			);

			wp_enqueue_script(
				'linsy-blog-toc',
				$toc_script_url,
				[],
				file_exists( $toc_script_path ) ? (string) filemtime( $toc_script_path ) : $version,
				true
			);
		}
	}
}
add_action( 'wp_enqueue_scripts', 'linsy_enqueue_rich_content_styles', 25 );
