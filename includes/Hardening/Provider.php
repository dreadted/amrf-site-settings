<?php

namespace Antropomorf\Hardening;

use Antropomorf\Utilities\SettingsRenderer;
use enshrined\svgSanitize\Sanitizer;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class Provider
 *
 * Security/performance hardening, split into two groups:
 *
 * - Unconditional: XML-RPC blocking, generic login error message, hiding
 *   the WP version generator tag and RSD link, blocking ?username= at
 *   login, removing the /wp/v2/users REST endpoint, and disabling core's
 *   emoji fallback. Near-universal, no downside.
 * - Toggleable, on the "Hardening" page (manage_options): allowing admins
 *   to upload sanitized SVGs, disabling author archives (and, tied to that
 *   same toggle, WP's own users sitemap — pointless and actively leaks
 *   usernames once author archives are gone), redirecting 404s to the
 *   homepage, removing jQuery Migrate, and disabling generated image
 *   sizes — these change behavior some sites rely on, so each defaults to
 *   true but stays a per-site opt-out.
 *
 * @package Antropomorf\Hardening
 */
class Provider
{
	private const PAGE_SLUG = 'amrf-site-settings-hardening';
	private const TABS_FILTER = 'amrf_hardening_tabs';
	private const TAB_PAGE_SLUG_IMAGES = 'amrf-site-settings-hardening-images';
	private const TAB_PAGE_SLUG_FRONTEND = 'amrf-site-settings-hardening-frontend';
	private const UPLOAD_HASH_META_KEY = '_amrf_upload_hash';
	// WordPress's own built-in sizes — a theme's own add_image_size() registrations are left alone.
	private const CORE_DEFAULT_IMAGE_SIZES = ['thumbnail', 'medium', 'medium_large', 'large', '1536x1536', '2048x2048'];

	private SettingsRenderer $renderer;

	// Set by convertImageUploadToWebp(), consumed by recordUploadHash() on the same request.
	private ?string $pendingUploadHash = null;

	public function __construct()
	{
		$this->renderer = new SettingsRenderer(self::TABS_FILTER, self::PAGE_SLUG, fn() => __('Hardening', 'amrf-admin'));

		$this->registerUnconditionalHardening();
		$this->registerToggleableHardening();

		add_filter('amrf_site_settings_pages', [$this, 'registerPages']);
		add_filter(self::TABS_FILTER, [$this, 'registerTabs']);
		add_action('admin_enqueue_scripts', [$this, 'enqueueSwitchStyles']);
	}

	/**
	 * @return void
	 */
	private function registerUnconditionalHardening(): void
	{
		add_filter('xmlrpc_enabled', '__return_false');

		add_filter('login_errors', function () {
			return __('There is an error.', 'amrf-admin');
		});

		remove_action('wp_head', 'wp_generator');

		// EditURI advertises xmlrpc.php's existence even though xmlrpc_enabled
		// blocks it above — no reason to point at it at all.
		remove_action('wp_head', 'rsd_link');

		add_action('login_init', [$this, 'blockUsernameInLoginUrl']);
		add_filter('rest_endpoints', [$this, 'disableUsersRestEndpoint']);

		$this->disableEmojiFallback();
	}

	/**
	 * Core's emoji fallback swaps emoji for images from s.w.org, a third-party request.
	 *
	 * @return void
	 */
	private function disableEmojiFallback(): void
	{
		remove_action('wp_head', 'print_emoji_detection_script', 7);
		remove_action('embed_head', 'print_emoji_detection_script');
		// Unhooking the legacy printer also makes wp_enqueue_emoji_styles() bail.
		remove_action('wp_print_styles', 'print_emoji_styles');
		remove_filter('the_content_feed', 'wp_staticize_emoji');
		remove_filter('comment_text_rss', 'wp_staticize_emoji');
		remove_filter('wp_mail', 'wp_staticize_emoji_for_email');

		// Core adds the admin hooks after plugins load.
		add_action('admin_init', function () {
			remove_action('admin_print_scripts', 'print_emoji_detection_script');
			remove_action('admin_print_styles', 'print_emoji_styles');
		});
	}

