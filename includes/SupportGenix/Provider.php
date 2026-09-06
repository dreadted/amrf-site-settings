<?php

namespace Antropomorf\SupportGenix;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Provider
 *
 * Wraps the third-party Support Genix Lite plugin (its own admin pages/
 * tables/hooks, prefixed apbd_wps_/apbd-wps/support-genix, untouched here):
 *
 * - A "Support Tickets" page — an iframe onto the front-end /ticket page —
 *   as its own top-level add_menu_page(), capability 'edit_posts'.
 *   Deliberately NOT nested under Admin\SiteSettingsMenu's "Site Settings":
 *   that menu's capability is 'edit_theme_options', and an Editor with only
 *   this one page allowed would need the submenu individually allow-listed
 *   for the parent menu to show at all. A separate top-level menu at
 *   'edit_posts' sidesteps that.
 * - An "Apply Defaults" button injected onto Support Genix Lite's OWN
 *   settings page — seeds its ticket categories/assignment rule/settings once.
 * - Ticket page visibility/edit-access lockdown for non-administrators.
 * - Brand-color shadowing on the plugin's portal header output via
 *   apply_filters('amrf_site_colors', [...]) — named generally since other
 *   consumers may want the same site colors.
 *
 * @package Antropomorf\SupportGenix
 */
class Provider
{
    /**
     * Support Genix Lite's own settings page slug — where the "Apply
     * Defaults" notice/button gets injected. Not a slug this plugin owns.
     */
    private const THIRD_PARTY_SETTINGS_PAGE = 'support-genix';

    /**
     * Slug of the front-end page Support Genix Lite serves ticket
     * submission/management on — created by that plugin itself.
     */
    private const TICKET_PAGE_SLUG = 'ticket';

    /** This module's own top-level admin menu slug, not one of Support Genix Lite's own. */
    private const MENU_SLUG = 'support-tickets';

    public function __construct()
    {
        add_action('admin_menu', [$this, 'addMenu']);

        add_action('in_admin_header', [$this, 'maybeShowDefaultsButton']);
        add_action('admin_init', [$this, 'handleApplyDefaults']);
        add_action('admin_notices', [$this, 'showDefaultsNotice']);

        add_action('pre_get_posts', [$this, 'hideTicketPageFromNonAdmins']);
        add_action('current_screen', [$this, 'preventTicketPageEditAccess']);

        add_action('apbd-wps/action/portal-header', [$this, 'startColorShadow'], 1);
        add_action('apbd-wps/action/portal-header', [$this, 'endColorShadow'], 100);

        add_action('admin_enqueue_scripts', [$this, 'enqueueAdminStyles']);
    }

    /**
     * Hides Support Genix Lite's own in-admin upsell nags (a promo banner
     * and offer bar/popup from its ApbdWps_OfferLite class). Not scoped to
     * one screen — that offer bar can appear on any wp-admin page.
     *
     * @return void
     */
    public function enqueueAdminStyles(): void
    {
        wp_enqueue_style(
            'amrf-support-genix',
            AMRF_ADMIN_PLUGIN_URL . 'assets/css/amrf-support-genix.css',
            [],
            filemtime(AMRF_ADMIN_PLUGIN_DIR . '/assets/css/amrf-support-genix.css')
        );
    }

    /**
     * Registers "Support Tickets" as its own top-level menu.
     *
     * @return void
     */
    public function addMenu(): void
    {
        add_menu_page(
            __('Support Tickets', 'amrf-admin'),
            __('Support Tickets', 'amrf-admin'),
            'edit_posts',
            self::MENU_SLUG,
            [$this, 'renderTicketsPage'],
            'dashicons-menu',
            35
        );
    }

    /**
     * The ticket portal is a normal front-end page (self::TICKET_PAGE_SLUG)
     * Support Genix Lite serves; this iframe lets non-admins reach it from
     * wp-admin without a separate login/navigation step.
     *
     * @return void
     */
    public function renderTicketsPage(): void
    {
        $url = home_url('/' . self::TICKET_PAGE_SLUG);

        echo '<div class="wrap" style="margin: 0;">';
        printf(
            '<iframe src="%s" style="border:0; height: 100dvh; width: calc(100%% + 20px); margin: 0 0 -65px -20px;"></iframe>',
            esc_url($url)
        );
        echo '</div>';
    }

