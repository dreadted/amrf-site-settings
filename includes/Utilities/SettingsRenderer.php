<?php

namespace Antropomorf\Utilities;

use Antropomorf\Settings\Repository;

if (!defined('ABSPATH')) {
    exit;
}

class SettingsRenderer
{
    public function render(): void
    {
        $roles = wp_roles()->roles;
        unset($roles['administrator']);

        $tabs = ['general' => __('General', AMRF_ADMIN_TEXT_DOMAIN)];
        foreach ($roles as $slug => $info) {
            $tabs[$slug] = $info['name'];
        }
        $current_tab = isset($_GET['tab'], $tabs[$_GET['tab']]) ? $_GET['tab'] : 'general';

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Admin Panel Settings', AMRF_ADMIN_TEXT_DOMAIN) . '</h1>';
        echo '<h2 class="nav-tab-wrapper">';
        foreach ($tabs as $tab => $label) {
            printf(
                '<a href="%1$s" class="nav-tab %2$s">%3$s</a>',
                esc_url(add_query_arg(['page' => 'amrf-admin-settings', 'tab' => $tab], admin_url('options-general.php'))),
                $current_tab === $tab ? 'nav-tab-active' : '',
                esc_html($label)
            );
        }
        echo '</h2>';
        echo '<form method="post" action="options.php">';
        settings_fields('amrf_admin_settings_group');
        do_settings_sections('amrf-admin-settings-' . $current_tab);
        submit_button();
        echo '</form>';
        echo '</div>';
    }
}
