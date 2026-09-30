<?php

namespace Antropomorf\SiteSettings;

use Antropomorf\Utilities\CachePurge;

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
        new SeoFrameworkIntegration();
        new SeoOutput();
        new Favicons();
        new LoginBranding();
        new SettingShortcode();
        new ContactLinkShortcodes();

        CachePurge::onOptionChange(Repository::OPTION_NAME);
        CachePurge::onOptionChange('blog_public');
    }
}
