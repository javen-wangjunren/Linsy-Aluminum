<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function linsy_hide_b2b_product_admin_filters() {
	if ( ! is_admin() || ! function_exists( 'get_current_screen' ) ) {
		return;
	}

	$screen = get_current_screen();
	if ( ! $screen || 'edit-product' !== $screen->id ) {
		return;
	}
	?>
	<style>
		body.post-type-product .tablenav select[name="stock_status"],
		body.post-type-product .tablenav select[name="product_brand"],
		body.post-type-product .tablenav select#product_brand {
			display: none !important;
		}
	</style>
	<script>
		document.addEventListener('DOMContentLoaded', function () {
			['select[name="stock_status"]', 'select[name="product_brand"]', 'select#product_brand'].forEach(function (selector) {
				document.querySelectorAll(selector).forEach(function (node) {
					var next = node.nextElementSibling;
					if (next && next.classList.contains('select2')) {
						next.remove();
					}
					node.remove();
				});
			});
		});
	</script>
	<?php
}
add_action( 'admin_head-edit.php', 'linsy_hide_b2b_product_admin_filters' );

function linsy_hide_product_edit_clear_cache_button() {
	if ( ! is_admin() || ! function_exists( 'get_current_screen' ) ) {
		return;
	}

	$screen = get_current_screen();
	if ( ! $screen || 'product' !== $screen->post_type || 'post' !== $screen->base ) {
		return;
	}
	?>
	<style>
		body.post-type-product #submitpost #minor-publishing-actions a[href*="action=purge_cache"] {
			display: none !important;
		}
	</style>
	<script>
		document.addEventListener('DOMContentLoaded', function () {
			document.querySelectorAll('#submitpost #minor-publishing-actions a[href*="action=purge_cache"]').forEach(function (node) {
				var wrapper = node.closest('.misc-pub-section');
				if (wrapper) {
					wrapper.remove();
					return;
				}
				node.remove();
			});
		});
	</script>
	<?php
}
add_action( 'admin_head-post.php', 'linsy_hide_product_edit_clear_cache_button' );
