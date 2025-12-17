<?php
if (!defined('ABSPATH')) exit;

function raffall_activate() {
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	$charset = $wpdb->get_charset_collate();

	$audit_sql = "CREATE TABLE {$wpdb->prefix}raffall_audit (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		event_type VARCHAR(64) NOT NULL,
		context JSON NULL,
		user_id BIGINT UNSIGNED NULL,
		order_id BIGINT UNSIGNED NULL,
		product_id BIGINT UNSIGNED NULL,
		created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (id)
	) $charset;";
	dbDelta($audit_sql);

	$instant_sql = "CREATE TABLE {$wpdb->prefix}raffall_instant_ledger (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		product_id BIGINT UNSIGNED NOT NULL,
		ticket_number BIGINT UNSIGNED NOT NULL,
		prize VARCHAR(255) NOT NULL,
		claimed TINYINT(1) NOT NULL DEFAULT 0,
		order_id BIGINT UNSIGNED NULL,
		user_id BIGINT UNSIGNED NULL,
		created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		UNIQUE KEY prod_ticket (product_id, ticket_number)
	) $charset;";
	dbDelta($instant_sql);

	if (!wp_next_scheduled('raffall_daily_gdpr_event')) {
		wp_schedule_event(time() + 3600, 'daily', 'raffall_daily_gdpr_event');
	}
}

function raffall_deactivate() {
	wp_clear_scheduled_hook('raffall_daily_gdpr_event');
}
