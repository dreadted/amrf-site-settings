<?php

namespace Antropomorf\SiteSettings;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class SeoOutput
 *
 * Document title, meta description, Open Graph/Twitter Card tags, and
 * Organization+Person JSON-LD. Gated behind the enable_seo_output toggle,
 * off by default for a site that already runs its own SEO plugin.
 * Favicons aren't handled here — WordPress's own Site Icon feature covers
 * that generically.
 *
 * @package Antropomorf\SiteSettings
 */
class SeoOutput
{
    public function __construct()
    {
        // pre_get_document_title never fires without title-tag support,
        // which some classic themes never declare.
        if (!current_theme_supports('title-tag')) {
            add_theme_support('title-tag');
        }

        add_filter('pre_get_document_title', [$this, 'filterDocumentTitle']);
        add_action('wp_head', [$this, 'renderMetaTags']);
        add_action('wp_head', [$this, 'renderJsonLd']);

        // Independent of enable_seo_output: WP's built-in /wp-sitemap.xml
        // exists regardless of this plugin's meta output.
        add_filter('wp_sitemaps_posts_query_args', [$this, 'restrictSitemapQueryArgs'], 10, 2);
    }

    /**
     * Restricts the "page" post type's sitemap entries to sitemap_page_ids
     * when restrict_sitemap is on. Empty selection means "show nothing"
     * (post__in => [0]), not "show everything".
     *
     * @param array $args WP_Query args for this sitemap's post type.
     * @param string $postType
     * @return array
     */
    public function restrictSitemapQueryArgs(array $args, string $postType): array
    {
        if ('page' !== $postType) {
            return $args;
        }

        $settings = Repository::getSettings();
        if (empty($settings['restrict_sitemap'])) {
            return $args;
        }

        $ids = array_map('absint', array_filter(explode(',', $settings['sitemap_page_ids'])));
        $args['post__in'] = $ids ?: [0];

        return $args;
    }

    /**
     * Document <title> — Repository's seo_title overrides WP's title-tag
     * output on the front page only; there's no per-post override field.
     *
     * @param string $title
     * @return string
     */
    public function filterDocumentTitle(string $title): string
    {
        if (!Repository::isSeoOutputEnabled() || !is_front_page()) {
            return $title;
        }

        $settings = Repository::getSettings();
        return $settings['seo_title'] ?: $title;
    }

    /**
     * @param array<string, string> $settings
     */
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

    /**
     * @param array<string, string> $settings
     */
    private function resolveImageUrl(array $settings): string
    {
        if (is_singular() && has_post_thumbnail()) {
            $thumbnail = get_the_post_thumbnail_url(null, 'large');
            if ($thumbnail) {
                return $thumbnail;
            }
        }

        return $settings['share_image'];
    }

    private function resolveUrl(): string
    {
        if (is_front_page()) {
            return home_url('/');
        }

        if (is_singular()) {
            return get_permalink() ?: home_url('/');
        }

        return home_url('/');
    }

    /**
     * Meta description, theme-color, Open Graph, and Twitter Card tags.
     *
     * @return void
     */
    public function renderMetaTags(): void
    {
        if (!Repository::isSeoOutputEnabled()) {
            return;
        }

        $settings = Repository::getSettings();

        if ($settings['theme_color']) {
            printf('<meta name="theme-color" content="%s" />' . "\n", esc_attr($settings['theme_color']));
        }

        $title = wp_get_document_title();
        $description = $this->resolveDescription($settings);
        $image = $this->resolveImageUrl($settings);
        $url = $this->resolveUrl();
        $locale = $settings['og_locale'] ?: get_locale();
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
<meta property="og:locale" content="<?php echo esc_attr($locale); ?>" />

<meta name="twitter:card" content="summary_large_image" />
<meta name="twitter:title" content="<?php echo esc_attr($title); ?>" />
<meta name="twitter:description" content="<?php echo esc_attr($description); ?>" />
<?php if ($image) : ?>
<meta name="twitter:image" content="<?php echo esc_url($image); ?>" />
<?php endif; ?>
<?php $xHandle = self::extractXHandle($settings['x_url']);
if ($xHandle) :
?>
<meta name="twitter:site" content="@<?php echo esc_attr($xHandle); ?>" />
<meta name="twitter:creator" content="@<?php echo esc_attr($xHandle); ?>" />
<?php endif;
    }

