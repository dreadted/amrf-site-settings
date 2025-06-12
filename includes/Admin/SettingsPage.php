<?php

namespace Antropomorf\Admin;

use Antropomorf\Settings\Manager;
use Antropomorf\Utilities\SettingsRenderer;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class SettingsPage
 *
 * Registers the settings page and enqueues assets for the admin panel settings.
 *
 * @package Antropomorf\Admin
 */
class SettingsPage
{
    private $manager;
    private $renderer;

    /**
     * SettingsPage constructor.
     *
     * @param Manager          $manager  Settings manager instance.
     * @param SettingsRenderer $renderer Renderer for the settings page.
     */
    public function __construct(Manager $manager, SettingsRenderer $renderer)
    {
        $this->manager = $manager;
        $this->renderer = $renderer;
        add_action('admin_menu', [$this, 'addAdminMenu']);
        add_action('admin_init', [$this->manager, 'init']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
    }

    /**
     * Register the options page under the Settings menu in the admin dashboard.
     *
     * @return void
     */
    public function addAdminMenu()
    {
        add_options_page(
            __('Admin Panel Settings', AMRF_ADMIN_TEXT_DOMAIN),
            __('Admin Panel Settings', AMRF_ADMIN_TEXT_DOMAIN),
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
        if ('settings_page_amrf-admin-settings' !== $hook) {
            return;
        }

        wp_enqueue_style(
            'amrf-admin-settings',
            AMRF_ADMIN_PLUGIN_URL . 'assets/css/amrf-admin-settings.css'
        );
    }
}
