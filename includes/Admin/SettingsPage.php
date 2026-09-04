<?php

namespace Antropomorf\Admin;

use Antropomorf\Utilities\SettingsRenderer;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class SettingsPage
 *
 * Registers the settings page and enqueues assets for the admin panel settings.
 * Knows nothing about which tabs exist or what they store — every tab
 * (this plugin's own General/role tabs included) registers itself onto the
 * amrf_admin_settings_tabs filter; this class just renders whatever's there
 * and, on admin_init, calls each tab's own 'register' callback.
 *
 * @package Antropomorf\Admin
 */
class SettingsPage
{
    private $renderer;

    /**
     * Hook suffix add_submenu_page() actually returns for this page — read
     * back instead of guessed, unlike the string literal this used to
     * compare against (fragile: it depends on the parent slug/menu type,
     * and silently breaks if either ever changes).
     */
    private $hookSuffix;

    /**
     * SettingsPage constructor.
     *
     * @param SettingsRenderer $renderer Renderer for the settings page.
     */
    public function __construct(SettingsRenderer $renderer)
    {
        $this->renderer = $renderer;
        add_action('admin_menu', [$this, 'addAdminMenu']);
        add_action('admin_init', [$this, 'registerTabSettings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
    }

    /**
     * Calls every registered tab's own 'register' callback (register_setting/
     * add_settings_section/add_settings_field for that tab) — the admin_init-
     * timed counterpart to SettingsRenderer::render()'s tab-list read.
     *
     * @return void
     */
    public function registerTabSettings(): void
    {
        foreach (apply_filters('amrf_admin_settings_tabs', []) as $tab) {
            if (!empty($tab['register']) && is_callable($tab['register'])) {
                call_user_func($tab['register']);
            }
        }
    }

    /**
     * Register the settings page as a submenu under the plugin's own "Site
     * Settings" top-level menu (Admin\SiteSettingsMenu) — still
     * manage_options, administrators only, just consolidated under the
     * plugin's own menu instead of WordPress core's "Settings" the way it
     * used to live, so everything this plugin owns is findable in one place.
     *
     * @return void
     */
    public function addAdminMenu()
    {
        $this->hookSuffix = add_submenu_page(
            SiteSettingsMenu::MENU_SLUG,
            __('Admin Panel Settings', 'amrf-admin'),
            __('Admin Panel Settings', 'amrf-admin'),
            'manage_options',
            'amrf-admin-settings',
            [$this->renderer, 'render']
        );
    }

    /**
     * Enqueue styles and scripts for the admin settings page.
     *
     * @param string $hook Current admin page hook suffix.
     * @return void
     */
    public function enqueueAssets($hook)
    {
        if ($this->hookSuffix !== $hook) {
            return;
        }

        wp_enqueue_style(
            'amrf-admin-settings',
            AMRF_ADMIN_PLUGIN_URL . 'assets/css/amrf-admin-settings.css'
        );
    }
}