	/**
	 * @return void
	 */
	private function registerToggleableHardening(): void
	{
		$settings = Repository::getSettings();

		// Unconditional: forces every image editor call (uploads, wp_generate_attachment_metadata(),
		// wp media regenerate, …) to output WebP at the configured quality, regardless of the
		// toggles below — a no-op when nothing is actually generated.
		add_filter('image_editor_output_format', [$this, 'forceWebpOutputFormat'], 10, 3);
		add_filter('wp_editor_set_quality', [$this, 'applyWebpQuality'], 10, 2);

		if ($settings['restrict_media_deletion']) {
			add_filter('map_meta_cap', [$this, 'restrictMediaDeletion'], 10, 4);
		}

		if ($settings['allow_svg_uploads']) {
			add_filter('upload_mimes', [$this, 'allowSvgMimeType']);
			add_filter('wp_check_filetype_and_ext', [$this, 'checkSvgFiletype'], 10, 4);
			add_filter('wp_handle_upload_prefilter', [$this, 'sanitizeUploadedSvg']);
			add_filter('wp_handle_sideload_prefilter', [$this, 'sanitizeUploadedSvg']);
		}

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

		if ($settings['disable_generated_image_sizes']) {
			add_filter('wp_img_tag_add_decoding_attr', '__return_false');
			add_filter('intermediate_image_sizes_advanced', [$this, 'removeCoreDefaultImageSizes']);
		}

		if ($settings['disable_site_search']) {
			add_action('parse_query', [$this, 'disableSiteSearch']);
		}

		if ($settings['restrict_site_to_logged_in']) {
			add_action('template_redirect', [$this, 'restrictSiteToLoggedIn'], 1);
		}

		if ($settings['convert_uploads_to_webp']) {
			add_filter('wp_handle_upload', [$this, 'convertImageUploadToWebp'], 10, 2);
			add_action('add_attachment', [$this, 'recordUploadHash']);
		}
	}

	// WordPress ties attachment deletion to the generic 'delete_posts' cap, not post ownership.
	public function restrictMediaDeletion(array $caps, string $cap, int $user_id, array $args): array
	{
		if ($cap !== 'delete_post' || user_can($user_id, 'manage_options')) {
			return $caps;
		}

		$post = get_post($args[0] ?? 0);

		if (!$post || $post->post_type !== 'attachment' || (int) $post->post_author === $user_id) {
			return $caps;
		}

		return ['do_not_allow'];
	}

	/**
	 * SVGs can carry <script>, event-handler attributes, and external
	 * references — treated as active content, not a plain image format, so
	 * this whole feature is gated to administrators (manage_options) and
	 * every uploaded file is sanitized before WordPress stores it.
	 * Deliberately not `unfiltered_html`: on a non-multisite install
	 * WordPress grants that capability to Editors too by default, so it
	 * doesn't actually distinguish admin from editor.
	 *
	 * @param array $mimes
	 * @return array
	 */
	public function allowSvgMimeType(array $mimes): array
	{
		if (current_user_can('manage_options')) {
			$mimes['svg'] = 'image/svg+xml';
		}
		return $mimes;
	}

	/**
	 * @param array|false $data
	 * @param string $file
	 * @param string $filename
	 * @param array $mimes
	 * @return array|false
	 */
	public function checkSvgFiletype($data, $file, $filename, $mimes)
	{
		if (!empty($data['ext']) && !empty($data['type'])) {
			return $data;
		}

		$filetype = wp_check_filetype($filename, $mimes);
		if ($filetype['ext'] === 'svg') {
			$data['ext'] = 'svg';
			$data['type'] = 'image/svg+xml';
		}

		return $data;
	}

