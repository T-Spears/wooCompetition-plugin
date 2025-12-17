<?php
if (!defined('ABSPATH')) exit;

add_action('init', 'raffall_register_block_assets');
function raffall_register_block_assets() {
	// register editor script and block styles (depends on wp-api-fetch for REST)
	wp_register_script(
		'raffall-block-editor',
		plugin_dir_url(__DIR__) . 'assets/blocks/raffall-block.js',
		['wp-blocks','wp-element','wp-components','wp-editor','wp-i18n','wp-api-fetch'],
		filemtime(plugin_dir_path(__DIR__) . 'assets/blocks/raffall-block.js'),
		true
	);
	wp_register_style(
		'raffall-block-style',
		plugin_dir_url(__DIR__) . 'assets/blocks/raffall-block.css',
		[],
		filemtime(plugin_dir_path(__DIR__) . 'assets/blocks/raffall-block.css')
	);

	register_block_type('woo-competitions/card', [
		'editor_script' => 'raffall-block-editor',
		'editor_style'  => 'raffall-block-style',
		'style'         => 'raffall-block-style',
		'render_callback' => 'raffall_render_block_card'
	]);
}

// REST endpoint to return competition products with search & per_page
add_action('rest_api_init', function () {
	register_rest_route('raffall/v1', '/competitions', [
		'methods' => 'GET',
		'callback' => 'raffall_rest_get_competitions',
		'args' => [
			'search' => ['required' => false, 'sanitize_callback' => 'sanitize_text_field'],
			'per_page' => ['required' => false, 'sanitize_callback' => 'absint', 'default' => 20],
		],
		'permission_callback' => '__return_true',
	]);
});

function raffall_rest_get_competitions(\WP_Request $request) {
	if (!function_exists('wc_get_products')) return new WP_Error('no_wc', 'WooCommerce required', ['status'=>400]);
	$search = $request->get_param('search') ?: '';
	$per_page = $request->get_param('per_page') ?: 20;

	$args = ['limit' => $per_page, 'status' => 'publish'];
	if ($search !== '') $args['search'] = $search;

	$prods = wc_get_products($args);
	$out = [];
	foreach ($prods as $p) {
		if ($p->get_meta('_raff_is_competition') !== 'yes') continue;
		$out[] = [
			'id' => $p->get_id(),
			'title' => $p->get_name(),
			'price' => $p->get_price(),
			'draw' => $p->get_meta('_raff_draw_date'),
			'image' => wp_get_attachment_image_url($p->get_image_id(), 'medium') ?: '',
			'url' => get_permalink($p->get_id()),
		];
	}
	return rest_ensure_response($out);
}

/**
 * Server-side render callback for the block.
 * Attributes: productId (int), showCountdown (bool), showProgress (bool)
 */
function raffall_render_block_card($attrs) {
    $pid = isset($attrs['productId']) ? intval($attrs['productId']) : 0;
    if (!$pid) return '';

    if (!function_exists('wc_get_product')) return '';

    $p = wc_get_product($pid);
    if (!$p) return '';

    // Build simple product-card markup similar to frontend cards (draw removed)
    $name = esc_html($p->get_name());
    $price = wc_price($p->get_price());
    $img = wp_get_attachment_image($p->get_image_id(), 'medium') ?: '';
    $cap = (int)$p->get_meta('_raff_ticket_cap');
    $stock_raw = $p->get_stock_quantity();
    $stock = is_numeric($stock_raw) ? intval($stock_raw) : 0;
    if ($cap < 1) {
        $next = (int)$p->get_meta('_raff_next_ticket');
        if ($next < 1) $next = 1;
        if (is_numeric($stock_raw)) $cap = $stock + max(0, $next - 1);
    }
    $cap = max(0, $cap);
    $sold = ($cap > 0) ? max(0, $cap - max(0, $stock)) : 0;
    $percent = ($cap > 0) ? round(($sold / $cap) * 100) : 0;

    ob_start();
    ?>
    <div class="raff-card-block" data-product-id="<?php echo $pid; ?>">
        <div class="raff-card-block-inner" style="display:flex;gap:12px;align-items:center;">
            <div class="raff-card-thumb"><?php echo $img; ?></div>
            <div class="raff-card-body">
                <h3 class="raff-card-title"><?php echo $name; ?></h3>
                <div class="raff-card-meta"><?php echo ($cap ? esc_html($percent) . '% sold (' . $sold . '/' . $cap . ')' : 'Tickets: —'); ?></div>
                <div style="display:flex;align-items:center;gap:12px;">
                    <div class="raff-card-price" style="font-weight:700;"><?php echo $price; ?></div>
                    <?php if ($cap > 0): ?>
                        <div class="raff-progress" style="flex:1;background:var(--raff-bg,#f1f1f1);border-radius:8px;height:10px;">
                            <span class="raff-progress-inner" style="display:block;height:100%;width:<?php echo esc_attr($percent); ?>%;background:linear-gradient(90deg,var(--raff-fill,#7b3cff),#3fb1ff);"></span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
