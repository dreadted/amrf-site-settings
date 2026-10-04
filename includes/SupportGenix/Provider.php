<?php

namespace Antropomorf\SupportGenix;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Support Genix Lite: Support Tickets page, Apply Defaults, ticket page lockdown, guest docs assets and brand colors.
 * Its own top-level menu at edit_posts: under Site Settings an Editor would need it allow-listed separately.
 *
 * @package Antropomorf\SupportGenix
 */
class Provider
{
	/** This module's own top-level admin menu slug, not one of Support Genix Lite's own. */
	private const MENU_SLUG = 'support-tickets';

	/**
	 * Docs styles Support Genix loads on every front-end page, unused for logged-out visitors.
	 */
	private const UNUSED_DOCS_STYLE_HANDLES = [
		'support-genix-docs-modern-category',
		'support-genix-docs-modern-search',
		'support-genix-docs-modern-components',
		'support-genix-docs-modern-grid',
	];

	/**
	 * Registered in the knowledge-base module's ClientStyle() but a script, so it needs wp_dequeue_script().
	 */
	private const UNUSED_DOCS_SCRIPT_HANDLE = 'support-genix-docs-modern';

	public function __construct()
	{
		add_action('admin_menu', [$this, 'addMenu']);

		new Defaults();

		// Support Genix registers these on wp_print_styles at 998, after wp_enqueue_scripts, so they can only be dequeued here.
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
	 * The ticket portal prints its own <html> without wp_head(), so the stylesheet is linked into its head hook.
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
	 * Mirrors Support Genix's language key so writes land where it reads; 'en' unless WPML or Polylang is active.
	 *
	 * @return string
	 */
	public static function resolveLanguageKey(): string
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
	 * @param string $lang
	 * @return int The page ID in Support Genix Lite's "Ticket Page" setting, 0 if unset.
	 */
	public static function configuredTicketPageId(string $lang): int
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
