<?php
if (!defined('ABSPATH')) exit;

function raffall_enqueue_admin_assets($hook = '') {
	// Only load on product edit/add screens (post.php/post-new.php)
	if (!is_admin()) return;
	if (!in_array($hook, ['post.php','post-new.php'], true)) return;
	if (function_exists('get_current_screen')) {
		$screen = get_current_screen();
		if (!$screen || ($screen->post_type ?? '') !== 'product') return;
	}

	wp_register_script('raffall-flatpickr-js', 'https://cdn.jsdelivr.net/npm/flatpickr', [], '4.6.13', true);
	wp_register_style('raffall-flatpickr-css', 'https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css', [], '4.6.13');
	wp_enqueue_script('raffall-flatpickr-js');
	wp_enqueue_style('raffall-flatpickr-css');

	wp_register_script('raffall-admin-draw', plugin_dir_url(__DIR__) . 'assets/admin-raff-draw.js', ['jquery','raffall-flatpickr-js'], '1.0', true);
	wp_enqueue_script('raffall-admin-draw');

	$tzs = function_exists('timezone_identifiers_list') ? timezone_identifiers_list() : [];
	wp_localize_script('raffall-admin-draw', 'raffAllAdminData', ['timezones' => $tzs]);
}
