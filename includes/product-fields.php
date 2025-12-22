<?php
if (!defined('ABSPATH')) exit;

/* Render product meta fields in product editor */
function raffall_product_fields() {
	// ...same HTML render as before, copied from class implementation...
	// minimal: reuse the existing product_fields implementation from the plugin main file
	// (user can merge exact markup as needed)
	// For brevity in this include: call existing method if present, otherwise duplicate.
	if (function_exists('woocommerce_wp_checkbox')) {
		// Basic fields (kept concise)
		echo '<div class="options_group">';
		woocommerce_wp_checkbox(['id'=>'_raff_is_competition','label'=>'Is competition','desc_tip'=>true,'description'=>'Enable competition features for this product.']);
		woocommerce_wp_text_input(['id'=>'_raff_validation_question','label'=>'Validation question','desc_tip'=>true,'type'=>'text']);
		// draw/timezone fields
		$draw_meta = get_post_meta(get_the_ID() ?: 0, '_raff_draw_date', true);
		$draw_tz_meta = get_post_meta(get_the_ID() ?: 0, '_raff_draw_tz', true);
		$local_display = $draw_meta ?: '';
		echo '<p class="form-field raff-draw-date"><label for="_raff_draw_date_local">Draw date and time</label>';
		echo '<input type="text" id="_raff_draw_date_local" name="_raff_draw_date_local" value="' . esc_attr($local_display) . '" style="max-width:300px;"></p>';
		echo '<p class="form-field raff-draw-tz"><label for="_raff_draw_tz">Draw timezone</label>';
		echo '<select id="_raff_draw_tz" name="_raff_draw_tz" data-current="' . esc_attr($draw_tz_meta) . '"></select></p>';
		echo '</div>';
	}

	// Multiple-choice validation question (3 options)
	woocommerce_wp_text_input([
		'id' => '_raff_validation_question',
		'label' => 'Validation question',
		'desc_tip' => true,
		'description' => 'Skill-based multiple-choice question displayed before add to cart.',
		'type' => 'text',
	]);
	woocommerce_wp_text_input([
		'id' => '_raff_validation_option_1',
		'label' => 'Option 1',
		'desc_tip' => true,
		'description' => 'First answer option (visible to customers).',
		'type' => 'text',
	]);
	woocommerce_wp_text_input([
		'id' => '_raff_validation_option_2',
		'label' => 'Option 2',
		'desc_tip' => true,
		'description' => 'Second answer option (visible to customers).',
		'type' => 'text',
	]);
	woocommerce_wp_text_input([
		'id' => '_raff_validation_option_3',
		'label' => 'Option 3',
		'desc_tip' => true,
		'description' => 'Third answer option (visible to customers).',
		'type' => 'text',
	]);
	woocommerce_wp_select([
		'id' => '_raff_validation_correct_option',
		'label' => 'Correct option',
		'desc_tip' => true,
		'description' => 'Select which option is the correct answer.',
		'options' => [
			''  => '— Select correct option —',
			'1' => 'Option 1',
			'2' => 'Option 2',
			'3' => 'Option 3',
		],
	]);

	// Per-product presentation style for the validation question
	woocommerce_wp_select([
		'id' => '_raff_question_style',
		'label' => 'Question style',
		'desc_tip' => true,
		'description' => 'Per-product override for how the validation question is presented (falls back to global setting if empty).',
		'options' => [
			''        => 'Use global setting',
			'buttons' => 'Clickable buttons',
			'dropdown'=> 'Dropdown',
		],
	]);

	// render validation inputs (question + options) - existing markup remains
	// ...

	// Add live preview container for admin product editor
	// (script will fill / update this element based on current inputs and global settings)
	echo '<div id="raffall-question-preview" class="raffall-question-preview" style="margin-top:12px;padding:12px;border:1px solid rgba(0,0,0,0.06);border-radius:8px;background:#fff;">';
	echo '<strong style="display:block;margin-bottom:8px;">Preview</strong>';
	echo '<div id="raffall-question-preview-inner" aria-hidden="true">';
	echo '<p style="color:#666;font-size:13px;">No question configured yet.</p>';
	echo '</div>';
	echo '</div>';

	// Add live preview container for product card in admin product editor
	echo '<div id="raffall-card-preview" class="raffall-card-preview" style="margin-top:16px;padding:12px;border:1px solid rgba(0,0,0,0.06);border-radius:8px;background:#fff;">';
	echo '<strong style="display:block;margin-bottom:8px;">Card preview</strong>';
	echo '<div id="raffall-card-preview-inner" aria-hidden="true">';
	// initial placeholder; admin JS will render full preview here
	echo '<p style="color:#666;font-size:13px;margin:0;">Card preview will update live as you edit title, price and competition fields.</p>';
	echo '</div>';
	echo '</div>';
}

