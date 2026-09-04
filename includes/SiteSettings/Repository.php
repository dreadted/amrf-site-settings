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
            'theme_color' => [__('Theme color (hex)', 'amrf-admin'), 'text', 'seo'],
            'background_color' => [__('Background color (hex)', 'amrf-admin'), 'text', 'seo'],

            'business_name' => [__('Business name', 'amrf-admin'), 'text', 'business'],
            'business_type' => [__('Business type (schema.org)', 'amrf-admin'), 'text', 'business'],
            'person_name' => [__('Person name', 'amrf-admin'), 'text', 'business'],
            'job_title' => [__('Job title', 'amrf-admin'), 'text', 'business'],
            'email' => [__('Email', 'amrf-admin'), 'email', 'business'],
            'phone' => [__('Phone', 'amrf-admin'), 'text', 'business'],
            // Swish is a nationwide Swedish payment scheme, not specific to
            // any one business that uses it — see Swish::buildUrl().
            'swish_number' => [__('Swish number', 'amrf-admin'), 'text', 'business'],
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
            'x_url' => [__('X (Twitter) URL', 'amrf-admin'), 'url', 'social'],
            'x_handle' => [__('X (Twitter) handle', 'amrf-admin'), 'text', 'social'],
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
     * @return array<string, string> Every field defaulted to an empty string.
     */
    public static function getDefaults(): array
    {
        return array_fill_keys(array_keys(self::getFields()), '');
    }

    /**
     * @return array<string, string>
     */
    public static function getSettings(): array
    {
        $stored = get_option(self::OPTION_NAME, []);
        return wp_parse_args(is_array($stored) ? $stored : [], self::getDefaults());
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
        return !empty(self::getSettings()['enable_seo_output']);
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

            if (!is_array($input) || !array_key_exists($key, $input)) {
                continue;
            }

            $value = (string) $input[$key];

            $output[$key] = match ($type) {
                'email' => sanitize_email($value),
                'url', 'media' => esc_url_raw($value),
                'textarea' => sanitize_textarea_field($value),
                'number' => (string) absint($value),
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
