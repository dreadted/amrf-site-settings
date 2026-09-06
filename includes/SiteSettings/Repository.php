<?php

namespace Antropomorf\SiteSettings;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Repository
 *
 * Storage, defaults, and sanitization for one site's business/contact info.
 *
 * @package Antropomorf\SiteSettings
 */
class Repository
{
    public const OPTION_NAME = 'amrf_site_settings';

    /** Legacy theme option, migrated once on activation. See migrateFromThemeIfNeeded(). */
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
            // Swish number lives on its own "Swish" tab now (Swish\Repository).
            // No canonical-URL field — reuse WordPress's own home_url()
            // instead of a second value that can drift.

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
            // it from the URL directly.
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
     * read from the active theme's own theme.json instead of defaulting to
     * blank — <input type="color"> renders an empty value as black, which
     * looks like a real (wrong) choice rather than "unset". Only ever
     * fills the gap before this option has been saved once: the moment the
     * SEO tab is submitted, sanitize() stores whatever the color picker
     * held (the theme default, or the user's own pick) as a literal value
     * from then on, per Settings API normal behavior.
     *
     * background_color prefers the theme's custom.contactFormBackground
     * token (see ptsussis-theme's theme.json) over styles.color.background
     * — the page's own background and a sensible manifest/splash-screen
     * background are often two different colors, and a theme that cares
     * to distinguish them can say so explicitly.
     *
     * WP_Theme_JSON_Resolver needs WP 5.8+, past this plugin's 5.6 floor —
     * class_exists guards it.
     *
     * @return array{theme_color: string, background_color: string}
     */
    private static function getThemeDefaultColors(): array
    {
        if (!class_exists('WP_Theme_JSON_Resolver')) {
            return ['theme_color' => '#000000', 'background_color' => '#ffffff'];
        }

        $settings = \WP_Theme_JSON_Resolver::get_merged_data()->get_settings();
        $palette = $settings['color']['palette']['theme'] ?? [];
        $by_slug = array_column($palette, 'color', 'slug');
        $first = $palette[0]['color'] ?? '';

        $theme_color = $by_slug['primary'] ?? $by_slug['accent-1'] ?? $first;
        $background_color = self::resolveColorReference($settings['custom']['contactFormBackground'] ?? '', $by_slug)
            ?? self::resolveThemeBackgroundColor($by_slug)
            ?? $by_slug['base'] ?? $by_slug['background'] ?? '';

        return [
            'theme_color' => $theme_color !== '' ? $theme_color : '#000000',
            'background_color' => $background_color !== '' ? $background_color : '#ffffff',
        ];
    }

    /**
     * theme.json's styles.color.background is resolved as e.g.
     * "var(--wp--preset--color--base)" — translate that back to a hex value
     * since <input type="color"> rejects var() references.
     *
     * @param array<string, string> $by_slug Palette slug => hex color.
     * @return string|null
     */
    private static function resolveThemeBackgroundColor(array $by_slug): ?string
    {
        $background = \WP_Theme_JSON_Resolver::get_merged_data()->get_raw_data()['styles']['color']['background'] ?? '';

        return self::resolveColorReference($background, $by_slug);
    }

    /**
     * A theme.json color value is either a literal hex, a
     * var(--wp--preset--color--slug) reference, or empty/unset.
     *
     * @param array<string, string> $by_slug Palette slug => hex color.
     * @return string|null
     */
    private static function resolveColorReference(string $value, array $by_slug): ?string
    {
        if ($value === '') {
            return null;
        }

        if (preg_match('/^#[0-9a-f]{3,8}$/i', $value)) {
            return $value;
        }

        if (preg_match('/--wp--preset--color--([a-z0-9-]+)/', $value, $matches)) {
            return $by_slug[$matches[1]] ?? null;
        }

        return null;
    }

    /**
     * The active theme's own sitewide button design, straight from
     * theme.json's styles.elements.button — whatever ptsussis-theme (or
     * any theme declaring this standard schema) says its buttons look
     * like, so a plugin-rendered button (the contact form's submit
     * button) can match it without either side hardcoding the other's
     * colors/fonts. Values are passed through as-is (var() references
     * included) — unlike getThemeDefaultColors(), nothing here needs
     * resolving to a literal hex, it only ever becomes CSS.
     *
     * @return array<string, string> CSS custom property name => value,
     *                                 only for whatever the theme actually
     *                                 declared.
     */
    public static function getThemeButtonStyle(): array
    {
        if (!class_exists('WP_Theme_JSON_Resolver')) {
            return [];
        }

        $button = \WP_Theme_JSON_Resolver::get_merged_data()->get_raw_data()['styles']['elements']['button'] ?? [];

        return array_filter([
            '--amrf-button-background' => $button['color']['background'] ?? '',
            '--amrf-button-text' => $button['color']['text'] ?? '',
            '--amrf-button-font-family' => $button['typography']['fontFamily'] ?? '',
            '--amrf-button-font-weight' => $button['typography']['fontWeight'] ?? '',
            '--amrf-button-border-radius' => $button['border']['radius'] ?? '',
            '--amrf-button-shadow' => $button['shadow'] ?? '',
            '--amrf-button-hover-background' => $button[':hover']['color']['background'] ?? '',
        ]);
    }

    /**
     * @return array<string, string>
     */
    public static function getSettings(): array
    {
        $stored = get_option(self::OPTION_NAME, []);
        $settings = wp_parse_args(is_array($stored) ? $stored : [], self::getDefaults());

        // A stored '' isn't a real choice here — nothing clears a color
        // picker — so treat it as unset.
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
     * WordPress's own "Discourage search engines" setting (core's
     * blog_public option, not one of this plugin's fields) — checked here
     * since core's noindex tag alone doesn't stop this plugin's own OG/
     * Twitter/JSON-LD output from still being scraped.
     *
     * @return bool
     */
    public static function isSearchEngineDiscouraged(): bool
    {
        return '0' === (string) get_option('blog_public', '1');
    }

    /**
     * All four tabs share this one option but each submits only its own
     * fields — start from current stored values and only touch keys this
     * submission actually included, or every other tab's fields get
     * silently blanked.
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
                // A checkbox is absent from $_POST when unchecked — the
                // "{$key}_submitted" marker (Provider::renderCheckboxField())
                // disambiguates that from "tab not submitted".
                if (is_array($input) && array_key_exists($key . '_submitted', $input)) {
                    $output[$key] = !empty($input[$key]) ? '1' : '';
                }
                continue;
            }

            if ($type === 'page_list') {
                // Same _submitted-marker fix as checkbox — a
                // deselect-everything save must actually clear the list.
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
                // Invalid values (possible via direct update_option()/REST
                // calls) fall back to what's already stored.
                'color' => preg_match('/^#[0-9a-f]{6}$/i', $value) ? $value : $output[$key],
                default => sanitize_text_field($value),
            };
        }

        return $output;
    }

    /**
     * Copies ptsussis-theme's site-settings option over on first
     * activation. No-ops if this plugin's option already holds data or the
     * legacy option doesn't exist.
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