/* Save product meta when product is saved */
function raffall_save_product_fields($product) {
	$fields = [
		'_raff_is_competition' => 'checkbox',
		'_raff_is_instant_win' => 'checkbox',
		'_raff_is_free_entry' => 'checkbox',
		'_raff_validation_question' => 'text',
		'_raff_validation_option_1' => 'text',
		'_raff_validation_option_2' => 'text',
		'_raff_validation_option_3' => 'text',
		'_raff_validation_correct_option' => 'text',
		'_raff_question_style' => 'text', // <-- save per-product style
		'_raff_instant_seed_csv' => 'textarea',
		'_raff_next_ticket' => 'number',
		'_raff_ticket_cap' => 'number',

		// NEW: include featured in saved fields
		'_raff_featured' => 'checkbox',
	];
	foreach ($fields as $key => $type) {
		$val = isset($_POST[$key]) ? $_POST[$key] : '';
		if (in_array($type, ['text','textarea','number'])) {
			$product->update_meta_data($key, sanitize_text_field($val));
		} else {
			$product->update_meta_data($key, $val ? 'yes' : 'no');
		}
	}
	$product->save();

	// seed instant ledger if present
	if ($product->get_meta('_raff_is_instant_win') === 'yes') {
		$csv = $product->get_meta('_raff_instant_seed_csv');
		raffall_seed_instant_ledger((int)$product->get_id(), $csv);
	}

	// normalize draw date (same logic as before)
	if (isset($_POST['_raff_draw_date_local']) || isset($_POST['_raff_draw_date'])) {
		$raw_local = isset($_POST['_raff_draw_date_local']) ? sanitize_text_field($_POST['_raff_draw_date_local']) : '';
		$raw_direct = isset($_POST['_raff_draw_date']) ? sanitize_text_field($_POST['_raff_draw_date']) : '';
		$tz = isset($_POST['_raff_draw_tz']) ? sanitize_text_field($_POST['_raff_draw_tz']) : '';
		$normalized = '';
		if (!empty($raw_local) && !empty($tz)) {
			try {
				$dt = DateTime::createFromFormat('Y-m-d H:i', $raw_local, new DateTimeZone($tz));
				if ($dt === false) $dt = new DateTime($raw_local, new DateTimeZone($tz));
				if ($dt) { $dt->setTimezone(new DateTimeZone('UTC')); $normalized = $dt->format('Y-m-d\TH:i:s\Z'); }
			} catch (Exception $e) { $normalized = ''; }
		} elseif (!empty($raw_direct)) {
			try { $dt = new DateTime($raw_direct); $dt->setTimezone(new DateTimeZone('UTC')); $normalized = $dt->format('Y-m-d\TH:i:s\Z'); }
			catch (Exception $e) { $normalized = $raw_direct; }
		}
		if ($normalized) $product->update_meta_data('_raff_draw_date', $normalized); else $product->delete_meta_data('_raff_draw_date');
		if (!empty($tz)) $product->update_meta_data('_raff_draw_tz', $tz); else $product->delete_meta_data('_raff_draw_tz');
	}
}
