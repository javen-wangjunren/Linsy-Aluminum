<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function linsy_admin_search_title_only( WP_Query $query ) {
	if ( ! is_admin() || ! $query->is_main_query() || ! $query->is_search() ) {
		return;
	}

	global $pagenow;
	if ( 'edit.php' !== $pagenow ) {
		return;
	}

	$post_type  = $query->get( 'post_type' );
	$post_types = [];

	if ( empty( $post_type ) ) {
		$post_types = [ 'post' ];
	} elseif ( is_array( $post_type ) ) {
		$post_types = $post_type;
	} else {
		$post_types = [ $post_type ];
	}

	$allowed = [ 'post', 'page', 'product' ];
	$matched = array_intersect( $post_types, $allowed );
	if ( empty( $matched ) ) {
		return;
	}

	$query->set( 'search_columns', [ 'post_title' ] );
}
add_action( 'pre_get_posts', 'linsy_admin_search_title_only', 20 );

function linsy_admin_export_allowed_post_type( string $post_type ): bool {
	return in_array( $post_type, [ 'post', 'page', 'product' ], true );
}

function linsy_admin_export_search_results_csv() {
	if ( ! is_admin() ) {
		return;
	}

	global $pagenow;
	if ( 'edit.php' !== $pagenow ) {
		return;
	}

	if ( empty( $_GET['linsy_export'] ) ) {
		return;
	}

	$nonce = isset( $_GET['linsy_export_nonce'] ) ? (string) $_GET['linsy_export_nonce'] : '';
	if ( ! wp_verify_nonce( $nonce, 'linsy_export_search_results_csv' ) ) {
		wp_die( esc_html__( 'Invalid request.', 'hello-elementor' ) );
	}

	$post_type = isset( $_GET['post_type'] ) ? sanitize_key( (string) $_GET['post_type'] ) : 'post';
	if ( ! linsy_admin_export_allowed_post_type( $post_type ) ) {
		wp_die( esc_html__( 'Unsupported post type.', 'hello-elementor' ) );
	}

	$post_type_object = get_post_type_object( $post_type );
	if ( ! $post_type_object || empty( $post_type_object->cap->edit_posts ) || ! current_user_can( $post_type_object->cap->edit_posts ) ) {
		wp_die( esc_html__( 'Sorry, you are not allowed to export.', 'hello-elementor' ) );
	}

	$search = isset( $_GET['s'] ) ? sanitize_text_field( (string) $_GET['s'] ) : '';

	$args = [
		'post_type'        => $post_type,
		'post_status'      => 'any',
		'posts_per_page'   => -1,
		'fields'           => 'ids',
		'no_found_rows'    => true,
		'suppress_filters' => false,
		's'                => $search,
		'search_columns'   => [ 'post_title' ],
	];

	if ( isset( $_GET['post_status'] ) && '' !== (string) $_GET['post_status'] ) {
		$args['post_status'] = sanitize_key( (string) $_GET['post_status'] );
	}
	if ( isset( $_GET['m'] ) && '' !== (string) $_GET['m'] ) {
		$args['m'] = (int) $_GET['m'];
	}
	if ( isset( $_GET['author'] ) && '' !== (string) $_GET['author'] ) {
		$args['author'] = (int) $_GET['author'];
	}

	$query = new WP_Query( $args );

	$filename_parts = [
		$post_type,
		$search ? $search : 'all',
		gmdate( 'Ymd-His' ),
	];
	$filename = sanitize_file_name( implode( '-', $filename_parts ) ) . '.csv';

	nocache_headers();
	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
	header( 'X-Content-Type-Options: nosniff' );

	echo "\xEF\xBB\xBF";

	$fh = fopen( 'php://output', 'w' );
	if ( $fh ) {
		fputcsv( $fh, [ 'Title', 'URL' ] );

		foreach ( $query->posts as $post_id ) {
			$title = get_the_title( $post_id );
			$url = get_permalink( $post_id );
			fputcsv( $fh, [ $title, $url ] );
		}

		fclose( $fh );
	}

	exit;
}
add_action( 'admin_init', 'linsy_admin_export_search_results_csv', 20 );

function linsy_admin_export_button() {
	if ( ! is_admin() ) {
		return;
	}

	global $pagenow;
	if ( 'edit.php' !== $pagenow ) {
		return;
	}

	$post_type = isset( $_GET['post_type'] ) ? sanitize_key( (string) $_GET['post_type'] ) : 'post';
	if ( ! linsy_admin_export_allowed_post_type( $post_type ) ) {
		return;
	}

	$post_type_object = get_post_type_object( $post_type );
	if ( ! $post_type_object || empty( $post_type_object->cap->edit_posts ) || ! current_user_can( $post_type_object->cap->edit_posts ) ) {
		return;
	}

	$args = $_GET;
	unset( $args['paged'], $args['linsy_export'], $args['linsy_export_nonce'] );
	$args['linsy_export'] = '1';
	$args['linsy_export_nonce'] = wp_create_nonce( 'linsy_export_search_results_csv' );

	$url = add_query_arg( $args, admin_url( 'edit.php' ) );

	echo '<a class="button" href="' . esc_url( $url ) . '">' . esc_html__( 'Export CSV', 'hello-elementor' ) . '</a>';
}
add_action( 'restrict_manage_posts', 'linsy_admin_export_button', 20 );