	/**
	 * @param array $file
	 * @return array
	 */
	public function sanitizeUploadedSvg(array $file): array
	{
		$is_svg = ($file['type'] ?? '') === 'image/svg+xml' || preg_match('/\.svg$/i', $file['name'] ?? '');

		if (!$is_svg) {
			return $file;
		}

		if (!current_user_can('manage_options')) {
			$file['error'] = __('You are not allowed to upload SVG files.', 'amrf-admin');
			return $file;
		}

		$content = file_get_contents($file['tmp_name']);

		if ($content === false || stripos($content, '<svg') === false) {
			$file['error'] = __('This file does not look like a valid SVG.', 'amrf-admin');
			return $file;
		}

		$sanitized = $this->sanitizeSvgMarkup($content);

		if ($sanitized === null) {
			$file['error'] = __('This SVG could not be sanitized and was rejected.', 'amrf-admin');
			return $file;
		}

		file_put_contents($file['tmp_name'], $sanitized);

		return $file;
	}

	/**
	 * Allowlist-sanitizes SVG markup and drops remote references; null if rejected.
	 *
	 * @param string $content
	 * @return string|null
	 */
	private function sanitizeSvgMarkup(string $content): ?string
	{
		$sanitizer = new Sanitizer();
		$sanitizer->removeRemoteReferences(true);
		$clean = $sanitizer->sanitize($content);

		return $clean === false ? null : $clean;
	}

	// Scoped to 'upload' context — sideloads are typically admin-triggered, not a direct user action.
	public function convertImageUploadToWebp(array $upload, string $context = 'upload'): array
	{
		if (
			$context !== 'upload'
			|| empty($upload['type'])
			|| strpos($upload['type'], 'image/') !== 0
		) {
			return $upload;
		}

		// Hashed before any processing (and before the webp-skip below), so re-uploading
		// the same source file is caught regardless of its format.
		$hash = hash_file('sha256', $upload['file']);
		$duplicate_id = $hash !== false ? $this->findAttachmentByHash($hash) : null;

		if ($duplicate_id !== null) {
			if (file_exists($upload['file'])) {
				unlink($upload['file']);
			}
			$upload['error'] = sprintf(
				// translators: %d is the existing attachment's post ID.
				__('This image is identical to an existing upload (attachment #%d) and was not saved again.', 'amrf-admin'),
				$duplicate_id
			);
			return $upload;
		}

		$this->pendingUploadHash = $hash !== false ? $hash : null;

		// Already webp — nothing to convert, but the dedup hash above still applies.
		if ($upload['type'] === 'image/webp') {
			return $upload;
		}

		$processed = $this->convertToWebp($upload['file']);

		if ($processed === null) {
			return $upload;
		}

		$upload['url'] = str_replace(basename($upload['file']), basename($processed), $upload['url']);
		$upload['file'] = $processed;
		$upload['type'] = 'image/webp';

		return $upload;
	}

	/**
	 * @return int|null Attachment ID with a matching stored hash, or null if none.
	 */
	private function findAttachmentByHash(string $hash): ?int
	{
		$matches = get_posts([
			'post_type' => 'attachment',
			'post_status' => 'inherit',
			'meta_key' => self::UPLOAD_HASH_META_KEY,
			'meta_value' => $hash,
			'fields' => 'ids',
			'posts_per_page' => 1,
			'no_found_rows' => true,
		]);

		return $matches ? (int) $matches[0] : null;
	}

	// Fires right after wp_insert_attachment() — the only point where we have both the hash and the new attachment ID.
	public function recordUploadHash(int $attachment_id): void
	{
		if ($this->pendingUploadHash === null) {
			return;
		}

		update_post_meta($attachment_id, self::UPLOAD_HASH_META_KEY, $this->pendingUploadHash);
		$this->pendingUploadHash = null;
	}

