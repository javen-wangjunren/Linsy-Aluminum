<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function linsy_product_export_allowed(): bool {
	$post_type_object = get_post_type_object( 'product' );
	if ( ! $post_type_object || empty( $post_type_object->cap->edit_posts ) ) {
		return false;
	}

	return current_user_can( $post_type_object->cap->edit_posts );
}

function linsy_product_export_allowed_for_post( int $post_id ): bool {
	if ( ! linsy_product_export_allowed() ) {
		return false;
	}

	return current_user_can( 'edit_post', $post_id );
}

function linsy_product_export_extract_model_keyword( string $title ): string {
	if ( preg_match( '/\b(\d{4})\b/', $title, $matches ) ) {
		return (string) $matches[1];
	}

	return '';
}

function linsy_product_export_posts_where_title_like( string $where, WP_Query $query ): string {
	$keyword = (string) $query->get( '_linsy_title_like' );
	if ( '' === $keyword ) {
		return $where;
	}

	global $wpdb;
	$like = '%' . $wpdb->esc_like( $keyword ) . '%';
	$where .= $wpdb->prepare( " AND {$wpdb->posts}.post_title LIKE %s", $like );

	return $where;
}

function linsy_product_export_find_related_blogs( string $keyword, int $limit = 10 ): array {
	$keyword = trim( $keyword );
	if ( '' === $keyword ) {
		return [];
	}

	$limit = max( 1, min( 10, $limit ) );

	add_filter( 'posts_where', 'linsy_product_export_posts_where_title_like', 10, 2 );
	$query = new WP_Query(
		[
			'post_type'        => 'post',
			'post_status'      => 'publish',
			'posts_per_page'   => $limit,
			'fields'           => 'ids',
			'orderby'          => 'date',
			'order'            => 'DESC',
			'no_found_rows'    => true,
			'suppress_filters' => false,
			'ignore_sticky_posts' => true,
			'_linsy_title_like' => $keyword,
		]
	);
	remove_filter( 'posts_where', 'linsy_product_export_posts_where_title_like', 10 );

	$items = [];
	foreach ( $query->posts as $post_id ) {
		$items[] = [
			'title' => get_the_title( $post_id ),
			'url'   => get_permalink( $post_id ),
		];
	}

	return $items;
}

function linsy_product_export_build_product_json( int $product_id ): array {
	$post = get_post( $product_id );
	if ( ! $post || 'product' !== $post->post_type ) {
		return [];
	}

	$title = (string) get_the_title( $product_id );
	$slug  = (string) $post->post_name;

	$keyword = linsy_product_export_extract_model_keyword( $title );

	$seo_title = (string) get_post_meta( $product_id, '_seopress_titles_title', true );
	$seo_desc = (string) get_post_meta( $product_id, '_seopress_titles_desc', true );

	$data = [
		'export_version'            => '1.0',
		'export_date'               => function_exists( 'wp_date' ) ? wp_date( DATE_ATOM ) : gmdate( DATE_ATOM ),
		'product_id'                => $product_id,
		'product_title'             => $title,
		'product_url'               => get_permalink( $product_id ),
		'product_slug'              => $slug,
		'product_content'           => (string) get_post_field( 'post_content', $product_id, 'raw' ),
		'product_short_description' => (string) get_post_field( 'post_excerpt', $product_id, 'raw' ),
		'seo_title'                 => '' !== $seo_title ? $seo_title : $title,
		'seo_meta_description'      => $seo_desc,
		'related_blog_keyword'      => $keyword,
		'related_blogs'             => $keyword ? linsy_product_export_find_related_blogs( $keyword, 10 ) : [],
	];

	return $data;
}

function linsy_product_export_ends_with( string $haystack, string $needle ): bool {
	$needle_length = strlen( $needle );
	if ( 0 === $needle_length ) {
		return true;
	}

	return substr( $haystack, -$needle_length ) === $needle;
}

