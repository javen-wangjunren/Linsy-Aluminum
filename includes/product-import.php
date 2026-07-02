<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function linsy_product_import_allowed(): bool {
	$post_type_object = get_post_type_object( 'product' );
	if ( ! $post_type_object || empty( $post_type_object->cap->edit_posts ) ) {
		return false;
	}

	return current_user_can( $post_type_object->cap->edit_posts );
}

function linsy_product_import_allowed_for_post( int $post_id ): bool {
	if ( ! linsy_product_import_allowed() ) {
		return false;
	}

	return current_user_can( 'edit_post', $post_id );
}

function linsy_product_import_decode_text( string $value ): string {
	$value = html_entity_decode( $value, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	return trim( $value );
}

function linsy_product_import_ends_with( string $haystack, string $needle ): bool {
	$needle_length = strlen( $needle );
	if ( 0 === $needle_length ) {
		return true;
	}

	return substr( $haystack, -$needle_length ) === $needle;
}

function linsy_product_import_allowed_html(): array {
	$allowed = wp_kses_allowed_html( 'post' );

	$table_tags = [
		'table' => [
			'class' => true,
		],
		'thead' => [],
		'tbody' => [],
		'tfoot' => [],
		'tr'    => [],
		'th'    => [
			'colspan' => true,
			'rowspan' => true,
			'scope'   => true,
		],
		'td'    => [
			'colspan' => true,
			'rowspan' => true,
		],
	];

	foreach ( $table_tags as $tag => $attrs ) {
		$allowed[ $tag ] = $attrs;
	}

	return $allowed;
}

function linsy_product_import_filter_html( string $html ): string {
	$html = trim( $html );
	if ( '' === $html ) {
		return '';
	}

	return wp_kses( $html, linsy_product_import_allowed_html() );
}

function linsy_product_import_read_json_string( string $json ): array {
	$data = json_decode( $json, true );
	if ( ! is_array( $data ) ) {
		return [];
	}

	return $data;
}

function linsy_product_import_read_json_file( string $path ): array {
	$contents = file_get_contents( $path );
	if ( false === $contents ) {
		return [];
	}

	return linsy_product_import_read_json_string( $contents );
}

function linsy_product_import_read_zip_file( string $path ): array {
	if ( ! class_exists( 'ZipArchive' ) ) {
		return [];
	}

	$zip = new ZipArchive();
	$opened = $zip->open( $path );
	if ( true !== $opened ) {
		return [];
	}

	$items = [];
	for ( $i = 0; $i < $zip->numFiles; $i++ ) {
		$name = (string) $zip->getNameIndex( $i );
		$name = trim( $name );
		if ( '' === $name ) {
			continue;
		}
		if ( ! linsy_product_import_ends_with( strtolower( $name ), '.import.json' ) ) {
			continue;
		}
		$contents = $zip->getFromIndex( $i );
		if ( false === $contents ) {
			continue;
		}
		$data = linsy_product_import_read_json_string( (string) $contents );
		if ( empty( $data ) ) {
			continue;
		}
		$items[ $name ] = $data;
	}

	$zip->close();

	return $items;
}

function linsy_product_import_validate( array $data ): array {
	$errors = [];

	$import_version = isset( $data['import_version'] ) ? (string) $data['import_version'] : '';
	if ( '1.0' !== $import_version ) {
		$errors[] = 'import_version_invalid';
	}

	$product_id = isset( $data['product_id'] ) ? (int) $data['product_id'] : 0;
	$product_slug = isset( $data['product_slug'] ) ? (string) $data['product_slug'] : '';
	if ( ! $product_id && '' === trim( $product_slug ) ) {
		$errors[] = 'missing_product_identifier';
	}

	$required = [
		'product_content_html',
		'product_short_description_html',
		'seo_title',
		'seo_meta_description',
	];

	foreach ( $required as $key ) {
		if ( ! isset( $data[ $key ] ) || '' === trim( (string) $data[ $key ] ) ) {
			$errors[] = 'missing_' . $key;
		}
	}

	return $errors;
}

function linsy_product_import_locate_product_id( array $data ): int {
	$product_id = isset( $data['product_id'] ) ? (int) $data['product_id'] : 0;
	if ( $product_id ) {
		$post = get_post( $product_id );
		if ( $post && 'product' === $post->post_type ) {
			return $product_id;
		}
	}

	$slug = isset( $data['product_slug'] ) ? sanitize_title( (string) $data['product_slug'] ) : '';
	if ( '' === $slug ) {
		return 0;
	}

	$posts = get_posts(
		[
			'post_type'      => 'product',
			'name'           => $slug,
			'post_status'    => 'any',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'no_found_rows'  => true,
		]
	);
	if ( empty( $posts ) ) {
		return 0;
	}

	return (int) $posts[0];
}

function linsy_product_import_backup( int $product_id, string $source_filename ): void {
	$backup = [
		'created_at' => function_exists( 'wp_date' ) ? wp_date( DATE_ATOM ) : gmdate( DATE_ATOM ),
		'source'     => $source_filename,
		'post'       => [
			'post_content' => (string) get_post_field( 'post_content', $product_id, 'raw' ),
			'post_excerpt' => (string) get_post_field( 'post_excerpt', $product_id, 'raw' ),
		],
		'seopress'    => [
			'title' => (string) get_post_meta( $product_id, '_seopress_titles_title', true ),
			'desc'  => (string) get_post_meta( $product_id, '_seopress_titles_desc', true ),
		],
	];

	$key = '_linsy_import_backup_' . gmdate( 'YmdHis' );
	update_post_meta( $product_id, $key, wp_json_encode( $backup, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );

	$all_meta = get_post_meta( $product_id );
	$backup_keys = [];
	foreach ( $all_meta as $meta_key => $values ) {
		if ( 0 === strpos( (string) $meta_key, '_linsy_import_backup_' ) ) {
			$backup_keys[] = (string) $meta_key;
		}
	}

	sort( $backup_keys, SORT_STRING );
	$max = 5;
	if ( count( $backup_keys ) <= $max ) {
		return;
	}

	$to_delete = array_slice( $backup_keys, 0, count( $backup_keys ) - $max );
	foreach ( $to_delete as $delete_key ) {
		delete_post_meta( $product_id, $delete_key );
	}
}

function linsy_product_import_apply( int $product_id, array $data, string $source_filename, bool $dry_run ): array {
	if ( ! linsy_product_import_allowed_for_post( $product_id ) ) {
		return [ 'ok' => false, 'error' => 'not_allowed' ];
	}

	$errors = linsy_product_import_validate( $data );
	if ( ! empty( $errors ) ) {
		return [ 'ok' => false, 'error' => implode( ',', $errors ) ];
	}

	$content_html = linsy_product_import_filter_html( (string) $data['product_content_html'] );
	$short_html = linsy_product_import_filter_html( (string) $data['product_short_description_html'] );

	$seo_title = linsy_product_import_decode_text( (string) $data['seo_title'] );
	$seo_desc = linsy_product_import_decode_text( (string) $data['seo_meta_description'] );

	$seo_title = sanitize_text_field( $seo_title );
	$seo_desc = sanitize_textarea_field( $seo_desc );

	if ( $dry_run ) {
		return [
			'ok'         => true,
			'dry_run'    => true,
			'product_id' => $product_id,
		];
	}

	linsy_product_import_backup( $product_id, $source_filename );

	wp_update_post(
		[
			'ID'           => $product_id,
			'post_content' => wp_slash( $content_html ),
			'post_excerpt' => wp_slash( $short_html ),
		]
	);

	update_post_meta( $product_id, '_seopress_titles_title', $seo_title );
	update_post_meta( $product_id, '_seopress_titles_desc', $seo_desc );

	return [
		'ok'         => true,
		'dry_run'    => false,
		'product_id' => $product_id,
	];
}

function linsy_product_import_store_report( array $report ): string {
	$user_id = get_current_user_id();
	$key = 'linsy_import_report_' . $user_id . '_' . wp_generate_password( 10, false, false );
	set_transient( $key, $report, 10 * MINUTE_IN_SECONDS );
	return $key;
}

function linsy_product_import_get_report( string $key ): array {
	$report = get_transient( $key );
	if ( ! is_array( $report ) ) {
		return [];
	}
	return $report;
}

function linsy_product_import_render_report( array $report ) {
	if ( empty( $report ) ) {
		return;
	}

	$dry_run = ! empty( $report['dry_run'] );
	$success = isset( $report['success'] ) && is_array( $report['success'] ) ? $report['success'] : [];
	$failed = isset( $report['failed'] ) && is_array( $report['failed'] ) ? $report['failed'] : [];

	echo '<h2>' . esc_html__( 'Import Report', 'hello-elementor' ) . '</h2>';
	if ( $dry_run ) {
		echo '<p><strong>' . esc_html__( 'Dry run enabled: no changes were written.', 'hello-elementor' ) . '</strong></p>';
	}

	echo '<p>' . esc_html__( 'Success:', 'hello-elementor' ) . ' ' . esc_html( (string) count( $success ) ) . ' &nbsp; ';
	echo esc_html__( 'Failed:', 'hello-elementor' ) . ' ' . esc_html( (string) count( $failed ) ) . '</p>';

	if ( ! empty( $success ) ) {
		echo '<h3>' . esc_html__( 'Success Items', 'hello-elementor' ) . '</h3>';
		echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'File', 'hello-elementor' ) . '</th><th>' . esc_html__( 'Product', 'hello-elementor' ) . '</th></tr></thead><tbody>';
		foreach ( $success as $item ) {
			$file = isset( $item['file'] ) ? (string) $item['file'] : '';
			$product_id = isset( $item['product_id'] ) ? (int) $item['product_id'] : 0;
			$edit_url = $product_id ? get_edit_post_link( $product_id, 'raw' ) : '';
			echo '<tr><td>' . esc_html( $file ) . '</td><td>';
			if ( $edit_url ) {
				echo '<a href="' . esc_url( $edit_url ) . '">' . esc_html( (string) $product_id ) . '</a>';
			} else {
				echo esc_html( (string) $product_id );
			}
			echo '</td></tr>';
		}
		echo '</tbody></table>';
	}

	if ( ! empty( $failed ) ) {
		echo '<h3>' . esc_html__( 'Failed Items', 'hello-elementor' ) . '</h3>';
		echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'File', 'hello-elementor' ) . '</th><th>' . esc_html__( 'Error', 'hello-elementor' ) . '</th></tr></thead><tbody>';
		foreach ( $failed as $item ) {
			$file = isset( $item['file'] ) ? (string) $item['file'] : '';
			$error = isset( $item['error'] ) ? (string) $item['error'] : '';
			echo '<tr><td>' . esc_html( $file ) . '</td><td>' . esc_html( $error ) . '</td></tr>';
		}
		echo '</tbody></table>';
	}
}

