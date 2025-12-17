<?php
if (!defined('ABSPATH')) exit;

/* Assign tickets on order complete, and reveal instant wins */
function raffall_assign_tickets_and_instant_wins($order_id) {
	$order = wc_get_order($order_id);
	if (!$order) return;
	foreach ($order->get_items('line_item') as $item_id => $item) {
		$product = $item->get_product();
		if (!$product) continue;
		$pid = $product->get_id();
		if ($product->get_meta('_raff_is_competition') !== 'yes') continue;
		$qty = (int)$item->get_quantity();
		$next = (int)$product->get_meta('_raff_next_ticket');
		if ($next < 1) $next = 1;
		$tickets = [];
		for ($i=0;$i<$qty;$i++) $tickets[] = $next++;
		$product->update_meta_data('_raff_next_ticket', $next);
		$product->save();
		wc_add_order_item_meta($item_id, '_raff_tickets', implode(',', $tickets));
		raffall_audit('tickets_assigned', ['order_id'=>$order_id,'product_id'=>$pid,'tickets'=>$tickets]);
		if ($product->get_meta('_raff_is_instant_win') === 'yes') {
			$wins = raffall_check_instant_wins($pid, $tickets, $order);
			if (!empty($wins)) {
				wc_add_order_item_meta($item_id, '_raff_instant_wins', wp_json_encode($wins));
				raffall_audit('instant_wins_revealed', ['order_id'=>$order_id,'product_id'=>$pid,'wins'=>$wins]);
				$note = "Instant wins:\n";
				foreach ($wins as $w) $note .= "Ticket {$w['ticket']} → {$w['prize']}\n";
				$order->add_order_note($note);
			}
		}
	}
}

/* Seed instant ledger from CSV */
function raffall_seed_instant_ledger(int $product_id, string $csv = '') {
	if (!$csv) return;
	global $wpdb;
	$table = $wpdb->prefix . 'raffall_instant_ledger';
	$lines = preg_split('/\r\n|\r|\n/', $csv);
	foreach ($lines as $line) {
		$line = trim($line); if (!$line) continue;
		$parts = array_map('trim', explode(',', $line, 2)); if (count($parts) < 2) continue;
		$ticket = (int)$parts[0]; $prize = sanitize_text_field($parts[1]);
		$wpdb->query($wpdb->prepare("INSERT IGNORE INTO $table (product_id, ticket_number, prize) VALUES (%d,%d,%s)", $product_id, $ticket, $prize));
	}
	raffall_audit('instant_seed', ['product_id'=>$product_id,'seeded'=>count($lines)]);
}

/* Check instant ledger for matches and mark claimed */
function raffall_check_instant_wins(int $product_id, array $tickets, WC_Order $order): array {
	global $wpdb;
	$table = $wpdb->prefix . 'raffall_instant_ledger';
	$wins = [];
	foreach ($tickets as $t) {
		$row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE product_id=%d AND ticket_number=%d AND claimed=0", $product_id, $t), ARRAY_A);
		if ($row) {
			$wins[] = ['ticket' => (int)$row['ticket_number'], 'prize' => $row['prize']];
			$wpdb->update($table, ['claimed'=>1,'order_id'=>$order->get_id(),'user_id'=>(int)$order->get_user_id()], ['id'=>(int)$row['id']]);
		}
	}
	return $wins;
}
