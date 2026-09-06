<?php

namespace Antropomorf\Admin;

use Antropomorf\Utilities\SettingsRenderer;

if (!defined('ABSPATH')) {
  exit;
}

/**
 * Class SiteSettingsMenu
 *
 * Owns the plugin's top-level "Site Settings" admin menu. Tabs registered on
 * amrf_site_settings_tabs share one page via a nav-tab-wrapper (see
 * SiteSettings\Provider); modules on amrf_site_settings_pages each get their
 * own add_submenu_page() when they need their own capability or full-custom
 * markup.
 *
 * Capability: edit_theme_options, not manage_options — matches what
 * Hooks\FrontendHooks grants via a role's "site_menus_cap" toggle, so
 * non-admin roles can reach this menu without a separate capability.
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
    $this->renderer = new SettingsRenderer(self::TABS_FILTER, self::MENU_SLUG, __('Site Settings', 'amrf-admin'));

    add_action('admin_menu', [$this, 'addMenu']);
    add_action('admin_init', [$this, 'registerSettings']);
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
      'dashicons-admin-site-alt3',
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
   * Generic render callback for an amrf_site_settings_pages entry — a plain
   * heading plus the same Settings API form glue the tabbed page uses. A
   * module needing fully custom markup passes its own 'render' callback
   * instead of 'option_group'/'page_slug' to bypass this.
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
    SettingsRenderer::renderSettingsForm($page['option_group'], $page['page_slug'], !empty($page['show_reset']));
    echo '</div>';
  }

  /**
   * Calls every registered tab's and page's own 'register' callback
   * (register_setting/add_settings_section/add_settings_field), on admin_init.
   *
   * @return void
   */
  public function registerSettings(): void
  {
    foreach (apply_filters(self::TABS_FILTER, []) as $tab) {
      if (!empty($tab['register']) && is_callable($tab['register'])) {
        call_user_func($tab['register']);
      }
    }

    foreach (apply_filters(self::PAGES_FILTER, []) as $page) {
      if (!empty($page['register']) && is_callable($page['register'])) {
        call_user_func($page['register']);
      }
    }
  }
}
