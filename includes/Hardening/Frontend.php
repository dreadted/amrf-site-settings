<?php

namespace Antropomorf\Hardening;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * The Frontend tab's hardening: author archives, blog posts, comments and
 * site search off, 404s redirected home, jQuery Migrate removed, and the
 * logged-in-only lock.
 *
 * @package Antropomorf\Hardening
 */
class Frontend
{
	/**
	 * @param array $settings Repository::getSettings().
	 */
	public function __construct(array $settings)
	{
		if ($settings['disable_author_archives']) {
			add_action('template_redirect', [$this, 'disableAuthorArchives']);
			// A users sitemap only ever points at author archives — pointless,
			// and actively leaks usernames, once those archives are disabled.
			add_filter('wp_sitemaps_add_provider', [$this, 'removeUsersSitemapProvider'], 10, 2);
		}

		// Priority 1: the 404 must be set before redirect404ToHome() runs at 10.
		if ($settings['disable_posts']) {
			add_action('template_redirect', [$this, 'disablePostRequests'], 1);
			add_filter('feed_links_show_posts_feed', '__return_false');
			add_filter('feed_links_extra_show_category_feed', '__return_false');
			add_filter('feed_links_extra_show_tag_feed', '__return_false');
			add_filter('feed_links_extra_show_author_feed', '__return_false');
			add_filter('feed_links_extra_show_search_feed', '__return_false');
			add_filter('wp_sitemaps_post_types', [$this, 'removePostsFromSitemaps']);
			add_filter('wp_sitemaps_taxonomies', [$this, 'removePostTaxonomiesFromSitemaps']);
		}

		if ($settings['disable_comments']) {
			add_action('template_redirect', [$this, 'disableCommentFeeds'], 1);
			add_filter('comments_open', '__return_false', 20);
			add_filter('pings_open', '__return_false', 20);
			add_filter('comments_array', '__return_empty_array', 20);
			add_filter('feed_links_show_comments_feed', '__return_false');
			add_filter('feed_links_extra_show_post_comments_feed', '__return_false');
		}

		if ($settings['redirect_404_to_home']) {
			add_action('template_redirect', [$this, 'redirect404ToHome']);
		}

		if ($settings['remove_jquery_migrate']) {
			add_filter('wp_default_scripts', [$this, 'removeJqueryMigrate']);
		}

		if ($settings['disable_site_search']) {
			add_action('parse_query', [$this, 'disableSiteSearch']);
		}

		if ($settings['restrict_site_to_logged_in']) {
			add_action('template_redirect', [$this, 'restrictSiteToLoggedIn'], 1);
		}
	}

	/**
	 * @return void
	 */
	public function disableAuthorArchives(): void
	{
		if (is_author()) {
			wp_redirect(home_url());
			exit;
		}
	}

	/**
	 * Dropping the provider makes /wp-sitemap-users-1.xml a real 404, not just hidden from the index.
	 *
	 * @param \WP_Sitemaps_Provider|null $provider
	 * @param string $name
	 * @return \WP_Sitemaps_Provider|null
	 */
	public function removeUsersSitemapProvider($provider, string $name)
	{
		return $name === 'users' ? null : $provider;
	}

	public function disablePostRequests(): void
	{
		$is_posts_page = is_home() && !is_front_page();
		// /feed/ matches neither is_home() nor any archive, so posts feeds are matched by exclusion.
		$is_posts_feed = is_feed() && !is_comment_feed() && !is_post_type_archive() && !is_tax();

		if (is_singular('post') || is_category() || is_tag() || is_date() || $is_posts_page || $is_posts_feed) {
			$this->force404();
		}
	}

	public function disableCommentFeeds(): void
	{
		if (is_comment_feed()) {
			$this->force404();
		}
	}

	private function force404(): void
	{
		global $wp_query;

		$wp_query->set_404();
		// set_404() keeps is_feed, which would still let template-loader.php call do_feed().
		$wp_query->is_feed = false;
		status_header(404);
		nocache_headers();
	}

	/**
	 * @param array<string, \WP_Post_Type> $post_types
	 * @return array<string, \WP_Post_Type>
	 */
	public function removePostsFromSitemaps(array $post_types): array
	{
		unset($post_types['post']);
		return $post_types;
	}

	/**
	 * @param array<string, \WP_Taxonomy> $taxonomies
	 * @return array<string, \WP_Taxonomy>
	 */
	public function removePostTaxonomiesFromSitemaps(array $taxonomies): array
	{
		unset($taxonomies['category'], $taxonomies['post_tag']);
		return $taxonomies;
	}

	/**
	 * @return void
	 */
	public function redirect404ToHome(): void
	{
		if (is_user_logged_in() || !is_404()) {
			return;
		}

		// WP's own sitemap routes report is_404() true before their own
		// template_redirect renderer runs — don't redirect those away.
		if (get_query_var('sitemap') || get_query_var('sitemap-stylesheet')) {
			return;
		}

		// Machine-read files (RFC 8615): a probe must get a real 404, not homepage HTML.
		$path = (string) wp_parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
		if (str_starts_with($path, '/.well-known/')) {
			return;
		}

		// Not 301: browsers cache it, so a republished page would keep redirecting.
		wp_safe_redirect(home_url(), 302);
		exit;
	}

	// Blank gray placeholder, same spirit as WP core's own .maintenance page — no
	// markup/text to leak that the site exists behind it, just a 503 for crawlers.
	public function restrictSiteToLoggedIn(): void
	{
		if (is_user_logged_in()) {
			return;
		}

		status_header(503);
		nocache_headers();
		header('Content-Type: text/html; charset=utf-8');
		header('X-Robots-Tag: noindex, nofollow');
		echo '<!doctype html><html><head><meta charset="utf-8"><meta name="robots" content="noindex,nofollow"><title></title></head><body style="margin:0;min-height:100vh;background:#e5e5e5;"></body></html>';
		exit;
	}

	// Turns every front-end search into a genuine 404 instead of real results.
	public function disableSiteSearch($query): void
	{
		if (!$query->is_search() || is_admin()) {
			return;
		}

		$query->is_search = false;
		$query->query_vars['s'] = false;
		$query->query['s'] = false;
		$query->set_404();
		status_header(404);
		nocache_headers();
	}

	/**
	 * @param \WP_Scripts $scripts
	 * @return void
	 */
	public function removeJqueryMigrate($scripts): void
	{
		if (is_admin() || !isset($scripts->registered['jquery'])) {
			return;
		}

		$script = $scripts->registered['jquery'];
		if ($script->deps) {
			$script->deps = array_diff($script->deps, ['jquery-migrate']);
		}
	}
}
