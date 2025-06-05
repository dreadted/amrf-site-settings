<?php

namespace Antropomorf\Hooks;

use Antropomorf\Admin\MenuManager;
use Antropomorf\Admin\SettingsPage;
use Antropomorf\Utilities\SettingsRenderer;
use Antropomorf\Settings\Manager as SettingsManager;
use Antropomorf\Settings\Repository;

if (!defined('ABSPATH')) {
    exit;
}

class AdminHooks
{
    public static function register(): void
    {
        register_activation_hook(AMRF_ADMIN_PLUGIN_FILE, [Repository::class, 'activate']);
        add_action('plugins_loaded', [self::class, 'init']);
    }

    public static function init(): void
    {
        $roles = wp_roles()->roles;
        $menuManager = new MenuManager($roles);
        $menuManager->scan();

        $settingsManager = new SettingsManager(
            $roles,
            $menuManager->getMenuItems(),
            $menuManager->getAdminPages()
        );

        $renderer = new SettingsRenderer();

        new SettingsPage($settingsManager, $renderer);
    }
}
