<?php
if (!defined('ABSPATH')) exit;

function raffall_register_account_endpoints() {
	add_rewrite_endpoint('tickets', EP_ROOT | EP_PAGES);
	add_rewrite_endpoint('instant-wins', EP_ROOT | EP_PAGES);
}

function raffall_add_account_menu_items($items) {
	$new = [];
	foreach ($items as $key => $label) {
		$new[$key] = $label;
		if ($key === 'orders') {
			$new['tickets'] = 'My tickets';
			$new['instant-wins'] = 'Instant wins';
		}
	}
	return $new;
}

function raffall_account_tickets_view() {
	$user_id = get_current_user_id();
	if (!$user_id) { echo '<p>Please log in to view your tickets.</p>'; return; }
	$orders = wc_get_orders(['customer_id'=>$user_id,'limit'=>-1,'status'=>['completed','processing']]);
	echo '<h3>Your tickets</h3><table class="shop_table">';
	foreach ($orders as $order) {
		foreach ($order->get_items('line_item') as $item) {
			$tickets = wc_get_order_item_meta($item->get_id(), '_raff_tickets', true);
			if (!$tickets) continue;
			$product = $item->get_product();
			echo '<tr><td>#'.esc_html($order->get_id()).'</td><td>'.esc_html($product ? $product->get_name() : 'Competition').'</td><td>'.esc_html($tickets).'</td></tr>';
		}
	}
	echo '</table>';
}

function raffall_account_instant_wins_view() {
	$user_id = get_current_user_id();
	if (!$user_id) { echo '<p>Please log in to view instant wins.</p>'; return; }
	global $wpdb;
	$table = $wpdb->prefix . 'raffall_instant_ledger';
	$rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table WHERE user_id=%d AND claimed=1 ORDER BY created_at DESC", $user_id), ARRAY_A);
	echo '<h3>Your instant wins</h3>';
	if (empty($rows)) { echo '<p>No instant wins yet.</p>'; return; }
	echo '<table class="shop_table">';
	foreach ($rows as $r) echo '<tr><td>'.esc_html(get_the_title($r['product_id'])).'</td><td>'.esc_html($r['ticket_number']).'</td><td>'.esc_html($r['prize']).'</td></tr>';
	echo '</table>';
}