function linsy_product_import_tools_page() {
	if ( ! linsy_product_import_allowed() ) {
		wp_die( esc_html__( 'Sorry, you are not allowed to import.', 'hello-elementor' ) );
	}

	$target_product_id = isset( $_GET['product_id'] ) ? (int) $_GET['product_id'] : 0;
	$report_key = isset( $_GET['report'] ) ? sanitize_key( (string) $_GET['report'] ) : '';
	$report = $report_key ? linsy_product_import_get_report( $report_key ) : [];

	echo '<div class="wrap">';
	echo '<h1>' . esc_html__( 'Product Import', 'hello-elementor' ) . '</h1>';

	if ( $target_product_id ) {
		$edit_url = get_edit_post_link( $target_product_id, 'raw' );
		if ( $edit_url ) {
			echo '<p>' . esc_html__( 'Target product:', 'hello-elementor' ) . ' <a href="' . esc_url( $edit_url ) . '">' . esc_html( (string) $target_product_id ) . '</a></p>';
		}
	}

	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" enctype="multipart/form-data">';
	echo '<input type="hidden" name="action" value="linsy_product_import_bulk">';
	wp_nonce_field( 'linsy_product_import_bulk', 'linsy_import_nonce' );
	if ( $target_product_id ) {
		echo '<input type="hidden" name="target_product_id" value="' . esc_attr( (string) $target_product_id ) . '">';
	}

	echo '<table class="form-table" role="presentation"><tbody>';
	echo '<tr><th scope="row"><label for="linsy_import_file">' . esc_html__( 'Import File', 'hello-elementor' ) . '</label></th><td>';
	echo '<input type="file" id="linsy_import_file" name="import_file" accept=".json,.zip" required>';
	echo '<p class="description">' . esc_html__( 'Upload a .import.json file or a .zip containing multiple .import.json files.', 'hello-elementor' ) . '</p>';
	echo '</td></tr>';
	echo '<tr><th scope="row">' . esc_html__( 'Options', 'hello-elementor' ) . '</th><td>';
	echo '<label><input type="checkbox" name="dry_run" value="1"> ' . esc_html__( 'Dry run (no write)', 'hello-elementor' ) . '</label>';
	echo '</td></tr>';
	echo '</tbody></table>';

	submit_button( esc_html__( 'Import', 'hello-elementor' ) );
	echo '</form>';

	linsy_product_import_render_report( $report );
	echo '</div>';
}

