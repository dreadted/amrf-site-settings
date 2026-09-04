<?php

namespace Antropomorf\SupportGenix;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Provider
 *
 * Generalized from amrf-theme's includes/support-genix.php (same boilerplate
 * pattern copied into every new theme via wp_install.sh's scaffolding — see
 * the plan's Context section). Wraps the third-party Support Genix Lite
 * plugin (its own admin pages/tables/hooks are prefixed apbd_wps_/apbd-wps/
 * support-genix, unrelated to this plugin's own naming, and untouched here):
 *
 * - A "Support Tickets" page — an iframe onto the front-end /ticket page —
 *   as its own top-level add_menu_page(), capability 'edit_posts', matching
 *   amrf-theme's original support_tickets_menu() exactly (slug, icon,
 *   position). Deliberately NOT nested under Admin\SiteSettingsMenu's "Site
 *   Settings" the way GDPR/Umami are: that menu's own top-level capability
 *   is 'edit_theme_options', and WordPress only skips checking a parent's
 *   own capability when it has an accessible submenu — an Editor with only
 *   this one page allowed would need that specific submenu individually
 *   allow-listed (not just the parent) for "Site Settings" to show at all,
 *   a confusing gotcha discovered when testing as an Editor. A separate
 *   top-level menu, gated at 'edit_posts' directly, sidesteps that.
 * - An "Apply Defaults" button injected onto Support Genix Lite's OWN
 *   settings page (page slug 'support-genix', not one of ours) — seeds its
 *   ticket categories/assignment rule and settings option once.
 * - Ticket page visibility/edit-access lockdown for non-administrators.
 * - Brand-color shadowing on the plugin's own portal header output, via
 *   apply_filters('amrf_site_colors', [...]) instead of the original
 *   theme's regex-against-style.css guess at --primary/--secondary custom
 *   properties — a theme now states its own colors explicitly, and the
 *   filter is named for the site's colors generally, not this one module,
 *   since other consumers may want the same site colors later.
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

    /**
     * This module's own top-level admin menu slug — matches amrf-theme's
     * original support_tickets_menu() exactly, not one of Support Genix
     * Lite's own slugs.
     */
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
     * and an offer bar/popup injected by its ApbdWps_OfferLite class) —
     * ported from amrf-theme's assets/admin.css. Not scoped to one screen:
     * that offer bar can appear on any wp-admin page, same reasoning as
     * why WordPress's own update-nag removal (Hooks\FrontendHooks) isn't
     * page-specific either.
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
     * Registers "Support Tickets" as its own top-level menu — icon and
     * position match amrf-theme's original support_tickets_menu() exactly
     * (includes/support-genix.php).
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
     * Same iframe wrapper the theme used — the ticket portal itself is a
     * normal front-end page (self::TICKET_PAGE_SLUG) that Support Genix Lite
     * serves; this just lets non-administrators reach it from wp-admin
     * without a separate front-end login/navigation step.
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
     * defaults, once. Ported as-is from the theme — table/column names and
     * values belong to that plugin's own schema, not this one's to
     * generalize further.
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
            // Support Genix Lite's own setup wizard writes these two keys
            // through its DisableSetupWizard()/dataSetupWizard() methods —
            // step 4 is that plugin's CURRENT wizard-completed value,
            // confirmed by running its wizard by hand (modules/
            // Apbd_wps_settings.php). The original theme code this was
            // ported from had this at 3 — stale from an older plugin
            // version, silently wrong after an update. If Support Genix
            // Lite adds more wizard steps in a future update, this will
            // need bumping again; there's no version-independent way to
            // ask the plugin for its own "wizard finished" step number.
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
     * Replicates Support Genix Lite's own language-key resolution
     * (core/ApbdWpsBaseModuleLite.php, ~line 933) so our writes land under
     * the SAME key the plugin's own GetOption()/AddOption() would use to
     * read them back. It's WPML/Polylang-gated, not locale-gated — without
     * either active it's always 'en' regardless of site language (verified:
     * this sandbox runs sv_SE with neither plugin installed, and the
     * plugin's own setup wizard still produced 'en' keys). Standard .po/.mo
     * translation of Genix's own UI strings is unrelated to this key and
     * unaffected by it either way — this only matters if WPML/Polylang gets
     * installed later.
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
     * Finds or creates the front-end ticket portal page and returns its ID,
     * for the 'ticket_page' setting Support Genix Lite's own portal_
     * templates()/portal_redirect() check to decide whether/how to render
     * the portal (see modules/Apbd_wps_settings.php) — NOT slug-based, so
     * home_url('/' . self::TICKET_PAGE_SLUG) only actually works once this
     * setting points at a real page.
     *
     * Page shape (title/slug/content/status) matches exactly what running
     * the plugin's own setup wizard by hand produced — a Gutenberg
     * shortcode block, not the shortcode as bare text, though both render
     * identically; matching it exactly is just closer editor parity with a
     * wizard-created page.
     *
     * @param string $lang Resolved language key (see resolveLanguageKey()) —
     *                      passed in rather than re-resolved, so this reads
     *                      back under the exact same key it's about to be
     *                      written under.
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
     * Keeps the ticket portal page out of WordPress's own built-in
     * /wp-sitemap.xml — it's a login/ticket-creation utility page, not
     * content, and there's no scenario where search-engine indexing of it
     * is wanted. Deliberately unconditional (no settings toggle): unlike
     * enable_seo_output, this isn't a judgment call a site owner would
     * ever want to flip the other way.
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
     * buffered portal-header output with this site's colors, declared via
     * apply_filters('amrf_site_colors', [...]) — this is a site-wide
     * concept (any module can read it), not something specific to Support
     * Genix, so it's named for the site's colors, not this one consumer.
     *
     * Search targets confirmed against the installed plugin's own source
     * (modules/Apbd_wps_settings.php: get_primary_brand_color()/
     * get_secondary_brand_color(), both plain hardcoded returns with no
     * apply_filters() of their own — this plugin genuinely doesn't expose
     * a hook for its brand colors, hence shadowing its rendered output
     * instead). '#0bbc5c'/'#ff6e30' are those two methods' real values —
     * matches the original theme code exactly, confirmed by reading the
     * plugin source rather than by guessing from a CLI test (an earlier,
     * WRONG "fix" here briefly swapped these for '#029bdd'/'#ff00b2' after
     * a CLI test happened to capture an unrelated CSS bundle — the
     * plugin's OWN wp-admin settings page styling, not the front-end
     * ticket portal this action actually renders).
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