    /**
     * Shows the "Apply Defaults" form on Support Genix Lite's OWN settings
     * page, once, until the defaults have been applied.
     *
     * @return void
     */
    public function maybeShowDefaultsButton(): void
    {
        global $pagenow;

        if ($pagenow !== 'admin.php' || ($_GET['page'] ?? '') !== self::THIRD_PARTY_SETTINGS_PAGE) {
            return;
        }
        if (get_option('support_genix_default_settings')) {
            return;
        }

        ?>
        <div class="notice notice-info">
            <p><?php esc_html_e("Apply this site's default settings for Support Genix.", 'amrf-admin'); ?></p>
            <p>
                <form method="post" style="display: inline-block; margin-right: 10px;">
                    <?php wp_nonce_field('amrf_apply_support_genix_defaults', '_wpnonce'); ?>
                    <input type="hidden" name="amrf_apply_support_genix_defaults" value="1">
                    <input type="submit" class="button button-primary" value="<?php esc_attr_e('Apply Defaults', 'amrf-admin'); ?>">
                </form>
            </p>
        </div>
        <?php
    }

    /**
     * @return void
     */
    public function handleApplyDefaults(): void
    {
        if (!isset($_POST['amrf_apply_support_genix_defaults'])) {
            return;
        }

        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'amrf_apply_support_genix_defaults')) {
            wp_die(esc_html__('Security check failed', 'amrf-admin'));
        }

        $result = $this->applyDefaultSettings();
        set_transient('amrf_support_genix_defaults_processed', $result ? 'success' : 'error', 60);

        wp_redirect(admin_url('admin.php?page=' . self::THIRD_PARTY_SETTINGS_PAGE));
        exit;
    }

    /**
     * @return void
     */
    public function showDefaultsNotice(): void
    {
        $message = get_transient('amrf_support_genix_defaults_processed');
        if (!$message) {
            return;
        }
        delete_transient('amrf_support_genix_defaults_processed');

        if ($message === 'success') {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Support Genix defaults applied successfully!', 'amrf-admin') . '</p></div>';
        } else {
            echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__('There was an error applying the Support Genix defaults.', 'amrf-admin') . '</p></div>';
        }
    }

    /**
     * Seeds Support Genix Lite's own tables/option with this site's
     * defaults, once. Table/column names and values belong to that
     * plugin's own schema.
     *
     * @return bool
     */
    private function applyDefaultSettings(): bool
    {
        global $wpdb;

        $wpdb->query('START TRANSACTION');

        try {
            $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM {$wpdb->prefix}apbd_wps_role WHERE slug != %s",
                    'administrator'
                )
            );

            $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM {$wpdb->prefix}apbd_wps_role_access WHERE role_slug != %s",
                    'administrator'
                )
            );

            $this->removeSupportRoles();
            $this->setupDefaultTicketRules();
            $this->setupDefaultTicketCategories();
            $this->updateSupportGenixSettings();

            update_option('support_genix_default_settings', true);

            $wpdb->query('COMMIT');
            return true;
        } catch (\Exception $e) {
            $wpdb->query('ROLLBACK');
            error_log('Support Genix Default Settings: ' . $e->getMessage());
            return false;
        }
    }

    private function removeSupportRoles(): void
    {
        foreach (['awps-support-manager', 'awps-support-agent'] as $role) {
            if (get_role($role)) {
                remove_role($role);
            }
        }
    }

    private function setupDefaultTicketRules(): void
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'apbd_wps_ticket_assign_rule';

        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            throw new \Exception('Ticket assignment rules table does not exist');
        }

        $wpdb->query("TRUNCATE TABLE $table_name");

        $result = $wpdb->insert(
            $table_name,
            [
                'id' => 1,
                'cat_ids' => '0',
                'rule_type' => 'A',
                'rule_id' => '1',
                'status' => 'A',
            ],
            ['%d', '%s', '%s', '%s', '%s']
        );

        if (false === $result) {
            throw new \Exception('Failed to insert ticket assignment rule: ' . $wpdb->last_error);
        }
    }

    private function setupDefaultTicketCategories(): void
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'apbd_wps_ticket_category';

        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            throw new \Exception('Ticket categories table does not exist');
        }

        $wpdb->query("TRUNCATE TABLE $table_name");

        $categories = [
            ['id' => 1, 'title' => 'task'],
            ['id' => 2, 'title' => 'bug'],
            ['id' => 3, 'title' => 'request'],
            ['id' => 4, 'title' => 'other'],
        ];

        foreach ($categories as $category) {
            $result = $wpdb->insert($table_name, $category, ['%d', '%s']);
            if (false === $result) {
                throw new \Exception('Failed to insert ticket category: ' . $wpdb->last_error);
            }
        }
    }

    private function updateSupportGenixSettings(): void
    {
        $upload_dir = wp_upload_dir();
        $base_upload_url = $upload_dir['baseurl'];
        $lang = $this->resolveLanguageKey();

        $current_settings = get_option('support-genix_o_Apbd_wps_settings', []);

        $updated_settings = [
            'app_favicon' => $base_upload_url . '/favicon.svg',
            'app_logo' => [$lang => $base_upload_url . '/logo.svg'],
            'client_role' => 'editor',
            'disable_guest_ticket_creation' => 'Y',
            'disable_guest_email_to_ticket_creation' => 'Y',
            'footer_cp_text' => [$lang => ''],
            'ticket_page' => [$lang => $this->ensureTicketPage($lang)],
            // Step 4 is Support Genix Lite's current wizard-completed value
            // (confirmed by running its wizard by hand) — may need bumping
            // if that plugin adds wizard steps in a future update.
            'setup_wizard_step' => 4,
            'setup_wizard_finished' => true,
        ];

        $merged_settings = array_merge($current_settings, $updated_settings);
        update_option('support-genix_o_Apbd_wps_settings', $merged_settings, true);

        // update_option() returns false both on a real failure AND when the
        // new value is identical to what's already stored (a documented WP
        // quirk) — re-running this once ticket_page/wizard state already
        // match would otherwise always look like a failure. Verify what's
        // actually persisted instead of trusting the return value.
        $stored = get_option('support-genix_o_Apbd_wps_settings', []);
        foreach ($updated_settings as $key => $value) {
            if (($stored[$key] ?? null) !== $value) {
                throw new \Exception('Failed to update support-genix settings');
            }
        }
    }

    /**
     * Replicates Support Genix Lite's own language-key resolution so writes
     * land under the same key its GetOption()/AddOption() reads back.
     * WPML/Polylang-gated, not locale-gated — always 'en' without either
     * active, regardless of site language.
     *
     * @return string
     */
    private function resolveLanguageKey(): string
    {
        $lang = 'en';

        if (defined('ICL_SITEPRESS_VERSION')) {
            $lang = apply_filters('wpml_current_language', $lang);
        } elseif (function_exists('pll_current_language')) {
            $lang = call_user_func('pll_current_language');
        }

        $lang = apply_filters('support_genix_current_language_key', $lang);
        $lang = sanitize_text_field($lang);

        return ($lang && $lang !== 'all') ? $lang : 'en';
    }

    /**
     * Finds or creates the front-end ticket portal page and returns its ID
     * for the 'ticket_page' setting — NOT slug-based, so
     * home_url('/' . self::TICKET_PAGE_SLUG) only works once this setting
     * points at a real page. Page shape matches what the plugin's own setup
     * wizard produces.
     *
     * @param string $lang Resolved language key — passed in so this reads
     *                      back under the exact key it's about to write under.
     * @return int Page ID.
     */
    private function ensureTicketPage(string $lang): int
    {
        $current_settings = get_option('support-genix_o_Apbd_wps_settings', []);
        $configured_id = (int) ($current_settings['ticket_page'][$lang] ?? 0);
        if ($configured_id && get_post($configured_id)) {
            return $configured_id;
        }

        $existing = get_page_by_path(self::TICKET_PAGE_SLUG);
        if ($existing) {
            return $existing->ID;
        }

        $page_id = wp_insert_post(
            [
                'post_type' => 'page',
                'post_title' => __('Ticket', 'amrf-admin'),
                'post_name' => self::TICKET_PAGE_SLUG,
                'post_content' => '<!-- wp:shortcode -->[supportgenix]<!-- /wp:shortcode -->',
                'post_status' => 'publish',
                'comment_status' => 'closed',
                'ping_status' => 'closed',
            ],
            true
        );

        if (is_wp_error($page_id)) {
            throw new \Exception('Failed to create ticket page: ' . $page_id->get_error_message());
        }

        return $page_id;
    }

    /**
     * @param \WP_Query $query
     * @return void
     */
    public function hideTicketPageFromNonAdmins($query): void
    {
        if (!is_admin() || !$query->is_main_query() || current_user_can('administrator')) {
            return;
        }
        if ('page' !== $query->get('post_type')) {
            return;
        }

        $page = get_page_by_path(self::TICKET_PAGE_SLUG);
        if (!$page) {
            return;
        }

        $exclude = $query->get('post__not_in');
        $exclude = is_array($exclude) ? $exclude : [];
        $exclude[] = $page->ID;
        $query->set('post__not_in', $exclude);
    }

    /**
     * Keeps the ticket portal page out of WP's built-in /wp-sitemap.xml —
     * it's a login/ticket-creation utility page, never wanted in search
     * results. Unconditional, no settings toggle.
     *
     * @param array $args WP_Query args for this sitemap's post type.
     * @param string $post_type
     * @return array
     */
    public function excludeTicketPageFromSitemap(array $args, string $post_type): array
    {
        if ('page' !== $post_type) {
            return $args;
        }

        $page = get_page_by_path(self::TICKET_PAGE_SLUG);
        if (!$page) {
            return $args;
        }

        $exclude = isset($args['post__not_in']) && is_array($args['post__not_in']) ? $args['post__not_in'] : [];
        $exclude[] = $page->ID;
        $args['post__not_in'] = $exclude;

        return $args;
    }

    /**
     * @return void
     */
    public function preventTicketPageEditAccess(): void
    {
        if (!is_admin() || current_user_can('administrator')) {
            return;
        }

        $screen = get_current_screen();
        if (!$screen || $screen->base !== 'post' || $screen->post_type !== 'page') {
            return;
        }

        $post_id = isset($_GET['post']) ? intval($_GET['post']) : 0;
        if (!$post_id) {
            return;
        }

        $post = get_post($post_id);
        if ($post && $post->post_name === self::TICKET_PAGE_SLUG) {
            wp_die(esc_html__('You are not authorized to edit this page.', 'amrf-admin'));
        }
    }

    /**
     * @return void
     */
    public function startColorShadow(): void
    {
        ob_start();
    }

    /**
     * Replaces Support Genix Lite's own default brand colors in its
     * buffered portal-header output with this site's colors
     * (amrf_site_colors filter) — the plugin hardcodes its brand colors
     * with no filter of its own, so this shadows the rendered output
     * instead. '#0bbc5c'/'#ff6e30' are its real values (get_primary_brand_color()/
     * get_secondary_brand_color() in modules/Apbd_wps_settings.php).
     *
     * @return void
     */
    public function endColorShadow(): void
    {
        $output = ob_get_clean();

        $plugin_defaults = [
            'primary' => '#0bbc5c',
            'secondary' => '#ff6e30',
        ];

        $colors = apply_filters('amrf_site_colors', $plugin_defaults);

        $output = str_replace($plugin_defaults['primary'], $colors['primary'], $output);
        $output = str_replace($plugin_defaults['secondary'], $colors['secondary'], $output);

        echo $output;
    }
}
