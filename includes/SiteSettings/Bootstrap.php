<?php

namespace Antropomorf\SiteSettings;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Bootstrap
 *
 * Entry point for the Site Settings module, called once from amrf-admin.php
 * alongside Hooks\AdminHooks::register()/FrontendHooks::register().
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