function linsy_product_import_register_tools_page() {
	add_management_page(
		esc_html__( 'Product Import', 'hello-elementor' ),
		esc_html__( 'Product Import', 'hello-elementor' ),
		'edit_posts',
		'linsy-product-import',
		'linsy_product_import_tools_page'
	);
}
add_action( 'admin_menu', 'linsy_product_import_register_tools_page', 20 );

function linsy_product_import_handle_bulk() {
	if ( ! linsy_product_import_allowed() ) {
		wp_die( esc_html__( 'Sorry, you are not allowed to import.', 'hello-elementor' ) );
	}

	$nonce = isset( $_POST['linsy_import_nonce'] ) ? (string) $_POST['linsy_import_nonce'] : '';
	if ( ! wp_verify_nonce( $nonce, 'linsy_product_import_bulk' ) ) {
		wp_die( esc_html__( 'Invalid request.', 'hello-elementor' ) );
	}

	if ( empty( $_FILES['import_file'] ) || empty( $_FILES['import_file']['tmp_name'] ) ) {
		wp_die( esc_html__( 'No file uploaded.', 'hello-elementor' ) );
	}

	$dry_run = ! empty( $_POST['dry_run'] );
	$target_product_id = isset( $_POST['target_product_id'] ) ? (int) $_POST['target_product_id'] : 0;

	$uploaded = wp_handle_upload(
		$_FILES['import_file'],
		[
			'test_form' => false,
		]
	);
	if ( empty( $uploaded['file'] ) ) {
		wp_die( esc_html__( 'Upload failed.', 'hello-elementor' ) );
	}

	$path = (string) $uploaded['file'];
	$original_name = isset( $_FILES['import_file']['name'] ) ? (string) $_FILES['import_file']['name'] : basename( $path );

	$items = [];
	if ( linsy_product_import_ends_with( strtolower( $original_name ), '.zip' ) ) {
		$items = linsy_product_import_read_zip_file( $path );
	} else {
		$data = linsy_product_import_read_json_file( $path );
		if ( ! empty( $data ) ) {
			$items[ $original_name ] = $data;
		}
	}

	$report = [
		'dry_run' => $dry_run,
		'success' => [],
		'failed'  => [],
	];

	foreach ( $items as $filename => $data ) {
		$product_id = linsy_product_import_locate_product_id( $data );
		if ( $target_product_id && $product_id && $target_product_id !== $product_id ) {
			$report['failed'][] = [
				'file'  => $filename,
				'error' => 'target_product_mismatch',
			];
			continue;
		}
		if ( ! $product_id && $target_product_id ) {
			$product_id = $target_product_id;
		}
		if ( ! $product_id ) {
			$report['failed'][] = [
				'file'  => $filename,
				'error' => 'product_not_found',
			];
			continue;
		}

		$result = linsy_product_import_apply( $product_id, $data, (string) $filename, $dry_run );
		if ( ! empty( $result['ok'] ) ) {
			$report['success'][] = [
				'file'       => $filename,
				'product_id' => $product_id,
			];
		} else {
			$report['failed'][] = [
				'file'  => $filename,
				'error' => isset( $result['error'] ) ? (string) $result['error'] : 'unknown_error',
			];
		}
	}

	@unlink( $path );

	$report_key = linsy_product_import_store_report( $report );
	$redirect = add_query_arg(
		[
			'page'      => 'linsy-product-import',
			'report'    => $report_key,
			'product_id' => $target_product_id ? $target_product_id : null,
		],
		admin_url( 'tools.php' )
	);
	wp_safe_redirect( $redirect );
	exit;
}
add_action( 'admin_post_linsy_product_import_bulk', 'linsy_product_import_handle_bulk', 20 );

