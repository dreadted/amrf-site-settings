<?php

namespace Antropomorf\Admin;

use Antropomorf\Settings\Manager;
use Antropomorf\Utilities\SettingsRenderer;

if (!defined('ABSPATH')) {
    exit;
}

class SettingsPage
{
    private $manager;
    private $renderer;

    public function __construct(Manager $manager, SettingsRenderer $renderer)
    {
        $this->manager = $manager;
        $this->renderer = $renderer;
        add_action('admin_menu', [$this, 'addAdminMenu']);
        add_action('admin_init', [$this->manager, 'init']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
    }

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

    public function enqueueAssets($hook)
    {
        if ('settings_page_amrf-admin-settings' !== $hook) {
            return;
        }

        wp_enqueue_style(
            'amrf-admin-settings',
            AMRF_ADMIN_PLUGIN_URL . 'assets/css/amrf-admin-settings.css'
        );
        wp_enqueue_script(
            'amrf-admin-settings',
            AMRF_ADMIN_PLUGIN_URL . 'assets/js/amrf-admin-settings.js',
            ['jquery'],
            false,
            true
        );
    }
}