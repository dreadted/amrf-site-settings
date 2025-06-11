<?php

namespace Antropomorf\Hooks;

use Antropomorf\Admin\MenuManager;
use Antropomorf\Admin\SettingsPage;
use Antropomorf\Utilities\SettingsRenderer;
use Antropomorf\Utilities\VersionHelper;
use Antropomorf\Settings\Manager as SettingsManager;
use Antropomorf\Settings\Repository;

if (!defined('ABSPATH')) {
    exit;
}

class AdminHooks
{
    public static function register(): void
    {
        register_activation_hook( AMRF_ADMIN_PLUGIN_FILE, [ Repository::class, 'activate' ] );
        add_action( 'plugins_loaded', [ self::class, 'init' ] );
        add_filter(
            'plugin_action_links_' . plugin_basename( AMRF_ADMIN_PLUGIN_FILE ),
            [ self::class, 'settings_link' ]
        );
        add_filter(
            'admin_footer_text',
            [ self::class, 'show_version_in_footer' ]
        );
    }

    public static function init(): void
    {
        $roles = wp_roles()->roles;
        $menuManager = new MenuManager($roles);
        $menuManager->scan();

        $settingsManager = new SettingsManager(
            $roles,
            $menuManager->getMenuItems()
        );

        $renderer = new SettingsRenderer();

        new SettingsPage($settingsManager, $renderer);
    }

    public static function settings_link( array $links ): array
    {
        $settings_url = admin_url( 'options-general.php?page=amrf-admin-settings' );
        array_unshift(
            $links,
            sprintf(
                '<a href="%1$s">%2$s</a>',
                esc_url( $settings_url ),
                esc_html__( 'Settings', AMRF_ADMIN_TEXT_DOMAIN )
            )
        );
        return $links;
    }

    public static function show_version_in_footer( string $footer_text ): string
    {
        $screen = get_current_screen();
        if ( $screen && 'settings_page_amrf-admin-settings' === $screen->id ) {
            $version = VersionHelper::getVersion( AMRF_ADMIN_PLUGIN_FILE );
            return sprintf(
                '<strong>%s</strong> v%s',
                esc_html__( 'Admin Panel Settings', AMRF_ADMIN_TEXT_DOMAIN ),
                esc_html( $version )
            );
        }
        return $footer_text;
    }
}
