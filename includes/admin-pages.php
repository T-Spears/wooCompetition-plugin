<?php
if (!defined('ABSPATH')) exit;

function raffall_add_admin_settings_page() {
    // Top-level menu for the plugin. All plugin pages live under this menu.
    $capability = 'manage_options';
    $parent_slug = 'woocompetitions';

    add_menu_page(
        'WooCompetitions',                // page title
        'WooCompetitions',                // menu title
        $capability,                      // capability
        $parent_slug,                     // menu slug
        'raffall_render_settings_page',   // callback
        'dashicons-tickets',              // icon
        56                                 // position
    );

    // Submenu: Settings (same slug as parent to avoid duplicate top-level page)
    add_submenu_page(
        $parent_slug,
        'Settings',
        'Settings',
        $capability,
        $parent_slug,
        'raffall_render_settings_page'
    );

    // Submenu: Competitions (admin UI for export / allocation / credit)
    add_submenu_page(
        $parent_slug,
        'Competitions',
        'Competitions',
        $capability,
        'raffall-competitions',
        'raffall_render_competitions_page'
    );

    // Submenu: Winners (link to CPT list)
    add_submenu_page(
        $parent_slug,
        'Winners',
        'Winners',
        $capability,
        'edit.php?post_type=raff_winner'
    );

    // NEW: Submenu: How it works (documentation / quick help)
    add_submenu_page(
        $parent_slug,
        'How the plugin works',
        'How it works',
        $capability,
        'raffall-how-it-works',
        'raffall_render_how_it_works'
    );
}

function raffall_register_admin_settings() {
    register_setting('raffall_display_group', 'raffall_fill_color', ['type'=>'string','default'=>'#7b3cff']);
    register_setting('raffall_display_group', 'raffall_bg_color', ['type'=>'string','default' => '#f1f1f1']);
    register_setting('raffall_display_group', 'raffall_flip_bg', ['type'=>'string','default' => '#fff']);
    register_setting('raffall_display_group', 'raffall_flip_text', ['type'=>'string','default' => '#222']);
    register_setting('raffall_display_group', 'raffall_show_countdown_product', ['type'=>'string','default' => '1']);
    register_setting('raffall_display_group', 'raffall_show_progress_product', ['type'=>'string','default' => '1']);
    register_setting('raffall_display_group', 'raffall_show_countdown_cards', ['type'=>'string','default' => '0']);
    register_setting('raffall_display_group', 'raffall_show_progress_cards', ['type'=>'string','default' => '0']);
    register_setting('raffall_display_group', 'raffall_cart_sidebar_enable', ['type'=>'string','default' => '1']);

    // New: question button colours
    register_setting('raffall_display_group', 'raffall_question_btn_bg', ['type'=>'string', 'default' => '#ffffff']);
    register_setting('raffall_display_group', 'raffall_question_btn_text', ['type'=>'string', 'default' => '#222222']);
    register_setting('raffall_display_group', 'raffall_question_btn_bg_active', ['type'=>'string', 'default' => '#7b3cff']);

    // New: validation question presentation style (buttons | dropdown)
    register_setting('raffall_display_group', 'raffall_question_style', ['type' => 'string', 'default' => 'buttons']);
}

