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

        add_action('update_option', function($option, $old, $new){
            if (str_starts_with($option, 'raffall_')) $this->audit('option_update', ['option' => $option]);
        }, 10, 3);
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

    /* Frontend: countdown and progress */
    public function render_countdown_and_progress() {
        global $product;
        if (!$product || $product->get_meta('_raff_is_competition') !== 'yes') return;

        $draw = $product->get_meta('_raff_draw_date'); // stored as UTC ISO like 2025-12-13T15:00:00Z
        $cap  = (int) $product->get_meta('_raff_ticket_cap');
        $stock = $product->get_stock_quantity();
        if ($cap < 1) {
            $next = (int) $product->get_meta('_raff_next_ticket');
            if ($next < 1) $next = 1;
            if (is_numeric($stock)) {
                $cap = $stock + max(0, $next - 1);
            }
        }
        $cap = max(0, $cap);
        $sold = ($cap > 0) ? max(0, $cap - max(0, (int)$stock)) : 0;
        $percent = ($cap > 0) ? round(($sold / $cap) * 100) : 0;

        echo '<div class="raff-meta-block" style="margin-bottom:12px;">';
        if ($draw) {
            echo '<div class="raff-countdown" data-draw="' . esc_attr($draw) . '">';
            echo '<strong>Draw in:</strong> <span class="raff-countdown-timer">Loading…</span>';
            echo '</div>';
        }
        if ($cap > 0) {
            echo '<div class="raff-progress" aria-label="Tickets sold">';
            echo '<div class="raff-progress-bar" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="' . esc_attr($percent) . '" style="--raff-percent:' . esc_attr($percent) . '%">';
            echo '<span class="raff-progress-inner" style="width:' . esc_attr($percent) . '%"></span>';
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
                ];
                $competitions[] = $item;
                if ($item['instant']) $instants[] = $item;
                if ($item['free']) $free[] = $item;
            }
        }
        ob_start();
        echo '<div class="raff-home">';
        echo '<h2>Premium competitions</h2>';
        $this->render_cards($competitions);
        echo '<h2 style="margin-top:24px;">Instant win prizes</h2>';
        $this->render_cards($instants);
        echo '<h2 style="margin-top:24px;">Free entry prizes</h2>';
        $this->render_cards($free, true);
        echo '</div>';
        return ob_get_clean();
    }

    public function shortcode_winners($atts) {
        $q = new WP_Query([
            'post_type' => 'raff_winner',
            'posts_per_page' => 12,
            'paged' => max(1, get_query_var('paged')),
        ]);
        ob_start();
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

    private function render_cards(array $items, bool $show_free_info = false) {
        if (empty($items)) { echo '<p>No items available.</p>'; return; }
        echo '<div class="raff-grid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:16px;">';
        foreach ($items as $it) {
            echo '<div class="raff-card" style="border:1px solid #eee;border-radius:12px;overflow:hidden;">';
            if ($it['image']) echo '<img src="' . esc_url($it['image']) . '" alt="" style="width:100%;height:auto;display:block;">';
            echo '<div style="padding:12px">';
            echo '<h3 style="margin:0 0 6px;">' . esc_html($it['name']) . '</h3>';
            echo '<p style="margin:0 0 8px;">Draw: ' . esc_html($it['draw'] ?: 'TBA') . '</p>';
            echo '<p style="margin:0 0 8px;">Price: ' . wc_price($it['price']) . '</p>';
            echo '<a class="button" href="' . esc_url($it['url']) . '">View competition</a>';
            if ($show_free_info) echo '<p style="font-size:12px;color:#666;margin-top:8px;">Free entry available via postal method with parity limits.</p>';
            echo '</div></div>';
        }
        echo '</div>';
    }

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
        wp_register_script('raffall-frontend', plugin_dir_url(__FILE__) . 'assets/raffall-frontend.js', ['jquery'], self::VERSION, true);
        wp_enqueue_script('raffall-frontend');
        wp_register_style('raffall-frontend-css', plugin_dir_url(__FILE__) . 'assets/raffall-frontend.css', [], self::VERSION);
        wp_enqueue_style('raffall-frontend-css');
    }

    /* Admin assets for Flatpickr timezone picker */
    public function enqueue_raff_admin_assets($hook) {
        if (!in_array($hook, ['post.php', 'post-new.php'], true)) return;
        $screen = get_current_screen();
        if ($screen && $screen->post_type !== 'product') return;

        wp_enqueue_style('flatpickr-css', 'https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css', [], self::VERSION);
        wp_enqueue_script('flatpickr-js', 'https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.js', [], self::VERSION, true);

        wp_register_script('raffall-admin-draw', plugin_dir_url(__FILE__) . 'assets/admin-raff-draw.js', ['jquery', 'flatpickr-js'], self::VERSION, true);
        wp_enqueue_script('raffall-admin-draw');

        $tzs = timezone_identifiers_list();
        wp_localize_script('raffall-admin-draw', 'raffAllAdminData', [
            'timezones' => $tzs,
        ]);
    }
}

new RaffAll();

