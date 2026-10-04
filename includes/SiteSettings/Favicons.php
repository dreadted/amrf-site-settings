<?php

namespace Antropomorf\SiteSettings;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Favicon links and /site.webmanifest from BrandImages; always output, since they're site identity, not SEO.
 *
 * @package Antropomorf\SiteSettings
 */
class Favicons
{
	private const QUERY_VAR = 'amrf_webmanifest';

	public function __construct()
	{
		register_activation_hook(AMRF_ADMIN_PLUGIN_FILE, [self::class, 'flushRewriteRulesOnActivation']);
		register_deactivation_hook(AMRF_ADMIN_PLUGIN_FILE, [self::class, 'flushRewriteRulesOnDeactivation']);

		add_action('wp_head', [$this, 'renderLinkTags']);
		add_action('init', [self::class, 'registerRewriteRules']);
		add_filter('query_vars', [$this, 'registerQueryVar']);
		add_action('parse_request', [$this, 'renderManifest']);
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
	 * @return void
	 */
	public function renderLinkTags(): void
	{
		$settings = Repository::getSettings();
		$themeColor = $settings['theme_color'];
		$shortName = $settings['business_name'] ?: get_bloginfo('name');
		$icons = [
			'icon_svg' => ' type="image/svg+xml"',
			'icon_ico' => '',
			'icon_192' => ' type="image/png" sizes="192x192"',
		];

		foreach ($icons as $key => $attributes) {
			$url = BrandImages::url($key);
			if ($url) {
				printf("<link rel=\"icon\"%s href=\"%s\" />\n", $attributes, esc_url($url));
			}
		}
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
	 * Non-index.php targets go to .htaccess, served without PHP. The touch icon
	 * is root-only: linked in <head>, Android picks it as the tab icon.
	 *
	 * @return array<string, string> Regex => target.
	 */
	private static function rewriteRules(): array
	{
		$rules = ['^site\.webmanifest$' => 'index.php?' . self::QUERY_VAR . '=1'];
		$files = [
			'favicon\.ico$' => 'icon_ico',
			'apple-touch-icon(-precomposed)?\.png$' => 'apple_touch_icon',
		];

		foreach ($files as $regex => $key) {
			$path = BrandImages::homePath($key);
			if ($path) {
				$rules[$regex] = $path;
			}
		}

		return $rules;
	}

	/**
	 * @param array<int, string> $vars
	 * @return array<int, string>
	 */
	public function registerQueryVar(array $vars): array
	{
		$vars[] = self::QUERY_VAR;
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
		if (empty($wp->query_vars[self::QUERY_VAR])) {
			return;
		}

		$settings = Repository::getSettings();
		$icons = [
			'icon_svg' => ['sizes' => 'any', 'type' => 'image/svg+xml'],
			'icon_192' => ['sizes' => '192x192', 'type' => 'image/png'],
			'icon_512' => ['sizes' => '512x512', 'type' => 'image/png'],
		];
		$manifestIcons = [];

		foreach ($icons as $key => $icon) {
			$url = BrandImages::url($key);
			if ($url) {
				$manifestIcons[] = ['src' => $url] + $icon;
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
}