	// Full resolution preserved — only the format changes here. The smaller variants
	// actually served on the front end come from the theme's add_image_size()
	// registrations, generated via wp_generate_attachment_metadata() (see
	// forceWebpOutputFormat()/applyWebpQuality() below).
	private function convertToWebp(string $file_path): ?string
	{
		$editor = wp_get_image_editor($file_path);
		if (is_wp_error($editor)) {
			error_log('amrf-site-settings: could not load image editor for ' . $file_path . ': ' . $editor->get_error_message());
			return null;
		}

		$editor->set_quality(Repository::getSettings()['webp_quality']);

		$info = pathinfo($file_path);
		// wp_unique_filename avoids collisions with an existing file that
		// already has this same base name but a different original extension.
		$webp_filename = wp_unique_filename($info['dirname'], $info['filename'] . '.webp');
		$webp_path = $info['dirname'] . '/' . $webp_filename;

		$saved = $editor->save($webp_path, 'image/webp');
		if (is_wp_error($saved)) {
			error_log('amrf-site-settings: could not save webp for ' . $file_path . ': ' . $saved->get_error_message());
			return null;
		}

		if ($file_path !== $webp_path && file_exists($file_path)) {
			unlink($file_path);
		}

		return $webp_path;
	}

	/**
	 * @param array<string, array<string, mixed>> $sizes
	 * @return array<string, array<string, mixed>>
	 */
	public function removeCoreDefaultImageSizes(array $sizes): array
	{
		foreach (self::CORE_DEFAULT_IMAGE_SIZES as $name) {
			unset($sizes[$name]);
		}

		return $sizes;
	}

	/**
	 * Forces every raster size WP_Image_Editor generates — uploads, regenerated
	 * attachment metadata, `wp media regenerate` — to be saved as WebP.
	 *
	 * @param array<string, string> $output_format
	 * @return array<string, string>
	 */
	// $filename is null on some WP_Image_Editor code paths (e.g. make_subsize()) — unused here regardless.
	public function forceWebpOutputFormat(array $output_format, ?string $filename, string $mime_type): array
	{
		if ($mime_type !== 'image/webp') {
			$output_format[$mime_type] = 'image/webp';
		}

		return $output_format;
	}

	public function applyWebpQuality(int $quality, string $mime_type): int
	{
		return $mime_type === 'image/webp' ? Repository::getSettings()['webp_quality'] : $quality;
	}

	/**
	 * @return void
	 */
	public function blockUsernameInLoginUrl(): void
	{
		if (isset($_GET['username'])) {
			wp_redirect(wp_login_url());
			exit;
		}
	}

