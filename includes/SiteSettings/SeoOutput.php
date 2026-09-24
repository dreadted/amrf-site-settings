<?php

namespace Antropomorf\SiteSettings;

if (!defined('ABSPATH')) {
    exit;
}

// Title, meta description, OG/Twitter and JSON-LD from Site Settings, only when The SEO Framework isn't active.
class SeoOutput
{
    public function __construct()
    {
        add_action('plugins_loaded', [$this, 'registerIfInactive']);
    }

    public function registerIfInactive(): void
    {
        if (function_exists('tsf')) {
            return;
        }

        add_action('after_setup_theme', [$this, 'ensureTitleTagSupport'], 11);
        add_filter('pre_get_document_title', [$this, 'filterDocumentTitle']);
        add_action('wp_head', [$this, 'renderMetaTags']);
        add_action('wp_head', [$this, 'renderJsonLd']);
    }

    // pre_get_document_title never fires without title-tag support.
    public function ensureTitleTagSupport(): void
    {
        if (!current_theme_supports('title-tag')) {
            add_theme_support('title-tag');
        }
    }

    public function filterDocumentTitle(string $title): string
    {
        if (!Repository::isSeoOutputEnabled() || !is_front_page()) {
            return $title;
        }

        return Repository::getSettings()['seo_title'] ?: $title;
    }

    public function renderMetaTags(): void
    {
        if (!Repository::isSeoOutputEnabled()) {
            return;
        }

        $settings = Repository::getSettings();
        $title = wp_get_document_title();
        $description = $this->resolveDescription($settings);
        $image = JsonLd::resolveImageUrl($settings);
        $url = is_singular() && !is_front_page() ? (get_permalink() ?: home_url('/')) : home_url('/');
        $xHandle = self::extractXHandle($settings['x_url']);
?>
<meta name="description" content="<?php echo esc_attr($description); ?>" />
<meta property="og:title" content="<?php echo esc_attr($title); ?>" />
<meta property="og:description" content="<?php echo esc_attr($description); ?>" />
<?php if ($image) : ?>
<meta property="og:image" content="<?php echo esc_url($image); ?>" />
<?php endif; ?>
<meta property="og:type" content="website" />
<meta property="og:url" content="<?php echo esc_url($url); ?>" />
<?php if ($settings['business_name']) : ?>
<meta property="og:site_name" content="<?php echo esc_attr($settings['business_name']); ?>" />
<?php endif; ?>
<meta property="og:locale" content="<?php echo esc_attr(get_locale()); ?>" />
<meta name="twitter:card" content="summary_large_image" />
<meta name="twitter:title" content="<?php echo esc_attr($title); ?>" />
<meta name="twitter:description" content="<?php echo esc_attr($description); ?>" />
<?php if ($image) : ?>
<meta name="twitter:image" content="<?php echo esc_url($image); ?>" />
<?php endif; ?>
<?php if ($xHandle) : ?>
<meta name="twitter:site" content="@<?php echo esc_attr($xHandle); ?>" />
<?php endif;
    }

    public function renderJsonLd(): void
    {
        if (!Repository::isSeoOutputEnabled()) {
            return;
        }

        $nodes = JsonLd::buildNodes(Repository::getSettings());
        if (!$nodes) {
            return;
        }

        printf(
            '<script type="application/ld+json">%s</script>' . "\n",
            wp_json_encode(['@context' => 'https://schema.org', '@graph' => $nodes], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        );
    }

    private function resolveDescription(array $settings): string
    {
        if (is_singular() && !is_front_page()) {
            $excerpt = get_the_excerpt();
            if ($excerpt) {
                return wp_strip_all_tags($excerpt);
            }
        }

        return $settings['meta_description'] ?: get_bloginfo('description');
    }

    // Handle from an x.com/twitter.com profile URL, without the leading "@".
    private static function extractXHandle(string $url): string
    {
        if (!preg_match('~^https?://(?:www\.)?(?:x|twitter)\.com/@?([A-Za-z0-9_]{1,15})(?:[/?#]|$)~i', $url, $matches)) {
            return '';
        }

        return $matches[1];
    }
}
