<?php

namespace Antropomorf\Settings;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class Repository
 *
 * Storage/defaults for the plugin's own General + per-role settings.
 *
 * RECONSTRUCTED (2026-09-04) after an accidental `rm -rf` deleted the
 * working copy — this file was never read in full during the session that
 * was lost (only referenced via grep for its OPTION_NAME and via how
 * Hooks\FrontendHooks/Settings\Manager consume its fields), so this is an
 * inferred-from-usage rebuild, NOT a verbatim restoration. Please review
 * against the field list you actually expect before relying on it.
 *
 * @package Antropomorf\Settings
 */
class Repository
{
	public const OPTION_NAME = 'amrf_admin_settings';

	/**
	 * @return array
	 */
	public static function getDefaultSettings(): array
	{
		$roles = function_exists('wp_roles') ? wp_roles()->roles : [];
		$user_group_settings = [];
		foreach ($roles as $slug => $info) {
			if ($slug === 'administrator') {
				continue;
			}
			$user_group_settings[$slug] = [
				'login_redirect_url' => '',
				'admin_default_page' => '',
				'allowed_menu_items' => [],
				'rank_math_all_caps' => false,
				'site_menus_cap' => false,
			];
		}

		return [
			'add_page_editor_link' => false,
			'minimum_password_length' => '',
			'prevent_password_change' => false,
			'hide_application_passwords' => false,
			'remove_admin_bar_items' => false,
			'remove_dashboard_widgets' => false,
			'user_group_settings' => $user_group_settings,
		];
	}

	/**
	 * @return array
	 */
	public static function getSettings(): array
	{
		$stored = get_option(self::OPTION_NAME, []);
		$stored = is_array($stored) ? $stored : [];
		return wp_parse_args($stored, self::getDefaultSettings());
	}

	/**
	 * @param array $settings
	 * @return void
	 */
	public static function updateSettings(array $settings): void
	{
		update_option(self::OPTION_NAME, $settings);
	}

	/**
	 * Runs on plugin activation.
	 *
	 * @return void
	 */
	public static function activate(): void
	{
		$existing = get_option(self::OPTION_NAME, []);
		if (empty($existing)) {
			update_option(self::OPTION_NAME, self::getDefaultSettings());
		}
	}
}
