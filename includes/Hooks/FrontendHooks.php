<?php

namespace Antropomorf\Hooks;

use Antropomorf\Settings\Repository;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class FrontendHooks
 *
 * Sets up frontend and admin_init hooks to apply role-based settings on pages and dashboards.
 *
 * @package Antropomorf\Hooks
 */
class FrontendHooks
{
	/**
	 * Register frontend hook to initialize role-based settings on init.
	 *
	 * @return void
	 */
	public static function register(): void
	{
		add_action('init', [self::class, 'init']);
	}

	/**
	 * Initialize frontend hooks based on saved settings.
	 *
	 * @return void
	 */
	public static function init(): void

	{
		add_filter('auth_cookie', function ($cookie, $user_id, $expiration, $scheme, $token) {
			set_transient('user_' . $user_id . '_logging_in', true, 60);
			return $cookie;
		}, 10, 5);

		if (! is_user_logged_in()) {
			return;
		}

		$settings = Repository::getSettings();

		if (!empty($settings['add_page_editor_link'])) {
			add_action('admin_menu', [self::class, 'addCustomPageToMenu']);
		}

		if (!empty($settings['remove_dashboard_widgets'])) {
			add_action('wp_dashboard_setup', [self::class, 'removeDashboardWidgets']);
		}

		if (!empty($settings['minimum_password_length'])) {
			add_action('user_profile_update_errors', [self::class, 'enforcePasswordLengthOnProfileUpdate'], 10, 3);
			add_action('password_reset', [self::class, 'enforcePasswordLengthOnReset'], 10, 2);
		}

		if (!empty($settings['prevent_password_change'])) {
			add_filter('show_password_fields', [self::class, 'filterShowPasswordFields'], 10, 2);
			add_filter('allow_password_reset', [self::class, 'filterAllowPasswordReset'], 10, 2);
		}

		if (!empty($settings['hide_application_passwords'])) {
			add_action('admin_init', [self::class, 'hideApplicationPasswords']);
		}

		if (!empty($settings['remove_admin_bar_items'])) {
			add_action('wp_before_admin_bar_render', [self::class, 'removeAdminBarItems'], 999);
		}

		// Unconditional — the selector only matches FluentForm's own admin
		// pages, so it's a no-op elsewhere.
		add_action('admin_head', [self::class, 'hideFluentFormsHeaderForNonAdmins']);

		// Also unconditional, same reasoning: current_user_can() checks
		// below make it a no-op for admins/actual form managers.
		add_action('admin_init', [self::class, 'redirectFluentFormsMainPageForNonManagers']);

		if (!empty($settings['user_group_settings'])) {
			$user_group_settings = $settings['user_group_settings'];

			add_action('admin_init', function () use ($user_group_settings) {
				if (!current_user_can('administrator')) {
					remove_action('admin_notices', 'update_nag', 3);

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

					self::setCapabilities($user, $user_group_settings, 'site_menus_cap');
					self::setCapabilities($user, $user_group_settings, 'fluentform_entries_access');
				}
			});

			add_action('admin_menu', function () use ($user_group_settings) {
				if (!current_user_can('administrator')) {
					global $menu, $submenu;
					$user = wp_get_current_user();
					$allowed = [];
					foreach ($user->roles as $role) {
						if (isset($user_group_settings[$role]['allowed_menu_items'])) {
							$allowed = $user_group_settings[$role]['allowed_menu_items'];
							break;
						}
					}

					$matches = function ($slug) use ($allowed) {
						$path = strtok($slug, '?');
						foreach ($allowed as $a) {
							if ($slug === $a || $path === $a) {
								return true;
							}
						}
						return false;
					};

					// Filter submenu items first, so parents with an allowed child can be kept.
					$parents_with_allowed_child = [];
					if (!is_null($submenu) && is_array($submenu)) {
						foreach ($submenu as $parent => $items) {
							foreach ($items as $key => $item) {
								if ($matches($item[2])) {
									$parents_with_allowed_child[$parent] = true;
								} else {
									unset($submenu[$parent][$key]);
								}
							}
						}
					}

					foreach ($menu as $key => $item) {
						$slug = $item[2];
						$keep = $matches($slug) || !empty($parents_with_allowed_child[$slug]);
						if (!$keep) {
							unset($menu[$key]);
							unset($submenu[$slug]);
						}
					}
				}
			}, 999);
		}
	}

