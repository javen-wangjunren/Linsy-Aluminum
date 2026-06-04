<?php
/**
 * Theme functions and definitions
 *
 * @package HelloElementor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

define( 'HELLO_ELEMENTOR_VERSION', '3.4.6' );
define( 'EHP_THEME_SLUG', 'hello-elementor' );

define( 'HELLO_THEME_PATH', get_template_directory() );
define( 'HELLO_THEME_URL', get_template_directory_uri() );
define( 'HELLO_THEME_ASSETS_PATH', HELLO_THEME_PATH . '/assets/' );
define( 'HELLO_THEME_ASSETS_URL', HELLO_THEME_URL . '/assets/' );
define( 'HELLO_THEME_SCRIPTS_PATH', HELLO_THEME_ASSETS_PATH . 'js/' );
define( 'HELLO_THEME_SCRIPTS_URL', HELLO_THEME_ASSETS_URL . 'js/' );
define( 'HELLO_THEME_STYLE_PATH', HELLO_THEME_ASSETS_PATH . 'css/' );
define( 'HELLO_THEME_STYLE_URL', HELLO_THEME_ASSETS_URL . 'css/' );
define( 'HELLO_THEME_IMAGES_PATH', HELLO_THEME_ASSETS_PATH . 'images/' );
define( 'HELLO_THEME_IMAGES_URL', HELLO_THEME_ASSETS_URL . 'images/' );

define( 'LINSY_CONTACT_WHATSAPP_URL', 'https://api.whatsapp.com/send?phone=8618124703776' );
define( 'LINSY_CONTACT_PHONE_TEL', '+8618124703776' );
define( 'LINSY_CONTACT_EMAIL', 'david@linsyaluminum.com' );

if ( ! isset( $content_width ) ) {
	$content_width = 800; // Pixels.
}

if ( ! function_exists( 'hello_elementor_setup' ) ) {
	/**
	 * Set up theme support.
	 *
	 * @return void
	 */
	function hello_elementor_setup() {
		if ( is_admin() ) {
			hello_maybe_update_theme_version_in_db();
		}

		if ( apply_filters( 'hello_elementor_register_menus', true ) ) {
			register_nav_menus( [ 'menu-1' => esc_html__( 'Header', 'hello-elementor' ) ] );
			register_nav_menus( [ 'menu-2' => esc_html__( 'Footer', 'hello-elementor' ) ] );
		}

		if ( apply_filters( 'hello_elementor_post_type_support', true ) ) {
			add_post_type_support( 'page', 'excerpt' );
		}

		if ( apply_filters( 'hello_elementor_add_theme_support', true ) ) {
			add_theme_support( 'post-thumbnails' );
			add_theme_support( 'automatic-feed-links' );
			add_theme_support( 'title-tag' );
			add_theme_support(
				'html5',
				[
					'search-form',
					'comment-form',
					'comment-list',
					'gallery',
					'caption',
					'script',
					'style',
					'navigation-widgets',
				]
			);
			add_theme_support(
				'custom-logo',
				[
					'height'      => 100,
					'width'       => 350,
					'flex-height' => true,
					'flex-width'  => true,
				]
			);
			add_theme_support( 'align-wide' );
			add_theme_support( 'responsive-embeds' );

			/*
			 * Editor Styles
			 */
			add_theme_support( 'editor-styles' );
			add_editor_style( 'assets/css/editor-styles.css' );

			/*
			 * WooCommerce.
			 */
			if ( apply_filters( 'hello_elementor_add_woocommerce_support', true ) ) {
				// WooCommerce in general.
				add_theme_support( 'woocommerce' );
				// Enabling WooCommerce product gallery features (are off by default since WC 3.0.0).
				// zoom.
				add_theme_support( 'wc-product-gallery-zoom' );
				// lightbox.
				add_theme_support( 'wc-product-gallery-lightbox' );
				// swipe.
				add_theme_support( 'wc-product-gallery-slider' );
			}
		}
	}
}
add_action( 'after_setup_theme', 'hello_elementor_setup' );

function hello_maybe_update_theme_version_in_db() {
	$theme_version_option_name = 'hello_theme_version';
	// The theme version saved in the database.
	$hello_theme_db_version = get_option( $theme_version_option_name );

	// If the 'hello_theme_version' option does not exist in the DB, or the version needs to be updated, do the update.
	if ( ! $hello_theme_db_version || version_compare( $hello_theme_db_version, HELLO_ELEMENTOR_VERSION, '<' ) ) {
		update_option( $theme_version_option_name, HELLO_ELEMENTOR_VERSION );
	}
}

if ( ! function_exists( 'hello_elementor_display_header_footer' ) ) {
	/**
	 * Check whether to display header footer.
	 *
	 * @return bool
	 */
	function hello_elementor_display_header_footer() {
		$hello_elementor_header_footer = true;

		return apply_filters( 'hello_elementor_header_footer', $hello_elementor_header_footer );
	}
}