function raffall_render_settings_page() {
    if (!current_user_can('manage_options')) return;
    ?>
    <div class="wrap">
        <h1>WooCompetitions — Settings</h1>
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
                        <label><input type="checkbox" name="raffall_show_countdown_product" value="1" <?php checked('1', get_option('raffall_show_countdown_product', '1')); ?>> Show countdown on product pages</label>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Product page: show progress</th>
                    <td>
                        <label><input type="checkbox" name="raffall_show_progress_product" value="1" <?php checked('1', get_option('raffall_show_progress_product', '1')); ?>> Show progress on product pages</label>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Product cards: show countdown</th>
                    <td>
                        <label><input type="checkbox" name="raffall_show_countdown_cards" value="1" <?php checked('1', get_option('raffall_show_countdown_cards', '0')); ?>> Show countdown on product cards</label>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Product cards: show progress</th>
                    <td>
                        <label><input type="checkbox" name="raffall_show_progress_cards" value="1" <?php checked('1', get_option('raffall_show_progress_cards', '0')); ?>> Show progress on product cards</label>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Cart sidebar: enable</th>
                    <td>
                        <label><input type="checkbox" name="raffall_cart_sidebar_enable" value="1" <?php checked('1', get_option('raffall_cart_sidebar_enable', '1')); ?>> Enable cart sidebar by default</label>
                        <p class="description">When enabled the cart sidebar will be injected into the footer. It remains fully customisable in the frontend.</p>
                    </td>
                </tr>

                <!-- NEW: validation question presentation -->
                <tr>
                    <th scope="row">Validation question style</th>
                    <td>
                        <?php $style = get_option('raffall_question_style', 'buttons'); ?>
                        <select name="raffall_question_style" id="raffall_question_style">
                            <option value="buttons" <?php selected('buttons', $style); ?>>Clickable buttons (modern)</option>
                            <option value="dropdown" <?php selected('dropdown', $style); ?>>Dropdown</option>
                        </select>
                        <p class="description">Choose how the validation question is presented to customers on product pages.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Question button background</th>
                    <td>
                        <input type="color" id="raffall_question_btn_bg" name="raffall_question_btn_bg" value="<?php echo esc_attr(get_option('raffall_question_btn_bg', '#ffffff')); ?>">
                        <p class="description">Background colour for unselected question buttons.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Question button text colour</th>
                    <td>
                        <input type="color" id="raffall_question_btn_text" name="raffall_question_btn_text" value="<?php echo esc_attr(get_option('raffall_question_btn_text', '#222222')); ?>">
                        <p class="description">Text colour used inside question buttons.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Question button active background</th>
                    <td>
                        <input type="color" id="raffall_question_btn_bg_active" name="raffall_question_btn_bg_active" value="<?php echo esc_attr(get_option('raffall_question_btn_bg_active', '#7b3cff')); ?>">
                        <p class="description">Background colour for the selected/active question button.</p>
                    </td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}

