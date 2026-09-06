<?php

namespace Antropomorf\SiteSettings;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Repository
 *
 * Storage, defaults, and sanitization for one site's business/contact info —
 * ported from ptsussis-theme's includes/site-settings.php, generalized (no
 * theme-specific seeded content, og:locale/theme/background colors added as
 * real fields instead of hardcoded constants in that theme's seo.php).
 *
 * @package Antropomorf\SiteSettings
 */
class Repository
{
    public const OPTION_NAME = 'amrf_site_settings';

    /**
     * The theme option this replaces on the site that had it — read once,
     * on activation, to carry existing production data over. See
     * migrateFromThemeIfNeeded().
     */
    private const LEGACY_THEME_OPTION_NAME = 'ptsussis_site_settings';

    /**
     * Field key => [label, type, section]. type is the <input> type, except
     * "textarea" and "url" (see Provider::renderField() for how those two
     * render) and "media". Order here is the order fields render in.
     *
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    public static function getFields(): array
    {
        return [
            'enable_seo_output' => [__('Enable SEO Output', 'amrf-admin'), 'checkbox', 'seo'],
            'seo_title' => [__('SEO title', 'amrf-admin'), 'text', 'seo'],
            'meta_description' => [__('Meta description', 'amrf-admin'), 'textarea', 'seo'],
            'share_image' => [__('Share image', 'amrf-admin'), 'media', 'seo'],
            'og_locale' => [__('Open Graph locale (e.g. sv_SE)', 'amrf-admin'), 'text', 'seo'],
            'theme_color' => [__('Theme color', 'amrf-admin'), 'color', 'seo'],
            'background_color' => [__('Background color', 'amrf-admin'), 'color', 'seo'],
            'restrict_sitemap' => [__('Restrict Sitemap to Selected Pages', 'amrf-admin'), 'checkbox', 'seo'],
            'sitemap_page_ids' => [__('Pages Included in Sitemap', 'amrf-admin'), 'page_list', 'seo'],

            'business_name' => [__('Business name', 'amrf-admin'), 'text', 'business'],
            'business_type' => [__('Business type (schema.org)', 'amrf-admin'), 'text', 'business'],
            'person_name' => [__('Person name', 'amrf-admin'), 'text', 'business'],
            'job_title' => [__('Job title', 'amrf-admin'), 'text', 'business'],
            'email' => [__('Email', 'amrf-admin'), 'email', 'business'],
            'phone' => [__('Phone', 'amrf-admin'), 'text', 'business'],
            // Swish number moved to its own "Swish" tab on the Forms page
            // (Swish\Repository) — it now drives QR generation too, not
            // just this field's old display-only purpose. Swish\Repository::
            // getSettings() reads whatever's left of this option's own
            // 'swish_number' key once, as a one-time migration for a site
            // that already had it set — the key itself is left orphaned
            // here rather than actively scrubbed, same as any other retired
            // field in this codebase.
            // No canonical-URL field — reuse WordPress's own siteurl/home
            // option (home_url()) instead, one source of truth per
            // environment rather than two that can drift apart.

            'street' => [__('Street address', 'amrf-admin'), 'text', 'address'],
            'postal_code' => [__('Postal code', 'amrf-admin'), 'text', 'address'],
            'city' => [__('City', 'amrf-admin'), 'text', 'address'],
            'region' => [__('Region', 'amrf-admin'), 'text', 'address'],
            'country' => [__('Country', 'amrf-admin'), 'text', 'address'],
            'latitude' => [__('Latitude', 'amrf-admin'), 'text', 'address'],
            'longitude' => [__('Longitude', 'amrf-admin'), 'text', 'address'],

            'facebook_url' => [__('Facebook URL', 'amrf-admin'), 'url', 'social'],
            'instagram_url' => [__('Instagram URL', 'amrf-admin'), 'url', 'social'],
            // No separate handle field — SeoOutput::extractXHandle() pulls
            // it straight out of the path segment after x.com/, so there's
            // only one value to keep in sync per site instead of two.
            'x_url' => [__('X (Twitter) URL', 'amrf-admin'), 'url', 'social'],
        ];
    }

    /**
     * @return array<string, string> Section key => section label, in render order.
     */
    public static function getSections(): array
    {
        return [
            'seo' => __('SEO', 'amrf-admin'),
            'business' => __('Business & Contact', 'amrf-admin'),
            'address' => __('Address', 'amrf-admin'),
            'social' => __('Social Media', 'amrf-admin'),
        ];
    }

