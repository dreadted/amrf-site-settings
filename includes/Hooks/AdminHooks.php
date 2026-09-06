<?php

namespace Antropomorf\Hooks;

use Antropomorf\Admin\MenuManager;
use Antropomorf\Admin\SettingsPage;
use Antropomorf\Admin\SiteSettingsMenu;
use Antropomorf\Utilities\SettingsRenderer;
use Antropomorf\Utilities\VersionHelper;
use Antropomorf\Settings\Manager as SettingsManager;
use Antropomorf\Settings\Repository;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class AdminHooks
 *
 * Sets up activation hook and admin-specific WordPress hooks for menu and settings integration.
 *
 * @package Antropomorf\Hooks
 */
class AdminHooks
{
    /**
     * Register activation hook and add WordPress action/filter hooks for admin.
     *
     * @return void
     */
    public static function register(): void
    {
        register_activation_hook(AMRF_ADMIN_PLUGIN_FILE, [Repository::class, 'activate']);
        add_action('plugins_loaded', [self::class, 'init']);
        add_filter(
            'plugin_action_links_' . plugin_basename(AMRF_ADMIN_PLUGIN_FILE),
            [self::class, 'getSettingsLink']
        );
        add_filter(
            'admin_footer_text',
            [self::class, 'showVersionInFooter']
        );
    }

    /**
     * Initialize admin hooks: scan menus, setup settings manager and renderers.
     *
     * @return void
     */
    public static function init(): void
    {
        $roles = wp_roles()->roles;
        $menuManager = new MenuManager($roles);
        $menuManager->scan();

        $settingsManager = new SettingsManager(
            $roles,
            $menuManager->getMenuItems()
        );
        // Priority 5 so General/role tabs always sort first.
        add_filter('amrf_admin_settings_tabs', [$settingsManager, 'registerTabs'], 5);

        $renderer = new SettingsRenderer('amrf_admin_settings_tabs', 'amrf-admin-settings', __('Admin Panel Settings', 'amrf-admin'));

        // Must instantiate before SettingsPage: its add_menu_page() call
        // populates $admin_page_hooks for 'amrf-site-settings', which
        // SettingsPage's add_submenu_page() needs to compute the right hook
        // suffix.
        new SiteSettingsMenu();
        new SettingsPage($renderer);
    }

    /**
     * Add a Settings link on the Plugins page for this plugin.
     *
     * @param array $links Existing action links.
     * @return array Modified action links including settings.
     */
    public static function getSettingsLink(array $links): array
    {
        $settings_url = menu_page_url('amrf-admin-settings', false);
        array_unshift(
            $links,
            sprintf(
                '<a href="%1$s">%2$s</a>',
                esc_url($settings_url),
                esc_html__('Settings', 'amrf-admin')
            )
        );
        return $links;
    }

    /**
     * Show plugin version in the WordPress admin footer on every page under
     * this plugin's "Site Settings" top-level menu. $screen->parent_base
     * matches the top-level page itself and every one of its submenus.
     *
     * @param string $footer_text Original footer text.
     * @return string Footer text with version appended for this plugin.
     */
    public static function showVersionInFooter(string $footer_text): string
    {
        $screen = get_current_screen();
        if ($screen && $screen->parent_base === SiteSettingsMenu::MENU_SLUG) {
            $version = VersionHelper::getVersion();
            return sprintf(
                '<strong>%s</strong> v%s',
                esc_html__('Site Settings', 'amrf-admin'),
                esc_html($version)
            );
        }
        return $footer_text;
    }
}
