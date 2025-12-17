<?php
/**
 * Plugin Name: WooCompetitions
 * Description: Competitions via WooCommerce: validation question gate (3-option), ticket allocation, instant wins, winners page, audit logging, GDPR auto-anonymization (2 years), countdown and percent sold bar, Flatpickr admin picker with timezone.
 * Version: 1.3.4
 * Author: Tai Spears (SpearsTech)
 * Requires PHP: 8.0
 */

if (!defined('ABSPATH')) exit;

// Load feature files (each feature isolated)
require_once __DIR__ . '/includes/activation.php';
require_once __DIR__ . '/includes/audit.php';
require_once __DIR__ . '/includes/cpt-winners.php';
require_once __DIR__ . '/includes/product-fields.php';
require_once __DIR__ . '/includes/admin-assets.php';
require_once __DIR__ . '/includes/admin-pages.php';
require_once __DIR__ . '/includes/frontend-assets.php';
require_once __DIR__ . '/includes/frontend-display.php'; // <-- new include
require_once __DIR__ . '/includes/cart-sidebar.php';
require_once __DIR__ . '/includes/shortcodes.php';
require_once __DIR__ . '/includes/account.php';
require_once __DIR__ . '/includes/tickets-instant.php';
require_once __DIR__ . '/includes/gdpr.php';
require_once __DIR__ . '/includes/validation.php';
require_once __DIR__ . '/includes/block-registration.php';

class RaffAll {
    const VERSION = '1.3.4';
    const AUDIT_TABLE = 'raffall_audit';
    const INSTANT_TABLE = 'raffall_instant_ledger';

    public function __construct() {
        // activation/deactivation callbacks now point to procedural functions
        register_activation_hook(__FILE__, 'raffall_activate');
        register_deactivation_hook(__FILE__, 'raffall_deactivate');

        // Defer WooCommerce-dependent initialization until plugins have loaded.
        add_action('plugins_loaded', [$this, 'maybe_init']);
    }

    // Called on plugins_loaded to check for WooCommerce and initialise plugin hooks.
    public function maybe_init() {
        if (!class_exists('WooCommerce')) {
            // Show admin notice for users who can activate plugins
            add_action('admin_notices', [$this, 'woocommerce_missing_notice']);
            return;
        }

        // WooCommerce is available — register all hooks that depend on it.
        $this->init_hooks();
    }

    // Register all hooks/filters/actions that require WooCommerce (moved from constructor).
    private function init_hooks() {
        // register CPT
        add_action('init', 'raffall_register_winner_cpt');

        // product editor fields & save
        add_action('woocommerce_product_options_general_product_data', 'raffall_product_fields');
        add_action('woocommerce_admin_process_product_object', 'raffall_save_product_fields');

        // admin assets for product editor
        add_action('admin_enqueue_scripts', 'raffall_enqueue_admin_assets');

        // product page display & validation
        add_action('woocommerce_before_add_to_cart_button', 'raffall_render_countdown_and_progress', 5);
        add_action('woocommerce_before_add_to_cart_button', 'raffall_render_validation_question');
        add_filter('woocommerce_add_to_cart_validation', 'raffall_validate_question_before_add_to_cart', 10, 4);

        // ticket assignment & instant wins on order complete
        add_action('woocommerce_payment_complete', 'raffall_assign_tickets_and_instant_wins');
        add_action('woocommerce_order_status_completed', 'raffall_assign_tickets_and_instant_wins');

        // account endpoints & menu
        add_action('init', 'raffall_register_account_endpoints');
        add_filter('woocommerce_account_menu_items', 'raffall_add_account_menu_items');
        add_action('woocommerce_account_tickets_endpoint', 'raffall_account_tickets_view');
        add_action('woocommerce_account_instant-wins_endpoint', 'raffall_account_instant_wins_view');

        // shortcodes
        add_shortcode('raffall_home', 'raffall_shortcode_home');
        add_shortcode('raffall_winners', 'raffall_shortcode_winners');
        add_shortcode('raffall_cart_sidebar', 'raffall_render_cart_sidebar_shortcode');

        // GDPR job
        add_action('raffall_daily_gdpr_event', 'raffall_gdpr_anonymize_job');

        // frontend assets & sidebar
        add_action('wp_enqueue_scripts', 'raffall_enqueue_raff_frontend_assets');
        add_action('wp_footer', 'raffall_render_cart_sidebar');

        // admin actions
        add_action('admin_post_raffall_export_entries', 'raffall_admin_export_entries');
        add_action('admin_post_raffall_allocate_winner', 'raffall_admin_allocate_winner');
        add_action('admin_post_raffall_grant_site_credit', 'raffall_admin_grant_site_credit');

        // audit option updates
        add_action('update_option', function($option, $old, $new){
            if (str_starts_with($option, 'raffall_')) raffall_audit('option_update', ['option' => $option]);
        }, 10, 3);

        // admin pages & settings
        add_action('admin_menu', 'raffall_add_admin_settings_page');
        add_action('admin_init', 'raffall_register_admin_settings');
    }

    /* Duplicate methods removed: the implementations above are used to avoid redeclaration errors */

    // Admin notice when WooCommerce is not active
    public function woocommerce_missing_notice() {
        if (!current_user_can('activate_plugins')) return;
        $install_url = esc_url(self_admin_url('plugin-install.php?tab=search&s=WooCommerce'));
        $plugins_url = esc_url(admin_url('plugins.php'));
        echo '<div class="notice notice-error"><p>';
        echo 'Raff-all Premium Competitions requires <strong>WooCommerce</strong>. Please install and activate WooCommerce. ';
        echo '<a href="' . $install_url . '">Install WooCommerce</a> or go to <a href="' . $plugins_url . '">Plugins</a>.';
        echo '</p></div>';
    }

    // Compatibility wrapper: some hooks may still reference [$this,'enqueue_raff_admin_assets']
    // Keep a thin method that delegates to the procedural function if present.
    public function enqueue_raff_admin_assets($hook = '') {
        if (function_exists('raffall_enqueue_admin_assets')) {
            return raffall_enqueue_admin_assets($hook);
        }
        // no-op fallback
        return null;
    }

    // Compatibility wrapper in case a class method callback is registered for countdown rendering.
    public function render_countdown_and_progress() {
        if (function_exists('raffall_render_countdown_and_progress')) {
            return raffall_render_countdown_and_progress();
        }
        return null;
    }
} // end class RaffAll

new RaffAll();