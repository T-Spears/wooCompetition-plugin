<?php
/**
 * Plugin Name: Raff-all Premium Competitions
 * Description: Competitions via WooCommerce: validation question gate (3-option), ticket allocation, instant wins, winners page, audit logging, GDPR auto-anonymization (2 years), countdown and percent sold bar, Flatpickr admin picker with timezone.
 * Version: 1.2.0
 * Author: Raff-all
 * Requires PHP: 8.0
 */

if (!defined('ABSPATH')) exit;

class RaffAll {
    const VERSION = '1.2.0';
    const AUDIT_TABLE = 'raffall_audit';
    const INSTANT_TABLE = 'raffall_instant_ledger';

    public function __construct() {
        register_activation_hook(__FILE__, [$this, 'activate']);
        register_deactivation_hook(__FILE__, [$this, 'deactivate']);

        // Bail early if WooCommerce is not active to avoid fatal errors.
        // Activation/deactivation still registered above.
        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', [$this, 'woocommerce_missing_notice']);
            // Do not register WooCommerce-dependent hooks
            return;
        }

        add_action('init', [$this, 'register_winner_cpt']);
        add_action('woocommerce_product_options_general_product_data', [$this, 'product_fields']);
        add_action('woocommerce_admin_process_product_object', [$this, 'save_product_fields']);

        // Admin assets for product editor (Flatpickr + timezone)
        add_action('admin_enqueue_scripts', [$this, 'enqueue_raff_admin_assets']);

        add_action('woocommerce_before_add_to_cart_button', [$this, 'render_countdown_and_progress'], 5);
        add_action('woocommerce_before_add_to_cart_button', [$this, 'render_validation_question']);
        add_filter('woocommerce_add_to_cart_validation', [$this, 'validate_question_before_add_to_cart'], 10, 4);

        add_action('woocommerce_payment_complete', [$this, 'assign_tickets_and_instant_wins']);
        add_action('woocommerce_order_status_completed', [$this, 'assign_tickets_and_instant_wins']);

        add_action('init', [$this, 'register_account_endpoints']);
        add_filter('woocommerce_account_menu_items', [$this, 'add_account_menu_items']);
        add_action('woocommerce_account_tickets_endpoint', [$this, 'account_tickets_view']);
        add_action('woocommerce_account_instant-wins_endpoint', [$this, 'account_instant_wins_view']);

        add_shortcode('raffall_home', [$this, 'shortcode_home']);
        add_shortcode('raffall_winners', [$this, 'shortcode_winners']);

        add_action('raffall_daily_gdpr_event', [$this, 'gdpr_anonymize_job']);

        add_action('wp_enqueue_scripts', [$this, 'enqueue_raff_frontend_assets']);
        add_action('wp_footer', [$this, 'render_cart_sidebar']); // inject sidebar markup into footer
        add_shortcode('raffall_cart_sidebar', [$this, 'render_cart_sidebar_shortcode']); // shortcode for manual placement

        // Admin actions for export and admin-managed allocation/credit
        add_action('admin_post_raffall_export_entries', [$this, 'admin_export_entries']);
        add_action('admin_post_raffall_allocate_winner', [$this, 'admin_allocate_winner']);
        add_action('admin_post_raffall_grant_site_credit', [$this, 'admin_grant_site_credit']);

        add_action('update_option', function($option, $old, $new){
            if (str_starts_with($option, 'raffall_')) $this->audit('option_update', ['option' => $option]);
        }, 10, 3);

