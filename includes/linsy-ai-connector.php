<?php
/**
 * AI Content Ops Connector — Stage 1 PoC
 *
 * 为 AI Agent 提供 REST API 端点，实现：
 *   - 博客文章列表查询（支持 AI 状态筛选）
 *   - 单篇文章完整数据获取（含 SEO meta、关联产品/文章）
 *   - AI 优化结果写入（Revision 模式，不直接覆盖线上内容）
 *   - AI Revision 列表查看与 Apply/Restore
 *
 * 认证方式：WordPress Application Passwords（Basic Auth）
 * 权限要求：edit_posts
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ============================================================
// 常量
// ============================================================

define( 'AI_OPS_CONNECTOR_VERSION', '0.1.0' );
define( 'AI_OPS_API_NAMESPACE', 'ai-ops/v1' );

// ============================================================
// AI Status 管理
// ============================================================

/**
 * 有效的 AI 状态值
 */
function ai_ops_valid_statuses(): array {
	return [
		'not_processed',
		'queued',
		'processing',
		'optimized',
		'needs_review',
		'approved',
		'published',
	];
}

/**
 * 获取文章的 AI 状态
 */
function ai_ops_get_status( int $post_id ): string {
	$status = (string) get_post_meta( $post_id, '_ai_ops_status', true );
	if ( '' === $status || ! in_array( $status, ai_ops_valid_statuses(), true ) ) {
		return 'not_processed';
	}
	return $status;
}

/**
 * 设置文章的 AI 状态
 */
function ai_ops_set_status( int $post_id, string $status ): bool {
	if ( ! in_array( $status, ai_ops_valid_statuses(), true ) ) {
		return false;
	}
	update_post_meta( $post_id, '_ai_ops_status', $status );
	return true;
}

// ============================================================
// AI Revision 管理
// ============================================================

/**
 * 保存一份 AI Revision
 *
 * @return string revision key (基于时间戳)，失败返回空字符串
 */
