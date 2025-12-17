<?php
if (!defined('ABSPATH')) exit;

function raffall_enqueue_raff_frontend_assets() {
	$has_wc = function_exists('wc_get_cart_url') && function_exists('wc_get_checkout_url');
	wp_register_script('raffall-frontend', plugin_dir_url(__DIR__) . 'assets/raffall-frontend.js', ['jquery'], '1.2.0', true);
	wp_register_style('raffall-frontend-css', plugin_dir_url(__DIR__) . 'assets/raffall-frontend.css', [], '1.2.0');
	wp_register_script('raffall-cart-sidebar', plugin_dir_url(__DIR__) . 'assets/raffall-cart-sidebar.js', ['jquery'], '1.2.0', true);
	wp_register_style('raffall-cart-sidebar-css', plugin_dir_url(__DIR__) . 'assets/raffall-cart-sidebar.css', [], '1.2.0');

	wp_enqueue_script('raffall-cart-sidebar');
	wp_enqueue_style('raffall-cart-sidebar-css');

	$sidebar_defaults = [
		'enabled' => get_option('raffall_cart_sidebar_enable', '1') === '1',
		'cart_url' => $has_wc ? wc_get_cart_url() : home_url('/cart/'),
		'checkout_url' => $has_wc ? wc_get_checkout_url() : home_url('/checkout/'),
		'strings' => [
			'title' => __('Your cart','raffall'),
			'view_cart' => __('View cart','raffall'),
			'checkout' => __('Checkout','raffall'),
		],
	];
	wp_localize_script('raffall-cart-sidebar', 'raffAllCartSidebar', $sidebar_defaults);

	wp_enqueue_script('raffall-frontend');
	wp_enqueue_style('raffall-frontend-css');

	$fill = esc_attr(get_option('raffall_fill_color', '#7b3cff'));
	$bg   = esc_attr(get_option('raffall_bg_color', '#f1f1f1'));
	$vars = ":root{ --raff-fill: {$fill}; --raff-bg: {$bg}; }";
	wp_add_inline_style('raffall-frontend-css', $vars);
}
