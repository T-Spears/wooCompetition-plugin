<?php
if (!defined('ABSPATH')) exit;

function raffall_shortcode_home($atts) {
	if (!function_exists('wc_get_products')) return '<p>Premium competitions are unavailable because WooCommerce is not active.</p>';
	$atts = shortcode_atts(['show_countdown'=>null,'show_progress'=>null,'fill_color'=>null,'bg_color'=>null], (array)$atts, 'raffall_home');
	$prods = wc_get_products(['limit'=>-1,'status'=>'publish']);
	$items = [];
	foreach ($prods as $p) if ($p->get_meta('_raff_is_competition') === 'yes') {
		$items[] = ['id'=>$p->get_id(),'name'=>$p->get_name(),'draw'=>$p->get_meta('_raff_draw_date'),'price'=>$p->get_price(),'product_obj'=>$p];
	}
	ob_start();
	echo '<div class="raff-home"><h2>Premium competitions</h2>';
	raffall_render_cards($items, false, $atts);
	echo '</div>';
	return ob_get_clean();
}

function raffall_shortcode_winners($atts) {
	$q = new WP_Query(['post_type'=>'raff_winner','posts_per_page'=>12]);
	ob_start();
	echo '<div class="raff-winners"><h2>Recent Winners</h2>';
	if ($q->have_posts()) { while ($q->have_posts()) { $q->the_post(); echo '<div>'.esc_html(get_the_title()).'</div>'; } wp_reset_postdata(); } else echo '<p>No winners published yet.</p>';
	echo '</div>';
	return ob_get_clean();
}

function raffall_render_cards(array $items, bool $show_free_info=false, array $opts=[]) {
	if (empty($items)) { echo '<p>No items available.</p>'; return; }
	echo '<div class="raff-grid">';
	foreach ($items as $it) {
		echo '<div class="raff-card"><h3>'.esc_html($it['name']).'</h3>';
		echo '<p>Draw: '.esc_html($it['draw'] ?: 'TBA').'</p>';
		echo '<p>Price: '.(function_exists('wc_price') ? wc_price($it['price']) : esc_html($it['price'])).'</p>';
		echo '<a class="button" href="'.esc_url(get_permalink($it['id'])).'">View competition</a></div>';
	}
	echo '</div>';
}
