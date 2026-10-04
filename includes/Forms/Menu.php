<?php

namespace Antropomorf\Forms;

use Antropomorf\Utilities\SettingsRenderer;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Registers the "Forms" page shell onto amrf_site_settings_pages (see
 * Admin\SiteSettingsMenu) — knows nothing about Swish or Contact Forms
 * specifically, only that some tabs exist on amrf_forms_tabs.
 *
 * @package Antropomorf\Forms
 */
class Menu
{
	/** Stored in each role's allowed_menu_items, so a rename hides the page until that role's tab is saved again. */
	public const PAGE_SLUG = 'amrf-site-settings-forms';

	private const TABS_FILTER = 'amrf_forms_tabs';

	public function __construct()
	{
		add_filter('amrf_site_settings_pages', [$this, 'registerPage']);
	}

	/**
	 * @param array $pages Pages registered so far by other callbacks on this filter.
	 * @return array Pages with 'forms' appended.
	 */
	public function registerPage(array $pages): array
	{
		$pages['forms'] = [
			'page_title' => __('Forms', 'amrf-admin'),
			'menu_title' => __('Forms', 'amrf-admin'),
			'capability' => 'edit_theme_options',
			'menu_slug' => self::PAGE_SLUG,
			'render' => [$this, 'render'],
			'register' => fn() => SettingsRenderer::registerSettings(self::TABS_FILTER),
		];

		return $pages;
	}

	/**
	 * Renders its own tab strip via SettingsRenderer instead of
	 * SiteSettingsMenu's default plain-heading fallback.
	 *
	 * @return void
	 */
	public function render(): void
	{
		(new SettingsRenderer(self::TABS_FILTER, self::PAGE_SLUG, fn() => __('Forms', 'amrf-admin')))->render();
	}
}
