<?php

namespace Antropomorf\Settings;

if (!defined('ABSPATH')) {
    exit;
}

class Manager
{
    private array $roles;
    private array $menuItems;
    private array $adminPages;

    public function __construct(array $roles, array $menuItems, array $adminPages)
    {
        $this->roles = $roles;
        $this->menuItems = $menuItems;
        $this->adminPages = $adminPages;
    }

    public function init(): void
    {
        register_setting(
            'amrf_admin_settings_group',
            Repository::OPTION_NAME,
            [$this, 'sanitize']
        );

        add_settings_section(
            'general_settings_section',
            __('General Settings', AMRF_ADMIN_TEXT_DOMAIN),
            [$this, 'printGeneralSectionInfo'],
            'amrf-admin-settings-general'
        );
        add_settings_field(
            'add_page_editor_link',
            __('Add Page Editor Link', AMRF_ADMIN_TEXT_DOMAIN),
            [$this, 'addPageEditorLinkCallback'],
            'amrf-admin-settings-general',
            'general_settings_section'
        );
        add_settings_field(
            'minimum_password_length',
            __('Minimum Password Length', AMRF_ADMIN_TEXT_DOMAIN),
            [$this, 'minimumPasswordLengthCallback'],
            'amrf-admin-settings-general',
            'general_settings_section'
        );
        add_settings_field(
            'prevent_password_change',
            __('Prevent Non-Admins from Changing Passwords', AMRF_ADMIN_TEXT_DOMAIN),
            [$this, 'preventPasswordChangeCallback'],
            'amrf-admin-settings-general',
            'general_settings_section'
        );
        add_settings_field(
            'hide_application_passwords',
            __('Hide Application Passwords for Non-Admins', AMRF_ADMIN_TEXT_DOMAIN),
            [$this, 'hideApplicationPasswordsCallback'],
            'amrf-admin-settings-general',
            'general_settings_section'
        );
        add_settings_field(
            'remove_admin_bar_items',
            __('Remove Admin Bar Items for Non-Admins', AMRF_ADMIN_TEXT_DOMAIN),
            [$this, 'removeAdminBarItemsCallback'],
            'amrf-admin-settings-general',
            'general_settings_section'
        );
        add_settings_field(
            'remove_dashboard_widgets',
            __('Remove Default Dashboard Widgets', AMRF_ADMIN_TEXT_DOMAIN),
            [$this, 'removeDashboardWidgetsCallback'],
            'amrf-admin-settings-general',
            'general_settings_section'
        );

        foreach ($this->roles as $slug => $info) {
            if ($slug === 'administrator') {
                continue;
            }
            add_settings_section(
                'user_role_section_' . $slug,
                sprintf(__('%s Settings', AMRF_ADMIN_TEXT_DOMAIN), $info['name']),
                [$this, 'printUserRoleSectionInfo'],
                'amrf-admin-settings-' . $slug
            );
            add_settings_field(
                'user_role_' . $slug . '_settings',
                sprintf(__('%s Settings', AMRF_ADMIN_TEXT_DOMAIN), $info['name']),
                [$this, 'userRoleSettingsCallback'],
                'amrf-admin-settings-' . $slug,
                'user_role_section_' . $slug,
                ['role' => $slug]
            );
        }
    }

    public function sanitize($input): array
    {
        $current = get_option(Repository::OPTION_NAME, []);
        $current['add_page_editor_link'] = ! empty($input['add_page_editor_link']);
        if (isset($input['minimum_password_length'])) {
            $current['minimum_password_length'] = absint($input['minimum_password_length']);
        }
        $current['prevent_password_change'] = ! empty($input['prevent_password_change']);
        $current['hide_application_passwords'] = ! empty($input['hide_application_passwords']);
        $current['remove_admin_bar_items'] = ! empty($input['remove_admin_bar_items']);
        $current['remove_dashboard_widgets'] = ! empty($input['remove_dashboard_widgets']);

        if (! empty($input['user_group_settings']) && is_array($input['user_group_settings'])) {
            foreach ($input['user_group_settings'] as $role => $settings) {
                if (! isset($current['user_group_settings'][$role])) {
                    $current['user_group_settings'][$role] = [];
                }
                if (isset($settings['login_redirect_url'])) {
                    $current['user_group_settings'][$role]['login_redirect_url'] = esc_url_raw($settings['login_redirect_url']);
                }
                if (isset($settings['admin_default_page'])) {
                    $current['user_group_settings'][$role]['admin_default_page'] = sanitize_text_field($settings['admin_default_page']);
                }
                if (! empty($settings['allowed_menu_items']) && is_array($settings['allowed_menu_items'])) {
                    $current['user_group_settings'][$role]['allowed_menu_items'] = array_map(
                        'sanitize_text_field',
                        $settings['allowed_menu_items']
                    );
                }
            }
        }
        return $current;
    }

    public function printGeneralSectionInfo(): void
    {
        echo '<p>' . esc_html__('Configure general admin panel settings.', AMRF_ADMIN_TEXT_DOMAIN) . '</p>';
    }

    public function printUserRoleSectionInfo(): void
    {
        echo '<p>' . esc_html__('Configure settings for each user role.', AMRF_ADMIN_TEXT_DOMAIN) . '</p>';
    }

    private function renderCheckbox(string $key, string $description): void
    {
        $opts = Repository::getSettings();
        $defaults = Repository::getDefaultSettings();
        $checked = isset($opts[$key]) ? $opts[$key] : $defaults[$key];
        printf(
            '<label class="switch"><input type="checkbox" name="%1$s[%2$s]" value="1" %3$s /><span class="slider round"></span></label><p class="description">%4$s</p>',
            Repository::OPTION_NAME,
            esc_attr($key),
            checked(1, $checked, false),
            esc_html__($description, AMRF_ADMIN_TEXT_DOMAIN)
        );
    }

