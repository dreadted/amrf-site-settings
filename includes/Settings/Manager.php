<?php

namespace Antropomorf\Settings;

if (!defined('ABSPATH')) {
	exit;
}

use Antropomorf\Utilities\MenuScanner;

/**
 * Class Manager
 *
 * Handles registration, sanitization, and rendering callbacks for plugin settings.
 *
 * @package Antropomorf\Settings
 */
class Manager
{
	private array $roles;
	private array $menuItems;

	/**
	 * Manager constructor.
	 *
	 * @param array $roles     List of user roles.
	 * @param array $menuItems Menu items indexed by role.
	 */
	public function __construct(array $roles, array $menuItems)
	{
		$this->roles = $roles;
		$this->menuItems = $menuItems;
	}

	/**
	 * Initialize settings: register settings and add settings sections/fields.
	 *
	 * @return void
	 */
	public function init(): void
	{
		register_setting(
			'amrf_admin_settings_group',
			Repository::OPTION_NAME,
			[$this, 'sanitize']
		);

		add_settings_section(
			'general_settings_section',
			__('General Settings', 'amrf-admin'),
			[$this, 'printGeneralSectionInfo'],
			'amrf-admin-settings-general'
		);
		add_settings_field(
			'add_page_editor_link',
			__('Add Page Editor Link', 'amrf-admin'),
			[$this, 'addPageEditorLinkCallback'],
			'amrf-admin-settings-general',
			'general_settings_section'
		);
		add_settings_field(
			'minimum_password_length',
			__('Minimum Password Length', 'amrf-admin'),
			[$this, 'minimumPasswordLengthCallback'],
			'amrf-admin-settings-general',
			'general_settings_section'
		);
		add_settings_field(
			'prevent_password_change',
			__('Prevent Non-Admins from Changing Passwords', 'amrf-admin'),
			[$this, 'preventPasswordChangeCallback'],
			'amrf-admin-settings-general',
			'general_settings_section'
		);
		add_settings_field(
			'hide_application_passwords',
			__('Hide Application Passwords for Non-Admins', 'amrf-admin'),
			[$this, 'hideApplicationPasswordsCallback'],
			'amrf-admin-settings-general',
			'general_settings_section'
		);
		add_settings_field(
			'remove_admin_bar_items',
			__('Remove Admin Bar Items for Non-Admins', 'amrf-admin'),
			[$this, 'removeAdminBarItemsCallback'],
			'amrf-admin-settings-general',
			'general_settings_section'
		);
		add_settings_field(
			'remove_dashboard_widgets',
			__('Remove Default Dashboard Widgets', 'amrf-admin'),
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
				sprintf(__('%s Settings', 'amrf-admin'), translate_user_role($info['name'])),
				[$this, 'printUserRoleSectionInfo'],
				'amrf-admin-settings-' . $slug
			);
			add_settings_field(
				'user_role_' . $slug . '_settings',
				sprintf(__('%s Settings', 'amrf-admin'), $info['name']),
				[$this, 'userRoleSettingsCallback'],
				'amrf-admin-settings-' . $slug,
				'user_role_section_' . $slug,
				['role' => $slug]
			);
		}
	}

	/**
	 * Sanitize settings input before saving to the database.
	 *
	 * @param array $input Raw input values from settings form.
	 * @return array Sanitized settings array.
	 */
	public function sanitize($input): array
	{
		$current = get_option(Repository::OPTION_NAME, []);
		$current_tab = $_POST['current_tab'] ?? 'general';

		if ($current_tab === 'general') {
			// Process only general settings

			$current['add_page_editor_link'] = ! empty($input['add_page_editor_link']);
			if (isset($input['minimum_password_length'])) {
				$current['minimum_password_length'] = absint($input['minimum_password_length']);
			}
			$current['prevent_password_change'] = ! empty($input['prevent_password_change']);
			$current['hide_application_passwords'] = ! empty($input['hide_application_passwords']);
			$current['remove_admin_bar_items'] = ! empty($input['remove_admin_bar_items']);
			$current['remove_dashboard_widgets'] = ! empty($input['remove_dashboard_widgets']);
		} elseif (array_key_exists($current_tab, $this->roles)) {

			$role = $current_tab;

			// Initialize role settings if they don't exist
			if (!isset($current['user_group_settings'][$role])) {
				$current['user_group_settings'][$role] = $this->default_settings['user_group_settings'][$role] ?? [];
			}

			// Only process settings for the current role
			if (!empty($input['user_group_settings'][$role])) {
				$role_settings = $input['user_group_settings'][$role];

				if (isset($role_settings['login_redirect_url'])) {
					$current['user_group_settings'][$role]['login_redirect_url'] = esc_url_raw($role_settings['login_redirect_url']);
				}

				if (isset($role_settings['admin_default_page'])) {
					$current['user_group_settings'][$role]['admin_default_page'] = sanitize_text_field($role_settings['admin_default_page']);
				}

				if (!empty($role_settings['allowed_menu_items']) && is_array($role_settings['allowed_menu_items'])) {
					$current['user_group_settings'][$role]['allowed_menu_items'] = array_map('sanitize_text_field', $role_settings['allowed_menu_items']);
				} else $current['user_group_settings'][$role]['allowed_menu_items'] = [];

				$current['user_group_settings'][$role]['rank_math_all_caps'] = isset($role_settings['rank_math_all_caps']);

				$current['user_group_settings'][$role]['site_menus_cap'] = isset($role_settings['site_menus_cap']);
			}
		}
		return $current;
	}

	/**
	 * Output introductory text for the general settings section.
	 *
	 * @return void
	 */
	public function printGeneralSectionInfo(): void
	{
		echo '<p>' . esc_html__('Configure general admin panel settings.', 'amrf-admin') . '</p>';
	}

	/**
	 * Output introductory text for a user role settings section.
	 *
	 * @return void
	 */
	public function printUserRoleSectionInfo(): void
	{
		echo '<p>' . esc_html__('Configure settings for each user role.', 'amrf-admin') . '</p>';
	}



	/**
	 * Callback to render the 'Add Page Editor Link' checkbox.
	 *
	 * @return void
	 */
	public function addPageEditorLinkCallback(): void
	{
		$this->renderCheckbox('add_page_editor_link', esc_html__('Adds a link to the front page editor in the admin menu.', 'amrf-admin'));
	}

	/**
	 * Callback to render the minimum password length field.
	 *
	 * @return void
	 */
	public function minimumPasswordLengthCallback(): void
	{
		$settings = Repository::getSettings();
		$defaults = Repository::getDefaultSettings();
		$value = $settings['minimum_password_length'] ?? $defaults['minimum_password_length'];
		printf(
			'<input type="number" name="%1$s[minimum_password_length]" value="%2$d" min="8" /><p class="description">%3$s</p>',
			Repository::OPTION_NAME,
			esc_attr($value),
			esc_html__('Minimum required characters for user passwords.', 'amrf-admin')
		);
	}

	/**
	 * Callback to render the 'Prevent Non-Admins from Changing Passwords' checkbox.
	 *
	 * @return void
	 */
	public function preventPasswordChangeCallback(): void
	{
		$this->renderCheckbox('prevent_password_change', esc_html__('Prevents non-admin users from changing their passwords.', 'amrf-admin'));
	}

	/**
	 * Callback to render the 'Hide Application Passwords' checkbox.
	 *
	 * @return void
	 */
	public function hideApplicationPasswordsCallback(): void
	{
		$this->renderCheckbox('hide_application_passwords', esc_html__('Hides application passwords section for non-admin users.', 'amrf-admin'));
	}

	/**
	 * Callback to render the 'Remove Admin Bar Items' checkbox.
	 *
	 * @return void
	 */
	public function removeAdminBarItemsCallback(): void
	{
		$this->renderCheckbox('remove_admin_bar_items', esc_html__('Removes comments and new content links from admin bar for non-admins.', 'amrf-admin'));
	}

	/**
	 * Callback to render the 'Remove Dashboard Widgets' checkbox.
	 *
	 * @return void
	 */
	public function removeDashboardWidgetsCallback(): void
	{
		$this->renderCheckbox('remove_dashboard_widgets', esc_html__('Removes default dashboard widgets (Activity, Quick Draft, etc.).', 'amrf-admin'));
	}

	/**
	 * Callback to render settings fields for a specific user role.
	 *
	 * @param array $args Arguments containing 'role' key for the user role slug.
	 * @return void
	 */
	public function userRoleSettingsCallback(array $args): void
	{
		// Rescan menu items and admin pages each time the settings page renders
		$this->menuItems  = MenuScanner::scanMenuItems($this->roles);
		// $this->adminPages = MenuScanner::scanAdminPages();

		$role = $args['role'];

		$settings = Repository::getSettings()['user_group_settings'][$role] ?? Repository::getDefaultSettings()['user_group_settings'][$role] ?? [];
		echo '<div class="user-role-settings">';

		// Login Redirect
		echo '<div class="setting-row"><h4>' . esc_html__('Login Redirect', 'amrf-admin') . '</h4>';
		$redirect = $settings['login_redirect_url'] ?? Repository::getDefaultSettings()['user_group_settings'][$role]['login_redirect_url'] ?? '';
		echo '<select name="' . Repository::OPTION_NAME . '[user_group_settings][' . esc_attr($role) . '][login_redirect_url]">';
		echo '<option value="">' . esc_html__('-- Select Redirect URL --', 'amrf-admin') . '</option>';
		echo '<option value="/" ' . selected($redirect, '/', false) . '>' . esc_html__('-- Front Page --', 'amrf-admin') . '</option>';
		foreach ($this->menuItems[$role]['menu_items'] as $item) {
			$page = (strpos($item['slug'], 'php') === false) ? esc_attr('admin.php?page=' . $item['slug']) : esc_attr($item['slug']);
			printf('<option value="%1$s" %2$s>%3$s</option>', $page, selected($redirect, $page, false), esc_html($item['name']));
		}
		echo '</select><p class="description">' . esc_html__('URL to redirect this user role to after login.', 'amrf-admin') . '</p></div>';

		// Default Admin Page
		echo '<div class="setting-row"><h4>' . esc_html__('Default Admin Page', 'amrf-admin') . '</h4>';
		$defaultPage = $settings['admin_default_page'] ?? Repository::getDefaultSettings()['user_group_settings'][$role]['admin_default_page'] ?? '';
		$allowed = $settings['allowed_menu_items'] ?? Repository::getDefaultSettings()['user_group_settings'][$role]['allowed_menu_items'] ?? [];
		$filtered = array_filter($this->menuItems[$role]['menu_items'], fn($item) => in_array($item['slug'], $allowed, true));
		echo '<select name="' . Repository::OPTION_NAME . '[user_group_settings][' . esc_attr($role) . '][admin_default_page]">';
		echo '<option value="">' . esc_html__('-- Select Default Page --', 'amrf-admin') . '</option>';
		foreach ($filtered as $item) {
			$page = (strpos($item['slug'], 'php') === false) ? esc_attr('admin.php?page=' . $item['slug']) : esc_attr($item['slug']);
			printf('<option value="%1$s" %2$s>%3$s</option>', $page, selected($defaultPage, $page, false), esc_html($item['name']));
		}
		echo '</select><p class="description">' . esc_html__('Default page this user role sees when accessing /wp-admin/ (must be one of the allowed menu items below).', 'amrf-admin') . '</p></div>';

		// Rank Math Capabilities 
		echo '<div class="setting-row"><h4>' . esc_html__('Rank Math Access', 'amrf-admin') . '</h4>';
		$this->renderCheckbox('rank_math_all_caps', esc_html__('When enabled, this role will have access to all Rank Math features.', 'amrf-admin'), ['user_group_settings', $role]);
		echo '</div>';

		// Site Menus Capability
		echo '<div class="setting-row"><h4>' . esc_html__('Site Menus Access', 'amrf-admin') . '</h4>';
		$this->renderCheckbox('site_menus_cap', esc_html__('Enable to allow this user role to access and edit site menus.', 'amrf-admin'), ['user_group_settings', $role]);
		echo '</div>';

		// Allowed Menu Items
		echo '<div class="setting-row"><h4>' . esc_html__('Allowed Menu Items', 'amrf-admin') . '</h4><div class="menu-items-container">';
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
		echo '</div><p class="description">' . esc_html__('Select which admin menu items should be visible to this user role.', 'amrf-admin') . '</p></div>';
		echo '</div>';
	}

	/**
	 * Render a toggle checkbox input for a setting.
	 *
	 * @param string $key         Setting key name.
	 * @param string $description Description text for the checkbox.
	 * @param array  $path        Optional path segments for nested settings.
	 * @return void
	 */
	private function renderCheckbox(string $key, string $description, array $path = []): void
	{
		$settings = Repository::getSettings();
		$defaults = Repository::getDefaultSettings();

		// Get the value by traversing the path if provided
		$value = $this->getNestedValue($settings, $path, $key);
		$default_value = $this->getNestedValue($defaults, $path, $key);

		$checked = $value ?? $default_value ?? false;

		// Build the name attribute
		$name = Repository::OPTION_NAME;
		if (!empty($path)) {
			$name .= '[' . implode('][', array_map('esc_attr', $path)) . ']';
		}
		$name .= '[' . esc_attr($key) . ']';

		printf(
			'<label class="switch"><input type="checkbox" name="%s" value="1" %s /><span class="slider round"></span></label><p class="description">%s</p>',
			$name,
			checked(1, $checked, false),
			$description
		);
	}

	/**
	 * Retrieve a nested value from an array using a path and key.
	 *
	 * @param array  $array The source array to search.
	 * @param array  $path  List of keys defining the nested path.
	 * @param string $key   The key of the desired value at the path.
	 * @return mixed|null The value if found, null otherwise.
	 */
	private function getNestedValue(array $array, array $path, string $key)
	{
		$current = $array;

		foreach ($path as $segment) {
			if (!isset($current[$segment])) {
				return null;
			}
			$current = $current[$segment];
		}

		return $current[$key] ?? null;
	}
}
