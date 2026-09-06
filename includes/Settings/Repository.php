<?php

namespace Antropomorf\Settings;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Repository
 *
 * Manages storage, retrieval, and defaulting of plugin settings.
 *
 * @package Antropomorf\Settings
 */
class Repository
{
    public const OPTION_NAME = 'amrf_admin_settings';

    /**
     * Get the default settings for the plugin.
     *
     * @return array Default settings array.
     */
    public static function getDefaultSettings(): array
    {
        return [
            'add_page_editor_link' => false,
            'page_editor_link_target' => '/#builder_active',
            'minimum_password_length' => 20,
            'prevent_password_change' => true,
            'hide_application_passwords' => true,
            'remove_admin_bar_items' => true,
            'remove_dashboard_widgets' => true,
            'user_group_settings' => [
                'editor' => [
                    'login_redirect_url' => '/',
                    'admin_default_page' => 'profile.php',
                    'allowed_menu_items' => [
                        // Full URL, not just the fragment — this menu item's
                        // slug (add_menu_page()'s own $menu_slug argument in
                        // FrontendHooks::addCustomPageToMenu()) IS its target
                        // URL, and allowed_menu_items is matched against the
                        // exact slug. A bare '#builder_active' entry here
                        // would silently never match. Manager::sanitize()'s
                        // syncPageEditorLinkAllowedMenuItem() keeps this in
                        // sync automatically after the initial activation.
                        home_url('/#builder_active'),
                        'fluent_forms',
                        'nav-menus.php',
                        'profile.php',
                        'support-tickets',
                        'umami-analytics',
                        'upload.php',
                    ],
                    'site_menus_cap' => false,
                    'fluentform_entries_access' => false,
                ],
            ],
        ];
    }

    /**
     * Recursively merge missing keys from default settings into current settings.
     *
     * @param array $default Default settings.
     * @param array $current Current settings to merge missing defaults into.
     * @return array Resulting merged settings.
     */
    public static function recursiveMergeMissing(array $default, array $current): array
    {
        foreach ($default as $key => $value) {
            if (! array_key_exists($key, $current)) {
                $current[$key] = $value;
            } elseif (is_array($value) && is_array($current[$key])) {
                $current[$key] = self::recursiveMergeMissing($value, $current[$key]);
            }
        }
        return $current;
    }

    /**
     * Activation hook to initialize the plugin settings in the database.
     *
     * @return void
     */
    public static function activate(): void
    {
        $current = get_option(self::OPTION_NAME, []);
        $merged = self::recursiveMergeMissing(self::getDefaultSettings(), $current);
        update_option(self::OPTION_NAME, $merged);
    }

    /**
     * Retrieve current plugin settings or fallback to default settings.
     *
     * @return array Plugin settings array.
     */
    public static function getSettings(): array
    {
        return get_option(self::OPTION_NAME, self::getDefaultSettings());
    }

    /**
     * Update the plugin settings in the database.
     *
     * @param array $settings Settings array to save.
     * @return void
     */
    public static function updateSettings(array $settings): void
    {
        update_option(self::OPTION_NAME, $settings);
    }
}
