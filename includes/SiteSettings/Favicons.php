<?php

namespace Antropomorf\SiteSettings;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Favicons
 *
 * Favicon <link> tags + /site.webmanifest. Unconditional, independent of
 * enable_seo_output — these are core site identity, not optional SEO
 * output, so they still need to render even with that toggle off.
 *
 * Assumes the active theme ships favicon.svg/favicon.ico/apple-touch-
 * icon.png/icon-192.png/favicon.png under its own assets/images/ — true for
 * ptsussis-theme, not guaranteed for every theme this plugin might run
 * under.
 *
 * @package Antropomorf\SiteSettings
 */
class Favicons
{
    private const QUERY_VAR = 'amrf_webmanifest';

    public function __construct()
    {
        register_activation_hook(AMRF_ADMIN_PLUGIN_FILE, [self::class, 'flushRewriteRulesOnActivation']);

        add_action('wp_head', [$this, 'renderLinkTags']);
        add_action('init', [$this, 'registerRewriteRule']);
        add_filter('query_vars', [$this, 'registerQueryVar']);
        add_filter('redirect_canonical', [$this, 'skipCanonicalRedirect']);
        add_action('template_redirect', [$this, 'renderManifest']);
    }

    /**
     * A rewrite rule only takes effect after a flush — WP core's own
     * documented pattern for a plugin/theme that registers one.
     *
     * @return void
     */
    public static function flushRewriteRulesOnActivation(): void
    {
        flush_rewrite_rules();
    }

    /**
     * @return void
     */
    public function renderLinkTags(): void
    {
        $images = get_stylesheet_directory_uri() . '/assets/images';
        $themeColor = Repository::getSettings()['theme_color'];
        ?>
<link rel="icon" type="image/svg+xml" href="<?php echo esc_url($images . '/favicon.svg'); ?>" />
<link rel="icon" href="<?php echo esc_url($images . '/favicon.ico'); ?>" />
<link rel="apple-touch-icon" href="<?php echo esc_url($images . '/apple-touch-icon.png'); ?>" />
<link rel="manifest" href="<?php echo esc_url(home_url('/site.webmanifest')); ?>" />
<meta name="theme-color" content="<?php echo esc_attr($themeColor); ?>" />
        <?php
    }

    /**
     * @return void
     */
    public function registerRewriteRule(): void
    {
        add_rewrite_rule('^site\.webmanifest$', 'index.php?' . self::QUERY_VAR . '=1', 'top');
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
     * Without this, redirect_canonical() 301s /site.webmanifest to
     * /site.webmanifest/ — it doesn't recognize .webmanifest as a "real
     * file" extension the way it does e.g. .xml.
     *
     * @param string|false $redirectUrl
     * @return string|false
     */
    public function skipCanonicalRedirect($redirectUrl)
    {
        return get_query_var(self::QUERY_VAR) ? false : $redirectUrl;
    }

    /**
     * @return void
     */
    public function renderManifest(): void
    {
        if (!get_query_var(self::QUERY_VAR)) {
            return;
        }

        $settings = Repository::getSettings();
        $images = get_stylesheet_directory_uri() . '/assets/images';

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
            'icons' => [
                ['src' => $images . '/favicon.svg', 'sizes' => 'any', 'type' => 'image/svg+xml'],
                ['src' => $images . '/icon-192.png', 'sizes' => '192x192', 'type' => 'image/png'],
                ['src' => $images . '/favicon.png', 'sizes' => '512x512', 'type' => 'image/png'],
            ],
        ];

        header('Content-Type: application/manifest+json');
        echo wp_json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }
}
