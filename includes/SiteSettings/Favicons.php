<?php

namespace Antropomorf\SiteSettings;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Site icon from the theme's BrandImages, unless a core Site Icon is set: feeds core's
 * get_site_icon_url() and icon tags, /site.webmanifest, /favicon.ico and /apple-touch-icon.png.
 *
 * @package Antropomorf\SiteSettings
 */
class Favicons
{
	private const MANIFEST_QUERY_VAR = 'amrf_webmanifest';
	private const TOUCH_ICON_QUERY_VAR = 'amrf_touch_icon';
	private const CACHE_SECONDS = WEEK_IN_SECONDS;
	private const FAVICON_REGEX = 'favicon\\.ico$';
	private const HTACCESS_RETRY_TRANSIENT = 'amrf_favicon_htaccess_retry';

	public function __construct()
	{
		register_activation_hook(AMRF_ADMIN_PLUGIN_FILE, [self::class, 'flushRewriteRulesOnActivation']);
		register_deactivation_hook(AMRF_ADMIN_PLUGIN_FILE, [self::class, 'flushRewriteRulesOnDeactivation']);

		add_filter('get_site_icon_url', [$this, 'siteIconUrl'], 10, 2);
		add_filter('site_icon_meta_tags', [$this, 'iconTags']);
		add_action('wp_head', [$this, 'renderHeadTags']);
		add_action('init', [self::class, 'registerRewriteRules']);
		add_filter('query_vars', [$this, 'registerQueryVars']);
		add_action('parse_request', [$this, 'renderManifest']);
		add_action('parse_request', [$this, 'renderTouchIcon']);
		add_action('do_faviconico', [$this, 'renderFaviconIco']);
		add_action('admin_init', [$this, 'ensureHtaccessRule']);
	}

	/**
	 * Activation runs after 'init', so the rules must be added here before the flush.
	 *
	 * @return void
	 */
	public static function flushRewriteRulesOnActivation(): void
	{
		self::registerRewriteRules();
		flush_rewrite_rules();
	}

	/**
	 * The rules were already added on this request's 'init', so drop them before the flush.
	 *
	 * @return void
	 */
	public static function flushRewriteRulesOnDeactivation(): void
	{
		global $wp_rewrite;

		foreach (array_keys(self::rewriteRules()) as $regex) {
			unset($wp_rewrite->extra_rules_top[$regex], $wp_rewrite->non_wp_rules[$regex]);
		}
		flush_rewrite_rules();
	}

	/**
	 * @return bool Whether an admin has set a core Site Icon, which overrides the theme's icons.
	 */
	public static function hasCustomSiteIcon(): bool
	{
		$id = (int) get_option('site_icon');

		return $id && wp_get_attachment_image_url($id, 'full');
	}

	/**
	 * Callers may pass a fallback as $url (the embed passes the WordPress logo), so it can't signal a custom icon.
	 *
	 * @param string $url
	 * @param int    $size
	 * @return string
	 */
	public function siteIconUrl($url, $size): string
	{
		if (self::hasCustomSiteIcon()) {
			return (string) $url;
		}

		return BrandImages::forSize((int) $size) ?: (string) $url;
	}

	/**
	 * The touch icon is never linked: Chrome on Android would pick the opaque square as tab icon.
	 * iOS fetches /apple-touch-icon.png on its own.
	 *
	 * @param array<int, string> $tags
	 * @return array<int, string>
	 */
	public function iconTags($tags): array
	{
		if (self::hasCustomSiteIcon()) {
			return array_values(array_filter((array) $tags, static function ($tag): bool {
				return !str_contains($tag, 'apple-touch-icon') && !str_contains($tag, 'msapplication-TileImage');
			}));
		}

		$icons = [
			'icon_svg' => ' type="image/svg+xml"',
			'icon_ico' => '',
			'icon_192' => ' type="image/png" sizes="192x192"',
		];
		$tags = [];

		foreach ($icons as $key => $attributes) {
			$url = BrandImages::url($key);
			if ($url) {
				$tags[] = sprintf('<link rel="icon"%s href="%s" />', $attributes, esc_url($url));
			}
		}

		return $tags;
	}

	/**
	 * @return void
	 */
	public function renderHeadTags(): void
	{
		$settings = Repository::getSettings();
		$themeColor = $settings['theme_color'];
		$shortName = $settings['business_name'] ?: get_bloginfo('name');
		?>
<link rel="manifest" href="<?php echo esc_url(home_url('/site.webmanifest')); ?>" />
<meta name="theme-color" content="<?php echo esc_attr($themeColor); ?>" />
<meta name="apple-mobile-web-app-title" content="<?php echo esc_attr($shortName); ?>" />
		<?php
	}

	/**
	 * @return void
	 */
	public static function registerRewriteRules(): void
	{
		foreach (self::rewriteRules() as $regex => $target) {
			add_rewrite_rule($regex, $target, 'top');
		}
	}

	/**
	 * /favicon.ico is a static .htaccess rule, since LiteSpeed answers a missing /favicon.ico
	 * itself without reaching index.php. Elsewhere core routes it to do_favicon().
	 *
	 * @return array<string, string> Regex => target.
	 */
	private static function rewriteRules(): array
	{
		$rules = [
			'^site\.webmanifest$' => 'index.php?' . self::MANIFEST_QUERY_VAR . '=1',
			'^apple-touch-icon(-precomposed)?\.png$' => 'index.php?' . self::TOUCH_ICON_QUERY_VAR . '=1',
		];

		$url = self::faviconIcoUrl();
		$path = BrandImages::homePath($url);
		if ($path && BrandImages::localPath($url)) {
			$rules[self::FAVICON_REGEX] = $path;
		}

		return $rules;
	}

