<?php

namespace Antropomorf\SupportGenix;

use Antropomorf\SiteSettings\BrandImages;

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
 * - Ticket page lockdown for non-administrators: hidden from the Pages list,
 *   no edit/delete, and left out of the sitemap. The page is the one in
 *   Support Genix Lite's own "Ticket Page" setting (ticketPageId()).
 * - Dequeues the docs/knowledge-base styles and script Support Genix Lite
 *   always loads on the front end, even though this site never shows that
 *   content to logged-out visitors.
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

	/** Set once "Apply Defaults" has run, which hides the button for good. */
	private const DEFAULTS_APPLIED_OPTION = 'amrf_support_genix_defaults_applied';

	/**
	 * Docs-related style handles Support Genix Lite always enqueues on the
	 * front end even though logged-out visitors never see any Support Genix
	 * content — Chrome DevTools CSS Coverage confirms 100% unused bytes for
	 * each of these on the public site.
	 */
	private const UNUSED_DOCS_STYLE_HANDLES = [
		'support-genix-docs-modern-category',
		'support-genix-docs-modern-search',
		'support-genix-docs-modern-components',
		'support-genix-docs-modern-grid',
	];

	/**
	 * The docs/knowledge-base script handle — registered as
	 * "{$assetsSlug}-docs-modern" (modules/Apbd_wps_knowledge_base.php:793),
	 * confusingly inside that module's ClientStyle() method alongside the
	 * style handles above, not a separate ClientScript() method. It's a
	 * SCRIPT handle, so it needs wp_dequeue_script(), not wp_dequeue_style()
	 * — see dequeueGuestDocsScript().
	 */
	private const UNUSED_DOCS_SCRIPT_HANDLE = 'support-genix-docs-modern';

	public function __construct()
	{
		add_action('admin_menu', [$this, 'addMenu']);

		add_action('in_admin_header', [$this, 'maybeShowDefaultsButton']);
		add_action('admin_init', [$this, 'handleApplyDefaults']);
		add_action('admin_notices', [$this, 'showDefaultsNotice']);

		// Support Genix Lite registers these styles (and, confusingly, the docs
		// script below) on its own 'wp_print_styles' callback at priority 998
		// (core/secondary_helper.php), not on 'wp_enqueue_scripts' — a dequeue
		// on wp_enqueue_scripts, however late, would run before they exist and
		// silently do nothing. Priority 999 on the same action, front-end only,
		// guarantees it runs right after.
		add_action('wp_print_styles', [$this, 'dequeueUnusedDocsStyles'], 999);
		add_action('wp_print_styles', [$this, 'dequeueGuestDocsScript'], 999);

		add_action('pre_get_posts', [$this, 'hideTicketPageFromNonAdmins']);
		add_filter('map_meta_cap', [$this, 'restrictTicketPageToAdmins'], 10, 4);

		add_action('apbd-wps/action/portal-header', [$this, 'startColorShadow'], 1);
		add_action('apbd-wps/action/portal-header', [$this, 'endColorShadow'], 100);
		add_action('apbd-wps/action/portal-header', [$this, 'printPortalStyles']);

		add_action('admin_enqueue_scripts', [$this, 'enqueueAdminStyles']);

		add_filter('the_seo_framework_sitemap_exclude_ids', [$this, 'excludeTicketPageFromSitemap']);
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
	 * The ticket portal (Apbd_wps_settings::portal_templates(), also what
	 * our own "Support Tickets" admin iframe points at) prints its own raw
	 * <html> document and never calls wp_head() — so amrf-support-genix.css,
	 * enqueued above via admin_enqueue_scripts, never reaches it on either
	 * surface. Link the same stylesheet directly into its one <head>
	 * extension point instead.
	 *
	 * @return void
	 */
	public function printPortalStyles(): void
	{
		printf(
			'<link rel="stylesheet" href="%s">',
			esc_url(AMRF_ADMIN_PLUGIN_URL . 'assets/css/amrf-support-genix.css?v=' . filemtime(AMRF_ADMIN_PLUGIN_DIR . '/assets/css/amrf-support-genix.css'))
		);
	}

	/**
	 * Dequeues Support Genix Lite's unused docs/knowledge-base styles on the
	 * front end (self::UNUSED_DOCS_STYLE_HANDLES) — this site never shows
	 * that content to logged-out visitors.
	 *
	 * @return void
	 */
	public function dequeueUnusedDocsStyles(): void
	{
		foreach (self::UNUSED_DOCS_STYLE_HANDLES as $handle) {
			wp_dequeue_style($handle);
		}
	}

	/**
	 * Dequeues the docs/knowledge-base script (self::UNUSED_DOCS_SCRIPT_HANDLE)
	 * for logged-out visitors only — unlike the styles above, logged-in users
	 * (e.g. staff browsing the docs while signed in) still get it.
	 *
	 * @return void
	 */
	public function dequeueGuestDocsScript(): void
	{
		if (is_user_logged_in()) {
			return;
		}

		wp_dequeue_script(self::UNUSED_DOCS_SCRIPT_HANDLE);
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
	 * The ticket portal is a normal front-end page (ticketPageId()) Support
	 * Genix Lite serves; this iframe lets non-admins reach it from wp-admin
	 * without a separate login/navigation step.
	 *
	 * @return void
	 */
	public function renderTicketsPage(): void
	{
		$page_id = self::ticketPageId();
		if (!$page_id) {
			printf(
				'<div class="wrap"><div class="notice notice-warning"><p>%s</p></div></div>',
				esc_html__('Support Genix has no ticket page yet.', 'amrf-admin')
			);
			return;
		}

		echo '<div class="wrap" style="margin: 0;">';
		printf(
			'<iframe src="%s" style="border:0; height: 100dvh; width: calc(100%% + 20px); margin: 0 0 -65px -20px;"></iframe>',
			esc_url(get_permalink($page_id))
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
		if (!current_user_can('manage_options') || get_option(self::DEFAULTS_APPLIED_OPTION)) {
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
		if (!isset($_POST['amrf_apply_support_genix_defaults']) || get_option(self::DEFAULTS_APPLIED_OPTION)) {
			return;
		}

		$nonce = isset($_POST['_wpnonce']) ? sanitize_key(wp_unslash($_POST['_wpnonce'])) : '';
		if (!current_user_can('manage_options') || !wp_verify_nonce($nonce, 'amrf_apply_support_genix_defaults')) {
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
			$deleted_roles = $wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$wpdb->prefix}apbd_wps_role WHERE slug != %s",
					'administrator'
				)
			);
			$deleted_access = $wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$wpdb->prefix}apbd_wps_role_access WHERE role_slug != %s",
					'administrator'
				)
			);
			if (false === $deleted_roles || false === $deleted_access) {
				throw new \Exception('Failed to delete support roles: ' . $wpdb->last_error);
			}

			$this->removeSupportRoles();
			$this->setupDefaultTicketRules();
			$this->setupDefaultTicketCategories();
			$this->updateSupportGenixSettings();

			update_option(self::DEFAULTS_APPLIED_OPTION, true);

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

		// DELETE, not TRUNCATE: TRUNCATE commits implicitly and would defeat the rollback.
		if (false === $wpdb->query("DELETE FROM $table_name")) {
			throw new \Exception('Failed to clear ticket assignment rules: ' . $wpdb->last_error);
		}

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

		if (false === $wpdb->query("DELETE FROM $table_name")) {
			throw new \Exception('Failed to clear ticket categories: ' . $wpdb->last_error);
		}

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
		$lang = self::resolveLanguageKey();

		$current_settings = get_option('support-genix_o_Apbd_wps_settings', []);

		$updated_settings = [
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

		$favicon = BrandImages::url('icon_svg');
		if ($favicon) {
			$updated_settings['app_favicon'] = $favicon;
		}
		$logo = BrandImages::url('logo');
		if ($logo) {
			$updated_settings['app_logo'] = [$lang => $logo];
		}

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
	private static function resolveLanguageKey(): string
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
	 * for the 'ticket_page' setting. Page shape matches what the plugin's
	 * own setup wizard produces.
	 *
	 * @param string $lang Resolved language key — passed in so this reads
	 *                      back under the exact key it's about to write under.
	 * @return int Page ID.
	 */
	private function ensureTicketPage(string $lang): int
	{
		$configured_id = self::configuredTicketPageId($lang);
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
	 * @param string $lang
	 * @return int The page ID in Support Genix Lite's "Ticket Page" setting, 0 if unset.
	 */
	private static function configuredTicketPageId(string $lang): int
	{
		$settings = get_option('support-genix_o_Apbd_wps_settings', []);

		return (int) ($settings['ticket_page'][$lang] ?? 0);
	}

	/**
	 * Read from the stored setting, not Support Genix Lite's API, so the page stays locked while that plugin is inactive.
	 *
	 * @return int The ticket portal page's ID, 0 if Support Genix Lite has none.
	 */
	public static function ticketPageId(): int
	{
		$page_id = self::configuredTicketPageId(self::resolveLanguageKey());

		return ($page_id && get_post_type($page_id) === 'page') ? $page_id : 0;
	}

	/**
	 * @param \WP_Query $query
	 * @return void
	 */
	public function hideTicketPageFromNonAdmins($query): void
	{
		global $pagenow;

		if (!is_admin() || !$query->is_main_query() || current_user_can('manage_options')) {
			return;
		}
		if ($pagenow !== 'edit.php' || 'page' !== $query->get('post_type')) {
			return;
		}

		$page_id = self::ticketPageId();
		if (!$page_id) {
			return;
		}

		$exclude = $query->get('post__not_in');
		$exclude = is_array($exclude) ? $exclude : [];
		$exclude[] = $page_id;
		$query->set('post__not_in', $exclude);
	}

	/**
	 * Like core does for the privacy policy page: also closes post.php, REST, Quick Edit and the admin bar's "Edit page" link.
	 *
	 * @param string[] $caps
	 * @param string   $cap
	 * @param int      $user_id
	 * @param array    $args
	 * @return string[]
	 */
	public function restrictTicketPageToAdmins(array $caps, string $cap, int $user_id, array $args): array
	{
		if (!in_array($cap, ['edit_post', 'edit_page', 'delete_post', 'delete_page'], true) || empty($args[0])) {
			return $caps;
		}

		$page_id = self::ticketPageId();
		if ($page_id && (int) $args[0] === $page_id) {
			$caps[] = 'manage_options';
		}

		return $caps;
	}

	/**
	 * Keeps the ticket portal page out of the sitemap (The SEO Framework's
	 * own exclude-ids filter) — it's a login/ticket-creation utility page,
	 * never wanted in search results. Unconditional, no settings toggle.
	 *
	 * @param int[] $excludedIds
	 * @return int[]
	 */
	public function excludeTicketPageFromSitemap(array $excludedIds): array
	{
		$page_id = self::ticketPageId();
		if ($page_id) {
			$excludedIds[] = $page_id;
		}

		return $excludedIds;
	}

	/**
	 * @return void
	 */
	public function startColorShadow(): void
	{
		ob_start();
	}

	/**
	 * Replaces Support Genix Lite's hardcoded '#0bbc5c'/'#ff6e30' (its only
	 * always-rendered brand colors, no filter of its own) with the viewer's
	 * own wp-admin color scheme, or the site admin's if logged out.
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

		$admin_colors = \Antropomorf\SiteSettings\Repository::getAdminColorSchemeColors();
		$replacement_defaults = [
			'primary' => $admin_colors['primary'] ?: $plugin_defaults['primary'],
			'secondary' => $admin_colors['secondary'] ?: $plugin_defaults['secondary'],
		];

		$colors = apply_filters('amrf_site_colors', $replacement_defaults);

		$output = str_replace($plugin_defaults['primary'], $colors['primary'], $output);
		$output = str_replace($plugin_defaults['secondary'], $colors['secondary'], $output);

		echo $output;
	}
}
