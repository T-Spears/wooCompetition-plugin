<?php
if (!defined('ABSPATH')) exit;

function raffall_enqueue_raff_frontend_assets() {
    $has_wc = function_exists('wc_get_cart_url') && function_exists('wc_get_checkout_url');

    // Use the stable plugin URL constant to build asset paths
    $url_front_js    = RAFFALL_PLUGIN_URL . 'assets/raffall-frontend.js';
    $url_front_css   = RAFFALL_PLUGIN_URL . 'assets/raffall-frontend.css';
    $url_sidebar_js  = RAFFALL_PLUGIN_URL . 'assets/raffall-cart-sidebar.js';
    $url_sidebar_css = RAFFALL_PLUGIN_URL . 'assets/raffall-cart-sidebar.css';
    $url_q_styles    = RAFFALL_PLUGIN_URL . 'assets/raffall-question-styles.css';
    $url_q_js        = RAFFALL_PLUGIN_URL . 'assets/raffall-question.js';

    wp_register_script('raffall-frontend', $url_front_js, ['jquery'], RaffAll::VERSION ?? '1.0.0', true);
    wp_register_style('raffall-frontend-css', $url_front_css, [], RaffAll::VERSION ?? '1.0.0');
    wp_register_script('raffall-cart-sidebar', $url_sidebar_js, ['jquery'], RaffAll::VERSION ?? '1.0.0', true);
    wp_register_style('raffall-cart-sidebar-css', $url_sidebar_css, [], RaffAll::VERSION ?? '1.0.0');

    wp_enqueue_script('raffall-cart-sidebar');
    wp_enqueue_style('raffall-cart-sidebar-css');

    // localize defaults and urls (guard wc helpers)
    $sidebar_defaults = [
        'enabled' => get_option('raffall_cart_sidebar_enable', '1') === '1',
        'cart_url' => $has_wc ? wc_get_cart_url() : home_url('/cart/'),
        'checkout_url' => $has_wc ? wc_get_checkout_url() : home_url('/checkout/'),
        'strings' => [
            'title' => __('Your cart','raffall'),
            'view_cart' => __('View cart','raffall'),
            'checkout' => __('Checkout','raffall'),
            'empty' => __('Your cart is empty','raffall'),
            'customise' => __('Customize','raffall'),
            'reset' => __('Reset','raffall'),
        ],
    ];
    wp_localize_script('raffall-cart-sidebar', 'raffAllCartSidebar', $sidebar_defaults);

    wp_enqueue_script('raffall-frontend');
    wp_enqueue_style('raffall-frontend-css');

    // NEW: question style CSS + JS (modern look + button interactions)
    wp_register_style('raffall-question-styles', $url_q_styles, [], '1.0.0');
    wp_enqueue_style('raffall-question-styles');

    wp_register_script('raffall-question', $url_q_js, ['jquery'], '1.0.0', true);
    wp_enqueue_script('raffall-question');

    // Inject CSS variables from options so colours can be changed from admin
    $fill = esc_attr(get_option('raffall_fill_color', '#7b3cff'));
    $bg   = esc_attr(get_option('raffall_bg_color', '#f1f1f1'));
    $flip_bg = esc_attr(get_option('raffall_flip_bg', '#fff'));
    $flip_text = esc_attr(get_option('raffall_flip_text', '#222'));
    $q_btn_bg = esc_attr(get_option('raffall_question_btn_bg', '#ffffff'));
    $q_btn_text = esc_attr(get_option('raffall_question_btn_text', '#222222'));
    $q_btn_active = esc_attr(get_option('raffall_question_btn_bg_active', $fill));

    $vars = ":root{ --raff-fill: {$fill}; --raff-bg: {$bg}; --raff-flip-bg: {$flip_bg}; --raff-flip-text: {$flip_text}; --raff-question-btn-bg: {$q_btn_bg}; --raff-question-btn-text: {$q_btn_text}; --raff-question-btn-bg-active: {$q_btn_active}; }";
    wp_add_inline_style('raffall-frontend-css', $vars);
}
