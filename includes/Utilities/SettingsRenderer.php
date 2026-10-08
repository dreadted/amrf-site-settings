<?php

namespace Antropomorf\Utilities;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * One tabbed settings page (nav-tab-wrapper switched by $_GET['tab']), instantiated once per tab group.
 *
 * @package Antropomorf\Utilities
 */
class SettingsRenderer
{
	private string $tabsFilter;
	private string $menuSlug;

	/** @var callable Called in render() so __() follows the viewing user's locale. */
	private $pageTitle;

	/**
	 * @param string   $tabsFilter Filter returning the tabs, each ['label', 'option_group',
	 *                              'page_slug', 'show_reset', 'register'] plus optional 'capability'.
	 * @param string   $menuSlug   This page's admin menu slug.
	 * @param callable $pageTitle  Returns the page heading; called late so it follows the user's locale.
	 */
	public function __construct(string $tabsFilter, string $menuSlug, callable $pageTitle)
	{
		$this->tabsFilter = $tabsFilter;
		$this->menuSlug = $menuSlug;
		$this->pageTitle = $pageTitle;
	}

	public function render(): void
	{
		$tabs = array_filter(
			apply_filters($this->tabsFilter, []),
			fn($tab) => empty($tab['capability']) || current_user_can($tab['capability'])
		);
		if (empty($tabs)) {
			return;
		}

		$requested_tab = isset($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : '';
		$current_tab = isset($tabs[$requested_tab]) ? $requested_tab : array_key_first($tabs);
		$tab = $tabs[$current_tab];

		echo '<div class="wrap">';
		echo '<h1>' . esc_html(call_user_func($this->pageTitle)) . '</h1>';
		// Core prints these only under the Settings menu (options-head.php).
		settings_errors();
		echo '<h2 class="nav-tab-wrapper">';
		foreach ($tabs as $id => $tab_info) {
			printf(
				'<a href="%1$s" class="nav-tab %2$s">%3$s</a>',
				esc_url(add_query_arg('tab', $id, menu_page_url($this->menuSlug, false))),
				$current_tab === $id ? 'nav-tab-active' : '',
				esc_html($tab_info['label'])
			);
		}
		echo '</h2>';

		self::renderSettingsForm($tab['option_group'], $tab['page_slug'], !empty($tab['show_reset']), $current_tab);

		echo '</div>';
	}

	/**
	 * Runs each entry's 'register' callback from a tabs or pages filter, on admin_init.
	 *
	 * @param string $filter Filter whose entries may carry a 'register' callable.
	 * @return void
	 */
	public static function registerSettings(string $filter): void
	{
		foreach (apply_filters($filter, []) as $entry) {
			if (!empty($entry['register']) && is_callable($entry['register'])) {
				call_user_func($entry['register']);
			}
		}
	}

	/**
	 * Shared Settings API form glue, also used by standalone pages with no tab
	 * strip of their own.
	 *
	 * @param string      $option_group Settings API option group.
	 * @param string      $page_slug    Settings API page slug.
	 * @param bool        $show_reset   Whether to render a "Reset to Defaults" button.
	 * @param string|null $current_tab  Tab id to carry in a hidden field, or null
	 *                                  when the page has no tabs of its own.
	 */
	public static function renderSettingsForm(string $option_group, string $page_slug, bool $show_reset = false, ?string $current_tab = null): void
	{
		echo '<form method="post" action="options.php">';
		if ($current_tab !== null) {
			echo '<input type="hidden" name="current_tab" value="' . esc_attr($current_tab) . '">';
		}
		settings_fields($option_group);
		do_settings_sections($page_slug);
		submit_button();
		if ($show_reset) {
			submit_button(__('Reset to Defaults', 'amrf-admin'), 'secondary', 'amrf_reset_defaults', false);
		}
		echo '</form>';
	}
}