	/**
	 * @param array $endpoints
	 * @return array
	 */
	public function disableUsersRestEndpoint(array $endpoints): array
	{
		unset($endpoints['/wp/v2/users']);
		return $endpoints;
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
	 * Returning anything that isn't a WP_Sitemaps_Provider instance drops
	 * the provider entirely (see WP_Sitemaps_Registry::add_provider()) —
	 * /wp-sitemap-users-1.xml becomes a genuine 404 rather than just being
	 * hidden from the index, so there's nothing left to find by guessing
	 * the URL either.
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

	/**
	 * @param array $pages Pages registered so far by other callbacks on this filter.
	 * @return array Pages with 'hardening' appended.
	 */
	public function registerPages(array $pages): array
	{
		$pages['hardening'] = [
			'page_title' => __('Hardening', 'amrf-admin'),
			'menu_title' => __('Hardening', 'amrf-admin'),
			'capability' => 'manage_options',
			'menu_slug' => self::PAGE_SLUG,
			'register' => fn() => SettingsRenderer::registerSettings(self::TABS_FILTER),
			'render' => [$this->renderer, 'render'],
		];

		return $pages;
	}

	public function registerTabs(array $tabs): array
	{
		$tabs['images'] = [
			'label' => __('Images', 'amrf-admin'),
			'option_group' => Repository::OPTION_GROUP_IMAGES,
			'page_slug' => self::TAB_PAGE_SLUG_IMAGES,
			'show_reset' => false,
			'register' => [$this, 'registerImagesTab'],
		];

		$tabs['frontend'] = [
			'label' => __('Frontend', 'amrf-admin'),
			'option_group' => Repository::OPTION_GROUP_FRONTEND,
			'page_slug' => self::TAB_PAGE_SLUG_FRONTEND,
			'show_reset' => false,
			'register' => [$this, 'registerFrontendTab'],
		];

		return $tabs;
	}

	/**
	 * @return void
	 */
	public function registerImagesTab(): void
	{
		register_setting(
			Repository::OPTION_GROUP_IMAGES,
			Repository::OPTION_NAME,
			[Repository::class, 'sanitize']
		);

		add_settings_section('hardening_images_section', '', '__return_false', self::TAB_PAGE_SLUG_IMAGES);

		// Registered first so it renders at the top of the tab, ahead of the
		// $fields loop below.
		add_settings_field(
			'convert_uploads_to_webp',
			__('Convert uploads to WebP', 'amrf-admin'),
			function () {
				$this->renderCheckbox(
					'convert_uploads_to_webp',
					__('Automatically converts every non-WebP image upload to WebP at the quality below, and blocks exact duplicates of an already-uploaded image.', 'amrf-admin')
				);
			},
			self::TAB_PAGE_SLUG_IMAGES,
			'hardening_images_section'
		);

		add_settings_field(
			'webp_quality',
			__('WebP quality', 'amrf-admin'),
			function () {
				$this->renderWebpQualityField();
			},
			self::TAB_PAGE_SLUG_IMAGES,
			'hardening_images_section'
		);

		$fields = [
			'restrict_media_deletion' => [
				__('Restrict media deletion', 'amrf-admin'),
				__('Non-administrators can only delete media library items they uploaded themselves. Without this, WordPress lets anyone who can upload files delete any attachment, regardless of who uploaded it.', 'amrf-admin'),
			],
			'allow_svg_uploads' => [
				__('Allow SVG uploads', 'amrf-admin'),
				__('Lets administrators upload SVG files through the Media Library — every file is sanitized (scripts, event handlers, and embedded HTML stripped) before it\'s stored.', 'amrf-admin'),
			],
			'disable_generated_image_sizes' => [
				__('Disable generated image sizes', 'amrf-admin'),
				__('Stops WordPress from generating its own default image sizes (thumbnail, medium, medium_large, large, 1536x1536, 2048x2048). Sizes a theme registers itself are unaffected.', 'amrf-admin'),
			],
		];

		$this->registerCheckboxFields($fields, self::TAB_PAGE_SLUG_IMAGES, 'hardening_images_section');
	}

	/**
	 * @return void
	 */
	public function registerFrontendTab(): void
	{
		register_setting(
			Repository::OPTION_GROUP_FRONTEND,
			Repository::OPTION_NAME,
			[Repository::class, 'sanitize']
		);

		add_settings_section('hardening_frontend_section', '', '__return_false', self::TAB_PAGE_SLUG_FRONTEND);

		// Registered first, ahead of the $fields loop below, and styled with the
		// alert-colored switch — this one takes the whole site offline for
		// logged-out visitors, a much bigger consequence than the rest of this tab.
		add_settings_field(
			'restrict_site_to_logged_in',
			__('Restrict site to logged-in users', 'amrf-admin'),
			function () {
				$this->renderCheckbox(
					'restrict_site_to_logged_in',
					__('Blocks every front-end page for logged-out visitors with a blank placeholder page — the WordPress admin and login screen stay reachable. Use this to preview a site privately before launch.', 'amrf-admin'),
					true
				);
			},
			self::TAB_PAGE_SLUG_FRONTEND,
			'hardening_frontend_section'
		);

		$fields = [
			'disable_author_archives' => [
				__('Disable author archives', 'amrf-admin'),
				__('Redirects author archive pages to the homepage — mainly useful on single-author sites, or to avoid leaking usernames via author URLs.', 'amrf-admin'),
			],
			'disable_posts' => [
				__('Disable blog posts', 'amrf-admin'),
				__('For sites built from pages only. Posts, category, tag and date archives, and the RSS feeds return a 404 (then redirect to the homepage if "Redirect 404 pages to the homepage" is on), and posts are left out of the WordPress sitemap. Existing posts and the admin are unaffected.', 'amrf-admin'),
			],
			'disable_comments' => [
				__('Disable comments', 'amrf-admin'),
				__('Closes comments, pingbacks and trackbacks on all content regardless of each page\'s discussion settings, hides already approved comments, and turns the comment feeds into a 404.', 'amrf-admin'),
			],
			'redirect_404_to_home' => [
				__('Redirect 404 pages to the homepage', 'amrf-admin'),
				__('Applies to logged-out visitors only. Turn off if this site should show a real 404 page instead.', 'amrf-admin'),
			],
			'disable_site_search' => [
				__('Disable site search', 'amrf-admin'),
				__('Turns the built-in WordPress search into a 404 for every visitor — useful while a site is still under construction and shouldn\'t expose a working search box yet.', 'amrf-admin'),
			],
			'remove_jquery_migrate' => [
				__('Remove jQuery Migrate', 'amrf-admin'),
				__('Turn off if an older plugin or theme on this site depends on jQuery Migrate\'s compatibility shims.', 'amrf-admin'),
			],
		];

		$this->registerCheckboxFields($fields, self::TAB_PAGE_SLUG_FRONTEND, 'hardening_frontend_section');
	}

	private function registerCheckboxFields(array $fields, string $page_slug, string $section): void
	{
		foreach ($fields as $key => [$label, $description]) {
			add_settings_field(
				$key,
				$label,
				function () use ($key, $description) {
					$this->renderCheckbox($key, $description);
				},
				$page_slug,
				$section
			);
		}
	}

	/**
	 * Same .switch/.slider markup as Settings\Manager::renderCheckbox().
	 *
	 * @param string $key
	 * @param string $description
	 * @param bool   $alert Renders the slider in the alert color once checked —
	 *                       for toggles with consequences big enough to want a
	 *                       visual warning when left on.
	 * @return void
	 */
	private function renderCheckbox(string $key, string $description, bool $alert = false): void
	{
		$settings = Repository::getSettings();
		$name = Repository::OPTION_NAME . '[' . $key . ']';

		printf(
			'<label class="switch%1$s"><input type="checkbox" name="%2$s" value="1" %3$s /><span class="slider round"></span></label><p class="description">%4$s</p>',
			$alert ? ' switch-alert' : '',
			esc_attr($name),
			checked(!empty($settings[$key]), true, false),
			esc_html($description)
		);
	}

	/**
	 * @return void
	 */
	private function renderWebpQualityField(): void
	{
		$settings = Repository::getSettings();

		printf(
			'<input type="number" min="1" max="100" name="%1$s[webp_quality]" value="%2$d" />'
				. '<p class="description">%3$s</p>',
			esc_attr(Repository::OPTION_NAME),
			(int) $settings['webp_quality'],
			esc_html__('Applies to both the WebP format conversion above and every automatically generated responsive image size (1–100).', 'amrf-admin')
		);
	}

	/**
	 * Shared .switch/.slider styles, enqueued unconditionally — cheap, scoped
	 * class names.
	 *
	 * @return void
	 */
	public function enqueueSwitchStyles(): void
	{
		wp_enqueue_style(
			'amrf-admin-settings',
			AMRF_ADMIN_PLUGIN_URL . 'assets/css/amrf-admin-settings.css'
		);
	}
}