    /**
     * Pulls the handle straight out of an X/Twitter profile URL instead of
     * requiring it as a separate, easily-out-of-sync field — e.g.
     * "https://x.com/example" or "https://twitter.com/example/" both yield
     * "example".
     *
     * @return string Handle without the leading "@", or '' if $url doesn't
     *                match a profile URL.
     */
    private static function extractXHandle(string $url): string
    {
        if (!preg_match('~^https?://(?:www\.)?(?:x|twitter)\.com/@?([A-Za-z0-9_]{1,15})(?:[/?#]|$)~i', $url, $matches)) {
            return '';
        }

        return $matches[1];
    }

    /**
     * Organization + Person JSON-LD, front page only.
     *
     * @param array<string, string> $settings
     * @return array<string, mixed>|null
     */
    private function buildJsonLd(array $settings, string $imageUrl): ?array
    {
        $orgId = home_url('/') . '#organization';
        $personId = home_url('/') . '#person';

        $hasOrg = $settings['business_name'] && $settings['business_type'];
        $hasPerson = (bool) $settings['person_name'];

        if (!$hasOrg && !$hasPerson) {
            return null;
        }

        $sameAs = array_values(array_filter([
            $settings['facebook_url'],
            $settings['instagram_url'],
            $settings['x_url'],
        ]));

        $organization = null;
        if ($hasOrg) {
            $organization = [
                '@type' => $settings['business_type'],
                '@id' => $orgId,
                'name' => $settings['business_name'],
                'url' => home_url('/'),
            ];

            if ($imageUrl) {
                $organization['image'] = $imageUrl;
            }
            if ($settings['street'] && $settings['city']) {
                $organization['address'] = [
                    '@type' => 'PostalAddress',
                    'streetAddress' => $settings['street'],
                    'postalCode' => $settings['postal_code'],
                    'addressLocality' => $settings['city'],
                    'addressRegion' => $settings['region'],
                    'addressCountry' => $settings['country'],
                ];
            }
            if ($settings['latitude'] && $settings['longitude']) {
                $organization['geo'] = [
                    '@type' => 'GeoCoordinates',
                    'latitude' => (float) $settings['latitude'],
                    'longitude' => (float) $settings['longitude'],
                ];
            }
            if ($settings['phone']) {
                $organization['telephone'] = $settings['phone'];
            }
            // No 'email' -- it's obfuscated everywhere else it's displayed,
            // shouldn't leak in plain text here.
            if ($sameAs) {
                $organization['sameAs'] = $sameAs;
            }
            if ($hasPerson) {
                $organization['founder'] = ['@id' => $personId];
            }
        }

        $person = null;
        if ($hasPerson) {
            $person = [
                '@type' => 'Person',
                '@id' => $personId,
                'name' => $settings['person_name'],
            ];

            if ($settings['job_title']) {
                $person['jobTitle'] = $settings['job_title'];
            }
            if ($hasOrg) {
                $person['worksFor'] = ['@id' => $orgId];
            }
        }

        $nodes = array_values(array_filter([$organization, $person]));

        if (count($nodes) === 1) {
            return ['@context' => 'https://schema.org'] + $nodes[0];
        }

        return [
            '@context' => 'https://schema.org',
            '@graph' => $nodes,
        ];
    }

    public function renderJsonLd(): void
    {
        if (!Repository::isSeoOutputEnabled() || !is_front_page()) {
            return;
        }

        $settings = Repository::getSettings();
        $jsonLd = $this->buildJsonLd($settings, $this->resolveImageUrl($settings));

        if (!$jsonLd) {
            return;
        }

        echo '<script type="application/ld+json">' . wp_json_encode($jsonLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>' . "\n";
    }
}
