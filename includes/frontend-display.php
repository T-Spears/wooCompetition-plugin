<?php
if (!defined('ABSPATH')) exit;

/**
 * Procedural renderer for product countdown + progress.
 * Hook: add_action('woocommerce_before_add_to_cart_button', 'raffall_render_countdown_and_progress', 5);
 */
function raffall_render_countdown_and_progress() {
	global $product;

	// Try to obtain a WC_Product instance safely
	$prod = null;
	if (function_exists('wc_get_product')) {
		$prod = wc_get_product($product);
	} elseif (is_object($product) && method_exists($product, 'get_meta')) {
		$prod = $product;
	}

	if (!$prod) return;

	// Only render for competition products
	$is_comp = $prod->get_meta('_raff_is_competition');
	if ($is_comp !== 'yes' && $is_comp !== true) return;

	$show_countdown = get_option('raffall_show_countdown_product', '1') === '1';
	$show_progress  = get_option('raffall_show_progress_product', '1') === '1';
	if (!$show_countdown && !$show_progress) return;

	$draw = $prod->get_meta('_raff_draw_date'); // may be empty
	$cap  = (int) $prod->get_meta('_raff_ticket_cap');

	// Normalize stock
	$stock_raw = $prod->get_stock_quantity();
	$stock = is_numeric($stock_raw) ? (int) $stock_raw : 0;

	// If ticket cap unset, attempt to derive from stock/next ticket
	if ($cap < 1) {
		$next = (int) $prod->get_meta('_raff_next_ticket');
		if ($next < 1) $next = 1;
		if (is_numeric($stock_raw)) {
			$cap = $stock + max(0, $next - 1);
		}
	}

	$cap = max(0, $cap);
	$sold = ($cap > 0) ? max(0, $cap - max(0, $stock)) : 0;
	$percent = ($cap > 0) ? round(($sold / $cap) * 100) : 0;

	echo '<div class="raff-meta-block" style="margin-bottom:12px;">';

	if (!empty($draw) && $show_countdown) {
		echo '<div class="raff-countdown" data-draw="' . esc_attr($draw) . '">';
		echo '<div class="raff-flip" data-unit="days"><div class="number"><span class="current">00</span><span class="next">00</span></div><div class="label">Days</div></div>';
		echo '<div class="raff-flip" data-unit="hours"><div class="number"><span class="current">00</span><span class="next">00</span></div><div class="label">Hours</div></div>';
		echo '<div class="raff-flip" data-unit="minutes"><div class="number"><span class="current">00</span><span class="next">00</span></div><div class="label">Minutes</div></div>';
		echo '<div class="raff-flip" data-unit="seconds"><div class="number"><span class="current">00</span><span class="next">00</span></div><div class="label">Seconds</div></div>';
		echo '</div>';
	}

	if ($cap > 0 && $show_progress) {
		echo '<div class="raff-progress" aria-label="Tickets sold">';
		echo '<div class="raff-progress-bar" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="' . esc_attr($percent) . '" style="--raff-percent:' . esc_attr($percent) . '%; background: var(--raff-bg);">';
		echo '<span class="raff-progress-inner" style="width:' . esc_attr($percent) . '%; background: linear-gradient(90deg,var(--raff-fill),#3fb1ff);"></span>';
		echo '</div>';
		echo '<div class="raff-progress-text" style="font-size:13px;color:#333;margin-top:6px;">' . esc_html($percent) . '% sold (' . esc_html($sold) . '/' . esc_html($cap) . ')</div>';
		echo '</div>';
	}

	echo '</div>';
}
