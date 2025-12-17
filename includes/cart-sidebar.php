<?php
if (!defined('ABSPATH')) exit;

function raffall_get_cart_sidebar_markup(): string {
	if (!function_exists('WC') || !WC() || !method_exists('WC','cart') || !WC()->cart) return '';
	ob_start();
	$cart = WC()->cart;
	$items = $cart ? $cart->get_cart() : [];
	$cart_count = $cart ? $cart->get_cart_contents_count() : 0;
	?>
	<div id="raffall-cart-sidebar" class="raffall-cart-sidebar" data-open="false" aria-hidden="true">
		<!-- simplified markup, see original plugin for full -->
		<div class="raffall-cart-body">
			<?php if (empty($items)) : ?>
				<p><?php echo esc_html__('Your cart is empty','raffall'); ?></p>
			<?php else: ?>
				<ul><?php foreach ($items as $item){ $p = $item['data']; echo '<li>'.esc_html($p->get_name()).' x'.(int)$item['quantity'].'</li>'; }?></ul>
			<?php endif; ?>
		</div>
	</div>
	<?php
	return ob_get_clean();
}

function raffall_render_cart_sidebar() {
	if (get_option('raffall_cart_sidebar_enable', '1') !== '1') return;
	echo raffall_get_cart_sidebar_markup();
}

function raffall_render_cart_sidebar_shortcode($atts = []) {
	return raffall_get_cart_sidebar_markup();
}