    public function addPageEditorLinkCallback(): void
    {
        $this->renderCheckbox('add_page_editor_link', 'Adds a link to the front page editor in the admin menu.');
    }

    public function minimumPasswordLengthCallback(): void
    {
        $opts = Repository::getSettings();
        $defaults = Repository::getDefaultSettings();
        $value = $opts['minimum_password_length'] ?? $defaults['minimum_password_length'];
        printf(
            '<input type="number" name="%1$s[minimum_password_length]" value="%2$d" min="8" /><p class="description">%3$s</p>',
            Repository::OPTION_NAME,
            esc_attr($value),
            esc_html__('Minimum required characters for user passwords.', AMRF_ADMIN_TEXT_DOMAIN)
        );
    }

    public function preventPasswordChangeCallback(): void
    {
        $this->renderCheckbox('prevent_password_change', 'Prevents non-admin users from changing their passwords.');
    }

    public function hideApplicationPasswordsCallback(): void
    {
        $this->renderCheckbox('hide_application_passwords', 'Hides application passwords section for non-admin users.');
    }

    public function removeAdminBarItemsCallback(): void
    {
        $this->renderCheckbox('remove_admin_bar_items', 'Removes comments and new content links from admin bar for non-admins.');
    }

    public function removeDashboardWidgetsCallback(): void
    {
        $this->renderCheckbox('remove_dashboard_widgets', 'Removes default dashboard widgets (Activity, Quick Draft, etc.).');
    }

    public function userRoleSettingsCallback(array $args): void
    {
        $role = $args['role'];
        $settings = Repository::getSettings()['user_group_settings'][$role] ?? Repository::getDefaultSettings()['user_group_settings'][$role] ?? [];
        echo '<div class="user-role-settings">';
        echo '<div class="setting-row"><h4>' . esc_html__('Login Redirect', AMRF_ADMIN_TEXT_DOMAIN) . '</h4>';
        $redirect = $settings['login_redirect_url'] ?? Repository::getDefaultSettings()['user_group_settings'][$role]['login_redirect_url'] ?? '';
        echo '<select name="' . Repository::OPTION_NAME . '[user_group_settings][' . esc_attr($role) . '][login_redirect_url]">';
        echo '<option value="">' . esc_html__('-- Select Redirect URL --', AMRF_ADMIN_TEXT_DOMAIN) . '</option>';
        echo '<option value="/" ' . selected($redirect, '/', false) . '>' . esc_html__('-- Front Page --', AMRF_ADMIN_TEXT_DOMAIN) . '</option>';
        foreach ($this->menuItems[$role]['menu_items'] as $item) {
            $page = (strpos($item['slug'], 'php') === false) ? esc_attr('admin.php?page=' . $item['slug']) : esc_attr($item['slug']);
            printf('<option value="%1$s" %2$s>%3$s</option>', $page, selected($redirect, $page, false), esc_html($item['name']));
        }
        echo '</select><p class="description">' . esc_html__('URL to redirect this user role to after login.', AMRF_ADMIN_TEXT_DOMAIN) . '</p></div>';
        echo '<div class="setting-row"><h4>' . esc_html__('Default Admin Page', AMRF_ADMIN_TEXT_DOMAIN) . '</h4>';
        $defaultPage = $settings['admin_default_page'] ?? Repository::getDefaultSettings()['user_group_settings'][$role]['admin_default_page'] ?? '';
        $allowed = $settings['allowed_menu_items'] ?? Repository::getDefaultSettings()['user_group_settings'][$role]['allowed_menu_items'] ?? [];
        $filtered = array_filter($this->menuItems[$role]['menu_items'], fn($item) => in_array($item['slug'], $allowed, true));
        echo '<select name="' . Repository::OPTION_NAME . '[user_group_settings][' . esc_attr($role) . '][admin_default_page]">';
        echo '<option value="">' . esc_html__('-- Select Default Page --', AMRF_ADMIN_TEXT_DOMAIN) . '</option>';
        foreach ($filtered as $item) {
            $page = (strpos($item['slug'], 'php') === false) ? esc_attr('admin.php?page=' . $item['slug']) : esc_attr($item['slug']);
            printf('<option value="%1$s" %2$s>%3$s</option>', $page, selected($defaultPage, $page, false), esc_html($item['name']));
        }
        echo '</select><p class="description">' . esc_html__('Default page this user role sees when accessing /wp-admin/ (must be one of the allowed menu items below).', AMRF_ADMIN_TEXT_DOMAIN) . '</p></div>';
        echo '<div class="setting-row"><h4>' . esc_html__('Allowed Menu Items', AMRF_ADMIN_TEXT_DOMAIN) . '</h4><div class="menu-items-container">';
        foreach ($this->menuItems[$role]['menu_items'] as $item) {
            $checked = in_array($item['slug'], $allowed, true) ? 'checked' : '';
            printf(
                '<div class="menu-item-checkbox"><input type="checkbox" name="%1$s[user_group_settings][%2$s][allowed_menu_items][]" value="%3$s" %4$s /><label>%5$s <code>%3$s</code></label></div>',
                Repository::OPTION_NAME,
                esc_attr($role),
                esc_attr($item['slug']),
                $checked,
                esc_html($item['name'])
            );
        }
        echo '</div><p class="description">' . esc_html__('Select which admin menu items should be visible to this user role.', AMRF_ADMIN_TEXT_DOMAIN) . '</p></div>';
        echo '</div>';
    }
}
