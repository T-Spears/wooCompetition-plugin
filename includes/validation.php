<?php
if (!defined('ABSPATH')) exit;

function raffall_render_validation_question() {
	global $product;
	if (!$product || $product->get_meta('_raff_is_competition') !== 'yes') return;
	$q = $product->get_meta('_raff_validation_question'); if (!$q) return;
	$opt1 = $product->get_meta('_raff_validation_option_1');
	$opt2 = $product->get_meta('_raff_validation_option_2');
	$opt3 = $product->get_meta('_raff_validation_option_3');
	if (empty($opt1) && empty($opt2) && empty($opt3)) {
		echo '<div class="raff-validation"><label><strong>'.esc_html($q).'</strong></label>';
		echo '<input type="text" id="raff_answer" name="raff_answer" placeholder="Your answer" required></div>';
		return;
	}
	echo '<fieldset class="raff-validation"><legend>'.esc_html($q).'</legend>';
	$opts = [1=>$opt1,2=>$opt2,3=>$opt3];
	foreach ($opts as $idx=>$label) {
		if (empty($label)) continue;
		echo '<div><input type="radio" id="raff_choice_'.$idx.'" name="raff_choice" value="'.$idx.'" required>';
		echo '<label for="raff_choice_'.$idx.'">'.esc_html($label).'</label></div>';
	}
	echo '<input type="hidden" name="raff_product_id" value="'.esc_attr($product->get_id()).'"></fieldset>';
}

function raffall_validate_question_before_add_to_cart($passed, $product_id, $quantity, $variation_id = 0) {
	$is_comp = get_post_meta($product_id, '_raff_is_competition', true) === 'yes';
	$question = get_post_meta($product_id, '_raff_validation_question', true);
	if ($is_comp && $question) {
		$opt1 = get_post_meta($product_id, '_raff_validation_option_1', true);
		$opt2 = get_post_meta($product_id, '_raff_validation_option_2', true);
		$opt3 = get_post_meta($product_id, '_raff_validation_option_3', true);
		$correct = get_post_meta($product_id, '_raff_validation_correct_option', true);
		$has_options = ($opt1 || $opt2 || $opt3) && in_array($correct, ['1','2','3'], true);
		if ($has_options) {
			$user_choice = isset($_POST['raff_choice']) ? sanitize_text_field($_POST['raff_choice']) : '';
			if (!$user_choice) { wc_add_notice('Please select an answer to the validation question.', 'error'); raffall_audit('validation_missing_choice',['product_id'=>$product_id]); return false; }
			if ($user_choice !== $correct) { wc_add_notice('Incorrect answer to the validation question.', 'error'); raffall_audit('validation_incorrect_choice',['product_id'=>$product_id,'choice'=>raffall_mask($user_choice)]); return false; }
			raffall_audit('validation_pass_choice',['product_id'=>$product_id,'choice'=>(int)$user_choice]); return $passed;
		}
		$answer = get_post_meta($product_id,'_raff_validation_answer',true);
		if ($answer) {
			$user_answer = isset($_POST['raff_answer']) ? trim($_POST['raff_answer']) : '';
			if (!$user_answer) { wc_add_notice('Please answer the validation question.','error'); raffall_audit('validation_missing',['product_id'=>$product_id]); return false; }
			if (mb_strtolower($user_answer) !== mb_strtolower($answer)) { wc_add_notice('Incorrect answer to the validation question.','error'); raffall_audit('validation_incorrect',['product_id'=>$product_id,'answer'=>raffall_mask($user_answer)]); return false; }
			raffall_audit('validation_pass',['product_id'=>$product_id]);
		}
	}
	return $passed;
}