function linsy_product_import_edit_page_ui() {
	if ( ! is_admin() ) {
		return;
	}

	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! $screen || 'product' !== $screen->post_type || 'post' !== $screen->base ) {
		return;
	}

	$post_id = isset( $_GET['post'] ) ? (int) $_GET['post'] : 0;
	if ( ! $post_id || ! linsy_product_import_allowed_for_post( $post_id ) ) {
		return;
	}

	$tools_url = add_query_arg(
		[
			'page'       => 'linsy-product-import',
			'product_id' => $post_id,
		],
		admin_url( 'tools.php' )
	);
	$form_id = 'linsy-product-import-single-' . $post_id;

	echo '<div class="misc-pub-section"><a class="button" href="' . esc_url( $tools_url ) . '">' . esc_html__( 'Open Import Tool', 'hello-elementor' ) . '</a></div>';

	echo '<div class="misc-pub-section">';
	echo '<input type="file" name="import_file" accept=".json" required form="' . esc_attr( $form_id ) . '" style="max-width: 100%;">';
	echo '<p style="margin: 6px 0 0;"><button type="submit" class="button" form="' . esc_attr( $form_id ) . '">' . esc_html__( 'Import JSON', 'hello-elementor' ) . '</button></p>';
	echo '</div>';
}
add_action( 'post_submitbox_misc_actions', 'linsy_product_import_edit_page_ui', 25 );

