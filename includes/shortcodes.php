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
    // opts can override global options: show_countdown, show_progress, fill_color, bg_color
    $show_countdown_cards = array_key_exists('show_countdown', $opts) && $opts['show_countdown'] !== null
        ? (bool)$opts['show_countdown']
        : (get_option('raffall_show_countdown_cards','0') === '1');
    $show_progress_cards = array_key_exists('show_progress', $opts) && $opts['show_progress'] !== null
        ? (bool)$opts['show_progress']
        : (get_option('raffall_show_progress_cards','0') === '1');

    if (empty($items)) { echo '<p>No items available.</p>'; return; }
    echo '<div class="raff-grid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:16px;">';
    foreach ($items as $it) {
        echo '<div class="raff-card" style="border:1px solid #eee;border-radius:12px;overflow:hidden;">';
        if ($it['image']) echo '<img src="' . esc_url($it['image']) . '" alt="" style="width:100%;height:auto;display:block;">';
        echo '<div style="padding:12px">';
        echo '<h3 style="margin:0 0 6px;">' . esc_html($it['name']) . '</h3>';
        echo '<p style="margin:0 0 8px;">Price: ' . wc_price($it['price']) . '</p>';

        // Optionally include countdown on product cards
        if ($show_countdown_cards && !empty($it['draw'])) {
            echo '<div class="raff-countdown" data-draw="' . esc_attr($it['draw']) . '" style="margin-bottom:8px;">';
            echo '<div class="raff-flip" data-unit="days"><div class="number"><span class="current">00</span><span class="next">00</span></div><div class="label">Days</div></div>';
            echo '<div class="raff-flip" data-unit="hours"><div class="number"><span class="current">00</span><span class="next">00</span></div><div class="label">Hours</div></div>';
            echo '<div class="raff-flip" data-unit="minutes"><div class="number"><span class="current">00</span><span class="next">00</span></div><div class="label">Minutes</div></div>';
            echo '<div class="raff-flip" data-unit="seconds"><div class="number"><span class="current">00</span><span class="next">00</span></div><div class="label">Seconds</div></div>';
            echo '</div>';
        }

        echo '<a class="button" href="' . esc_url($it['url']) . '">View competition</a>';
        // ...existing code...
        echo '</div></div>';
    }
    echo '</div>';
}
