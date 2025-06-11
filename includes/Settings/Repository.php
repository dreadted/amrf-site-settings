<?php

namespace Antropomorf\Settings;

if (!defined('ABSPATH')) {
    exit;
}

class Repository
{
    public const OPTION_NAME = 'amrf_admin_settings';

    public static function getDefaultSettings(): array
    {
        return [
            'add_page_editor_link' => true,
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
                        '#builder_active',
                        'fluent_forms',
                        'nav-menus.php',
                        'profile.php',
                        'rank-math',
                        'support-tickets',
                        'umami-analytics',
                        'upload.php',
                    ],
                    'rank_math_all_caps' => false,
                    'site_menus_cap' => false,
                ],
            ],
        ];
    }

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

    public static function activate(): void
    {
        $current = get_option(self::OPTION_NAME, []);
        $merged = self::recursiveMergeMissing(self::getDefaultSettings(), $current);
        update_option(self::OPTION_NAME, $merged);
    }

    public static function getSettings(): array
    {
        return get_option(self::OPTION_NAME, self::getDefaultSettings());
    }

    public static function updateSettings(array $settings): void
    {
        update_option(self::OPTION_NAME, $settings);
    }
}