if ( ! function_exists( 'hello_elementor_scripts_styles' ) ) {
	/**
	 * Theme Scripts & Styles.
	 *
	 * @return void
	 */
	function hello_elementor_scripts_styles() {
		if ( apply_filters( 'hello_elementor_enqueue_style', true ) ) {
			wp_enqueue_style(
				'hello-elementor',
				HELLO_THEME_STYLE_URL . 'reset.css',
				[],
				HELLO_ELEMENTOR_VERSION
			);
		}

		if ( apply_filters( 'hello_elementor_enqueue_theme_style', true ) ) {
			wp_enqueue_style(
				'hello-elementor-theme-style',
				HELLO_THEME_STYLE_URL . 'theme.css',
				[],
				HELLO_ELEMENTOR_VERSION
			);
		}

		if ( hello_elementor_display_header_footer() ) {
			wp_enqueue_style(
				'hello-elementor-header-footer',
				HELLO_THEME_STYLE_URL . 'header-footer.css',
				[],
				HELLO_ELEMENTOR_VERSION
			);
		}
	}
}
add_action( 'wp_enqueue_scripts', 'hello_elementor_scripts_styles' );

if ( ! function_exists( 'hello_elementor_register_elementor_locations' ) ) {
	/**
	 * Register Elementor Locations.
	 *
	 * @param ElementorPro\Modules\ThemeBuilder\Classes\Locations_Manager $elementor_theme_manager theme manager.
	 *
	 * @return void
	 */
	function hello_elementor_register_elementor_locations( $elementor_theme_manager ) {
		if ( apply_filters( 'hello_elementor_register_elementor_locations', true ) ) {
			$elementor_theme_manager->register_all_core_location();
		}
	}
}
add_action( 'elementor/theme/register_locations', 'hello_elementor_register_elementor_locations' );

if ( ! function_exists( 'hello_elementor_content_width' ) ) {
	/**
	 * Set default content width.
	 *
	 * @return void
	 */
	function hello_elementor_content_width() {
		$GLOBALS['content_width'] = apply_filters( 'hello_elementor_content_width', 800 );
	}
}
add_action( 'after_setup_theme', 'hello_elementor_content_width', 0 );

if ( ! function_exists( 'hello_elementor_add_description_meta_tag' ) ) {
	/**
	 * Add description meta tag with excerpt text.
	 *
	 * @return void
	 */
	function hello_elementor_add_description_meta_tag() {
		if ( ! apply_filters( 'hello_elementor_description_meta_tag', true ) ) {
			return;
		}

		if ( ! is_singular() ) {
			return;
		}

		$post = get_queried_object();
		if ( empty( $post->post_excerpt ) ) {
			return;
		}

		echo '<meta name="description" content="' . esc_attr( wp_strip_all_tags( $post->post_excerpt ) ) . '">' . "\n";
	}
}
add_action( 'wp_head', 'hello_elementor_add_description_meta_tag' );

// Settings page
require get_template_directory() . '/includes/settings-functions.php';

// Header & footer styling option, inside Elementor
require get_template_directory() . '/includes/elementor-functions.php';

require get_template_directory() . '/includes/woocommerce-conditional-assets.php';

if ( ! function_exists( 'hello_elementor_customizer' ) ) {
	// Customizer controls
	function hello_elementor_customizer() {
		if ( ! is_customize_preview() ) {
			return;
		}

		if ( ! hello_elementor_display_header_footer() ) {
			return;
		}

		require get_template_directory() . '/includes/customizer-functions.php';
	}
}
add_action( 'init', 'hello_elementor_customizer' );

if ( ! function_exists( 'hello_elementor_check_hide_title' ) ) {
	/**
	 * Check whether to display the page title.
	 *
	 * @param bool $val default value.
	 *
	 * @return bool
	 */
	function hello_elementor_check_hide_title( $val ) {
		if ( defined( 'ELEMENTOR_VERSION' ) ) {
			$current_doc = Elementor\Plugin::instance()->documents->get( get_the_ID() );
			if ( $current_doc && 'yes' === $current_doc->get_settings( 'hide_title' ) ) {
				$val = false;
			}
		}
		return $val;
	}
}
add_filter( 'hello_elementor_page_title', 'hello_elementor_check_hide_title' );

/**
 * BC:
 * In v2.7.0 the theme removed the `hello_elementor_body_open()` from `header.php` replacing it with `wp_body_open()`.
 * The following code prevents fatal errors in child themes that still use this function.
 */
if ( ! function_exists( 'hello_elementor_body_open' ) ) {
	function hello_elementor_body_open() {
		wp_body_open();
	}
}

require HELLO_THEME_PATH . '/theme.php';

HelloTheme\Theme::instance();