function linsy_product_export_send_json_download( array $data, string $filename ): void {
	$filename = sanitize_file_name( $filename );
	if ( '' === $filename ) {
		$filename = 'product.json';
	}
	if ( ! linsy_product_export_ends_with( strtolower( $filename ), '.json' ) ) {
		$filename .= '.json';
	}

	nocache_headers();
	header( 'Content-Type: application/json; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
	header( 'X-Content-Type-Options: nosniff' );

	echo wp_json_encode( $data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
	exit;
}

function linsy_product_export_send_zip_download( array $files, string $filename ): void {
	if ( ! class_exists( 'ZipArchive' ) ) {
		wp_die( esc_html__( 'ZipArchive is not available on this server.', 'hello-elementor' ) );
	}

	$filename = sanitize_file_name( $filename );
	if ( '' === $filename ) {
		$filename = 'products.zip';
	}
	if ( ! linsy_product_export_ends_with( strtolower( $filename ), '.zip' ) ) {
		$filename .= '.zip';
	}

	$tmp = wp_tempnam( $filename );
	if ( ! $tmp ) {
		wp_die( esc_html__( 'Failed to create temporary file.', 'hello-elementor' ) );
	}

	$zip = new ZipArchive();
	$opened = $zip->open( $tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE );
	if ( true !== $opened ) {
		@unlink( $tmp );
		wp_die( esc_html__( 'Failed to create zip file.', 'hello-elementor' ) );
	}

	foreach ( $files as $name => $content ) {
		$name = sanitize_file_name( (string) $name );
		if ( '' === $name ) {
			continue;
		}
		$zip->addFromString( $name, (string) $content );
	}

	$zip->close();

	nocache_headers();
	header( 'Content-Type: application/zip' );
	header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
	header( 'Content-Length: ' . (string) filesize( $tmp ) );
	header( 'X-Content-Type-Options: nosniff' );

	readfile( $tmp );
	@unlink( $tmp );
	exit;
}

function linsy_product_export_get_download_url( array $args ): string {
	$args['post_type'] = 'product';
	return add_query_arg( $args, admin_url( 'edit.php' ) );
}

function linsy_product_export_handle_requests() {
	if ( ! is_admin() ) {
		return;
	}

	global $pagenow;
	if ( 'edit.php' !== $pagenow && 'post.php' !== $pagenow ) {
		return;
	}

	if ( isset( $_GET['linsy_product_export'] ) ) {
		$post_id = isset( $_GET['post'] ) ? (int) $_GET['post'] : 0;
		$nonce = isset( $_GET['linsy_export_nonce'] ) ? (string) $_GET['linsy_export_nonce'] : '';
		if ( ! wp_verify_nonce( $nonce, 'linsy_product_export_json' ) ) {
			wp_die( esc_html__( 'Invalid request.', 'hello-elementor' ) );
		}
		if ( ! $post_id || ! linsy_product_export_allowed_for_post( $post_id ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to export.', 'hello-elementor' ) );
		}

		$data = linsy_product_export_build_product_json( $post_id );
		$slug = isset( $data['product_slug'] ) ? (string) $data['product_slug'] : (string) $post_id;
		linsy_product_export_send_json_download( $data, $slug . '.json' );
	}

	if ( isset( $_GET['linsy_product_export_id'] ) ) {
		$post_id = (int) $_GET['linsy_product_export_id'];
		$nonce = isset( $_GET['linsy_export_nonce'] ) ? (string) $_GET['linsy_export_nonce'] : '';
		if ( ! wp_verify_nonce( $nonce, 'linsy_product_export_json' ) ) {
			wp_die( esc_html__( 'Invalid request.', 'hello-elementor' ) );
		}
		if ( ! $post_id || ! linsy_product_export_allowed_for_post( $post_id ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to export.', 'hello-elementor' ) );
		}

		$data = linsy_product_export_build_product_json( $post_id );
		$slug = isset( $data['product_slug'] ) ? (string) $data['product_slug'] : (string) $post_id;
		linsy_product_export_send_json_download( $data, $slug . '.json' );
	}

	if ( isset( $_GET['linsy_products_export_zip'] ) ) {
		$nonce = isset( $_GET['linsy_export_nonce'] ) ? (string) $_GET['linsy_export_nonce'] : '';
		if ( ! wp_verify_nonce( $nonce, 'linsy_product_export_zip' ) ) {
			wp_die( esc_html__( 'Invalid request.', 'hello-elementor' ) );
		}
		if ( ! linsy_product_export_allowed() ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to export.', 'hello-elementor' ) );
		}

		$ids_raw = isset( $_GET['ids'] ) ? (string) $_GET['ids'] : '';
		$ids = array_filter( array_map( 'intval', explode( ',', $ids_raw ) ) );
		$ids = array_values( array_unique( $ids ) );

		$files = [];
		foreach ( $ids as $product_id ) {
			if ( ! linsy_product_export_allowed_for_post( $product_id ) ) {
				continue;
			}
			$data = linsy_product_export_build_product_json( $product_id );
			if ( empty( $data ) ) {
				continue;
			}
			$slug = isset( $data['product_slug'] ) ? (string) $data['product_slug'] : (string) $product_id;
			$files[ $slug . '.json' ] = wp_json_encode( $data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		}

		$zip_name = 'products-selected-' . gmdate( 'Ymd-His' ) . '.zip';
		linsy_product_export_send_zip_download( $files, $zip_name );
	}

	if ( isset( $_GET['linsy_products_export_all'] ) ) {
		$nonce = isset( $_GET['linsy_export_nonce'] ) ? (string) $_GET['linsy_export_nonce'] : '';
		if ( ! wp_verify_nonce( $nonce, 'linsy_product_export_all_zip' ) ) {
			wp_die( esc_html__( 'Invalid request.', 'hello-elementor' ) );
		}
		if ( ! linsy_product_export_allowed() ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to export.', 'hello-elementor' ) );
		}

		$ids = get_posts(
			[
				'post_type'      => 'product',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
			]
		);

		$files = [];
		foreach ( $ids as $product_id ) {
			if ( ! linsy_product_export_allowed_for_post( (int) $product_id ) ) {
				continue;
			}
			$data = linsy_product_export_build_product_json( (int) $product_id );
			if ( empty( $data ) ) {
				continue;
			}
			$slug = isset( $data['product_slug'] ) ? (string) $data['product_slug'] : (string) $product_id;
			$files[ $slug . '.json' ] = wp_json_encode( $data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		}

		$zip_name = 'products-all-' . gmdate( 'Ymd-His' ) . '.zip';
		linsy_product_export_send_zip_download( $files, $zip_name );
	}
}
add_action( 'admin_init', 'linsy_product_export_handle_requests', 20 );

function linsy_product_export_edit_page_button() {
	if ( ! is_admin() ) {
		return;
	}

	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! $screen || 'product' !== $screen->post_type || 'post' !== $screen->base ) {
		return;
	}

	$post_id = isset( $_GET['post'] ) ? (int) $_GET['post'] : 0;
	if ( ! $post_id || ! linsy_product_export_allowed_for_post( $post_id ) ) {
		return;
	}

	$url = add_query_arg(
		[
			'post'              => $post_id,
			'action'            => 'edit',
			'linsy_product_export' => '1',
			'linsy_export_nonce' => wp_create_nonce( 'linsy_product_export_json' ),
		],
		admin_url( 'post.php' )
	);

	echo '<div class="misc-pub-section"><a class="button" href="' . esc_url( $url ) . '">' . esc_html__( 'Export JSON', 'hello-elementor' ) . '</a></div>';
}
add_action( 'post_submitbox_misc_actions', 'linsy_product_export_edit_page_button', 20 );

function linsy_product_export_row_action( array $actions, WP_Post $post ): array {
	if ( 'product' !== $post->post_type ) {
		return $actions;
	}
	if ( ! linsy_product_export_allowed_for_post( (int) $post->ID ) ) {
		return $actions;
	}

	$url = linsy_product_export_get_download_url(
		[
			'linsy_product_export_id' => (int) $post->ID,
			'linsy_export_nonce'      => wp_create_nonce( 'linsy_product_export_json' ),
		]
	);

	$actions['linsy_export_json'] = '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Export JSON', 'hello-elementor' ) . '</a>';

	return $actions;
}
add_filter( 'post_row_actions', 'linsy_product_export_row_action', 20, 2 );

function linsy_product_export_bulk_action( array $actions ): array {
	$actions['linsy_export_products_zip'] = esc_html__( 'Export Selected (ZIP)', 'hello-elementor' );
	return $actions;
}
add_filter( 'bulk_actions-edit-product', 'linsy_product_export_bulk_action', 20 );

function linsy_product_export_handle_bulk_action( string $redirect_to, string $doaction, array $post_ids ): string {
	if ( 'linsy_export_products_zip' !== $doaction ) {
		return $redirect_to;
	}

	if ( ! linsy_product_export_allowed() ) {
		return $redirect_to;
	}

	$ids = array_values( array_unique( array_map( 'intval', $post_ids ) ) );
	$ids = array_filter( $ids );

	$url = linsy_product_export_get_download_url(
		[
			'linsy_products_export_zip' => '1',
			'ids'                      => implode( ',', $ids ),
			'linsy_export_nonce'        => wp_create_nonce( 'linsy_product_export_zip' ),
		]
	);

	return $url;
}
add_filter( 'handle_bulk_actions-edit-product', 'linsy_product_export_handle_bulk_action', 20, 3 );

function linsy_product_export_all_button() {
	if ( ! is_admin() ) {
		return;
	}

	global $pagenow;
	if ( 'edit.php' !== $pagenow ) {
		return;
	}

	$post_type = isset( $_GET['post_type'] ) ? sanitize_key( (string) $_GET['post_type'] ) : 'post';
	if ( 'product' !== $post_type ) {
		return;
	}

	if ( ! linsy_product_export_allowed() ) {
		return;
	}

	$url = linsy_product_export_get_download_url(
		[
			'linsy_products_export_all' => '1',
			'linsy_export_nonce'        => wp_create_nonce( 'linsy_product_export_all_zip' ),
		]
	);

	echo '<a class="button" href="' . esc_url( $url ) . '">' . esc_html__( 'Export All Products (ZIP)', 'hello-elementor' ) . '</a>';
}
add_action( 'restrict_manage_posts', 'linsy_product_export_all_button', 25 );
