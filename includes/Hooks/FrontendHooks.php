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

        // Add link to page editor if enabled
        if (!empty($settings['add_page_editor_link'])) {
            add_action('admin_menu', [self::class, 'addCustomPageToMenu']);
        }

        // Remove default dashboard widgets if enabled
        if (!empty($settings['remove_dashboard_widgets'])) {
            add_action('wp_dashboard_setup', [self::class, 'removeDashboardWidgets']);
        }

        // Password length enforcement
        if (!empty($settings['minimum_password_length'])) {
            add_action('user_profile_update_errors', [self::class, 'enforcePasswordLengthOnProfileUpdate'], 10, 3);
            add_action('password_reset', [self::class, 'enforcePasswordLengthOnReset'], 10, 2);
        }

        // Prevent password changes for non-admins if enabled
        if (!empty($settings['prevent_password_change'])) {
            add_filter('show_password_fields', [self::class, 'filterShowPasswordFields'], 10, 2);
            add_filter('allow_password_reset', [self::class, 'filterAllowPasswordReset'], 10, 2);
        }

        // Hide application passwords for non-admins if enabled
        if (!empty($settings['hide_application_passwords'])) {
            add_action('admin_init', [self::class, 'hideApplicationPasswords']);
        }

        // Remove admin bar items for non-admins if enabled
        if (!empty($settings['remove_admin_bar_items'])) {
            add_action('wp_before_admin_bar_render', [self::class, 'removeAdminBarItems']);
        }

        // Handle role specific settings
        if (!empty($settings['user_group_settings'])) {
            $user_group_settings = $settings['user_group_settings'];

            // Login redirect URL and Admin default page
            add_filter('auth_cookie', function ($cookie, $user_id, $expiration, $scheme, $token) {
                set_transient('user_' . $user_id . '_logging_in', true, 60);
                return $cookie;
            }, 10, 5);

            add_action('admin_init', function () use ($user_group_settings) {
                if (!current_user_can('administrator')) {
                    $user = wp_get_current_user();
                    $transient_key = 'user_' . $user->ID . '_logging_in';
                    $is_logging_in = get_transient($transient_key);

                    if ($is_logging_in) {
                        delete_transient($transient_key);
                        $login_redirect_url = self::getUserSetting($user, $user_group_settings, 'login_redirect_url');
                        if ($login_redirect_url) {
                            $redirect = (strpos($login_redirect_url, '.php') === false) ? home_url($login_redirect_url) : admin_url($login_redirect_url);
                            wp_safe_redirect($redirect);
                            exit;
                        }
                    } else {
                        $admin_default_page = self::getUserSetting($user, $user_group_settings, 'admin_default_page');
                        if ($admin_default_page && get_admin_url() === home_url($_SERVER['REQUEST_URI'])) {
                            wp_safe_redirect(admin_url($admin_default_page));
                            exit;
                        }
                    }
                    self::setCapabilities($user, $user_group_settings, 'rank_math_all_caps');
                    self::setCapabilities($user, $user_group_settings, 'site_menus_cap');
                }
            });



            add_action('admin_menu', function () use ($user_group_settings) {
                if (!current_user_can('administrator')) {
                    global $menu;
                    $user = wp_get_current_user();
                    $allowed = [];
                    foreach ($user->roles as $role) {
                        if (isset($user_group_settings[$role]['allowed_menu_items'])) {
                            $allowed = $user_group_settings[$role]['allowed_menu_items'];
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

    public static function enforcePasswordLengthOnProfileUpdate(
        \WP_Error $errors,
        bool $update,
        $user
    ): \WP_Error {
        if (current_user_can('administrator')) {
            return $errors;
        }

        $settings = Repository::getSettings();
        if (!empty($settings['minimum_password_length'])) {
            $min = absint($settings['minimum_password_length']);

            if (!empty($_POST['pass1']) && strlen($_POST['pass1']) < $min) {
                $errors->add(
                    'pass',
                    sprintf(
                        //  translators: %d is the number indicating minimum password length
                        __('ERROR: Password must be at least %d characters long.', AMRF_ADMIN_TEXT_DOMAIN),
                        $min
                    )
                );
            }
        }

        return $errors;
    }

    public static function enforcePasswordLengthOnReset(
        $user,
        string $new_pass
    ): void {
        if (current_user_can('administrator')) {
            return;
        }

        $settings = Repository::getSettings();
        if (!empty($settings['minimum_password_length'])) {
            $min = absint($settings['minimum_password_length']);

            if (strlen($new_pass) < $min) {
                wp_die(sprintf(
                    //  translators: %d is the number indicating minimum password length
                    __('ERROR: Password must be at least %d characters long.', AMRF_ADMIN_TEXT_DOMAIN),
                    $min
                ));
            }
        }
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

    private static function getUserSetting($user, $user_group_settings, $key, $default = null)
    {
        foreach ($user->roles as $role) {
            if (isset($user_group_settings[$role][$key])) {
                return $user_group_settings[$role][$key];
            }
        }
        return $default;
    }

    private static function getCapabilities(string $key): array
    {
        $capabilities =  [
            'rank_math_all_caps' => [
                'rank_math_site_analysis',
                'rank_math_onpage_analysis',
                'rank_math_onpage_general',
                'rank_math_onpage_snippet',
                'rank_math_onpage_social',
                'rank_math_titles',
                'rank_math_general',
                'rank_math_sitemap',
                'rank_math_404_monitor',
                'rank_math_link_builder',
                'rank_math_redirections',
                'rank_math_role_manager',
                'rank_math_analytics',
                'rank_math_onpage_advanced',
                'rank_math_content_ai',
                'rank_math_admin_bar',
            ],
            'site_menus_cap' => ['edit_theme_options']
        ];

        return $capabilities[$key] ?? [];
    }

    private static function setCapabilities($user, array $user_group_settings, string $key): void
    {
        $enabled =  self::getUserSetting($user, $user_group_settings, $key) ?? false;
        $capabilities = self::getCapabilities($key);

        if ($enabled) {
            foreach ($capabilities as $cap) {
                $user->add_cap($cap);
                // error_log('Adding ' . $cap . ' for ' . $user->user_login);
            }
        } else {
            foreach ($capabilities as $cap) {
                $user->remove_cap($cap);
                // error_log('Removing ' . $cap . ' for ' . $user->user_login);
            }
        }


        // error_log($key . ' is ' . ($enabled ? 'enabled' : 'disabled'));
        // error_log(print_r($capabilities, true));
    }
}