    /**
     * @return array<string, string> Every field defaulted to an empty
     *                                 string, except theme_color/
     *                                 background_color — see
     *                                 getThemeDefaultColors().
     */
    public static function getDefaults(): array
    {
        $defaults = array_fill_keys(array_keys(self::getFields()), '');
        return array_merge($defaults, self::getThemeDefaultColors());
    }

    /**
     * Starting values for the theme_color/background_color color pickers,
     * read from the active theme's own theme.json palette instead of
     * defaulting to blank — <input type="color"> renders an empty value as
     * black, which looks like a real (wrong) choice rather than "unset".
     * Only ever fills the gap before this option has been saved once: the
     * moment the SEO tab is submitted, sanitize() stores whatever the color
     * picker held (the theme default, or the user's own pick) as a literal
     * value from then on, per Settings API normal behavior.
     *
     * WP_Theme_JSON_Resolver was only added in WP 5.8, one version past
     * this plugin's declared floor (5.6) — class_exists guards it the same
     * way shortcode_exists() guards other optional integrations elsewhere
     * in this codebase.
     *
     * @return array{theme_color: string, background_color: string}
     */
    private static function getThemeDefaultColors(): array
    {
        if (!class_exists('WP_Theme_JSON_Resolver')) {
            return ['theme_color' => '#000000', 'background_color' => '#ffffff'];
        }

        $palette = \WP_Theme_JSON_Resolver::get_merged_data()->get_settings()['color']['palette']['theme'] ?? [];
        $by_slug = array_column($palette, 'color', 'slug');
        $first = $palette[0]['color'] ?? '';

        $theme_color = $by_slug['primary'] ?? $by_slug['accent-1'] ?? $first;
        $background_color = self::resolveThemeBackgroundColor($by_slug) ?? $by_slug['base'] ?? $by_slug['background'] ?? '';

        return [
            'theme_color' => $theme_color !== '' ? $theme_color : '#000000',
            'background_color' => $background_color !== '' ? $background_color : '#ffffff',
        ];
    }

    /**
     * theme.json's styles.color.background is the theme author's own
     * explicit choice for the page background — preferred over guessing a
     * palette slug when a theme actually declares it. Resolved raw data
     * renders that as e.g. "var(--wp--preset--color--base)"; translate the
     * slug back to its hex via the same palette rather than emitting a
     * literal var() reference into an <input type="color">, which browsers
     * reject.
     *
     * @param array<string, string> $by_slug Palette slug => hex color.
     * @return string|null
     */
    private static function resolveThemeBackgroundColor(array $by_slug): ?string
    {
        $background = \WP_Theme_JSON_Resolver::get_merged_data()->get_raw_data()['styles']['color']['background'] ?? '';

        if ($background === '') {
            return null;
        }

        if (preg_match('/^#[0-9a-f]{3,8}$/i', $background)) {
            return $background;
        }

        if (preg_match('/--wp--preset--color--([a-z0-9-]+)/', $background, $matches)) {
            return $by_slug[$matches[1]] ?? null;
        }

        return null;
    }

    /**
     * @return array<string, string>
     */
    public static function getSettings(): array
    {
        $stored = get_option(self::OPTION_NAME, []);
        $settings = wp_parse_args(is_array($stored) ? $stored : [], self::getDefaults());

        // wp_parse_args() only fills in KEYS MISSING from $stored — a site
        // saved before theme_color/background_color existed, or before they
        // became color pickers, already has both stored as a literal ''
        // (the old text field's empty state). An empty string isn't a real
        // saved choice here the way it is for other text fields — nothing
        // lets you "clear" a color picker — so it's treated the same as
        // never having been set.
        foreach (['theme_color', 'background_color'] as $key) {
            if ($settings[$key] === '') {
                $settings[$key] = self::getThemeDefaultColors()[$key];
            }
        }

        return $settings;
    }

    /**
     * @param array<string, string> $settings
     */
    public static function updateSettings(array $settings): void
    {
        update_option(self::OPTION_NAME, $settings);
    }