        add_action('admin_menu', [$this, 'add_admin_settings_page']);
        add_action('admin_init', [$this, 'register_admin_settings']);
    }

    /* Activation and DB tables */
    public function activate() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset = $wpdb->get_charset_collate();

        $audit_sql = "CREATE TABLE {$wpdb->prefix}" . self::AUDIT_TABLE . " (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            event_type VARCHAR(64) NOT NULL,
            context JSON NULL,
            user_id BIGINT UNSIGNED NULL,
            order_id BIGINT UNSIGNED NULL,
            product_id BIGINT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY event_type (event_type),
            KEY user_id (user_id),
            KEY order_id (order_id),
            KEY product_id (product_id)
        ) $charset;";

        $instant_sql = "CREATE TABLE {$wpdb->prefix}" . self::INSTANT_TABLE . " (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            product_id BIGINT UNSIGNED NOT NULL,
            ticket_number BIGINT UNSIGNED NOT NULL,
            prize VARCHAR(255) NOT NULL,
            claimed TINYINT(1) NOT NULL DEFAULT 0,
            order_id BIGINT UNSIGNED NULL,
            user_id BIGINT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY prod_ticket (product_id, ticket_number),
            KEY product_id (product_id),
            KEY claimed (claimed)
        ) $charset;";

        dbDelta($audit_sql);
        dbDelta($instant_sql);

        if (!wp_next_scheduled('raffall_daily_gdpr_event')) {
            wp_schedule_event(time() + 3600, 'daily', 'raffall_daily_gdpr_event');
        }
    }

    public function deactivate() {
        wp_clear_scheduled_hook('raffall_daily_gdpr_event');
    }

    /* Winners CPT */
    public function register_winner_cpt() {
        register_post_type('raff_winner', [
            'label' => 'Winners',
            'public' => true,
            'has_archive' => true,
            'menu_icon' => 'dashicons-awards',
            'supports' => ['title', 'editor', 'thumbnail'],
        ]);

        register_post_meta('raff_winner', 'raff_competition_product_id', ['type' => 'integer', 'single' => true, 'show_in_rest' => true]);
        register_post_meta('raff_winner', 'raff_prize_name', ['type' => 'string', 'single' => true, 'show_in_rest' => true]);
        register_post_meta('raff_winner', 'raff_ticket_number', ['type' => 'number', 'single' => true, 'show_in_rest' => true]);
        register_post_meta('raff_winner', 'raff_winner_name', ['type' => 'string', 'single' => true, 'show_in_rest' => true]);
        register_post_meta('raff_winner', 'raff_consent_public', ['type' => 'boolean', 'single' => true, 'show_in_rest' => true, 'default' => false]);
    }

    /* Product fields */
    public function product_fields() {
        echo '<div class="options_group">';
        woocommerce_wp_checkbox([
            'id' => '_raff_is_competition',
            'label' => 'Is competition',
            'desc_tip' => true,
            'description' => 'Enable competition features for this product.',
        ]);
        woocommerce_wp_checkbox([
            'id' => '_raff_is_instant_win',
            'label' => 'Has instant wins',
            'desc_tip' => true,
            'description' => 'Assign instant wins via pre-seeded ticket numbers.',
        ]);
        woocommerce_wp_checkbox([
            'id' => '_raff_is_free_entry',
            'label' => 'Show free entry info',
            'desc_tip' => true,
            'description' => 'Displays parity postal/free entry instructions.',
        ]);

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
                '' => '— Select correct option —',
                '1' => 'Option 1',
                '2' => 'Option 2',
                '3' => 'Option 3',
            ],
        ]);

        // Draw date local input and timezone selector (Flatpickr will be initialized in admin script)
        $draw_meta = get_post_meta(get_the_ID() ?: 0, '_raff_draw_date', true);
        $draw_tz_meta = get_post_meta(get_the_ID() ?: 0, '_raff_draw_tz', true);
        $local_display = '';
        if ($draw_meta) {
            try {
                $dt = new DateTime($draw_meta);
                $site_tz = new DateTimeZone(get_option('timezone_string') ?: 'UTC');
                $dt->setTimezone($site_tz);
                $local_display = $dt->format('Y-m-d H:i');
            } catch (Exception $e) {
                $local_display = $draw_meta;
            }
        }
        echo '<p class="form-field raff-draw-date">';
        echo '<label for="_raff_draw_date_local">Draw date and time</label>';
        echo '<input type="text" id="_raff_draw_date_local" name="_raff_draw_date_local" value="' . esc_attr($local_display) . '" style="max-width:300px;">';
        echo '<span class="description" style="display:block;margin-top:6px;">Pick local date and time then choose the timezone below. The system will store the draw time in UTC.</span>';
        echo '</p>';
        echo '<p class="form-field raff-draw-tz">';
        echo '<label for="_raff_draw_tz">Draw timezone</label>';
        echo '<select id="_raff_draw_tz" name="_raff_draw_tz" data-current="' . esc_attr($draw_tz_meta) . '" style="max-width:320px;"></select>';
        echo '<span class="description" style="display:block;margin-top:6px;">Select the timezone that applies to the date/time you entered.</span>';
        echo '</p>';
        echo '<input type="hidden" id="_raff_draw_date" name="_raff_draw_date" value="' . esc_attr($draw_meta) . '">';

        woocommerce_wp_textarea_input([
            'id' => '_raff_instant_seed_csv',
            'label' => 'Instant wins seed CSV',
            'description' => "One per line: ticket_number,prize name. Example:\n12,£50 Amazon\n237,Free entry",
            'desc_tip' => true,
        ]);
        woocommerce_wp_text_input([
            'id' => '_raff_next_ticket',
            'label' => 'Next ticket number',
            'type' => 'number',
            'custom_attributes' => ['min' => '1'],
            'desc_tip' => true,
            'description' => 'Auto-incremented when orders complete.',
        ]);
        woocommerce_wp_text_input([
            'id' => '_raff_ticket_cap',
            'label' => 'Total ticket cap',
            'type' => 'number',
            'custom_attributes' => ['min' => '1', 'step' => '1'],
            'desc_tip' => true,
            'description' => 'Total number of tickets available for this competition (used to calculate % sold).',
        ]);
        echo '</div>';
    }

    public function save_product_fields($product) {
        $fields = [
            '_raff_is_competition' => 'checkbox',
            '_raff_is_instant_win' => 'checkbox',
            '_raff_is_free_entry' => 'checkbox',
            '_raff_validation_question' => 'text',
            '_raff_validation_option_1' => 'text',
            '_raff_validation_option_2' => 'text',
            '_raff_validation_option_3' => 'text',
            '_raff_validation_correct_option' => 'text',
            '_raff_instant_seed_csv' => 'textarea',
            '_raff_next_ticket' => 'number',
            '_raff_ticket_cap' => 'number',
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

        // Seed instant wins ledger from CSV input (idempotent; only adds new)
        if ($product->get_meta('_raff_is_instant_win') === 'yes') {
            $csv = $product->get_meta('_raff_instant_seed_csv');
            $this->seed_instant_ledger((int)$product->get_id(), $csv);
        }

        // Normalize and save draw date with timezone
        if (isset($_POST['_raff_draw_date_local']) || isset($_POST['_raff_draw_date'])) {
            $raw_local = isset($_POST['_raff_draw_date_local']) ? sanitize_text_field($_POST['_raff_draw_date_local']) : '';
            $raw_direct = isset($_POST['_raff_draw_date']) ? sanitize_text_field($_POST['_raff_draw_date']) : '';
            $tz = isset($_POST['_raff_draw_tz']) ? sanitize_text_field($_POST['_raff_draw_tz']) : '';

            $normalized = '';
            if (!empty($raw_local) && !empty($tz)) {
                try {
                    $dt = DateTime::createFromFormat('Y-m-d H:i', $raw_local, new DateTimeZone($tz));
                    if ($dt === false) {
                        $dt = new DateTime($raw_local, new DateTimeZone($tz));
                    }
                    if ($dt) {
                        $dt->setTimezone(new DateTimeZone('UTC'));
                        $normalized = $dt->format('Y-m-d\TH:i:s\Z');
                    }
                } catch (Exception $e) {
                    $normalized = '';
                }
            } elseif (!empty($raw_direct)) {
                try {
                    $dt = new DateTime($raw_direct);
                    $dt->setTimezone(new DateTimeZone('UTC'));
                    $normalized = $dt->format('Y-m-d\TH:i:s\Z');
                } catch (Exception $e) {
                    $normalized = $raw_direct;
                }
            }

            if ($normalized) {
                $product->update_meta_data('_raff_draw_date', $normalized);
            } else {
                $product->delete_meta_data('_raff_draw_date');
            }

            if (!empty($tz)) {
                $product->update_meta_data('_raff_draw_tz', $tz);
            } else {
                $product->delete_meta_data('_raff_draw_tz');
            }
        }
    }

    /* Duplicate methods removed: the implementations above are used to avoid redeclaration errors */

    /* GDPR anonymization */
    public function gdpr_anonymize_job() {
        $threshold = (new DateTimeImmutable('-2 years'))->format('Y-m-d H:i:s');
        $this->anonymize_users_before($threshold);
        $this->anonymize_guest_orders_before($threshold);
    }

    private function anonymize_users_before(string $datetime) {
        global $wpdb;
        $users = get_users([
            'fields' => ['ID'],
            'meta_query' => [
                'relation' => 'OR',
                ['key' => 'raff_retain_personal_data', 'compare' => 'NOT EXISTS'],
                ['key' => 'raff_retain_personal_data', 'value' => 'yes', 'compare' => '!='],
            ]
        ]);
        foreach ($users as $u) {
            $last_order = $wpdb->get_var($wpdb->prepare("
                SELECT MAX(post_date) FROM {$wpdb->posts}
                WHERE post_type='shop_order' AND post_author=%d
            ", $u->ID));
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
            $this->audit('gdpr_user_anonymized', ['user_id' => $u->ID]);
        }
    }

    private function anonymize_guest_orders_before(string $datetime) {
        global $wpdb;
        $orders = $wpdb->get_results($wpdb->prepare("
            SELECT ID FROM {$wpdb->posts}
            WHERE post_type='shop_order' AND post_date < %s
        ", $datetime));
        foreach ($orders as $o) {
            $order = wc_get_order($o->ID);
            if (!$order) continue;
            if ($order->get_user_id()) continue;

            $order->set_billing_first_name('');
            $order->set_billing_last_name('');
            $order->set_billing_address_1('');
            $order->set_billing_city('');
            $order->set_billing_postcode('');
            $order->set_billing_phone('');
            $order->save();
            $this->audit('gdpr_guest_order_anonymized', ['order_id' => $o->ID]);
        }
    }

    /* Audit logging */
    private function audit(string $event, array $ctx = []) {
        try {
            global $wpdb;
            $table = $wpdb->prefix . self::AUDIT_TABLE;
            $wpdb->insert($table, [
                'event_type' => $event,
                'context' => wp_json_encode($ctx, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'user_id' => get_current_user_id() ?: null,
                'order_id' => $ctx['order_id'] ?? null,
                'product_id' => $ctx['product_id'] ?? null,
                'created_at' => current_time('mysql'),
            ]);
        } catch (\Throwable $e) {
            // ignore
        }
    }

    private function mask(string $s): string {
        if (strlen($s) <= 2) return '*';
        return substr($s,0,1) . str_repeat('*', max(1, strlen($s)-2)) . substr($s,-1);
    }

    /* Enqueue assets */
    public function enqueue_raff_frontend_assets() {
        // Avoid calling WooCommerce helpers if WooCommerce is not present.
        $has_wc = function_exists('wc_get_cart_url') && function_exists('wc_get_checkout_url');
        wp_register_script('raffall-frontend', plugin_dir_url(__FILE__) . 'assets/raffall-frontend.js', ['jquery'], self::VERSION, true);
        wp_register_style('raffall-frontend-css', plugin_dir_url(__FILE__) . 'assets/raffall-frontend.css', [], self::VERSION);
        wp_register_script('raffall-cart-sidebar', plugin_dir_url(__FILE__) . 'assets/raffall-cart-sidebar.js', ['jquery'], self::VERSION, true);
        wp_register_style('raffall-cart-sidebar-css', plugin_dir_url(__FILE__) . 'assets/raffall-cart-sidebar.css', [], self::VERSION);

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

        // Inject CSS variables from options so colours can be changed from admin
        $fill = esc_attr(get_option('raffall_fill_color', '#7b3cff'));
        $bg   = esc_attr(get_option('raffall_bg_color', '#f1f1f1'));
        $flip_bg = esc_attr(get_option('raffall_flip_bg', '#fff'));
        $flip_text = esc_attr(get_option('raffall_flip_text', '#222'));

        $vars = ":root{ --raff-fill: {$fill}; --raff-bg: {$bg}; --raff-flip-bg: {$flip_bg}; --raff-flip-text: {$flip_text}; }";
        wp_add_inline_style('raffall-frontend-css', $vars);
    }

    /* Cart sidebar output helpers */
    public function render_cart_sidebar() {
        // echo sidebar only when enabled
        if (get_option('raffall_cart_sidebar_enable', '1') !== '1') return;
        echo $this->get_cart_sidebar_markup();
    }

    public function render_cart_sidebar_shortcode($atts = []) {
        return $this->get_cart_sidebar_markup();
    }

    private function get_cart_sidebar_markup() : string {
        // Return nothing if no WooCommerce/cart available to avoid WP fatal errors
        if (!function_exists('WC') || !WC() || !method_exists('WC','cart') || !WC()->cart) {
            return '';
        }

        ob_start();
        $cart = WC()->cart ?? null;
        $items = $cart ? $cart->get_cart() : [];
        $cart_count = $cart ? $cart->get_cart_contents_count() : 0;
        ?>
        <div id="raffall-cart-sidebar" class="raffall-cart-sidebar" data-open="false" aria-hidden="true">
            <div class="raffall-cart-overlay" data-action="close" tabindex="-1"></div>
            <aside class="raffall-cart-panel" role="complementary" aria-labelledby="raffall-cart-title">
                <header class="raffall-cart-header">
                    <h2 id="raffall-cart-title"><?php echo esc_html(__('Your cart','raffall')); ?> <span class="raffall-cart-count">(<?php echo (int)$cart_count; ?>)</span></h2>
                    <button class="raffall-cart-close" aria-label="<?php echo esc_attr__('Close cart','raffall'); ?>">×</button>
                </header>

                <div class="raffall-cart-body" data-cart-body>
                    <?php if (empty($items)) : ?>
                        <p class="raffall-cart-empty"><?php echo esc_html(__('Your cart is empty','raffall')); ?></p>
                    <?php else : ?>
                        <ul class="raffall-cart-items">
                            <?php foreach ($items as $key => $item) :
                                $product = $item['data'];
                                if (!$product) continue;
                                $thumb = $product->get_image();
                                $name = $product->get_name();
                                $qty = (int)$item['quantity'];
                                $price = wc_price(wc_get_price_to_display($product) * $qty);
                                $remove = esc_url(wc_get_cart_remove_url($key));
                                ?>
                                <li class="raffall-cart-item">
                                    <div class="raffall-cart-item-thumb"><?php echo $thumb; ?></div>
                                    <div class="raffall-cart-item-meta">
                                        <div class="raffall-cart-item-title"><?php echo esc_html($name); ?></div>
                                        <div class="raffall-cart-item-qty"><?php echo 'x' . $qty; ?></div>
                                        <div class="raffall-cart-item-price"><?php echo $price; ?></div>
                                    </div>
                                    <a class="raffall-cart-item-remove" href="<?php echo esc_url(wc_get_cart_remove_url($key)); ?>" aria-label="<?php echo esc_attr__('Remove item','raffall'); ?>">&times;</a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>

                <div class="raffall-cart-footer">
                    <div class="raffall-cart-totals">
                        <?php if ($cart) : ?>
                            <p class="raffall-cart-subtotal"><?php echo esc_html__('Subtotal:','raffall'); ?> <strong><?php echo wp_kses_post($cart->get_cart_subtotal()); ?></strong></p>
                        <?php endif; ?>
                    </div>
                    <div class="raffall-cart-actions">
                        <a class="button raffall-cart-view" href="<?php echo esc_url(wc_get_cart_url()); ?>"><?php echo esc_html__('View cart','raffall'); ?></a>
                        <a class="button alt raffall-cart-checkout" href="<?php echo esc_url(wc_get_checkout_url()); ?>"><?php echo esc_html__('Checkout','raffall'); ?></a>
                    </div>
                </div>

                <!-- customiser (front-end only; saves to localStorage) -->
                <div class="raffall-cart-customiser">
                    <button class="raffall-cart-customiser-toggle" type="button"><?php echo esc_html__('Customize','raffall'); ?></button>
                    <div class="raffall-cart-customiser-panel" aria-hidden="true">
                        <label>Background color<br><input type="color" data-custom="bg" value="#ffffff"></label>
                        <label>Accent color<br><input type="color" data-custom="accent" value="#7b3cff"></label>
                        <label>Text color<br><input type="color" data-custom="text" value="#222222"></label>
                        <label>Width (%)<br><input type="range" min="200" max="600" data-custom="width" value="360"></label>
                        <label>Position<br>
                            <select data-custom="position">
                                <option value="right">Right</option>
                                <option value="left">Left</option>
                            </select>
                        </label>
                        <div class="raffall-cart-customiser-actions">
                            <button class="raffall-cart-customiser-reset" type="button"><?php echo esc_html__('Reset','raffall'); ?></button>
                        </div>
                    </div>
                </div>
            </aside>

            <!-- small floating toggle button -->
            <button class="raffall-cart-toggle" aria-label="<?php echo esc_attr__('Open cart','raffall'); ?>">
                <span class="raffall-cart-toggle-icon">🛒</span>
                <span class="raffall-cart-toggle-count"><?php echo (int)$cart_count; ?></span>
            </button>
        </div>
        <?php
        return ob_get_clean();
    }

    // Admin settings UI
    public function add_admin_settings_page() {
        add_options_page('Raff-all Display', 'Raff-all Display', 'manage_options', 'raffall-display', [$this, 'render_settings_page']);
        add_submenu_page('tools.php', 'Raff-all Competitions', 'Raff-all Competitions', 'manage_options', 'raffall-competitions', [$this, 'render_competitions_page']);
    }

    public function register_admin_settings() {
        register_setting('raffall_display_group', 'raffall_fill_color', ['type'=>'string', 'default' => '#7b3cff']);
        register_setting('raffall_display_group', 'raffall_bg_color', ['type'=>'string', 'default' => '#f1f1f1']);
        register_setting('raffall_display_group', 'raffall_flip_bg', ['type'=>'string', 'default' => '#fff']);
        register_setting('raffall_display_group', 'raffall_flip_text', ['type'=>'string', 'default' => '#222']);
        register_setting('raffall_display_group', 'raffall_show_countdown_product', ['type'=>'string', 'default' => '1']);
        register_setting('raffall_display_group', 'raffall_show_progress_product', ['type'=>'string', 'default' => '1']);
        register_setting('raffall_display_group', 'raffall_show_countdown_cards', ['type'=>'string', 'default' => '0']);
        register_setting('raffall_display_group', 'raffall_show_progress_cards', ['type'=>'string', 'default' => '0']);
        register_setting('raffall_display_group', 'raffall_cart_sidebar_enable', ['type'=>'string', 'default' => '1']);
    }

    public function render_settings_page() {
        if (!current_user_can('manage_options')) return;
        ?>
        <div class="wrap">
            <h1>Raff-all display settings</h1>
            <form method="post" action="options.php">
                <?php settings_fields('raffall_display_group'); do_settings_sections('raffall_display_group'); ?>
                <table class="form-table">
                                <tr>
                                    <th scope="row"><label for="raffall_fill_color">Progress fill colour</label></th>
                                    <td><input type="text" id="raffall_fill_color" name="raffall_fill_color" value="<?php echo esc_attr(get_option('raffall_fill_color', '#7b3cff')); ?>" class="regular-text"></td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="raffall_bg_color">Progress background colour</label></th>
                                    <td><input type="text" id="raffall_bg_color" name="raffall_bg_color" value="<?php echo esc_attr(get_option('raffall_bg_color', '#f1f1f1')); ?>" class="regular-text"></td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="raffall_flip_bg">Flip tile background</label></th>
                                    <td><input type="text" id="raffall_flip_bg" name="raffall_flip_bg" value="<?php echo esc_attr(get_option('raffall_flip_bg', '#fff')); ?>" class="regular-text"></td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="raffall_flip_text">Flip tile text colour</label></th>
                                    <td><input type="text" id="raffall_flip_text" name="raffall_flip_text" value="<?php echo esc_attr(get_option('raffall_flip_text', '#222')); ?>" class="regular-text"></td>
                                </tr>
                                <tr>
                                    <th scope="row">Product page: show countdown</th>
                                    <td>
                                        <label><input type="checkbox" name="raffall_show_countdown_product" value="1" <?php checked('1', get_option('raffall_show_countdown_product','1')); ?>> Show countdown on product page</label>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">Product page: show progress</th>
                                    <td>
                                        <label><input type="checkbox" name="raffall_show_progress_product" value="1" <?php checked('1', get_option('raffall_show_progress_product','1')); ?>> Show progress on product page</label>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">Product cards: show countdown</th>
                                    <td>
                                        <label><input type="checkbox" name="raffall_show_countdown_cards" value="1" <?php checked('1', get_option('raffall_show_countdown_cards','0')); ?>> Show countdown on product cards / shortcodes</label>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">Product cards: show progress</th>
                                    <td>
                                        <label><input type="checkbox" name="raffall_show_progress_cards" value="1" <?php checked('1', get_option('raffall_show_progress_cards','0')); ?>> Show progress on product cards / shortcodes</label>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">Cart sidebar: enable</th>
                                    <td>
                                        <label><input type="checkbox" name="raffall_cart_sidebar_enable" value="1" <?php checked('1', get_option('raffall_cart_sidebar_enable','1')); ?>> Enable cart sidebar by default</label>
                                        <p class="description">When enabled the cart sidebar will be injected into the footer. It remains fully customisable in the frontend.</p>
                                    </td>
                                </tr>
                            </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    // New admin page: list competitions, export and allocation UI
    public function render_competitions_page() {
        if (!current_user_can('manage_options')) return;
        $competitions = $this->get_competition_products();
        ?>
        <div class="wrap">
            <h1>Raff-all Competitions</h1>

            <h2>Competitions</h2>
            <p>Export entries, allocate winners, or manually grant site credit.</p>

            <table class="widefat fixed striped">
                <thead><tr><th>Product</th><th>Draw</th><th>Tickets cap</th><th>Actions</th></tr></thead>
                <tbody>
                <?php foreach ($competitions as $p): 
                    $pid = $p->get_id();
                    $draw = $p->get_meta('_raff_draw_date');
                    $cap = $p->get_meta('_raff_ticket_cap');
                ?>
                    <tr>
                        <td><?php echo esc_html($p->get_name()); ?> (ID <?php echo $pid; ?>)</td>
                        <td><?php echo esc_html($draw ?: 'TBA'); ?></td>
                        <td><?php echo esc_html($cap ?: '—'); ?></td>
                        <td>
                            <!-- Export entries -->
                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline-block;margin-right:8px;">
                                <?php wp_nonce_field('raffall_export_entries_' . $pid, 'raffall_export_nonce'); ?>
                                <input type="hidden" name="action" value="raffall_export_entries">
                                <input type="hidden" name="product_id" value="<?php echo esc_attr($pid); ?>">
                                <button class="button" type="submit">Export CSV</button>
                            </form>

                            <!-- Allocate winner quick form -->
                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline-block;">
                                <?php wp_nonce_field('raffall_allocate_winner_' . $pid, 'raffall_allocate_nonce'); ?>
                                <input type="hidden" name="action" value="raffall_allocate_winner">
                                <input type="hidden" name="product_id" value="<?php echo esc_attr($pid); ?>">
                                <input type="text" name="ticket_number" placeholder="Ticket #" style="width:90px;margin-right:6px;">
                                <input type="text" name="winner_name" placeholder="Winner name" style="width:160px;margin-right:6px;">
                                <input type="email" name="winner_email" placeholder="Email (optional)" style="width:200px;margin-right:6px;">
                                <label style="margin-right:6px;"><input type="checkbox" name="give_credit" value="1"> Give credit</label>
                                <input type="number" step="0.01" name="credit_amount" placeholder="Amount" style="width:90px;margin-right:6px;">
                                <button class="button button-primary" type="submit">Allocate</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>

            <h2 style="margin-top:24px;">Manual site credit</h2>
            <p>Grant site credit to a registered user (stored in user meta 'raffall_site_credit').</p>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="max-width:680px;">
                <?php wp_nonce_field('raffall_grant_site_credit', 'raffall_credit_nonce'); ?>
                <input type="hidden" name="action" value="raffall_grant_site_credit">
                <table class="form-table">
                    <tr><th><label for="rc_email">User email</label></th>
                        <td><input id="rc_email" name="email" type="email" required class="regular-text"></td></tr>
                    <tr><th><label for="rc_amount">Amount</label></th>
                        <td><input id="rc_amount" name="amount" type="number" step="0.01" required class="regular-text" style="width:140px;"></td></tr>
                </table>
                <?php submit_button('Grant credit'); ?>
            </form>
        </div>
        <?php
    }

    // helper: get competition products
    private function get_competition_products(): array {
        $items = [];
        $prods = wc_get_products(['limit' => -1, 'status' => 'publish']);
        foreach ($prods as $p) {
            if ($p->get_meta('_raff_is_competition') === 'yes') $items[] = $p;
        }
        return $items;
    }

    // admin POST handler: export CSV of entries for a competition
    public function admin_export_entries() {
        if (!current_user_can('manage_options')) wp_die('Forbidden');
        $pid = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
        if (!$pid || !check_admin_referer('raffall_export_entries_' . $pid, 'raffall_export_nonce')) {
            wp_die('Invalid request');
        }

        // Prepare CSV headers
        $filename = 'raffall-entries-product-' . $pid . '-' . date('Y-m-d') . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $filename);
        $out = fopen('php://output','w');
        fputcsv($out, ['product_id','product_name','order_id','order_date','user_id','billing_name','billing_email','ticket_numbers','quantity','order_item_id']);

        // Iterate orders and collect items for this product
        $orders = wc_get_orders(['limit' => -1, 'status' => ['processing','completed','on-hold']]);
        foreach ($orders as $order) {
            foreach ($order->get_items('line_item') as $item) {
                $item_product = $item->get_product();
                if (!$item_product) continue;
                if ($item_product->get_id() !== $pid) continue;
                $item_meta = wc_get_order_item_meta($item->get_id(), '_raff_tickets', true);
                $tickets = $item_meta ? $item_meta : '';
                $billing_name = trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name());
                fputcsv($out, [
                    $pid,
                    $item_product->get_name(),
                    $order->get_id(),
                    $order->get_date_created() ? $order->get_date_created()->date('Y-m-d H:i:s') : '',
                    $order->get_user_id(),
                    $billing_name,
                    $order->get_billing_email(),
                    is_array($tickets) ? implode('|', $tickets) : $tickets,
                    (int)$item->get_quantity(),
                    $item->get_id()
                ]);
            }
        }
        fclose($out);
        exit;
    }

    // admin POST handler: allocate winner for a competition
    public function admin_allocate_winner() {
        if (!current_user_can('manage_options')) wp_die('Forbidden');

        $pid = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
        if (!$pid || !check_admin_referer('raffall_allocate_winner_' . $pid, 'raffall_allocate_nonce')) {
            wp_redirect(wp_get_referer() ?: admin_url()); exit;
        }

        $ticket = isset($_POST['ticket_number']) ? sanitize_text_field($_POST['ticket_number']) : '';
        $winner_name = isset($_POST['winner_name']) ? sanitize_text_field($_POST['winner_name']) : '';
        $winner_email = isset($_POST['winner_email']) ? sanitize_email($_POST['winner_email']) : '';
        $give_credit = isset($_POST['give_credit']) && $_POST['give_credit'] ? true : false;
        $credit_amount = isset($_POST['credit_amount']) ? floatval($_POST['credit_amount']) : 0.0;

        // create winner post
        $title = sprintf('Winner: %s - Ticket %s', $winner_name ?: $winner_email ?: 'Winner', $ticket ?: 'N/A');
        $post_id = wp_insert_post([
            'post_type' => 'raff_winner',
            'post_title' => $title,
            'post_status' => 'publish',
            'post_content' => '',
        ]);
        if ($post_id) {
            update_post_meta($post_id, 'raff_competition_product_id', $pid);
            update_post_meta($post_id, 'raff_prize_name', 'Prize'); // admin can edit later
            update_post_meta($post_id, 'raff_ticket_number', $ticket);
            update_post_meta($post_id, 'raff_winner_name', $winner_name ?: $winner_email);
            update_post_meta($post_id, 'raff_consent_public', false);

            $user_id = 0;
            if ($winner_email) {
                $user = get_user_by('email', $winner_email);
                if ($user) {
                    $user_id = $user->ID;
                    // optionally add site credit
                    if ($give_credit && $credit_amount > 0) {
                        $existing = (float)get_user_meta($user_id, 'raffall_site_credit', true) ?: 0;
                        $new = $existing + $credit_amount;
                        update_user_meta($user_id, 'raffall_site_credit', $new);
                        $this->audit('site_credit_granted', ['user_id' => $user_id, 'amount' => $credit_amount, 'source' => 'admin_allocate_winner', 'product_id' => $pid, 'ticket' => $ticket]);
                    }
                }
            }

            // If there is an instant ledger entry matching this product + ticket, mark claimed and attach order_id/user if possible
            global $wpdb;
            $table = $wpdb->prefix . self::INSTANT_TABLE;
            if (!empty($ticket)) {
                $wpdb->update($table, [
                    'claimed' => 1,
                    'user_id' => $user_id ? $user_id : null,
                ], [
                    'product_id' => $pid,
                    'ticket_number' => $ticket,
                ]);
            }

            $this->audit('winner_allocated', ['post_id'=>$post_id,'product_id'=>$pid,'ticket'=>$ticket,'user_id'=>$user_id]);
        }

        wp_redirect(add_query_arg('raffall_msg','winner_allocated', wp_get_referer() ?: admin_url())); exit;
    }

    // admin POST handler: grant arbitrary site credit to a user
    public function admin_grant_site_credit() {
        if (!current_user_can('manage_options')) wp_die('Forbidden');
        if (!check_admin_referer('raffall_grant_site_credit', 'raffall_credit_nonce')) {
            wp_redirect(wp_get_referer() ?: admin_url()); exit;
        }
        $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
        $amount = isset($_POST['amount']) ? floatval($_POST['amount']) : 0.0;
        if (!$email || $amount <= 0) {
            wp_redirect(add_query_arg('raffall_msg','invalid', wp_get_referer() ?: admin_url())); exit;
        }
        $user = get_user_by('email', $email);
        if (!$user) {
            wp_redirect(add_query_arg('raffall_msg','no_user', wp_get_referer() ?: admin_url())); exit;
        }
        $uid = $user->ID;
        $existing = (float)get_user_meta($uid, 'raffall_site_credit', true) ?: 0;
        $new = $existing + $amount;
        update_user_meta($uid, 'raffall_site_credit', $new);
        $this->audit('site_credit_granted', ['user_id' => $uid, 'amount' => $amount, 'source' => 'admin_manual']);
        wp_redirect(add_query_arg('raffall_msg','credit_granted', wp_get_referer() ?: admin_url())); exit;
    }

    /* Frontend: countdown and progress */
    public function render_countdown_and_progress() {
        global $product;

        // Ensure we have a WC_Product instance (handles cases where $product may be post/ID)
        $prod = wc_get_product($product);
        if (!$prod || $prod->get_meta('_raff_is_competition') !== 'yes') return;

        // Respect product page toggles
        $show_countdown = get_option('raffall_show_countdown_product', '1') === '1';
        $show_progress  = get_option('raffall_show_progress_product', '1') === '1';
        if (!$show_countdown && !$show_progress) return;

        $draw = $prod->get_meta('_raff_draw_date'); // stored as UTC ISO like 2025-12-13T15:00:00Z
        $cap  = (int) $prod->get_meta('_raff_ticket_cap');

        // Normalise stock value to an integer (some product types may return null)
        $stock_raw = $prod->get_stock_quantity();
        $stock = is_numeric($stock_raw) ? (int) $stock_raw : 0;

        if ($cap < 1) {
            $next = (int) $prod->get_meta('_raff_next_ticket');
            if ($next < 1) $next = 1;
            if (is_numeric($stock_raw)) {
                $cap = $stock + max(0, $next - 1);
            }
        }

        $cap = max(0, $cap);
        $sold = ($cap > 0) ? max(0, $cap - max(0, $stock)) : 0;
        $percent = ($cap > 0) ? round(($sold / $cap) * 100) : 0;

        echo '<div class="raff-meta-block" style="margin-bottom:12px;">';
        if ($draw && $show_countdown) {
            // Flip-style countdown structure: each unit has .raff-flip with data-unit
            echo '<div class="raff-countdown" data-draw="' . esc_attr($draw) . '">';
            echo '<div class="raff-flip" data-unit="days"><div class="number"><span class="current">00</span><span class="next">00</span></div><div class="label">Days</div></div>';
            echo '<div class="raff-flip" data-unit="hours"><div class="number"><span class="current">00</span><span class="next">00</span></div><div class="label">Hours</div></div>';
            echo '<div class="raff-flip" data-unit="minutes"><div class="number"><span class="current">00</span><span class="next">00</span></div><div class="label">Minutes</div></div>';
            echo '<div class="raff-flip" data-unit="seconds"><div class="number"><span class="current">00</span><span class="next">00</span></div><div class="label">Seconds</div></div>';
            echo '</div>';
        }
        if ($cap > 0 && $show_progress) {
            echo '<div class="raff-progress" aria-label="Tickets sold">';
            echo '<div class="raff-progress-bar" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="' . esc_attr($percent) . '" style="--raff-percent:' . esc_attr($percent) . '%; background: var(--raff-bg);">';
            echo '<span class="raff-progress-inner" style="width:' . esc_attr($percent) . '%; background: linear-gradient(90deg,var(--raff-fill),#3fb1ff);"></span>';
            echo '</div>';
            echo '<div class="raff-progress-text" style="font-size:13px;color:#333;margin-top:6px;">' . esc_html($percent) . '% sold (' . esc_html($sold) . '/' . esc_html($cap) . ')</div>';
            echo '</div>';
        }
        echo '</div>';
    }

    /* Validation question UI and gate */
    public function render_validation_question() {
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

        echo '<fieldset class="raff-validation" style="margin-bottom:12px;border:0;padding:0;">';
        echo '<legend style="font-weight:600;margin-bottom:6px;">' . esc_html($q) . '</legend>';

        $name = 'raff_choice';
        $opts = [
            1 => $opt1,
            2 => $opt2,
            3 => $opt3,
        ];
        foreach ($opts as $idx => $label) {
            if (empty($label)) continue;
            $id = 'raff_choice_' . $idx;
            echo '<div style="margin-bottom:6px;">';
            echo '<input type="radio" id="' . esc_attr($id) . '" name="' . esc_attr($name) . '" value="' . esc_attr($idx) . '" required>';
            echo '<label for="' . esc_attr($id) . '" style="margin-left:8px;">' . esc_html($label) . '</label>';
            echo '</div>';
        }

        echo '<input type="hidden" name="raff_product_id" value="' . esc_attr($product->get_id()) . '">';
        echo '<p style="font-size:12px;color:#666;margin-top:4px;">Select the correct option to proceed.</p>';
        echo '</fieldset>';
    }

    public function validate_question_before_add_to_cart($passed, $product_id, $quantity, $variation_id = 0) {
        $is_comp = get_post_meta($product_id, '_raff_is_competition', true) === 'yes';
        $question = get_post_meta($product_id, '_raff_validation_question', true);

        if ($is_comp && $question) {
            // Try multiple-choice first
            $opt1 = get_post_meta($product_id, '_raff_validation_option_1', true);
            $opt2 = get_post_meta($product_id, '_raff_validation_option_2', true);
            $opt3 = get_post_meta($product_id, '_raff_validation_option_3', true);
            $correct = get_post_meta($product_id, '_raff_validation_correct_option', true); // '1','2','3'

            $has_options = ($opt1 || $opt2 || $opt3) && in_array($correct, ['1','2','3'], true);

            if ($has_options) {
                $user_choice = isset($_POST['raff_choice']) ? sanitize_text_field($_POST['raff_choice']) : '';
                if (!$user_choice) {
                    wc_add_notice('Please select an answer to the validation question.', 'error');
                    $this->audit('validation_missing_choice', ['product_id' => $product_id]);
                    return false;
                }
                if ($user_choice !== $correct) {
                    wc_add_notice('Incorrect answer to the validation question.', 'error');
                    $this->audit('validation_incorrect_choice', ['product_id' => $product_id, 'choice' => $this->mask($user_choice)]);
                    return false;
                }
                $this->audit('validation_pass_choice', ['product_id' => $product_id, 'choice' => (int)$user_choice]);
                return $passed;
            }

            // Backwards compatibility: free-text answer (if options not configured)
            $answer   = get_post_meta($product_id, '_raff_validation_answer', true);
            if ($answer) {
                $user_answer = isset($_POST['raff_answer']) ? trim($_POST['raff_answer']) : '';
                if (!$user_answer) {
                    wc_add_notice('Please answer the validation question.', 'error');
                    $this->audit('validation_missing', ['product_id' => $product_id]);
                    return false;
                }
                if (mb_strtolower($user_answer) !== mb_strtolower($answer)) {
                    wc_add_notice('Incorrect answer to the validation question.', 'error');
                    $this->audit('validation_incorrect', ['product_id' => $product_id, 'answer' => $this->mask($user_answer)]);
                    return false;
                }
                $this->audit('validation_pass', ['product_id' => $product_id]);
            }
        }
        return $passed;
    }

    /* Ticket allocation and instant wins */
    public function assign_tickets_and_instant_wins($order_id) {
        $order = wc_get_order($order_id);
        if (!$order) return;

        foreach ($order->get_items('line_item') as $item_id => $item) {
            $product = $item->get_product();
            if (!$product) continue;
            $pid = $product->get_id();
            $is_comp = $product->get_meta('_raff_is_competition') === 'yes';
            if (!$is_comp) continue;

            $qty  = (int)$item->get_quantity();
            $next = (int)$product->get_meta('_raff_next_ticket');
            if ($next < 1) $next = 1;

            $tickets = [];
            for ($i=0; $i<$qty; $i++) {
                $tickets[] = $next++;
            }
            $product->update_meta_data('_raff_next_ticket', $next);
            $product->save();

            wc_add_order_item_meta($item_id, '_raff_tickets', implode(',', $tickets));
            $this->audit('tickets_assigned', ['order_id' => $order_id, 'product_id' => $pid, 'tickets' => $tickets]);

            if ($product->get_meta('_raff_is_instant_win') === 'yes') {
                $wins = $this->check_instant_wins($pid, $tickets, $order);
                if (!empty($wins)) {
                    wc_add_order_item_meta($item_id, '_raff_instant_wins', wp_json_encode($wins));
                    $this->audit('instant_wins_revealed', ['order_id' => $order_id, 'product_id' => $pid, 'wins' => $wins]);
                    $note = "Instant wins: \n";
                    foreach ($wins as $w) $note .= "Ticket {$w['ticket']} → {$w['prize']}\n";
                    $order->add_order_note($note);
                }
            }
        }
    }

    private function seed_instant_ledger(int $product_id, string $csv = '') {
        if (!$csv) return;
        global $wpdb;
        $table = $wpdb->prefix . self::INSTANT_TABLE;
        $lines = preg_split('/\r\n|\r|\n/', $csv);
        foreach ($lines as $line) {
            $line = trim($line);
            if (!$line) continue;
            $parts = array_map('trim', explode(',', $line, 2));
            if (count($parts) < 2) continue;
            $ticket = (int)$parts[0];
            $prize  = sanitize_text_field($parts[1]);
            $wpdb->query($wpdb->prepare(
                "INSERT IGNORE INTO $table (product_id, ticket_number, prize) VALUES (%d, %d, %s)",
                $product_id, $ticket, $prize
            ));
        }
        $this->audit('instant_seed', ['product_id' => $product_id, 'seeded' => count($lines)]);
    }

    private function check_instant_wins(int $product_id, array $tickets, WC_Order $order): array {
        global $wpdb;
        $table = $wpdb->prefix . self::INSTANT_TABLE;
        $wins = [];
        foreach ($tickets as $t) {
            $row = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $table WHERE product_id=%d AND ticket_number=%d AND claimed=0",
                $product_id, $t
            ), ARRAY_A);
            if ($row) {
                $wins[] = ['ticket' => (int)$row['ticket_number'], 'prize' => $row['prize']];
                $wpdb->update($table, [
                    'claimed' => 1,
                    'order_id' => $order->get_id(),
                    'user_id' => (int)$order->get_user_id(),
                ], [
                    'id' => (int)$row['id']
                ]);
            }
        }
        return $wins;
    }

    /* Account endpoints */
    public function register_account_endpoints() {
        add_rewrite_endpoint('tickets', EP_ROOT | EP_PAGES);
        add_rewrite_endpoint('instant-wins', EP_ROOT | EP_PAGES);
    }

    public function add_account_menu_items($items) {
        $new = [];
        foreach ($items as $key => $label) {
            $new[$key] = $label;
            if ($key === 'orders') {
                $new['tickets'] = 'My tickets';
                $new['instant-wins'] = 'Instant wins';
            }
        }
        return $new;
    }

    public function account_tickets_view() {
        $user_id = get_current_user_id();
        if (!$user_id) {
            echo '<p>Please log in to view your tickets.</p>';
            return;
        }
        $orders = wc_get_orders(['customer_id' => $user_id, 'limit' => -1, 'status' => ['completed','processing']]);
        echo '<h3>Your tickets</h3>';
        echo '<table class="shop_table"><thead><tr><th>Order</th><th>Competition</th><th>Tickets</th></tr></thead><tbody>';
        foreach ($orders as $order) {
            foreach ($order->get_items('line_item') as $item) {
                $tickets = wc_get_order_item_meta($item->get_id(), '_raff_tickets', true);
                if (!$tickets) continue;
                $product = $item->get_product();
                echo '<tr>';
                echo '<td>#' . esc_html($order->get_id()) . '</td>';
                echo '<td>' . esc_html($product ? $product->get_name() : 'Competition') . '</td>';
                echo '<td>' . esc_html($tickets) . '</td>';
                echo '</tr>';
            }
        }
        echo '</tbody></table>';
        echo '<p>Guest purchases are accessible via your email order confirmations.</p>';
    }

    public function account_instant_wins_view() {
        $user_id = get_current_user_id();
        if (!$user_id) {
            echo '<p>Please log in to view instant wins.</p>';
            return;
        }
        global $wpdb;
        $table = $wpdb->prefix . self::INSTANT_TABLE;
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE user_id=%d AND claimed=1 ORDER BY created_at DESC",
            $user_id
        ), ARRAY_A);
        echo '<h3>Your instant wins</h3>';
        if (empty($rows)) {
            echo '<p>No instant wins yet.</p>';
            return;
        }
        echo '<table class="shop_table"><thead><tr><th>Competition</th><th>Ticket</th><th>Prize</th><th>Order</th></tr></thead><tbody>';
        foreach ($rows as $r) {
            echo '<tr>';
            echo '<td>' . esc_html(get_the_title($r['product_id'])) . '</td>';
            echo '<td>' . esc_html($r['ticket_number']) . '</td>';
            echo '<td>' . esc_html($r['prize']) . '</td>';
            echo '<td>#' . esc_html($r['order_id']) . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
    }

    /* Shortcodes */
    public function shortcode_home($atts) {
        // Guard if WooCommerce not available
        if (!function_exists('wc_get_products')) {
            return '<p>Premium competitions are unavailable because WooCommerce is not active.</p>';
        }

        $atts = shortcode_atts([
            'show_countdown' => null, // null means use global option
            'show_progress' => null,
            'fill_color' => null,
            'bg_color' => null,
        ], (array)$atts, 'raffall_home');

        // If shortcode overrides colours, inject inline style scoped to this output
        $inline_style = '';
        if ($atts['fill_color'] || $atts['bg_color']) {
            $fill = $atts['fill_color'] ? esc_attr($atts['fill_color']) : get_option('raffall_fill_color', '#7b3cff');
            $bg   = $atts['bg_color'] ? esc_attr($atts['bg_color']) : get_option('raffall_bg_color', '#f1f1f1');
            $inline_style = "<style>.raff-home{--raff-fill:{$fill};--raff-bg:{$bg};}</style>";
        }

        $prods = wc_get_products(['limit' => -1, 'status' => 'publish']);
        $competitions = [];
        $instants = [];
        $free = [];
        foreach ($prods as $p) {
            if ($p->get_meta('_raff_is_competition') === 'yes') {
                $item = [
                    'id' => $p->get_id(),
                    'name' => $p->get_name(),
                    'price' => $p->get_price(),
                    'stock' => $p->get_stock_quantity(),
                    'draw' => $p->get_meta('_raff_draw_date'),
                    'instant' => $p->get_meta('_raff_is_instant_win') === 'yes',
                    'free' => $p->get_meta('_raff_is_free_entry') === 'yes',
                    'url' => get_permalink($p->get_id()),
                    'image' => wp_get_attachment_image_src($p->get_image_id(), 'medium')[0] ?? '',
                    'product_obj' => $p,
                ];
                $competitions[] = $item;
                if ($item['instant']) $instants[] = $item;
                if ($item['free']) $free[] = $item;
            }
        }
        ob_start();
        echo $inline_style;
        echo '<div class="raff-home">';
        echo '<h2>Premium competitions</h2>';
        $this->render_cards($competitions, false, $atts);
        echo '<h2 style="margin-top:24px;">Instant win prizes</h2>';
        $this->render_cards($instants, false, $atts);
        echo '<h2 style="margin-top:24px;">Free entry prizes</h2>';
        $this->render_cards($free, true, $atts);
        echo '</div>';
        return ob_get_clean();
    }

    public function shortcode_winners($atts) {
        $atts = shortcode_atts([
            'fill_color' => null,
            'bg_color' => null,
        ], (array)$atts, 'raffall_winners');

        $inline_style = '';
        if ($atts['fill_color'] || $atts['bg_color']) {
            $fill = $atts['fill_color'] ? esc_attr($atts['fill_color']) : get_option('raffall_fill_color', '#7b3cff');
            $bg   = $atts['bg_color'] ? esc_attr($atts['bg_color']) : get_option('raffall_bg_color', '#f1f1f1');
            $inline_style = "<style>.raff-winners{--raff-fill:{$fill};--raff-bg:{$bg};}</style>";
        }

        $q = new WP_Query([
            'post_type' => 'raff_winner',
            'posts_per_page' => 12,
            'paged' => max(1, get_query_var('paged')),
        ]);
        ob_start();
        echo $inline_style;
        echo '<div class="raff-winners">';
        echo '<h2>Recent Winners</h2>';
        if ($q->have_posts()) {
            echo '<div class="raff-winner-grid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:16px;">';
            while ($q->have_posts()) { $q->the_post();
                $pid = (int)get_post_meta(get_the_ID(), 'raff_competition_product_id', true);
                $prize = get_post_meta(get_the_ID(), 'raff_prize_name', true);
                $ticket= get_post_meta(get_the_ID(), 'raff_ticket_number', true);
                $name = get_post_meta(get_the_ID(), 'raff_winner_name', true);
                $consent = (bool)get_post_meta(get_the_ID(), 'raff_consent_public', true);
                echo '<div class="card" style="border:1px solid #eee;border-radius:8px;overflow:hidden;">';
                if (has_post_thumbnail()) {
                    echo get_the_post_thumbnail(get_the_ID(), 'medium', ['style'=>'width:100%;height:auto;display:block;']);
                }
                echo '<div style="padding:12px">';
                echo '<h3 style="margin:0 0 6px;">' . esc_html(get_the_title()) . '</h3>';
                echo '<p style="margin:0;">Prize: <strong>' . esc_html($prize ?: get_the_title($pid)) . '</strong></p>';
                echo '<p style="margin:0;">Ticket: ' . esc_html($ticket) . '</p>';
                echo '<p style="margin:0;">Winner: ' . esc_html($consent ? $name : 'Name withheld') . '</p>';
                echo '</div></div>';
            }
            echo '</div>';
            wp_reset_postdata();
        } else {
            echo '<p>No winners published yet.</p>';
        }
        echo '</div>';
        return ob_get_clean();
    }

    private function render_cards(array $items, bool $show_free_info = false, array $opts = []) {
        // opts can override global options: show_countdown, show_progress, fill_color, bg_color
        $show_countdown_cards = array_key_exists('show_countdown', $opts) && $opts['show_countdown'] !== null
            ? (bool)$opts['show_countdown']
            : (get_option('raffall_show_countdown_cards','0') === '1');
        $show_progress_cards = array_key_exists('show_progress', $opts) && $opts['show_progress'] !== null
            ? (bool)$opts['show_progress']
            : (get_option('raffall_show_progress_cards','0') === '1');

        if (empty($items)) { echo '<p>No items available.</p>'; return; }
        echo '<div class="raff-grid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:16px;">';
        foreach ($items as $it) {
            echo '<div class="raff-card" style="border:1px solid #eee;border-radius:12px;overflow:hidden;">';
            if ($it['image']) echo '<img src="' . esc_url($it['image']) . '" alt="" style="width:100%;height:auto;display:block;">';
            echo '<div style="padding:12px">';
            echo '<h3 style="margin:0 0 6px;">' . esc_html($it['name']) . '</h3>';
            echo '<p style="margin:0 0 8px;">Draw: ' . esc_html($it['draw'] ?: 'TBA') . '</p>';
            echo '<p style="margin:0 0 8px;">Price: ' . wc_price($it['price']) . '</p>';

            // Optionally include countdown on product cards
            if ($show_countdown_cards && !empty($it['draw'])) {
                echo '<div class="raff-countdown" data-draw="' . esc_attr($it['draw']) . '" style="margin-bottom:8px;">';
                echo '<div class="raff-flip" data-unit="days"><div class="number"><span class="current">00</span><span class="next">00</span></div><div class="label">Days</div></div>';
                echo '<div class="raff-flip" data-unit="hours"><div class="number"><span class="current">00</span><span class="next">00</span></div><div class="label">Hours</div></div>';
                echo '<div class="raff-flip" data-unit="minutes"><div class="number"><span class="current">00</span><span class="next">00</span></div><div class="label">Minutes</div></div>';
                echo '<div class="raff-flip" data-unit="seconds"><div class="number"><span class="current">00</span><span class="next">00</span></div><div class="label">Seconds</div></div>';
                echo '</div>';
            }

            echo '<a class="button" href="' . esc_url($it['url']) . '">View competition</a>';
            if ($show_progress_cards) {
                // compute percent using product if available
                $percent = 0; $sold = 0; $cap = 0;
                if (!empty($it['product_obj']) && is_object($it['product_obj'])) {
                    $p = $it['product_obj'];
                    $cap = (int)$p->get_meta('_raff_ticket_cap');
                    $stock = $p->get_stock_quantity();
                    if ($cap < 1) {
                        $next = (int)$p->get_meta('_raff_next_ticket');
                        if ($next < 1) $next = 1;
                        if (is_numeric($stock)) $cap = $stock + max(0, $next - 1);
                    }
                    $cap = max(0,$cap);
                    $sold = ($cap > 0) ? max(0, $cap - max(0, (int)$stock)) : 0;
                    $percent = ($cap > 0) ? round(($sold / $cap) * 100) : 0;
                }
                if ($cap > 0) {
                    echo '<div class="raff-progress" aria-label="Tickets sold" style="margin-top:8px;">';
                    echo '<div class="raff-progress-bar" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="' . esc_attr($percent) . '" style="--raff-percent:' . esc_attr($percent) . '%; background: var(--raff-bg);">';
                    echo '<span class="raff-progress-inner" style="width:' . esc_attr($percent) . '%; background: linear-gradient(90deg,var(--raff-fill),#3fb1ff);"></span>';
                    echo '</div>';
                    echo '<div class="raff-progress-text" style="font-size:13px;color:#333;margin-top:6px;">' . esc_html($percent) . '% sold (' . esc_html($sold) . '/' . esc_html($cap) . ')</div>';
                    echo '</div>';
                }
            }

            if ($show_free_info) echo '<p style="font-size:12px;color:#666;margin-top:8px;">Free entry available via postal method with parity limits.</p>';
            echo '</div></div>';
        }
        echo '</div>';
    }

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

} // end class RaffAll

new RaffAll();