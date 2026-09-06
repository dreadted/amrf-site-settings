<?php

namespace Antropomorf\SiteSettings;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Entry point for the Site Settings module.
 *
 * @package Antropomorf\SiteSettings
 */
class Bootstrap
{
    public static function register(): void
    {
        register_activation_hook(AMRF_ADMIN_PLUGIN_FILE, [Repository::class, 'migrateFromThemeIfNeeded']);

        new Provider();
        new SeoOutput();
    }
}
