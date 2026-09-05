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
        // Priority 5, not the default 10 — General/role tabs should always
        // sort first regardless of which order other modules' Bootstrap
        // classes happen to run in (this callback itself only fires on
        // plugins_loaded, later than a module that hooks the filter
        // synchronously from amrf-site-settings.php would).
        add_filter('amrf_admin_settings_tabs', [$settingsManager, 'registerTabs'], 5);

        $renderer = new SettingsRenderer('amrf_admin_settings_tabs', 'amrf-admin-settings', __('Admin Panel Settings', 'amrf-admin'));

        // SiteSettingsMenu first: its admin_menu callback calls add_menu_page()
        // for 'amrf-site-settings', which populates WordPress's own
        // $admin_page_hooks global for that slug. SettingsPage's admin_menu
        // callback then calls add_submenu_page() under that same slug — if it
        // ran first, get_plugin_page_hookname() would compute the wrong hook
        // suffix (falls back to a generic 'admin_page_' prefix instead of the
        // real one), since $admin_page_hooks wouldn't have an entry for the
        // parent yet. Same-priority admin_menu callbacks fire in registration
        // order, so instantiation order here is what actually decides this.
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
     * Show plugin version in the WordPress admin footer on the settings page.
     *
     * @param string $footer_text Original footer text.
     * @return string Footer text with version appended for this plugin.
     */
    public static function showVersionInFooter(string $footer_text): string
    {
        $screen = get_current_screen();
        $hook = get_plugin_page_hookname('amrf-admin-settings', SiteSettingsMenu::MENU_SLUG);
        if ($screen && $hook === $screen->id) {
            $version = VersionHelper::getVersion();
            return sprintf(
                '<strong>%s</strong> v%s',
                esc_html__('Admin Panel Settings', 'amrf-admin'),
                esc_html($version)
            );
        }
        return $footer_text;
    }
}