    public static function isSeoOutputEnabled(): bool
    {
        if (self::isSearchEngineDiscouraged()) {
            return false;
        }

        return !empty(self::getSettings()['enable_seo_output']);
    }

    /**
     * WordPress's own "Discourage search engines from indexing this site"
     * toggle (Settings > Reading), stored as the core blog_public option --
     * NOT one of this plugin's own fields. Checked here (rather than left
     * to WordPress's own noindex meta tag alone) because that tag is only
     * ever a request compliant crawlers may honor; it does nothing to stop
     * this plugin's own Open Graph/Twitter Card tags and Organization/
     * Person JSON-LD (business name, address, phone, geo-coordinates) from
     * still being emitted and picked up by link-preview scrapers that don't
     * consult robots meta at all. Gating isSeoOutputEnabled() on this closes
     * that gap without touching robots output itself, which core already
     * owns.
     *
     * @return bool
     */
    public static function isSearchEngineDiscouraged(): bool
    {
        return '0' === (string) get_option('blog_public', '1');
    }

    /**
     * All four tabs (SEO/Business/Address/Social) share this one option, but
     * each submits only its own fields — WordPress's Settings API never
     * merges a submission with the option's existing value on its own, so a
     * naive "rebuild the whole array from $input" sanitize callback quietly
     * blanks out every OTHER tab's fields on each save (they're simply
     * absent from $input). Start from the current stored values instead, and
     * only touch keys this particular submission actually included.
     *
     * @param mixed $input Raw POSTed value for this option.
     * @return array<string, string>
     */
    public static function sanitize($input): array
    {
        $fields = self::getFields();
        $output = self::getSettings();

        foreach ($fields as $key => $field) {
            [$label, $type] = $field;

            if ($type === 'checkbox') {
                // Unlike every other field type, a checkbox is simply
                // absent from $_POST when unchecked — so its own presence
                // can't tell "this tab wasn't submitted" apart from
                // "submitted, unchecked". Provider::renderCheckboxField()
                // renders a "{$key}_submitted" hidden marker alongside it
                // specifically to disambiguate that.
                if (is_array($input) && array_key_exists($key . '_submitted', $input)) {
                    $output[$key] = !empty($input[$key]) ? '1' : '';
                }
                continue;
            }

            if ($type === 'page_list') {
                // Same "absent when nothing's checked" problem as
                // checkbox above, same _submitted-marker fix — a
                // deselect-everything save has to actually clear the
                // list, not silently leave the old one in place.
                if (is_array($input) && array_key_exists($key . '_submitted', $input)) {
                    $ids = isset($input[$key]) && is_array($input[$key]) ? array_map('absint', $input[$key]) : [];
                    $ids = array_values(array_unique(array_filter($ids)));
                    $output[$key] = implode(',', $ids);
                }
                continue;
            }

            if (!is_array($input) || !array_key_exists($key, $input)) {
                continue;
            }

            $value = (string) $input[$key];

            $output[$key] = match ($type) {
                'email' => sanitize_email($value),
                'url', 'media' => esc_url_raw($value),
                'textarea' => sanitize_textarea_field($value),
                'number' => (string) absint($value),
                // <input type="color"> only ever submits a valid #rrggbb,
                // but sanitize() can be reached via any code that calls
                // update_option()/the REST settings endpoint directly —
                // an invalid value falls back to what's already stored
                // rather than persisting garbage a color picker rejects.
                'color' => preg_match('/^#[0-9a-f]{6}$/i', $value) ? $value : $output[$key],
                default => sanitize_text_field($value),
            };
        }

        return $output;
    }

    /**
     * Copies ptsussis-theme's own site-settings option over on first
     * activation, so a site migrating from that theme's built-in Site
     * Settings doesn't need its real business data re-entered by hand.
     * No-ops if this plugin's own option already holds data, or the legacy
     * theme option doesn't exist — safe to call on every activation.
     *
     * @return void
     */
    public static function migrateFromThemeIfNeeded(): void
    {
        $existing = get_option(self::OPTION_NAME, []);
        if (!empty($existing)) {
            return;
        }

        $legacy = get_option(self::LEGACY_THEME_OPTION_NAME, []);
        if (empty($legacy) || !is_array($legacy)) {
            return;
        }

        update_option(self::OPTION_NAME, self::sanitize($legacy));
    }
}
