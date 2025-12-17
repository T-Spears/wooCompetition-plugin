<?php
if (!defined('ABSPATH')) exit;

function raffall_render_validation_question() {
    global $product;
    if (!$product || $product->get_meta('_raff_is_competition') !== 'yes') return;

    $q = $product->get_meta('_raff_validation_question');
    if (!$q) return;

    $opt1 = $product->get_meta('_raff_validation_option_1');
    $opt2 = $product->get_meta('_raff_validation_option_2');
    $opt3 = $product->get_meta('_raff_validation_option_3');

    // If options are missing, fall back to a single text input (backwards compatibility)
    if (empty($opt1) && empty($opt2) && empty($opt3)) {
        echo '<div class="raff-validation">';
        echo '<label for="raff_answer"><strong>' . esc_html($q) . '</strong></label>';
        echo '<input type="text" id="raff_answer" name="raff_answer" placeholder="Your answer" required style="margin-top:6px;">';
        echo '<p style="font-size:12px;color:#666;margin-top:4px;">Please answer to proceed.</p>';
        echo '</div>';
        return;
    }

    // Prefer per-product style if set, otherwise fall back to global option
    $style = $product->get_meta('_raff_question_style') ?: get_option('raffall_question_style', 'radios');

    echo '<fieldset class="raff-validation raff-validation--' . esc_attr($style) . '" style="margin-bottom:12px;border:0;padding:0;">';
    echo '<legend style="font-weight:600;margin-bottom:6px;">' . esc_html($q) . '</legend>';

    $opts = [
        1 => $opt1,
        2 => $opt2,
        3 => $opt3,
    ];

    if ($style === 'dropdown') {
        echo '<select name="raff_choice" id="raff_choice_select" required style="padding:8px;border-radius:6px;border:1px solid #ddd;">';
        echo '<option value="">' . esc_html__('— Select an option —','raffall') . '</option>';
        foreach ($opts as $idx => $label) {
            if (empty($label)) continue;
            echo '<option value="' . esc_attr($idx) . '">' . esc_html($label) . '</option>';
        }
        echo '</select>';
    } elseif ($style === 'buttons') {
        // Render accessible button group + hidden input AND hidden radio inputs for robust submission
        echo '<div class="raff-question-buttons-wrapper">';
        echo '<input type="hidden" name="raff_choice" class="raff-choice-hidden" value="">';
        foreach ($opts as $idx => $label) {
            if (empty($label)) continue;
            $rid = 'raff_choice_radio_' . $idx;
            echo '<input type="radio" id="' . esc_attr($rid) . '" name="raff_choice" value="' . esc_attr($idx) . '" class="raff-hidden-radio" />';
        }
        echo '<div class="raff-question-buttons" role="radiogroup" aria-label="' . esc_attr($q) . '">';
        foreach ($opts as $idx => $label) {
            if (empty($label)) continue;
            echo '<button type="button" class="raff-question-btn" data-value="' . esc_attr($idx) . '" aria-pressed="false" tabindex="0">' . esc_html($label) . '</button>';
        }
        echo '</div>';
        echo '</div>';
    } else {
        // default: radios (existing behaviour)
        $name = 'raff_choice';
        foreach ($opts as $idx => $label) {
            if (empty($label)) continue;
            $id = 'raff_choice_' . $idx;
            echo '<div style="margin-bottom:6px;">';
            echo '<input type="radio" id="' . esc_attr($id) . '" name="' . esc_attr($name) . '" value="' . esc_attr($idx) . '" required>';
            echo '<label for="' . esc_attr($id) . '" style="margin-left:8px;">' . esc_html($label) . '</label>';
            echo '</div>';
        }
    }

    echo '<input type="hidden" name="raff_product_id" value="' . esc_attr($product->get_id()) . '">';
    echo '<p style="font-size:12px;color:#666;margin-top:4px;">Select the correct option to proceed.</p>';
    echo '</fieldset>';
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
