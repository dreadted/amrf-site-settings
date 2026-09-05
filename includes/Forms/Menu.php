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
 * Admin\SiteSettingsMenu, the shared "Site Settings" top-level menu) —
 * knows nothing about Swish or Contact Forms specifically, only that
 * SOME tabs exist on amrf_forms_tabs. Same relationship as
 * SiteSettings\Provider registering tabs onto amrf_site_settings_tabs
 * without Admin\SiteSettingsMenu knowing anything about SEO/Business/
 * Address/Social — see Utilities\SettingsRenderer's own docblock, which
 * is designed to be instantiated once per independent tab group.
 *
 * @package Antropomorf\Forms
 */
class Menu
{
  /**
   * Kept at its original 'amrf-site-settings-gdpr' value from when this
   * was ContactForm\Provider's own single "Contact Forms" page — a role's
   * saved allowed_menu_items allow-list (Settings\Repository, matched by
   * exact slug string in Hooks\FrontendHooks::init()) would silently lose
   * access to this page on any rename, on every site already using this
   * plugin, with no error and no migration path back. The slug is never
   * shown to a site visitor or editor, only the page_title/menu_title
   * below are (now "Forms"), so nothing is actually lost by keeping it —
   * same reasoning ContactForm\Provider's own docblock already gave for
   * this exact slug.
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
   * Admin\SiteSettingsMenu::registerSettings() calls this (this page
   * entry's own 'register' key) on admin_init — the counterpart to
   * SiteSettingsMenu's OWN identical loop over amrf_site_settings_tabs,
   * just one level down for this page's own amrf_forms_tabs tabs.
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
   * Bypasses Admin\SiteSettingsMenu::renderPage()'s default plain-heading
   * fallback (this page entry has no 'option_group'/'page_slug' of its
   * own — only a 'render' callback) in favor of its own tab strip, exactly
   * like Admin\SiteSettingsMenu and Admin\SettingsPage each render their
   * own via their own SettingsRenderer instance.
   *
   * @return void
   */
  public function render(): void
  {
    (new SettingsRenderer(self::TABS_FILTER, self::PAGE_SLUG, __('Forms', 'amrf-admin')))->render();
  }
}
