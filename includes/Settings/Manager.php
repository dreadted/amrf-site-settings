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
 * RECONSTRUCTED after an accidental `rm -rf` deleted the working copy this
 * file lived in (2026-09-04) — this class predates that incident and was
 * never touched during the session that was lost, so it was rebuilt from
 * partial reads captured earlier in that same conversation rather than from
 * a Write/Edit record. registerTabs()/ensureSettingRegistered()/
 * renderCheckbox() are verbatim (fully read before the loss). The General/
 * role-tab field registration and role-settings UI bodies below are a
 * best-effort reconstruction from those same reads plus the field names
 * Hooks\FrontendHooks::init() consumes (add_page_editor_link,
 * remove_dashboard_widgets, minimum_password_length, prevent_password_change,
 * hide_application_passwords, remove_admin_bar_items, user_group_settings.*)
 * and a screenshot of the rendered General tab — NOT verified against the
 * original byte-for-byte. Please review this file specifically before
 * relying on it in production.
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
	 * Guards register_setting() against being called twice in one request —
	 * General and every role tab share the same option/group, but each now
	 * registers its own settings independently via the amrf_admin_settings_tabs
	 * filter, so both paths can run in the same request.
	 */
	private static bool $settingRegistered = false;

	private function ensureSettingRegistered(): void
	{
		if (self::$settingRegistered) {
			return;
		}
		register_setting(
			'amrf_admin_settings_group',
			Repository::OPTION_NAME,
			[$this, 'sanitize']
		);
		self::$settingRegistered = true;
	}

	/**
	 * Registers this plugin's own General + per-role tabs onto the generic
	 * amrf_admin_settings_tabs filter — proves the registry mechanism against
	 * known, existing behavior before any external module uses it too.
	 *
	 * @param array $tabs Tabs registered so far by other callbacks on this filter.
	 * @return array Tabs with 'general' and each non-administrator role appended.
	 */
	public function registerTabs(array $tabs): array
	{
		$tabs['general'] = [
			'label' => __('General', 'amrf-admin'),
			'option_group' => 'amrf_admin_settings_group',
			'page_slug' => 'amrf-admin-settings-general',
			'show_reset' => true,
			'register' => [$this, 'registerGeneralTab'],
		];

		foreach ($this->roles as $slug => $info) {
			if ($slug === 'administrator') {
				continue;
			}
			$tabs[$slug] = [
				'label' => translate_user_role($info['name']),
				'option_group' => 'amrf_admin_settings_group',
				'page_slug' => 'amrf-admin-settings-' . $slug,
				'show_reset' => true,
				'register' => function () use ($slug, $info) {
					$this->registerRoleTab($slug, $info);
				},
			];
		}

		return $tabs;
	}

	/**
	 * Registers the General tab's own setting/section/fields. Called via this
	 * tab's 'register' callback from the tabs registry, not directly on admin_init.
	 *
	 * @return void
	 */
	public function registerGeneralTab(): void
	{
		$this->ensureSettingRegistered();

		add_settings_section('amrf_admin_settings_general_section', '', '__return_false', 'amrf-admin-settings-general');

		add_settings_field(
			'add_page_editor_link',
			esc_html__('Add Page Editor Link', 'amrf-admin'),
			[$this, 'addPageEditorLinkCallback'],
			'amrf-admin-settings-general',
			'amrf_admin_settings_general_section'
		);
		add_settings_field(
			'minimum_password_length',
			esc_html__('Minimum Password Length', 'amrf-admin'),
			[$this, 'minimumPasswordLengthCallback'],
			'amrf-admin-settings-general',
			'amrf_admin_settings_general_section'
		);
		add_settings_field(
			'prevent_password_change',
			esc_html__('Prevent Non-Admins from Changing Passwords', 'amrf-admin'),
			[$this, 'preventPasswordChangeCallback'],
			'amrf-admin-settings-general',
			'amrf_admin_settings_general_section'
		);
		add_settings_field(
			'hide_application_passwords',
			esc_html__('Hide Application Passwords for Non-Admins', 'amrf-admin'),
			[$this, 'hideApplicationPasswordsCallback'],
			'amrf-admin-settings-general',
			'amrf_admin_settings_general_section'
		);
		add_settings_field(
			'remove_admin_bar_items',
			esc_html__('Remove Admin Bar Items for Non-Admins', 'amrf-admin'),
			[$this, 'removeAdminBarItemsCallback'],
			'amrf-admin-settings-general',
			'amrf_admin_settings_general_section'
		);
		add_settings_field(
			'remove_dashboard_widgets',
			esc_html__('Remove Default Dashboard Widgets', 'amrf-admin'),
			[$this, 'removeDashboardWidgetsCallback'],
			'amrf-admin-settings-general',
			'amrf_admin_settings_general_section'
		);
	}

	/**
	 * Registers one role tab's own setting/section/field. Called via that
	 * tab's 'register' callback from the tabs registry.
	 *
	 * @param string $slug Role slug.
	 * @param array  $info Role info (from wp_roles()->roles).
	 * @return void
	 */
	public function registerRoleTab(string $slug, array $info): void
	{
		$this->ensureSettingRegistered();

		add_settings_section('amrf_admin_settings_role_' . $slug . '_section', '', '__return_false', 'amrf-admin-settings-' . $slug);

		add_settings_field(
			$slug,
			translate_user_role($info['name']),
			function () use ($slug) {
				$this->userRoleSettingsCallback(['role' => $slug]);
			},
			'amrf-admin-settings-' . $slug,
			'amrf_admin_settings_role_' . $slug . '_section'
		);
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
	 * Callback to render the 'Minimum Password Length' field.
	 *
	 * @return void
	 */
	public function minimumPasswordLengthCallback(): void
	{
		$settings = Repository::getSettings();
		$defaults = Repository::getDefaultSettings();
		$value = $settings['minimum_password_length'] ?? $defaults['minimum_password_length'] ?? '';

		printf(
			'<input type="number" min="0" name="%s[minimum_password_length]" value="%s" class="small-text" /><p class="description">%s</p>',
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
	 * Callback to render the 'Hide Application Passwords for Non-Admins' checkbox.
	 *
	 * @return void
	 */
	public function hideApplicationPasswordsCallback(): void
	{
		$this->renderCheckbox('hide_application_passwords', esc_html__('Hides application passwords section for non-admin users.', 'amrf-admin'));
	}

	/**
	 * Callback to render the 'Remove Admin Bar Items for Non-Admins' checkbox.
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
			echo '<option value="' . $page . '" ' . selected($redirect, $page, false) . '>' . esc_html($item['name']) . ' <code>' . esc_html($item['slug']) . '</code></option>';
		}
		echo '</select>';
		echo '</div>';

		// Default Admin Page
		echo '<div class="setting-row"><h4>' . esc_html__('Default Admin Page', 'amrf-admin') . '</h4>';
		$default_page = $settings['admin_default_page'] ?? Repository::getDefaultSettings()['user_group_settings'][$role]['admin_default_page'] ?? '';
		echo '<select name="' . Repository::OPTION_NAME . '[user_group_settings][' . esc_attr($role) . '][admin_default_page]">';
		echo '<option value="">' . esc_html__('-- Select Default Page --', 'amrf-admin') . '</option>';
		foreach ($this->menuItems[$role]['menu_items'] as $item) {
			$page = (strpos($item['slug'], 'php') === false) ? esc_attr('admin.php?page=' . $item['slug']) : esc_attr($item['slug']);
			echo '<option value="' . $page . '" ' . selected($default_page, $page, false) . '>' . esc_html($item['name']) . ' <code>' . esc_html($item['slug']) . '</code></option>';
		}
		echo '</select>';
		echo '</div>';

		// Allowed Menu Items
		echo '<div class="setting-row"><h4>' . esc_html__('Allowed Menu Items', 'amrf-admin') . '</h4>';
		echo '<div class="menu-items-container">';
		$allowed = $settings['allowed_menu_items'] ?? [];
		foreach ($this->menuItems[$role]['menu_items'] as $item) {
			$checked = in_array($item['slug'], $allowed, true);
			echo '<div class="menu-item-checkbox"><label>';
			printf(
				'<input type="checkbox" name="%s[user_group_settings][%s][allowed_menu_items][]" value="%s" %s /> %s <code>%s</code>',
				Repository::OPTION_NAME,
				esc_attr($role),
				esc_attr($item['slug']),
				checked($checked, true, false),
				esc_html($item['name']),
				esc_html($item['slug'])
			);
			echo '</label></div>';
		}
		echo '</div></div>';

		// Rank Math capability passthrough
		echo '<div class="setting-row"><h4>' . esc_html__('Rank Math', 'amrf-admin') . '</h4>';
		$this->renderCheckbox('rank_math_all_caps', esc_html__('Grant this role full Rank Math SEO capabilities.', 'amrf-admin'), ['user_group_settings', $role]);
		echo '</div>';

		// Site menus capability
		echo '<div class="setting-row">';
		$this->renderCheckbox('site_menus_cap', esc_html__('Enable to allow this user role to access and edit site menus.', 'amrf-admin'), ['user_group_settings', $role]);
		echo '</div>';

		echo '</div>';
	}

	/**
	 * @param mixed $input Raw POSTed value for the whole option.
	 * @return array Sanitized settings array.
	 */
	public function sanitize($input): array
	{
		$current = get_option(Repository::OPTION_NAME, []);
		$current = is_array($current) ? $current : [];

		if (isset($_POST['amrf_reset_defaults'])) {
			$current_tab = $_POST['current_tab'] ?? 'general';

			if ($current_tab === 'general') {
				return array_merge($current, [
					'add_page_editor_link' => false,
					'minimum_password_length' => '',
					'prevent_password_change' => false,
					'hide_application_passwords' => false,
					'remove_admin_bar_items' => false,
					'remove_dashboard_widgets' => false,
				]);
			} elseif (array_key_exists($current_tab, $this->roles)) {
				$role = $current_tab;
				$current['user_group_settings'][$role] = [];
				return $current;
			}

			return $current;
		}

		$merged = $current;

		if (isset($input['add_page_editor_link'])) {
			$merged['add_page_editor_link'] = true;
		} elseif (($_POST['current_tab'] ?? '') === 'general') {
			$merged['add_page_editor_link'] = false;
		}

		if (isset($input['minimum_password_length'])) {
			$merged['minimum_password_length'] = absint($input['minimum_password_length']);
		}

		foreach (['prevent_password_change', 'hide_application_passwords', 'remove_admin_bar_items', 'remove_dashboard_widgets'] as $key) {
			if (isset($input[$key])) {
				$merged[$key] = true;
			} elseif (($_POST['current_tab'] ?? '') === 'general') {
				$merged[$key] = false;
			}
		}

		if (isset($input['user_group_settings']) && is_array($input['user_group_settings'])) {
			foreach ($input['user_group_settings'] as $role => $role_settings) {
				if (!array_key_exists($role, $this->roles)) {
					continue;
				}
				$merged['user_group_settings'][$role]['login_redirect_url'] = sanitize_text_field($role_settings['login_redirect_url'] ?? '');
				$merged['user_group_settings'][$role]['admin_default_page'] = sanitize_text_field($role_settings['admin_default_page'] ?? '');
				$merged['user_group_settings'][$role]['allowed_menu_items'] = array_map('sanitize_text_field', $role_settings['allowed_menu_items'] ?? []);
				$merged['user_group_settings'][$role]['rank_math_all_caps'] = isset($role_settings['rank_math_all_caps']);
				$merged['user_group_settings'][$role]['site_menus_cap'] = isset($role_settings['site_menus_cap']);
			}
		}

		return $merged;
	}

	/**
	 * Same .switch/.slider toggle markup used across this plugin's own
	 * settings screens.
	 *
	 * @param string $key
	 * @param string $description
	 * @param array  $path Nested array path under the option, if any (e.g. ['user_group_settings', $role]).
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
	 * @param array $data Settings or defaults array to traverse.
	 * @param array $path Nested keys to walk before reaching $key.
	 * @param string $key Final key to read.
	 * @return mixed|null
	 */
	private function getNestedValue(array $data, array $path, string $key)
	{
		foreach ($path as $segment) {
			if (!isset($data[$segment]) || !is_array($data[$segment])) {
				return null;
			}
			$data = $data[$segment];
		}
		return $data[$key] ?? null;
	}
}
