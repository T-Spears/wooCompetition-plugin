<?php
if (!defined('ABSPATH')) exit;

function raffall_shortcode_home($atts) {
	// Guard if WooCommerce not available
	if (!function_exists('wc_get_products')) {
		return '<p>Premium competitions are unavailable because WooCommerce is not active.</p>';
	}

	$atts = shortcode_atts([
		'show_countdown' => null,
		'show_progress' => null,
		'fill_color' => null,
		'bg_color' => null,
	], (array)$atts, 'raffall_home');

	// If shortcode overrides colours, inject inline style scoped to this output
	$inline_style = '';
	if ($atts['fill_color'] || $atts['bg_color']) {
		$fill = $atts['fill_color'] ? esc_attr($atts['fill_color']) : get_option('raffall_fill_color', '#7b3cff');
		$bg   = $atts['bg_color'] ? esc_attr($atts['bg_color']) : get_option('raffall_bg_color', '#f1f1f1');
		$inline_style = "<style>.raff-home{--raff-fill:{$fill};--raff-bg:{$bg};}</style>";
	}

	// Load all published products and filter featured competitions
	$prods = wc_get_products(['limit' => -1, 'status' => 'publish']);
	$competitions = [];
	foreach ($prods as $p) {
		// only competitions that are explicitly marked featured
		if ($p->get_meta('_raff_is_competition') === 'yes' && $p->get_meta('_raff_featured') === 'yes') {
			$competitions[] = [
				'id' => $p->get_id(),
				'name' => $p->get_name(),
				'price' => $p->get_price(),
				'stock' => $p->get_stock_quantity(),
				'draw' => $p->get_meta('_raff_draw_date'),
				'instant' => $p->get_meta('_raff_is_instant_win') === 'yes',
				'free' => $p->get_meta('_raff_is_free_entry') === 'yes',
				'url' => get_permalink($p->get_id()),
				'image' => wp_get_attachment_image_src($p->get_image_id(), 'medium')[0] ?? '',
				'product_obj' => $p,
			];
		}
	}

	ob_start();
	echo $inline_style;
	echo '<div class="raff-home">';
	// heading changed to "Featured competitions"
	echo '<h2>Featured competitions</h2>';
	if (empty($competitions)) {
		echo '<p>No featured competitions at this time.</p>';
	} else {
		$this->render_cards($competitions, false, $atts);
	}
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

// Shortcode: individual countdown
function raffall_shortcode_countdown($atts = []) {
	$atts = shortcode_atts([
		'product_id' => 0,
		'draw' => '',
	], (array)$atts, 'raffall_countdown');

	$draw = trim($atts['draw']);
	$pid  = (int)$atts['product_id'];

	// prefer explicit draw attribute if provided
	if (empty($draw) && $pid) {
		if (function_exists('wc_get_product')) {
			$p = wc_get_product($pid);
			if ($p) $draw = $p->get_meta('_raff_draw_date');
		}
	}

	if (empty($draw)) return ''; // nothing to render

	// Output same flip countdown structure used on product pages
	$html  = '<div class="raff-countdown" data-draw="' . esc_attr($draw) . '">';
	$html .= '<div class="raff-flip" data-unit="days"><div class="number"><span class="current">00</span><span class="next">00</span></div><div class="label">Days</div></div>';
	$html .= '<div class="raff-flip" data-unit="hours"><div class="number"><span class="current">00</span><span class="next">00</span></div><div class="label">Hours</div></div>';
	$html .= '<div class="raff-flip" data-unit="minutes"><div class="number"><span class="current">00</span><span class="next">00</span></div><div class="label">Minutes</div></div>';
	$html .= '<div class="raff-flip" data-unit="seconds"><div class="number"><span class="current">00</span><span class="next">00</span></div><div class="label">Seconds</div></div>';
	$html .= '</div>';

	return $html;
}
add_shortcode('raffall_countdown', 'raffall_shortcode_countdown');

// Shortcode: individual progress bar (tickets sold)
function raffall_shortcode_progress($atts = []) {
	$atts = shortcode_atts([
		'product_id' => 0,
	], (array)$atts, 'raffall_progress');

	$pid = (int)$atts['product_id'];
	if (!$pid) return '';

	if (!function_exists('wc_get_product')) return '';

	$p = wc_get_product($pid);
	if (!$p) return '';

	$cap = (int) $p->get_meta('_raff_ticket_cap');
	$stock_raw = $p->get_stock_quantity();
	$stock = is_numeric($stock_raw) ? (int)$stock_raw : 0;

	if ($cap < 1) {
		$next = (int) $p->get_meta('_raff_next_ticket');
		if ($next < 1) $next = 1;
		if (is_numeric($stock_raw)) $cap = $stock + max(0, $next - 1);
	}

	$cap = max(0, $cap);
	$sold = ($cap > 0) ? max(0, $cap - max(0, $stock)) : 0;
	$percent = ($cap > 0) ? round(($sold / $cap) * 100) : 0;

	$html  = '<div class="raff-progress" aria-label="Tickets sold">';
	$html .= '<div class="raff-progress-bar" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="' . esc_attr($percent) . '" style="--raff-percent:' . esc_attr($percent) . '%; background: var(--raff-bg);">';
	$html .= '<span class="raff-progress-inner" style="width:' . esc_attr($percent) . '%; background: linear-gradient(90deg,var(--raff-fill),#3fb1ff);"></span>';
	$html .= '</div>';
	$html .= '<div class="raff-progress-text" style="font-size:13px;color:#333;margin-top:6px;">' . esc_html($percent) . '% sold (' . esc_html($sold) . '/' . esc_html($cap) . ')</div>';
	$html .= '</div>';

	return $html;
}
add_shortcode('raffall_progress', 'raffall_shortcode_progress');
