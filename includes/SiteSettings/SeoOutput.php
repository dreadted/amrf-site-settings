<?php

namespace Antropomorf\SiteSettings;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class SeoOutput
 *
 * Document title, meta description, Open Graph/Twitter Card tags, and
 * Organization+Person JSON-LD — ported from ptsussis-theme's
 * includes/seo.php, generalized: that version hardcoded og:locale
 * ('sv_SE') and the theme-color meta (its own brand hex) as constants;
 * this reads them from Repository's og_locale/theme_color fields instead
 * (the reason those fields exist at all — see Repository's own docblock).
 * Everything here is gated behind the enable_seo_output toggle, since
 * unlike a single-purpose theme this plugin has to stay off by default
 * for a site that already runs its own SEO plugin.
 *
 * Favicon links and the /site.webmanifest endpoint from the source file
 * are deliberately NOT ported here — those assume theme-specific asset
 * paths (get_stylesheet_directory_uri() . '/assets/images/...'), and this
 * plugin doesn't tie itself to any one theme's file layout. WordPress's
 * own Site Icon feature covers the favicon case generically instead.
 *
 * @package Antropomorf\SiteSettings
 */
class SeoOutput
{
    public function __construct()
    {
        // Some themes never declare title-tag support (classic themes that
        // still hardcode <title> in header.php) -- pre_get_document_title
        // silently never fires without it. This plugin can't assume the
        // active theme happens to have it (see functions.php's own note
        // about not tailoring the plugin to any one theme), so it adds the
        // support itself if missing, same as WordPress recommends any
        // plugin needing document-title control do.
        if (!current_theme_supports('title-tag')) {
            add_theme_support('title-tag');
        }

        add_filter('pre_get_document_title', [$this, 'filterDocumentTitle']);
        add_action('wp_head', [$this, 'renderMetaTags']);
        add_action('wp_head', [$this, 'renderJsonLd']);

        // Independent of enable_seo_output: WordPress's own built-in
        // /wp-sitemap.xml exists regardless of whether this plugin's meta
        // output is on, and a site owner may want to restrict it (e.g.
        // keep a support-ticket portal page or similar utility page out
        // of search results) without wanting the rest of the SEO output.
        add_filter('wp_sitemaps_posts_query_args', [$this, 'restrictSitemapQueryArgs'], 10, 2);
    }

    /**
     * Restricts the "page" post type's entries in WordPress's own built-in
     * sitemap to exactly the pages selected in sitemap_page_ids, when
     * restrict_sitemap is on. Empty selection while the toggle is on means
     * "show nothing" (post__in => [0], a query that matches no post) --
     * the toggle's whole purpose is to narrow what's exposed, so an empty
     * list should never silently fall back to exposing every page.
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
     * Document <title> — Repository's seo_title overrides WordPress's own
     * title-tag output, but only on the front page, matching the source:
     * there's no per-post override field, so every other page keeps
     * WordPress's normal title behavior untouched.
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
<?php if ($settings['x_handle']) :
    $handle = '@' . ltrim($settings['x_handle'], '@');
?>
<meta name="twitter:site" content="<?php echo esc_attr($handle); ?>" />
<meta name="twitter:creator" content="<?php echo esc_attr($handle); ?>" />
<?php endif;
    }

    /**
     * Organization + Person JSON-LD, front page only (same reasoning as
     * the document-title override: there's effectively only one route
     * this data describes).
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
            // No 'email' here deliberately, matching the source -- an
            // address that's otherwise given the obfuscation treatment
            // anywhere it's displayed shouldn't turn around and leak in
            // plain text here.
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