	/**
	 * Rewrites .htaccess on an admin's page load when its /favicon.ico rule is missing or stale,
	 * e.g. after a deploy, a clone or a Site Icon change. A failed write is retried hourly.
	 *
	 * @return void
	 */
	public function ensureHtaccessRule(): void
	{
		global $wp_rewrite;

		if (is_multisite() || !current_user_can('manage_options') || get_transient(self::HTACCESS_RETRY_TRANSIENT) || !function_exists('save_mod_rewrite_rules')) {
			return;
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		$file = get_home_path() . '.htaccess';
		$written = is_readable($file) ? extract_from_markers($file, 'WordPress') : [];
		if (!$written || !got_mod_rewrite()) {
			return;
		}

		$expected = explode("\n", $wp_rewrite->mod_rewrite_rules());
		if (self::faviconLines($written) === self::faviconLines($expected)) {
			return;
		}

		if (!save_mod_rewrite_rules()) {
			set_transient(self::HTACCESS_RETRY_TRANSIENT, 1, HOUR_IN_SECONDS);
		}
	}

	/**
	 * @param array<int, string> $lines
	 * @return array<int, string>
	 */
	private static function faviconLines(array $lines): array
	{
		$prefix = 'RewriteRule ^' . self::FAVICON_REGEX . ' ';

		return array_values(array_filter(array_map('trim', $lines), static fn(string $line): bool => str_starts_with($line, $prefix)));
	}

	/**
	 * @param array<int, string> $vars
	 * @return array<int, string>
	 */
	public function registerQueryVars(array $vars): array
	{
		$vars[] = self::MANIFEST_QUERY_VAR;
		$vars[] = self::TOUCH_ICON_QUERY_VAR;
		return $vars;
	}

	/**
	 * Served before the main query, so posts-disabled 404s and redirect_canonical never see it.
	 *
	 * @param \WP $wp
	 * @return void
	 */
	public function renderManifest(\WP $wp): void
	{
		if (empty($wp->query_vars[self::MANIFEST_QUERY_VAR])) {
			return;
		}

		$settings = Repository::getSettings();
		$manifestIcons = [];

		if (!self::hasCustomSiteIcon() && BrandImages::url('icon_svg')) {
			$manifestIcons[] = ['src' => BrandImages::url('icon_svg'), 'sizes' => 'any', 'type' => 'image/svg+xml'];
		}
		foreach ([192, 512] as $size) {
			$url = get_site_icon_url($size);
			if ($url && !in_array($url, array_column($manifestIcons, 'src'), true)) {
				$manifestIcons[] = ['src' => $url, 'sizes' => "{$size}x{$size}", 'type' => self::mimeType($url)];
			}
		}

		$manifest = [
			'name' => $settings['seo_title'] ?: ($settings['business_name'] ?: get_bloginfo('name')),
			'short_name' => $settings['business_name'] ?: get_bloginfo('name'),
			'description' => $settings['meta_description'] ?: get_bloginfo('description'),
			'start_url' => '/',
			// Not 'standalone' — that's what makes Chrome/Android offer to
			// install this as a PWA. A normal website, not an app.
			'display' => 'browser',
			'background_color' => $settings['background_color'],
			'theme_color' => $settings['theme_color'],
			'icons' => $manifestIcons,
		];

		header('Content-Type: application/manifest+json');
		echo wp_json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
		exit;
	}

	/**
	 * @param \WP $wp
	 * @return void
	 */
	public function renderTouchIcon(\WP $wp): void
	{
		if (empty($wp->query_vars[self::TOUCH_ICON_QUERY_VAR])) {
			return;
		}

		$url = self::hasCustomSiteIcon() ? get_site_icon_url(180) : BrandImages::url('apple_touch_icon');
		if (!$url) {
			status_header(404);
			nocache_headers();
			exit;
		}

		self::serve($url);
	}

	/**
	 * Without an icon this returns, and core redirects to the WordPress logo.
	 *
	 * @return void
	 */
	public function renderFaviconIco(): void
	{
		$url = self::faviconIcoUrl();

		if ($url) {
			self::serve($url);
		}
	}

	/**
	 * @return string
	 */
	private static function faviconIcoUrl(): string
	{
		$url = self::hasCustomSiteIcon() ? get_site_icon_url(32) : BrandImages::url('icon_ico');

		return $url ?: get_site_icon_url(32);
	}

	/**
	 * Sends the file itself rather than a redirect, which not every client probing the site root follows.
	 *
	 * @param string $url
	 * @return never
	 */
	private static function serve(string $url): never
	{
		$path = BrandImages::localPath($url);
		if (!$path) {
			wp_redirect($url);
			exit;
		}

		header('Content-Type: ' . self::mimeType($path));
		header('Content-Length: ' . filesize($path));
		header('Cache-Control: public, max-age=' . self::CACHE_SECONDS);
		readfile($path); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile -- streams a local image file.
		exit;
	}

	/**
	 * @param string $file URL or path.
	 * @return string
	 */
	private static function mimeType(string $file): string
	{
		$types = ['ico' => 'image/x-icon', 'svg' => 'image/svg+xml', 'png' => 'image/png', 'jpg|jpeg|jpe' => 'image/jpeg', 'webp' => 'image/webp'];

		return wp_check_filetype((string) strtok($file, '?#'), $types)['type'] ?: 'application/octet-stream';
	}
}
