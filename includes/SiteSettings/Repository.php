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
            'theme_color' => [__('Theme color', 'amrf-admin'), 'color', 'seo'],
            'background_color' => [__('Background color', 'amrf-admin'), 'color', 'seo'],

            'business_name' => [__('Business name', 'amrf-admin'), 'text', 'business'],
            'business_type' => [__('Business type (schema.org)', 'amrf-admin'), 'text', 'business'],
            'person_name' => [__('Person name', 'amrf-admin'), 'text', 'business'],
            'job_title' => [__('Job title', 'amrf-admin'), 'text', 'business'],
            'email' => [__('Email', 'amrf-admin'), 'email', 'business'],
            'phone' => [__('Phone', 'amrf-admin'), 'text', 'business'],
            // The Swish number is stored by Swish\Repository.
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
     * Used whenever theme_color/background_color is stored empty, which
     * sanitize() does for a value equal to this default so it keeps
     * following the theme. The theme's --amrf-theme-color/
     * --amrf-background-color CSS properties win; theme.json is the fallback.
     *
     * WP_Theme_JSON_Resolver needs WP 5.8+, past this plugin's 5.6 floor —
     * class_exists guards it.
     *
     * @return array{theme_color: string, background_color: string}
     */
    public static function getThemeDefaultColors(): array
    {
        if (!class_exists('WP_Theme_JSON_Resolver')) {
            return ['theme_color' => '#000000', 'background_color' => '#ffffff'];
        }

        $settings = \WP_Theme_JSON_Resolver::get_merged_data()->get_settings();
        $palette = $settings['color']['palette']['theme'] ?? [];
        $by_slug = array_column($palette, 'color', 'slug');
        $first = $palette[0]['color'] ?? '';
        $css_tokens = ThemeCssTokens::get();

        $theme_color = self::resolveColorReference($css_tokens['--amrf-theme-color'] ?? '', $by_slug)
            ?? $by_slug['primary'] ?? $by_slug['accent-1'] ?? $first;
        $background_color = self::resolveColorReference($css_tokens['--amrf-background-color'] ?? '', $by_slug)
            ?? self::resolveColorReference($settings['custom']['contactFormBackground'] ?? '', $by_slug)
            ?? self::resolveThemeBackgroundColor($by_slug)
            ?? $by_slug['base'] ?? $by_slug['background'] ?? '';

        return [
            'theme_color' => $theme_color !== '' ? $theme_color : '#000000',
            'background_color' => $background_color !== '' ? $background_color : '#ffffff',
        ];
    }

    /**
     * The wp-admin color scheme picked in the viewer's own profile (site
     * admin's if logged out) — a scheme's last two preview colors are its
     * highlight/notification shades, per core's own colors.css.
     *
     * @return array{primary: string, secondary: string}
     */
    public static function getAdminColorSchemeColors(): array
    {
        global $_wp_admin_css_colors;

        // register_admin_color_schemes() only runs on admin_init, so the
        // Support Genix portal on the front end needs it registered here.
        if (empty($_wp_admin_css_colors)) {
            if (!function_exists('register_admin_color_schemes')) {
                return ['primary' => '', 'secondary' => ''];
            }
            register_admin_color_schemes();
        }

        $user_id = get_current_user_id();
        if (!$user_id) {
            $admin_ids = get_users([
                'role' => 'administrator',
                'number' => 1,
                'orderby' => 'ID',
                'order' => 'ASC',
                'fields' => 'ID',
            ]);
            $user_id = $admin_ids[0] ?? 0;
        }
        if (!$user_id) {
            return ['primary' => '', 'secondary' => ''];
        }

        $scheme = get_user_option('admin_color', $user_id);
        if (empty($_wp_admin_css_colors[$scheme])) {
            return ['primary' => '', 'secondary' => ''];
        }

        $shades = array_slice($_wp_admin_css_colors[$scheme]->colors, -2);
        if (count($shades) < 2) {
            return ['primary' => '', 'secondary' => ''];
        }

        return ['primary' => $shades[0], 'secondary' => $shades[1]];
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
     * @return array<string, string>
     */
    public static function getSettings(): array
    {
        $stored = get_option(self::OPTION_NAME, []);
        $settings = wp_parse_args(is_array($stored) ? $stored : [], self::getDefaults());

        // Empty means "follow the theme" (see sanitize()).
        foreach (['theme_color', 'background_color'] as $key) {
            if ($settings[$key] === '') {
                $settings[$key] = self::getThemeDefaultColors()[$key];
            }
        }

        return $settings;
    }

    public static function isSeoOutputEnabled(): bool
    {
        return !empty(self::getSettings()['enable_seo_output']);
    }

    /**
     * Core's blog_public option ("Discourage search engines"), not one of this plugin's fields.
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

        // Also covers other tabs' saves, since $output starts from getSettings()'s resolved defaults.
        foreach (self::getThemeDefaultColors() as $key => $default) {
            if (strcasecmp($output[$key], $default) === 0) {
                $output[$key] = '';
            }
        }

        return $output;
    }
}
