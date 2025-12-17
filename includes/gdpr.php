<?php
if (!defined('ABSPATH')) exit;

function raffall_gdpr_anonymize_job() {
	$threshold = (new DateTimeImmutable('-2 years'))->format('Y-m-d H:i:s');
	raffall_anonymize_users_before($threshold);
	raffall_anonymize_guest_orders_before($threshold);
}

function raffall_anonymize_users_before(string $datetime) {
	global $wpdb;
	$users = get_users(['fields'=>['ID'],'meta_query'=>[['relation'=>'OR'],['key'=>'raff_retain_personal_data','compare'=>'NOT EXISTS'],['key'=>'raff_retain_personal_data','value'=>'yes','compare'=>'!=']]]);
	foreach ($users as $u) {
		$last_order = $wpdb->get_var($wpdb->prepare("SELECT MAX(post_date) FROM {$wpdb->posts} WHERE post_type='shop_order' AND post_author=%d", $u->ID));
		if ($last_order && $last_order > $datetime) continue;
		update_user_meta($u->ID, 'first_name', '');
		update_user_meta($u->ID, 'last_name', '');
		update_user_meta($u->ID, 'billing_first_name', '');
		update_user_meta($u->ID, 'billing_last_name', '');
		update_user_meta($u->ID, 'billing_address_1', '');
		update_user_meta($u->ID, 'billing_city', '');
		update_user_meta($u->ID, 'billing_postcode', '');
		update_user_meta($u->ID, 'billing_phone', '');
		update_user_meta($u->ID, 'raff_email_hash', wp_hash(get_userdata($u->ID)->user_email));
		raffall_audit('gdpr_user_anonymized', ['user_id'=>$u->ID]);
	}
}

function raffall_anonymize_guest_orders_before(string $datetime) {
	global $wpdb;
	$orders = $wpdb->get_results($wpdb->prepare("SELECT ID FROM {$wpdb->posts} WHERE post_type='shop_order' AND post_date < %s", $datetime));
	foreach ($orders as $o) {
		$order = wc_get_order($o->ID);
		if (!$order) continue;
		if ($order->get_user_id()) continue;
		$order->set_billing_first_name(''); $order->set_billing_last_name('');
		$order->set_billing_address_1(''); $order->set_billing_city('');
		$order->set_billing_postcode(''); $order->set_billing_phone('');
		$order->save();
		raffall_audit('gdpr_guest_order_anonymized', ['order_id'=>$o->ID]);
	}
}
