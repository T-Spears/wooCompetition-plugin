<?php
if (!defined('ABSPATH')) exit;

function raffall_register_winner_cpt() {
	register_post_type('raff_winner', [
		'label' => 'Winners',
		'public' => true,
		'has_archive' => true,
		'menu_icon' => 'dashicons-awards',
		'supports' => ['title','editor','thumbnail'],
	]);
	register_post_meta('raff_winner', 'raff_competition_product_id', ['type'=>'integer','single'=>true,'show_in_rest'=>true]);
	register_post_meta('raff_winner', 'raff_prize_name', ['type'=>'string','single'=>true,'show_in_rest'=>true]);
	register_post_meta('raff_winner', 'raff_ticket_number', ['type'=>'number','single'=>true,'show_in_rest'=>true]);
	register_post_meta('raff_winner', 'raff_winner_name', ['type'=>'string','single'=>true,'show_in_rest'=>true]);
	register_post_meta('raff_winner', 'raff_consent_public', ['type'=>'boolean','single'=>true,'show_in_rest'=>true,'default'=>false]);
}
