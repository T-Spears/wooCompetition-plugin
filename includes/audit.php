<?php
if (!defined('ABSPATH')) exit;

function raffall_audit(string $event, array $ctx = []) {
	try {
		global $wpdb;
		$table = $wpdb->prefix . 'raffall_audit';
		$wpdb->insert($table, [
			'event_type' => $event,
			'context' => wp_json_encode($ctx, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
			'user_id' => get_current_user_id() ?: null,
			'order_id' => $ctx['order_id'] ?? null,
			'product_id' => $ctx['product_id'] ?? null,
			'created_at' => current_time('mysql'),
		]);
	} catch (\Throwable $e) {
		// ignore
	}
}

function raffall_mask(string $s): string {
	if (strlen($s) <= 2) return '*';
	return substr($s,0,1) . str_repeat('*', max(1, strlen($s)-2)) . substr($s,-1);
}
