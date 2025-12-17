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

	// Flatpickr (existing)
	wp_register_script('raffall-flatpickr-js', 'https://cdn.jsdelivr.net/npm/flatpickr', [], '4.6.13', true);
	wp_register_style('raffall-flatpickr-css', 'https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css', [], '4.6.13');
	wp_enqueue_script('raffall-flatpickr-js');
	wp_enqueue_style('raffall-flatpickr-css');

	// Admin initializer (existing)
	wp_register_script('raffall-admin-draw', plugin_dir_url(__DIR__) . 'assets/admin-raff-draw.js', ['jquery','raffall-flatpickr-js'], '1.0', true);
	wp_enqueue_script('raffall-admin-draw');

	// Load question styles CSS into admin so preview looks like frontend
	wp_register_style('raffall-question-styles-admin', plugin_dir_url(__DIR__) . 'assets/raffall-question-styles.css', [], '1.0.0');
	wp_enqueue_style('raffall-question-styles-admin');

	// Admin preview JS (new) that updates preview in product editor
	wp_register_script('raffall-admin-preview', plugin_dir_url(__DIR__) . 'assets/admin-question-preview.js', ['jquery'], '1.0.0', true);
	wp_enqueue_script('raffall-admin-preview');

	// Enqueue admin card preview styles & script
	wp_register_style('raffall-admin-card-preview-css', plugin_dir_url(__DIR__) . 'assets/admin-card-preview.css', [], '1.0.0');
	wp_enqueue_style('raffall-admin-card-preview-css');

	wp_register_script('raffall-admin-card-preview', plugin_dir_url(__DIR__) . 'assets/admin-card-preview.js', ['jquery'], '1.0.0', true);
	wp_enqueue_script('raffall-admin-card-preview');

	// Localize timezones for draw script (existing)
	$tzs = function_exists('timezone_identifiers_list') ? timezone_identifiers_list() : [];
	wp_localize_script('raffall-admin-draw', 'raffAllAdminData', ['timezones' => $tzs]);

	// Localize preview settings for the admin preview script
	$preview_data = [
		'style' => get_option('raffall_question_style', 'radios'),
		'btn_bg' => get_option('raffall_question_btn_bg', '#ffffff'),
		'btn_text' => get_option('raffall_question_btn_text', '#222222'),
		'btn_bg_active' => get_option('raffall_question_btn_bg_active', '#7b3cff'),
		'text_strings' => [
			'select_placeholder' => __('— Select an option —','raffall'),
		],
	];
	wp_localize_script('raffall-admin-preview', 'raffAllPreviewData', $preview_data);

	// Localize preview settings (so preview matches frontend options)
	$card_preview_data = [
		'show_countdown_cards' => get_option('raffall_show_countdown_cards','0') === '1',
		'show_progress_cards' => get_option('raffall_show_progress_cards','0') === '1',
		'fill_color' => get_option('raffall_fill_color', '#7b3cff'),
		'bg_color' => get_option('raffall_bg_color', '#f1f1f1'),
		'question_btn_bg' => get_option('raffall_question_btn_bg', '#ffffff'),
		'question_btn_text' => get_option('raffall_question_btn_text', '#222222'),
		'question_btn_active' => get_option('raffall_question_btn_bg_active', '#7b3cff'),
	];
	wp_localize_script('raffall-admin-card-preview', 'raffAllCardPreviewData', $card_preview_data);
}