function raffall_render_competitions_page() {
    if (!current_user_can('manage_options')) return;
    // existing competitions page implementation (export, allocate, grant credit) goes here.
    // For brevity, re-use the earlier implementation or paste full markup as needed.
    $competitions = array_filter(wc_get_products(['limit'=>-1,'status'=>'publish']), function($p){ return $p->get_meta('_raff_is_competition') === 'yes'; });
    ?>
    <div class="wrap">
        <h1>WooCompetitions — Competitions</h1>
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
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline-block;margin-right:8px;">
                            <?php wp_nonce_field('raffall_export_entries_' . $pid, 'raffall_export_nonce'); ?>
                            <input type="hidden" name="action" value="raffall_export_entries">
                            <input type="hidden" name="product_id" value="<?php echo esc_attr($pid); ?>">
                            <button class="button" type="submit">Export CSV</button>
                        </form>
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

// NEW: render the "How it works" admin page
function raffall_render_how_it_works() {
    if (!current_user_can('manage_options')) return;
    ?>
    <div class="wrap">
        <h1>How WooCompetitions works</h1>
        <p>Quick overview of the plugin and where to find settings:</p>

        <h2>Core concepts</h2>
        <ul>
            <li><strong>Competitions</strong> are enabled on a product by ticking <em>Is competition</em> on the product edit screen.</li>
            <li><strong>Validation question</strong>: set a question and three options on the product — choose the correct option so customers must answer before adding to cart.</li>
            <li><strong>Instant wins</strong>: seed ticket-to-prize CSV on the product and enable <em>Has instant wins</em>.</li>
            <li><strong>Featured</strong>: tick <em>Featured competition</em> to include the product in the Featured competitions homepage shortcode.</li>
        </ul>

        <h2>Where to manage</h2>
        <ul>
            <li><a href="<?php echo esc_url(admin_url('admin.php?page=woocompetitions')); ?>">Settings → WooCompetitions</a> — visual and display settings.</li>
            <li><a href="<?php echo esc_url(admin_url('admin.php?page=raffall-competitions')); ?>">Competitions</a> — export entries, allocate winners, grant site credit.</li>
            <li><a href="<?php echo esc_url(admin_url('edit.php?post_type=raff_winner')); ?>">Winners</a> — published winners list.</li>
            <li><a href="<?php echo esc_url(admin_url('edit.php?post_type=product')); ?>">Products</a> — configure competition fields per product.</li>
        </ul>

        <h2>Shortcodes & Block</h2>
        <p>Use the shortcodes below in posts, pages or widgets. The Gutenberg block <em>Competition Card</em> is also available in the editor.</p>

        <h3>raffall_home</h3>
        <p>Displays featured competitions. Only products with <strong>Featured competition</strong> checked appear here.</p>
        <pre><code>[raffall_home]</code></pre>
        <p>No attributes. You can override colours using the global settings or the block/shortcode fill_color/bg_color attributes where supported.</p>

        <h3>raffall_winners</h3>
        <p>Shows recent published winners (grid).</p>
        <pre><code>[raffall_winners]</code></pre>
        <p>No attributes. Use the Winners CPT to manage entries.</p>

        <h3>raffall_countdown</h3>
        <p>Renders a flip-style countdown. You may pass either a product_id to use its stored draw time, or an explicit draw ISO string.</p>
        <ul>
            <li><code>product_id</code> (optional) — integer product ID to read the stored draw meta.</li>
            <li><code>draw</code> (optional) — explicit draw time string (ISO 8601, e.g. <code>2025-12-13T15:00:00Z</code>, or <code>YYYY-MM-DD HH:MM</code>).</li>
        </ul>
        <p>Examples:</p>
        <pre><code>[raffall_countdown product_id="123"] 
[raffall_countdown draw="2025-12-13T15:00:00Z"]</code></pre>
        <p>Output: HTML structure with <code>.raff-countdown</code> and child <code>.raff-flip[data-unit="days|hours|minutes|seconds"]</code> elements. Frontend JS will animate these when assets [...]</p>

        <h3>raffall_progress</h3>
        <p>Renders the tickets-sold progress bar for a competition product.</p>
        <ul>
            <li><code>product_id</code> (required) — integer product ID.</li>
        </ul>
        <p>Example:</p>
        <pre><code>[raffall_progress product_id="123"]</code></pre>
        <p>Output: container with <code>.raff-progress</code>, inner bar <code>.raff-progress-inner</code> and text. It uses product meta <code>_raff_ticket_cap</code>, <code>_raff_next_ticket</code> [...]</p>

        <h3>raffall_cart_sidebar</h3>
        <p>Shortcode to render the cart sidebar markup anywhere: <code>[raffall_cart_sidebar]</code>. This duplicates the footer-injected sidebar but allows manual placement.</p>

        <h3>Notes & tips</h3>
        <ul>
            <li>Shortcodes produce HTML that relies on plugin CSS and JS assets (raffall-frontend.css / raffall-frontend.js and raffall-question-styles.js). Make sure assets are enqueued and not [...]]</li>
            <li>For countdowns, valid draw strings include ISO 8601 (<code>YYYY-MM-DDTHH:MM:SSZ</code>), ISO without timezone (treated as UTC), <code>YYYY-MM-DD HH:MM</code>, or a product's stored meta.</li>
            <li>If a shortcode shows a blank area, open browser DevTools and check the Console and Network tabs for missing assets or JS errors.</li>
            <li>Server-side validation expects the form field <code>raff_choice</code> (buttons/dropdown) or <code>raff_answer</code> (legacy text). Keep backing up products before bulk edits.</li>
        </ul>

        <h2>Developer</h2>
        <p>The plugin exposes shortcodes and a block; if you need additional attributes (e.g. hide text, custom classes, or ajax refresh), tell me which options and I will extend the shortcodes with them.</p>
    </div>
    <?php
}