/**
 * 架构师优化：禁止对带有特定查询参数的页面进行 Object Cache
 * 减少 Redis Key 数量，防止 20 语言导致的缓存膨胀
 */
add_filter('wp_cache_get_fallback', function($fallback, $key, $group) {
    // 如果 URL 中包含搜索、分页等可能被爬虫利用的参数，不存入缓存
    if (isset($_GET['s']) || isset($_GET['paged'])) {
        return false; 
    }
    return $fallback;
}, 10, 3);


add_action('wp_head', function() {
    if ( is_singular() ) { // 只在详情页（产品页、文章页、单页面）执行
        global $post;
        
        $post_type = $post->post_type; // 获取类型：product, post, page
        $post_slug = $post->post_name; // 获取英文名：比如 aluminum-sheet
        
        // 核心判断逻辑：完全匹配你的 URL 结构
        if ( $post_type === 'product' ) {
            // 只有产品，加上 /product/ 前缀
            $full_original_path = '/product/' . $post_slug;
        } else {
            // 其他所有情况（blog, page），直接前面加个斜杠
            $full_original_path = '/' . $post_slug;
        }
        
        // 输出给 GTM
        echo '<script>window.original_page_slug = "' . esc_js($full_original_path) . '";</script>';
    }
});

function linsy_contact_widget_enqueue_assets() {
	if ( is_admin() ) {
		return;
	}

	wp_enqueue_style(
		'linsy-contact-widget',
		HELLO_THEME_STYLE_URL . 'contact-widget.css',
		[ 'hello-elementor-theme-style' ],
		HELLO_ELEMENTOR_VERSION
	);

	wp_enqueue_script(
		'linsy-contact-widget',
		HELLO_THEME_SCRIPTS_URL . 'contact-widget.js',
		[],
		HELLO_ELEMENTOR_VERSION,
		true
	);
}
add_action( 'wp_enqueue_scripts', 'linsy_contact_widget_enqueue_assets', 20 );

function linsy_contact_widget_inline_svg( string $name ): string {
	$base_dir = trailingslashit( HELLO_THEME_IMAGES_PATH . 'contact-widget' );
	$path = $base_dir . $name . '.svg';

	$real_base = realpath( $base_dir );
	$real_path = realpath( $path );

	if ( ! $real_base || ! $real_path || 0 !== strpos( $real_path, $real_base ) ) {
		return '';
	}

	$svg = file_get_contents( $real_path );
	if ( false === $svg ) {
		return '';
	}

	$allowed = [
		'svg'      => [
			'xmlns'             => true,
			'viewbox'           => true,
			'width'             => true,
			'height'            => true,
			'fill'              => true,
			'stroke'            => true,
			'stroke-width'      => true,
			'role'              => true,
			'aria-hidden'       => true,
			'focusable'         => true,
			'class'             => true,
			'preserveaspectratio' => true,
		],
		'g'        => [
			'fill'         => true,
			'stroke'       => true,
			'stroke-width' => true,
			'transform'    => true,
			'class'        => true,
		],
		'path'     => [
			'd'               => true,
			'fill'            => true,
			'stroke'          => true,
			'stroke-width'    => true,
			'stroke-linecap'  => true,
			'stroke-linejoin' => true,
			'fill-rule'       => true,
			'clip-rule'       => true,
		],
		'circle'   => [
			'cx'           => true,
			'cy'           => true,
			'r'            => true,
			'fill'         => true,
			'stroke'       => true,
			'stroke-width' => true,
		],
		'rect'     => [
			'x'            => true,
			'y'            => true,
			'width'        => true,
			'height'       => true,
			'rx'           => true,
			'ry'           => true,
			'fill'         => true,
			'stroke'       => true,
			'stroke-width' => true,
		],
		'line'     => [
			'x1'            => true,
			'y1'            => true,
			'x2'            => true,
			'y2'            => true,
			'stroke'        => true,
			'stroke-width'  => true,
			'stroke-linecap' => true,
		],
		'polyline' => [
			'points'       => true,
			'fill'         => true,
			'stroke'       => true,
			'stroke-width' => true,
		],
		'polygon'  => [
			'points'       => true,
			'fill'         => true,
			'stroke'       => true,
			'stroke-width' => true,
		],
		'title'    => [],
		'desc'     => [],
	];

	return wp_kses( $svg, $allowed );
}

function linsy_render_contact_widget() {
	if ( is_admin() ) {
		return;
	}

	if ( did_action( 'elementor/loaded' ) && class_exists( '\Elementor\Plugin' ) ) {
		$plugin = \Elementor\Plugin::instance();
		if ( isset( $plugin->editor ) && method_exists( $plugin->editor, 'is_edit_mode' ) && $plugin->editor->is_edit_mode() ) {
			return;
		}
	}

	get_template_part( 'template-parts/contact-widget' );
}
add_action( 'wp_footer', 'linsy_render_contact_widget', 20 );
