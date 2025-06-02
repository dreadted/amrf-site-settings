<?php

namespace Antropomorf\Hooks;

use Antropomorf\Settings\Repository;

if (!defined('ABSPATH')) {
    exit;
}

class FrontendHooks
{
    public static function register(): void
    {
        add_action('init', [self::class, 'init']);
    }

    public static function init(): void
    {
        $settings = Repository::getSettings();

        if (!empty($settings['add_page_editor_link'])) {
            add_action('admin_menu', [self::class, 'addCustomPageToMenu']);
        }

        if (!empty($settings['remove_dashboard_widgets'])) {
            add_action('wp_dashboard_setup', [self::class, 'removeDashboardWidgets']);
        }

        if (!empty($settings['minimum_password_length'])) {
            $min = absint($settings['minimum_password_length']);
            add_action(
                'user_profile_update_errors',
                function ($errors, $update, $user) use ($min) {
                    if (!empty($_POST['pass1']) && strlen($_POST['pass1']) < $min) {
                        $errors->add(
                            'pass',
                            sprintf(
                                __('ERROR: Password must be at least %d characters long.', AMRF_ADMIN_TEXT_DOMAIN),
                                $min
                            )
                        );
                    }
                    return $errors;
                },
                10,
                3
            );
            add_action(
                'password_reset',
                function ($user, $new_pass) use ($min) {
                    if (strlen($new_pass) < $min) {
                        wp_die(sprintf(
                            __('ERROR: Password must be at least %d characters long.', AMRF_ADMIN_TEXT_DOMAIN),
                            $min
                        ));
                    }
                },
                10,
                2
            );
        }

        if (!empty($settings['prevent_password_change'])) {
            add_filter('show_password_fields', [self::class, 'filterShowPasswordFields'], 10, 2);
            add_filter('allow_password_reset', [self::class, 'filterAllowPasswordReset'], 10, 2);
        }

        if (!empty($settings['hide_application_passwords'])) {
            add_action('admin_init', [self::class, 'hideApplicationPasswords']);
        }

        if (!empty($settings['remove_admin_bar_items'])) {
            add_action('wp_before_admin_bar_render', [self::class, 'removeAdminBarItems']);
        }

        if (!empty($settings['user_group_settings'])) {
            $ugs = $settings['user_group_settings'];
            add_filter(
                'login_redirect',
                function ($redirect_to, $request, $user) use ($ugs) {
                    if (is_wp_error($user) || empty($user->roles)) {
                        return $redirect_to;
                    }
                    foreach ((array) $user->roles as $role) {
                        if (isset($ugs[$role]['login_redirect_url'])) {
                            $url = $ugs[$role]['login_redirect_url'];
                            return strpos($url, 'php') === false ? home_url() : '/wp-admin/' . $url;
                        }
                    }
                    return $redirect_to;
                },
                10,
                3
            );
            add_action('admin_init', function () use ($ugs) {
                if (!current_user_can('administrator')) {
                    $user = wp_get_current_user();
                    foreach ($user->roles as $role) {
                        if (isset($ugs[$role]['admin_default_page'])) {
                            $default = $ugs[$role]['admin_default_page'];
                            if (!empty($default) && get_admin_url() === home_url($_SERVER['REQUEST_URI'])) {
                                wp_safe_redirect(admin_url($default));
                                exit;
                            }
                            break;
                        }
                    }
                }
            });
            add_action('admin_menu', function () use ($ugs) {
                if (!current_user_can('administrator')) {
                    global $menu;
                    $user = wp_get_current_user();
                    $allowed = [];
                    foreach ($user->roles as $role) {
                        if (isset($ugs[$role]['allowed_menu_items'])) {
                            $allowed = $ugs[$role]['allowed_menu_items'];
                            break;
                        }
                    }
                    foreach ($menu as $key => $item) {
                        $slug = $item[2];
                        $keep = false;
                        foreach ($allowed as $a) {
                            if (strpos($slug, $a) !== false) {
                                $keep = true;
                                break;
                            }
                        }
                        if (!$keep) {
                            unset($menu[$key]);
                        }
                    }
                }
            }, 999);
        }
    }

    public static function addCustomPageToMenu(): void
    {
        $front = get_option('page_on_front');
        $page = get_post($front);
        if ($page) {
            $url = home_url('/#builder_active');
            add_menu_page(
                'themify-editor',
                __('Page Editor', AMRF_ADMIN_TEXT_DOMAIN),
                'edit_posts',
                $url,
                '',
                'dashicons-edit',
                6
            );
        }
    }

    public static function removeDashboardWidgets(): void
    {
        remove_meta_box('dashboard_activity', 'dashboard', 'normal');
        remove_meta_box('dashboard_right_now', 'dashboard', 'normal');
        remove_meta_box('dashboard_quick_press', 'dashboard', 'side');
        remove_meta_box('dashboard_recent_comments', 'dashboard', 'normal');
        remove_meta_box('dashboard_primary', 'dashboard', 'side');
        remove_meta_box('dashboard_secondary', 'dashboard', 'side');
    }

    public static function filterShowPasswordFields($show, $profile_user)
    {
        return in_array('administrator', $profile_user->roles, true) ? $show : false;
    }

    public static function filterAllowPasswordReset($allow, $user_id)
    {
        $user = get_userdata($user_id);
        return in_array('administrator', $user->roles, true) ? $allow : false;
    }

    public static function hideApplicationPasswords(): void
    {
        if (!current_user_can('administrator')) {
            remove_action('application_passwords_section', ['WP_Application_Passwords', 'render_section']);
            remove_action('application_passwords', ['WP_Application_Passwords', 'render_app_passwords']);
            add_filter('wp_is_application_passwords_available', '__return_false');
        }
    }

    public static function removeAdminBarItems(): void
    {
        if (!current_user_can('administrator')) {
            global $wp_admin_bar;
            $wp_admin_bar->remove_menu('comments');
            $wp_admin_bar->remove_menu('new-content');
        }
    }
}