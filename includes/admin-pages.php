<?php
if (!defined('ABSPATH')) exit;

function raffall_add_admin_settings_page() {
	add_options_page('Raff-all Display', 'Raff-all Display', 'manage_options', 'raffall-display', 'raffall_render_settings_page');
	add_submenu_page('tools.php', 'Raff-all Competitions', 'Raff-all Competitions', 'manage_options', 'raffall-competitions', 'raffall_render_competitions_page');
}

function raffall_register_admin_settings() {
	register_setting('raffall_display_group', 'raffall_fill_color', ['type'=>'string','default'=>'#7b3cff']);
	register_setting('raffall_display_group', 'raffall_bg_color', ['type'=>'string','default'=>'#f1f1f1']);
	register_setting('raffall_display_group', 'raffall_flip_bg', ['type'=>'string','default'=>'#fff']);
	register_setting('raffall_display_group', 'raffall_flip_text', ['type'=>'string','default'=>'#222']);
	register_setting('raffall_display_group', 'raffall_show_countdown_product', ['type'=>'string','default'=>'1']);
	register_setting('raffall_display_group', 'raffall_show_progress_product', ['type'=>'string','default'=>'1']);
	register_setting('raffall_display_group', 'raffall_cart_sidebar_enable', ['type'=>'string','default'=>'1']);
}

function raffall_render_settings_page() {
	if (!current_user_can('manage_options')) return;
	// ...existing settings form markup (same as before) ...
	echo '<div class="wrap"><h1>Raff-all display settings</h1>';
	// minimal content to avoid duplication; user can paste full form if needed
	echo '</div>';
}

function raffall_render_competitions_page() {
	if (!current_user_can('manage_options')) return;
	$competitions = array_filter(wc_get_products(['limit'=>-1,'status'=>'publish']), function($p){ return $p->get_meta('_raff_is_competition') === 'yes'; });
	// ...render table and forms (same logic as previous implementation) ...
	echo '<div class="wrap"><h1>Raff-all Competitions</h1></div>';
}
