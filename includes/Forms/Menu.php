<?php

namespace Antropomorf\Forms;

use Antropomorf\Utilities\SettingsRenderer;

if (!defined('ABSPATH')) {
  exit;
}

/**
 * Class Menu
 *
 * Registers the "Forms" page shell onto amrf_site_settings_pages (see
 * Admin\SiteSettingsMenu) — knows nothing about Swish or Contact Forms
 * specifically, only that some tabs exist on amrf_forms_tabs.
 *
 * @package Antropomorf\Forms
 */
class Menu
{
  /**
   * A role's saved allowed_menu_items allow-list matches this slug exactly
   * (Hooks\FrontendHooks::init()) — renaming it would silently break access
   * for existing sites. Not shown to visitors/editors; only page_title/
   * menu_title ("Forms") are.
   */
  public const PAGE_SLUG = 'amrf-site-settings-gdpr';

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
      'register' => [$this, 'registerTabSettings'],
    ];

    return $pages;
  }

  /**
   * Called via this page's 'register' key (Admin\SiteSettingsMenu::registerSettings()),
   * on admin_init.
   *
   * @return void
   */
  public function registerTabSettings(): void
  {
    foreach (apply_filters(self::TABS_FILTER, []) as $tab) {
      if (!empty($tab['register']) && is_callable($tab['register'])) {
        call_user_func($tab['register']);
      }
    }
  }

  /**
   * Renders its own tab strip via SettingsRenderer instead of
   * SiteSettingsMenu's default plain-heading fallback.
   *
   * @return void
   */
  public function render(): void
  {
    (new SettingsRenderer(self::TABS_FILTER, self::PAGE_SLUG, __('Forms', 'amrf-admin')))->render();
  }
}
