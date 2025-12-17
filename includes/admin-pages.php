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

                <!-- NEW: validation question presentation -->
                <tr>
                    <th scope="row">Validation question style</th>
                    <td>
                        <?php $style = get_option('raffall_question_style', 'radios'); ?>
                        <select name="raffall_question_style" id="raffall_question_style">
                            <option value="radios" <?php selected('radios', $style); ?>>Radios (default)</option>
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