function linsy_product_import_edit_page_form() {
	if ( ! is_admin() ) {
		return;
	}

	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! $screen || 'product' !== $screen->post_type || 'post' !== $screen->base ) {
		return;
	}

	$post_id = isset( $_GET['post'] ) ? (int) $_GET['post'] : 0;
	if ( ! $post_id || ! linsy_product_import_allowed_for_post( $post_id ) ) {
		return;
	}

	$form_id = 'linsy-product-import-single-' . $post_id;

	echo '<form id="' . esc_attr( $form_id ) . '" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" enctype="multipart/form-data" style="display:none;">';
	echo '<input type="hidden" name="action" value="linsy_product_import_single">';
	echo '<input type="hidden" name="product_id" value="' . esc_attr( (string) $post_id ) . '">';
	wp_nonce_field( 'linsy_product_import_single', 'linsy_import_nonce' );
	echo '</form>';
}
add_action( 'admin_footer-post.php', 'linsy_product_import_edit_page_form', 20 );

function linsy_product_import_handle_single() {
	$product_id = isset( $_POST['product_id'] ) ? (int) $_POST['product_id'] : 0;
	if ( ! $product_id || ! linsy_product_import_allowed_for_post( $product_id ) ) {
		wp_die( esc_html__( 'Sorry, you are not allowed to import.', 'hello-elementor' ) );
	}

	$nonce = isset( $_POST['linsy_import_nonce'] ) ? (string) $_POST['linsy_import_nonce'] : '';
	if ( ! wp_verify_nonce( $nonce, 'linsy_product_import_single' ) ) {
		wp_die( esc_html__( 'Invalid request.', 'hello-elementor' ) );
	}

	if ( empty( $_FILES['import_file'] ) || empty( $_FILES['import_file']['tmp_name'] ) ) {
		wp_die( esc_html__( 'No file uploaded.', 'hello-elementor' ) );
	}

	$uploaded = wp_handle_upload(
		$_FILES['import_file'],
		[
			'test_form' => false,
		]
	);
	if ( empty( $uploaded['file'] ) ) {
		wp_die( esc_html__( 'Upload failed.', 'hello-elementor' ) );
	}

	$path = (string) $uploaded['file'];
	$original_name = isset( $_FILES['import_file']['name'] ) ? (string) $_FILES['import_file']['name'] : basename( $path );

	$data = linsy_product_import_read_json_file( $path );
	@unlink( $path );

	$reported_product_id = isset( $data['product_id'] ) ? (int) $data['product_id'] : 0;
	if ( ! $reported_product_id || $reported_product_id !== $product_id ) {
		wp_die( esc_html__( 'Product ID mismatch.', 'hello-elementor' ) );
	}

	$result = linsy_product_import_apply( $product_id, $data, $original_name, false );

	$report = [
		'dry_run' => false,
		'success' => [],
		'failed'  => [],
	];

	if ( ! empty( $result['ok'] ) ) {
		$report['success'][] = [
			'file'       => $original_name,
			'product_id' => $product_id,
		];
	} else {
		$report['failed'][] = [
			'file'  => $original_name,
			'error' => isset( $result['error'] ) ? (string) $result['error'] : 'unknown_error',
		];
	}

	$report_key = linsy_product_import_store_report( $report );
	$redirect = add_query_arg(
		[
			'post'   => $product_id,
			'action' => 'edit',
			'report' => $report_key,
		],
		admin_url( 'post.php' )
	);
	wp_safe_redirect( $redirect );
	exit;
}
add_action( 'admin_post_linsy_product_import_single', 'linsy_product_import_handle_single', 20 );

function linsy_product_import_edit_notice() {
	if ( ! is_admin() ) {
		return;
	}

	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! $screen || 'product' !== $screen->post_type || 'post' !== $screen->base ) {
		return;
	}

	$report_key = isset( $_GET['report'] ) ? sanitize_key( (string) $_GET['report'] ) : '';
	if ( '' === $report_key ) {
		return;
	}

	$report = linsy_product_import_get_report( $report_key );
	if ( empty( $report ) ) {
		return;
	}

	$failed = isset( $report['failed'] ) && is_array( $report['failed'] ) ? $report['failed'] : [];
	if ( ! empty( $failed ) ) {
		echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Import failed. Check the report in Tools → Product Import.', 'hello-elementor' ) . '</p></div>';
		return;
	}

	echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Import completed.', 'hello-elementor' ) . '</p></div>';
}
add_action( 'admin_notices', 'linsy_product_import_edit_notice', 20 );
