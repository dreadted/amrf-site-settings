<?php

namespace Antropomorf\Admin;

use Antropomorf\Utilities\SettingsRenderer;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * The top-level "Site Settings" menu: tabs share one page, amrf_site_settings_pages entries get their own submenus.
 * edit_theme_options, not manage_options, so roles granted site_menus_cap in RolePolicy can reach it.
 *
 * @package Antropomorf\Admin
 */
class SiteSettingsMenu
{
	private const CAPABILITY = 'edit_theme_options';

	/** Public so sibling Admin classes can reference this slug directly. */
	public const MENU_SLUG = 'amrf-site-settings';

	private const TABS_FILTER = 'amrf_site_settings_tabs';
	private const PAGES_FILTER = 'amrf_site_settings_pages';

	private SettingsRenderer $renderer;

	public function __construct()
	{
		$this->renderer = new SettingsRenderer(self::TABS_FILTER, self::MENU_SLUG, fn() => __('Site Settings', 'amrf-admin'));

		add_action('admin_menu', [$this, 'addMenu']);
		add_action('admin_init', [$this, 'registerSettings']);
		add_action('admin_enqueue_scripts', [$this, 'enqueueStyles']);
	}

	/**
	 * Registers the top-level menu plus its default (tabbed) submenu page,
	 * then every module's own page from amrf_site_settings_pages.
	 *
	 * @return void
	 */
	public function addMenu(): void
	{
		add_menu_page(
			__('Site Settings', 'amrf-admin'),
			__('Site Settings', 'amrf-admin'),
			self::CAPABILITY,
			self::MENU_SLUG,
			[$this->renderer, 'render'],
			'dashicons-admin-generic',
			80
		);

		// Same slug as the parent — WordPress collapses this into the parent's
		// own link instead of adding a duplicate entry.
		add_submenu_page(
			self::MENU_SLUG,
			__('Site Settings', 'amrf-admin'),
			__('Site Settings', 'amrf-admin'),
			self::CAPABILITY,
			self::MENU_SLUG,
			[$this->renderer, 'render']
		);

		foreach (apply_filters(self::PAGES_FILTER, []) as $page) {
			add_submenu_page(
				self::MENU_SLUG,
				$page['page_title'],
				$page['menu_title'],
				$page['capability'] ?? self::CAPABILITY,
				$page['menu_slug'],
				function () use ($page) {
					$this->renderPage($page);
				}
			);
		}
	}

	/**
	 * Default renderer for an amrf_site_settings_pages entry; a module with custom markup passes its own 'render'.
	 *
	 * @param array $page One amrf_site_settings_pages entry.
	 * @return void
	 */
	private function renderPage(array $page): void
	{
		if (!empty($page['render']) && is_callable($page['render'])) {
			call_user_func($page['render']);
			return;
		}

		echo '<div class="wrap">';
		echo '<h1>' . esc_html($page['page_title']) . '</h1>';
		settings_errors();
		SettingsRenderer::renderSettingsForm($page['option_group'], $page['page_slug'], !empty($page['show_reset']));
		echo '</div>';
	}

	/**
	 * Loads the switch and role-panel styles on this menu's own pages only; their class names are too generic for anywhere else.
	 *
	 * @return void
	 */
	public function enqueueStyles(): void
	{
		global $plugin_page, $submenu;

		$slugs = wp_list_pluck($submenu[self::MENU_SLUG] ?? [], 2);
		if (!$plugin_page || !in_array($plugin_page, $slugs, true)) {
			return;
		}

		wp_enqueue_style(
			'amrf-admin-settings',
			AMRF_ADMIN_PLUGIN_URL . 'assets/css/amrf-admin-settings.css',
			[],
			filemtime(AMRF_ADMIN_PLUGIN_DIR . '/assets/css/amrf-admin-settings.css')
		);
	}

	/**
	 * Calls every registered tab's and page's own 'register' callback
	 * (register_setting/add_settings_section/add_settings_field), on admin_init.
	 *
	 * @return void
	 */
	public function registerSettings(): void
	{
		SettingsRenderer::registerSettings(self::TABS_FILTER);
		SettingsRenderer::registerSettings(self::PAGES_FILTER);
	}
}
