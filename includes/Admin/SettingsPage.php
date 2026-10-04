<?php

namespace Antropomorf\Admin;

use Antropomorf\Utilities\SettingsRenderer;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Registers the "Admin Panel Settings" page. Tabs register
 * themselves onto the amrf_admin_settings_tabs filter; this class just
 * renders whatever's there.
 *
 * @package Antropomorf\Admin
 */
class SettingsPage
{
	private $renderer;

	/**
	 * SettingsPage constructor.
	 *
	 * @param SettingsRenderer $renderer Renderer for the settings page.
	 */
	public function __construct(SettingsRenderer $renderer)
	{
		$this->renderer = $renderer;
		add_action('admin_menu', [$this, 'addAdminMenu']);
		add_action('admin_init', fn() => SettingsRenderer::registerSettings('amrf_admin_settings_tabs'));
	}

	/**
	 * Registers as a submenu under the plugin's own "Site Settings" menu,
	 * administrators only.
	 *
	 * @return void
	 */
	public function addAdminMenu()
	{
		add_submenu_page(
			SiteSettingsMenu::MENU_SLUG,
			__('Admin Panel Settings', 'amrf-admin'),
			__('Admin Panel Settings', 'amrf-admin'),
			'manage_options',
			'amrf-admin-settings',
			[$this->renderer, 'render']
		);
	}
}