	/**
	 * Add a custom front page editor link to the admin menu for non-admin users.
	 *
	 * @return void
	 */
	public static function addCustomPageToMenu(): void
	{
		$front = get_option('page_on_front');
		$page = get_post($front);
		if ($page) {
			$settings = Repository::getSettings();
			$defaults = Repository::getDefaultSettings();
			$target = $settings['page_editor_link_target'] ?? $defaults['page_editor_link_target'];
			$url = home_url($target);
			add_menu_page(
				'themify-editor',
				__('Page Editor', 'amrf-admin'),
				'edit_posts',
				$url,
				'',
				'dashicons-edit',
				6
			);
		}
	}

	/**
	 * Remove default dashboard widgets for non-admin users.
	 *
	 * @return void
	 */
	public static function removeDashboardWidgets(): void
	{
		remove_meta_box('dashboard_activity', 'dashboard', 'normal');
		remove_meta_box('dashboard_right_now', 'dashboard', 'normal');
		remove_meta_box('dashboard_quick_press', 'dashboard', 'side');
		remove_meta_box('dashboard_recent_comments', 'dashboard', 'normal');
		remove_meta_box('dashboard_primary', 'dashboard', 'side');
		remove_meta_box('dashboard_secondary', 'dashboard', 'side');
	}