function ai_ops_save_revision( int $post_id, array $data ): string {
	$key = '_ai_ops_revision_' . gmdate( 'YmdHis' ) . '_' . wp_generate_password( 4, false );

	$revision = [
		'created_at'           => function_exists( 'wp_date' ) ? wp_date( DATE_ATOM ) : gmdate( DATE_ATOM ),
		'source'               => isset( $data['source'] ) ? sanitize_text_field( (string) $data['source'] ) : 'ai-agent',
		'post_title'           => isset( $data['post_title'] ) ? sanitize_text_field( (string) $data['post_title'] ) : '',
		'post_content_html'    => isset( $data['post_content_html'] ) ? (string) $data['post_content_html'] : '',
		'seo_title'            => isset( $data['seo_title'] ) ? sanitize_text_field( (string) $data['seo_title'] ) : '',
		'seo_meta_description' => isset( $data['seo_meta_description'] ) ? sanitize_textarea_field( (string) $data['seo_meta_description'] ) : '',
		'notes'                => isset( $data['notes'] ) ? $data['notes'] : [],
		'applied'              => false,
	];

	update_post_meta( $post_id, $key, wp_json_encode( $revision, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );

	return $key;
}

/**
 * 获取所有 AI Revision keys（按时间倒序）
 */
function ai_ops_get_revision_keys( int $post_id ): array {
	$all_meta = get_post_meta( $post_id );
	$keys     = [];

	foreach ( $all_meta as $meta_key => $values ) {
		if ( 0 === strpos( (string) $meta_key, '_ai_ops_revision_' ) ) {
			$keys[] = (string) $meta_key;
		}
	}

	rsort( $keys, SORT_STRING );
	return $keys;
}

/**
 * 获取单条 AI Revision
 */
function ai_ops_get_revision( int $post_id, string $revision_key ): ?array {
	if ( 0 !== strpos( $revision_key, '_ai_ops_revision_' ) ) {
		return null;
	}

	$raw = get_post_meta( $post_id, $revision_key, true );
	if ( empty( $raw ) ) {
		return null;
	}

	$data = json_decode( $raw, true );
	if ( ! is_array( $data ) ) {
		return null;
	}

	$data['_key'] = $revision_key;
	return $data;
}

/**
 * 获取所有 AI Revisions 内容
 */
function ai_ops_get_revisions( int $post_id ): array {
	$keys      = ai_ops_get_revision_keys( $post_id );
	$revisions = [];

	foreach ( $keys as $key ) {
		$rev = ai_ops_get_revision( $post_id, $key );
		if ( $rev ) {
			$revisions[] = $rev;
		}
	}

	return $revisions;
}

/**
 * Apply 一条 AI Revision 到线上
 */
function ai_ops_apply_revision( int $post_id, string $revision_key ): array {
	$revision = ai_ops_get_revision( $post_id, $revision_key );
	if ( ! $revision ) {
		return [ 'ok' => false, 'error' => 'revision_not_found' ];
	}

	// 先备份当前线上内容
	$backup_key = '_ai_ops_revision_baseline_' . gmdate( 'YmdHis' );
	$baseline   = [
		'created_at'           => function_exists( 'wp_date' ) ? wp_date( DATE_ATOM ) : gmdate( DATE_ATOM ),
		'post_title'           => (string) get_post_field( 'post_title', $post_id, 'raw' ),
		'post_content'         => (string) get_post_field( 'post_content', $post_id, 'raw' ),
		'seo_title'            => (string) get_post_meta( $post_id, '_seopress_titles_title', true ),
		'seo_meta_description' => (string) get_post_meta( $post_id, '_seopress_titles_desc', true ),
		'label'                => 'Baseline before applying revision: ' . $revision_key,
	];
	update_post_meta( $post_id, $backup_key, wp_json_encode( $baseline, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );

	// 写入线上
	$post_update = [
		'ID'           => $post_id,
		'post_content' => wp_slash( linsy_blog_import_filter_html( $revision['post_content_html'] ) ),
	];

	if ( ! empty( $revision['post_title'] ) ) {
		$post_update['post_title'] = wp_slash( $revision['post_title'] );
	}

	wp_update_post( $post_update );
	update_post_meta( $post_id, '_seopress_titles_title', $revision['seo_title'] );
	update_post_meta( $post_id, '_seopress_titles_desc', $revision['seo_meta_description'] );

	// 标记该 revision 已应用
	$revision['applied']    = true;
	$revision['applied_at'] = function_exists( 'wp_date' ) ? wp_date( DATE_ATOM ) : gmdate( DATE_ATOM );
	update_post_meta( $post_id, $revision_key, wp_json_encode( $revision, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );

	// 更新 AI 状态
	ai_ops_set_status( $post_id, 'approved' );

	return [
		'ok'           => true,
		'post_id'      => $post_id,
		'revision_key' => $revision_key,
		'baseline_key' => $backup_key,
	];
}

// ============================================================
// 权限检查
// ============================================================

function ai_ops_check_permission(): bool {
	return current_user_can( 'edit_posts' );
}

function ai_ops_check_post_permission( int $post_id ): bool {
	$post = get_post( $post_id );
	if ( ! $post || 'post' !== $post->post_type ) {
		return false;
	}
	return current_user_can( 'edit_post', $post_id );
}

// ============================================================
// 端点实现
// ============================================================

/**
 * GET /ai-ops/v1/status
 */
function ai_ops_rest_status( WP_REST_Request $request ): WP_REST_Response {
	return new WP_REST_Response( [
		'connector'          => 'ai-ops-connector',
		'version'            => AI_OPS_CONNECTOR_VERSION,
		'wordpress_version'  => get_bloginfo( 'version' ),
		'site_url'           => home_url(),
		'site_name'          => get_bloginfo( 'name' ),
		'total_posts'        => (int) wp_count_posts( 'post' )->publish,
		'ai_statuses'        => ai_ops_valid_statuses(),
		'endpoints'          => [
			'GET  /posts'                             => 'List posts',
			'GET  /posts/{id}'                        => 'Get post for SEO review',
			'POST /posts/{id}/optimize'               => 'Save AI optimized version',
			'GET  /posts/{id}/revisions'              => 'List AI revisions',
			'POST /posts/{id}/revisions/{key}/apply'  => 'Apply a revision to live',
		],
	] );
}

/**
 * GET /ai-ops/v1/posts
 */
function ai_ops_rest_list_posts( WP_REST_Request $request ): WP_REST_Response {
	$per_page  = min( max( (int) $request->get_param( 'per_page' ), 1 ), 100 );
	$page      = max( (int) $request->get_param( 'page' ), 1 );
	$status    = sanitize_key( (string) $request->get_param( 'status' ) ) ?: 'publish';
	$category  = sanitize_text_field( (string) $request->get_param( 'category' ) );
	$search    = sanitize_text_field( (string) $request->get_param( 'search' ) );
	$ai_status = sanitize_key( (string) $request->get_param( 'ai_status' ) );
	$orderby   = sanitize_key( (string) $request->get_param( 'orderby' ) ) ?: 'date';
	$order     = strtoupper( sanitize_key( (string) $request->get_param( 'order' ) ) ) === 'ASC' ? 'ASC' : 'DESC';

	$args = [
		'post_type'           => 'post',
		'post_status'         => $status,
		'posts_per_page'      => $per_page,
		'paged'               => $page,
		'orderby'             => $orderby,
		'order'               => $order,
		'ignore_sticky_posts' => true,
		'no_found_rows'       => false,
	];

	if ( '' !== $search ) {
		$args['s'] = $search;
	}

	if ( '' !== $category ) {
		$args['category_name'] = $category;
	}

	if ( '' !== $ai_status && in_array( $ai_status, ai_ops_valid_statuses(), true ) ) {
		$args['meta_query'] = [
			[
				'key'   => '_ai_ops_status',
				'value' => $ai_status,
			],
		];
	}

	$query = new WP_Query( $args );

	$posts = [];
	foreach ( $query->posts as $post ) {
		$posts[] = [
			'post_id'    => $post->ID,
			'post_title' => get_the_title( $post->ID ),
			'post_url'   => get_permalink( $post->ID ),
			'post_date'  => $post->post_date,
			'ai_status'  => ai_ops_get_status( $post->ID ),
		];
	}

	return new WP_REST_Response( [
		'total'       => (int) $query->found_posts,
		'per_page'    => $per_page,
		'page'        => $page,
		'total_pages' => (int) $query->max_num_pages,
		'posts'       => $posts,
	] );
}

/**
 * GET /ai-ops/v1/posts/{id}
 */
function ai_ops_rest_get_post( WP_REST_Request $request ): WP_REST_Response {
	$post_id = (int) $request->get_param( 'id' );

	if ( ! ai_ops_check_post_permission( $post_id ) ) {
		return new WP_REST_Response( [ 'error' => 'not_found_or_forbidden' ], 404 );
	}

	$data = linsy_blog_export_build_post_json( $post_id );
	if ( empty( $data ) ) {
		return new WP_REST_Response( [ 'error' => 'post_not_found' ], 404 );
	}

	$data['ai_status']         = ai_ops_get_status( $post_id );
	$data['ai_revision_count'] = count( ai_ops_get_revision_keys( $post_id ) );
	$data['categories']        = wp_get_post_categories( $post_id, [ 'fields' => 'names' ] );
	$data['tags']              = wp_get_post_tags( $post_id, [ 'fields' => 'names' ] );
	$data['post_modified']     = get_post_field( 'post_modified', $post_id );

	unset( $data['_export_slug'] );

	return new WP_REST_Response( $data );
}

/**
 * POST /ai-ops/v1/posts/{id}/optimize
 */
function ai_ops_rest_optimize_post( WP_REST_Request $request ): WP_REST_Response {
	$post_id = (int) $request->get_param( 'id' );

	if ( ! ai_ops_check_post_permission( $post_id ) ) {
		return new WP_REST_Response( [ 'error' => 'not_found_or_forbidden' ], 404 );
	}

	$params = $request->get_json_params();
	if ( empty( $params ) ) {
		return new WP_REST_Response( [ 'error' => 'invalid_json_body' ], 400 );
	}

	$apply_directly = ! empty( $params['apply'] );

	$required = [ 'post_content_html', 'seo_title', 'seo_meta_description' ];
	foreach ( $required as $field ) {
		if ( ! isset( $params[ $field ] ) || '' === trim( (string) $params[ $field ] ) ) {
			return new WP_REST_Response( [ 'error' => 'missing_field', 'field' => $field ], 400 );
		}
	}

	if ( $apply_directly ) {
		$import_data = [
			'import_version'       => '1.0',
			'post_id'              => $post_id,
			'post_title'           => isset( $params['post_title'] ) ? (string) $params['post_title'] : '',
			'post_content_html'    => (string) $params['post_content_html'],
			'seo_title'            => (string) $params['seo_title'],
			'seo_meta_description' => (string) $params['seo_meta_description'],
		];

		$result = linsy_blog_import_apply( $post_id, $import_data, 'ai-ops-connector', false );

		if ( ! empty( $result['ok'] ) ) {
			ai_ops_set_status( $post_id, 'approved' );
			return new WP_REST_Response( [
				'ok'      => true,
				'mode'    => 'direct',
				'post_id' => $post_id,
			] );
		}

		return new WP_REST_Response( [
			'ok'    => false,
			'error' => isset( $result['error'] ) ? $result['error'] : 'apply_failed',
		], 500 );
	}

	$revision_key = ai_ops_save_revision( $post_id, $params );
	if ( '' === $revision_key ) {
		return new WP_REST_Response( [ 'error' => 'revision_save_failed' ], 500 );
	}

	ai_ops_set_status( $post_id, 'optimized' );

	return new WP_REST_Response( [
		'ok'           => true,
		'mode'         => 'revision',
		'post_id'      => $post_id,
		'revision_key' => $revision_key,
		'status'       => 'optimized',
	] );
}

/**
 * GET /ai-ops/v1/posts/{id}/revisions
 */
function ai_ops_rest_list_revisions( WP_REST_Request $request ): WP_REST_Response {
	$post_id = (int) $request->get_param( 'id' );

	if ( ! ai_ops_check_post_permission( $post_id ) ) {
		return new WP_REST_Response( [ 'error' => 'not_found_or_forbidden' ], 404 );
	}

	$revisions = ai_ops_get_revisions( $post_id );

	$summary = [];
	foreach ( $revisions as $rev ) {
		$summary[] = [
			'key'        => $rev['_key'] ?? '',
			'created_at' => $rev['created_at'] ?? '',
			'source'     => $rev['source'] ?? '',
			'applied'    => $rev['applied'] ?? false,
			'notes'      => $rev['notes'] ?? [],
			'title'      => $rev['post_title'] ?? '',
		];
	}

	return new WP_REST_Response( [
		'post_id'         => $post_id,
		'current_status'  => ai_ops_get_status( $post_id ),
		'revision_count'  => count( $summary ),
		'revisions'       => $summary,
	] );
}

/**
 * GET /ai-ops/v1/posts/{id}/revisions/{key}
 */
function ai_ops_rest_get_revision( WP_REST_Request $request ): WP_REST_Response {
	$post_id      = (int) $request->get_param( 'id' );
	$revision_key = $request->get_param( 'key' );

	if ( ! ai_ops_check_post_permission( $post_id ) ) {
		return new WP_REST_Response( [ 'error' => 'not_found_or_forbidden' ], 404 );
	}

	$revision = ai_ops_get_revision( $post_id, $revision_key );
	if ( ! $revision ) {
		return new WP_REST_Response( [ 'error' => 'revision_not_found' ], 404 );
	}

	return new WP_REST_Response( $revision );
}

/**
 * POST /ai-ops/v1/posts/{id}/revisions/{key}/apply
 */
function ai_ops_rest_apply_revision( WP_REST_Request $request ): WP_REST_Response {
	$post_id      = (int) $request->get_param( 'id' );
	$revision_key = $request->get_param( 'key' );

	if ( ! ai_ops_check_post_permission( $post_id ) ) {
		return new WP_REST_Response( [ 'error' => 'not_found_or_forbidden' ], 404 );
	}

	$result = ai_ops_apply_revision( $post_id, $revision_key );

	if ( empty( $result['ok'] ) ) {
		return new WP_REST_Response( $result, 404 );
	}

	return new WP_REST_Response( $result );
}

// ============================================================
// 注册 REST API 路由
// ============================================================

function ai_ops_register_routes() {
	register_rest_route( AI_OPS_API_NAMESPACE, '/status', [
		'methods'             => 'GET',
		'callback'            => 'ai_ops_rest_status',
		'permission_callback' => 'ai_ops_check_permission',
	] );

	register_rest_route( AI_OPS_API_NAMESPACE, '/posts', [
		'methods'             => 'GET',
		'callback'            => 'ai_ops_rest_list_posts',
		'permission_callback' => 'ai_ops_check_permission',
		'args'                => [
			'per_page'  => [
				'type'    => 'integer',
				'default' => 20,
				'minimum' => 1,
				'maximum' => 100,
			],
			'page'      => [
				'type'    => 'integer',
				'default' => 1,
				'minimum' => 1,
			],
			'status'    => [
				'type'    => 'string',
				'default' => 'publish',
			],
			'category'  => [ 'type' => 'string' ],
			'search'    => [ 'type' => 'string' ],
			'ai_status' => [
				'type' => 'string',
				'enum' => ai_ops_valid_statuses(),
			],
			'orderby'   => [
				'type'    => 'string',
				'default' => 'date',
				'enum'    => [ 'date', 'title', 'modified', 'ID' ],
			],
			'order'     => [
				'type'    => 'string',
				'default' => 'DESC',
				'enum'    => [ 'ASC', 'DESC' ],
			],
		],
	] );

	register_rest_route( AI_OPS_API_NAMESPACE, '/posts/(?P<id>\d+)', [
		'methods'             => 'GET',
		'callback'            => 'ai_ops_rest_get_post',
		'permission_callback' => '__return_true',
		'args'                => [
			'id' => [
				'type'     => 'integer',
				'required' => true,
			],
		],
	] );

	register_rest_route( AI_OPS_API_NAMESPACE, '/posts/(?P<id>\d+)/optimize', [
		'methods'             => 'POST',
		'callback'            => 'ai_ops_rest_optimize_post',
		'permission_callback' => '__return_true',
		'args'                => [
			'id' => [
				'type'     => 'integer',
				'required' => true,
			],
		],
	] );

	register_rest_route( AI_OPS_API_NAMESPACE, '/posts/(?P<id>\d+)/revisions', [
		'methods'             => 'GET',
		'callback'            => 'ai_ops_rest_list_revisions',
		'permission_callback' => '__return_true',
		'args'                => [
			'id' => [
				'type'     => 'integer',
				'required' => true,
			],
		],
	] );

	register_rest_route( AI_OPS_API_NAMESPACE, '/posts/(?P<id>\d+)/revisions/(?P<key>[a-zA-Z0-9_]+)', [
		'methods'             => 'GET',
		'callback'            => 'ai_ops_rest_get_revision',
		'permission_callback' => '__return_true',
		'args'                => [
			'id'  => [
				'type'     => 'integer',
				'required' => true,
			],
			'key' => [
				'type'     => 'string',
				'required' => true,
			],
		],
	] );

	register_rest_route( AI_OPS_API_NAMESPACE, '/posts/(?P<id>\d+)/revisions/(?P<key>[a-zA-Z0-9_]+)/apply', [
		'methods'             => 'POST',
		'callback'            => 'ai_ops_rest_apply_revision',
		'permission_callback' => '__return_true',
		'args'                => [
			'id'  => [
				'type'     => 'integer',
				'required' => true,
			],
			'key' => [
				'type'     => 'string',
				'required' => true,
			],
		],
	] );
}
add_action( 'rest_api_init', 'ai_ops_register_routes' );