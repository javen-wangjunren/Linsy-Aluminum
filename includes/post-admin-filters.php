<?php
/**
 * Post Admin Filters - 文章列表"修改时间"列与筛选
 *
 * 利用 WordPress 内置的 post_modified 字段，在文章列表页增加：
 * 1. 可排序的"最后修改"列（带颜色高亮）
 * 2. 按修改时间筛选的下拉框（本月 / 最近7天 / 最近30天）
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 适用范围：哪些 post type 需要显示修改列
 */
function linsy_post_admin_types(): array {
	return [ 'post', 'page' ];
}

/**
 * 添加"最后修改"列
 */
add_filter( 'manage_posts_columns', 'linsy_add_modified_column' );
add_filter( 'manage_pages_columns', 'linsy_add_modified_column' );
function linsy_add_modified_column( $columns ) {
	// 放在日期列前面
	$new = [];
	foreach ( $columns as $key => $label ) {
		if ( 'date' === $key ) {
			$new['last_modified'] = __( '最后修改', 'hello-elementor' );
		}
		$new[ $key ] = $label;
	}
	return $new;
}

/**
 * 渲染修改列内容
 */
add_action( 'manage_posts_custom_column', 'linsy_render_modified_column', 10, 2 );
add_action( 'manage_pages_custom_column', 'linsy_render_modified_column', 10, 2 );
function linsy_render_modified_column( $column, $post_id ) {
	if ( 'last_modified' !== $column ) {
		return;
	}

	$post          = get_post( $post_id );
	$modified_ts   = mysql2date( 'U', $post->post_modified, false );
	$current_ts    = current_time( 'timestamp' );
	$diff_seconds  = $current_ts - $modified_ts;
	$diff_days     = floor( $diff_seconds / DAY_IN_SECONDS );

	// 日期 + 相对时间
	$date_str = date_i18n( 'Y-m-d', $modified_ts );
	$rel_str  = sprintf(
		/* translators: %s: human readable time difference */
		__( '%s前', 'hello-elementor' ),
		human_time_diff( $modified_ts, $current_ts )
	);

	// 颜色：≤7天绿色，≤30天橙色，否则灰色
	$color = $diff_days <= 7 ? '#22a958' : ( $diff_days <= 30 ? '#e8810c' : '#999' );

	printf(
		'<span style="color:%s; font-weight:600;">%s</span><br><span style="color:#999; font-size:11px;">%s</span>',
		esc_attr( $color ),
		esc_html( $date_str ),
		esc_html( $rel_str )
	);
}

/**
 * 让"最后修改"列可排序
 */
add_filter( 'manage_edit-post_sortable_columns', 'linsy_sortable_modified_column' );
add_filter( 'manage_edit-page_sortable_columns', 'linsy_sortable_modified_column' );
function linsy_sortable_modified_column( $columns ) {
	$columns['last_modified'] = 'modified';
	return $columns;
}

/**
 * 处理按 modified 排序的查询
 */
add_action( 'pre_get_posts', function ( $query ) {
	if ( ! is_admin() || ! $query->is_main_query() ) {
		return;
	}
	if ( 'modified' === $query->get( 'orderby' ) ) {
		$query->set( 'orderby', 'modified' );
	}
} );

/**
 * 在列表顶部添加筛选下拉框
 */
add_action( 'restrict_manage_posts', function () {
	$screen = get_current_screen();
	if ( ! $screen || ! in_array( $screen->post_type, linsy_post_admin_types(), true ) ) {
		return;
	}

	$current = isset( $_GET['linsy_modified_filter'] ) ? sanitize_text_field( wp_unslash( $_GET['linsy_modified_filter'] ) ) : '';

	?>
	<label for="linsy-modified-filter" class="screen-reader-text"><?php esc_html_e( '按修改时间筛选', 'hello-elementor' ); ?></label>
	<select name="linsy_modified_filter" id="linsy-modified-filter">
		<option value=""><?php esc_html_e( '所有修改时间', 'hello-elementor' ); ?></option>
		<option value="this_month" <?php selected( $current, 'this_month' ); ?>><?php esc_html_e( '本月修改', 'hello-elementor' ); ?></option>
		<option value="last_7" <?php selected( $current, 'last_7' ); ?>><?php esc_html_e( '最近7天', 'hello-elementor' ); ?></option>
		<option value="last_30" <?php selected( $current, 'last_30' ); ?>><?php esc_html_e( '最近30天', 'hello-elementor' ); ?></option>
	</select>
	<?php
} );

/**
 * 处理筛选查询逻辑
 */
add_filter( 'parse_query', function ( $query ) {
	if ( ! is_admin() || ! $query->is_main_query() ) {
		return $query;
	}

	$filter = isset( $_GET['linsy_modified_filter'] ) ? sanitize_text_field( wp_unslash( $_GET['linsy_modified_filter'] ) ) : '';
	if ( ! $filter ) {
		return $query;
	}

	$today_end = current_time( 'Y-m-d' ) . ' 23:59:59';

	$start = '';
	switch ( $filter ) {
		case 'this_month':
			$start = date( 'Y-m-01', current_time( 'timestamp' ) ) . ' 00:00:00';
			break;
		case 'last_7':
			$start = date( 'Y-m-d', strtotime( '-6 days', current_time( 'timestamp' ) ) ) . ' 00:00:00';
			break;
		case 'last_30':
			$start = date( 'Y-m-d', strtotime( '-29 days', current_time( 'timestamp' ) ) ) . ' 00:00:00';
			break;
	}

	if ( $start ) {
		$query->set( 'date_query', [
			[
				'column' => 'post_modified',
				'after'  => $start,
				'before' => $today_end,
			],
		] );
	}

	return $query;
} );