	/**
	 * Enforce minimum password length on profile update for non-admins.
	 *
	 * @param \WP_Error $errors Validation errors object.
	 * @param bool      $update Whether this is an existing user being updated.
	 * @param mixed     $user   WP_User object or user ID.
	 * @return \WP_Error Modified errors object after validation.
	 */
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
						__('ERROR: Password must be at least %d characters long.', 'amrf-admin'),
						$min
					)
				);
			}
		}

		return $errors;
	}

	/**
	 * Enforce minimum password length on password reset for non-admins.
	 *
	 * @param mixed  $user     WP_User object or user ID.
	 * @param string $new_pass The new password being set.
	 * @return void
	 */
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
					__('ERROR: Password must be at least %d characters long.', 'amrf-admin'),
					$min
				));
			}
		}
	}

	/**
	 * Filter display of password fields in profile for non-admin users.
	 *
	 * @param bool      $show         Whether to show password fields.
	 * @param \WP_User $profile_user The user object for the profile being edited.
	 * @return bool False for non-admins, original value for admins.
	 */
	public static function filterShowPasswordFields($show, $profile_user)
	{
		return in_array('administrator', $profile_user->roles, true) ? $show : false;
	}

	/**
	 * Filter the ability to reset password for non-admin users.
	 *
	 * @param bool $allow   Whether password reset is allowed.
	 * @param int  $user_id ID of the user.
	 * @return bool False for non-admins, original value for admins.
	 */
	public static function filterAllowPasswordReset($allow, $user_id)
	{
		$user = get_userdata($user_id);
		return in_array('administrator', $user->roles, true) ? $allow : false;
	}

	/**
	 * Hide application passwords functionality for non-admin users.
	 *
	 * @return void
	 */
	public static function hideApplicationPasswords(): void
	{
		if (!current_user_can('administrator')) {
			remove_action('application_passwords_section', ['WP_Application_Passwords', 'render_section']);
			remove_action('application_passwords', ['WP_Application_Passwords', 'render_app_passwords']);
			add_filter('wp_is_application_passwords_available', '__return_false');
		}
	}

	/**
	 * Remove all admin bar items except specific allowed ones for non-admin users.
	 *
	 * @return void
	 */
	public static function removeAdminBarItems(): void
	{
		if (!current_user_can('administrator')) {
			global $wp_admin_bar;

			$nodes = $wp_admin_bar->get_nodes();
			if (empty($nodes)) {
				return;
			}

			$allowed = [
				'menu-toggle',
				'wp-logo',
				'site-name',
				'view-site',
				'themify_builder',
				'top-secondary',
				'my-account',
				'user-actions',
				'user-info',
				'logout'
			];

			foreach ($nodes as $node) {
				if (!in_array($node->id, $allowed, true)) {
					$wp_admin_bar->remove_node($node->id);
				}
			}
		}
	}

	/**
	 * Hides FluentForm's header bar for non-admins, so a role scoped down
	 * to just Entries isn't shown navigation it can't open.
	 *
	 * @return void
	 */
	public static function hideFluentFormsHeaderForNonAdmins(): void
	{
		if (current_user_can('administrator')) {
			return;
		}

		echo '<style>#wpbody-content > div.ff_header { display: none; }</style>' . "\n";
	}

	/**
	 * FluentForm registers its top-level "Fluent Forms" menu item and its
	 * own "Forms" submenu under the exact same slug (fluent_forms) and
	 * capability (fluentform_dashboard_access) — hiding "Forms" from the
	 * visible menu (allowed_menu_items) doesn't stop a user who only has
	 * entries access from reaching it directly, since clicking the
	 * top-level sidebar label — or just typing the URL — lands on that
	 * same page. fluentform_dashboard_access itself can't be revoked
	 * either: FluentForm's own menu registration bails out entirely
	 * without it, taking the (wanted) Entries page down with it. Redirect
	 * away from this one specific page instead, for anyone who can see it
	 * but isn't an actual form manager.
	 *
	 * @return void
	 */
	public static function redirectFluentFormsMainPageForNonManagers(): void
	{
		if (!is_admin() || ($_GET['page'] ?? '') !== 'fluent_forms') {
			return;
		}

		if (current_user_can('administrator') || current_user_can('fluentform_forms_manager')) {
			return;
		}

		if (!current_user_can('fluentform_dashboard_access')) {
			// No FluentForm access at all — let WP show its own permission error.
			return;
		}

		wp_safe_redirect(admin_url('admin.php?page=fluent_forms_all_entries'));
		exit;
	}

	/**
	 * Retrieve a specific user-group setting for a user or return default.
	 *
	 * @param mixed $user               WP_User object or user ID.
	 * @param array $user_group_settings All user-group settings.
	 * @param string $key               Setting key to retrieve.
	 * @param mixed $default            Default value if setting not found.
	 * @return mixed Value of the setting or default.
	 */
	private static function getUserSetting($user, $user_group_settings, $key, $default = null)
	{
		foreach ($user->roles as $role) {
			if (isset($user_group_settings[$role][$key])) {
				return $user_group_settings[$role][$key];
			}
		}
		return $default;
	}

	/**
	 * Get list of capabilities corresponding to a specific settings key.
	 *
	 * @param string $key Capability key identifier.
	 * @return array List of capability slugs.
	 */
	private static function getCapabilities(string $key): array
	{
		$capabilities =  [
			'site_menus_cap' => ['edit_theme_options'],
			// Just enough for the Entries page — no forms_manager/settings_manager.
			'fluentform_entries_access' => [
				'fluentform_dashboard_access',
				'fluentform_entries_viewer',
				'fluentform_manage_entries',
			],
		];

		return $capabilities[$key] ?? [];
	}

	/**
	 * Grant or revoke capabilities for a user based on user-group settings.
	 *
	 * @param mixed  $user               WP_User object or user ID.
	 * @param array  $user_group_settings All user-group settings.
	 * @param string $key                Capability settings key.
	 * @return void
	 */
	private static function setCapabilities($user, array $user_group_settings, string $key): void
	{
		$enabled =  self::getUserSetting($user, $user_group_settings, $key) ?? false;
		$capabilities = self::getCapabilities($key);

		if ($enabled) {
			foreach ($capabilities as $cap) {
				$user->add_cap($cap);
			}
		} else {
			foreach ($capabilities as $cap) {
				$user->remove_cap($cap);
			}
		}
	}
}
