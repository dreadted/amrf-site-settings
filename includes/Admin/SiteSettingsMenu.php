<?php

namespace Antropomorf\Admin;

use Antropomorf\Utilities\SettingsRenderer;

if (!defined('ABSPATH')) {
  exit;
}

/**
 * Class SiteSettingsMenu
 *
 * Owns the plugin's own top-level "Site Settings" admin menu — separate from,
 * and unrelated to, the "Admin Panel Settings" page Admin\SettingsPage owns
 * under Settings. Two kinds of module hang off this menu:
 *
 * - Tabs on amrf_site_settings_tabs share ONE page (the menu's default/first
 *   submenu) via a nav-tab-wrapper, same mechanism as
 *   amrf_admin_settings_tabs — see SiteSettings\Provider for the SEO/
 *   Business & Contact/Address/Social Media tabs.
 * - Pages on amrf_site_settings_pages each get their OWN add_submenu_page(),
 *   for a module that doesn't fit the shared-tab-strip shape (its own
 *   capability, e.g. FluentFormPrivacy's GDPR page — retention/export/erase
 *   settings are more sensitive than site contact info, hence
 *   manage_options there instead of this menu's own default capability).
 *
 * Capability: edit_theme_options, not manage_options — this is the exact
 * capability Hooks\FrontendHooks already grants non-administrator roles at
 * login time when a role's "site_menus_cap" checkbox (Settings\Manager) is
 * enabled, so reusing it here means no new per-role toggle is needed for
 * roles that should be able to fill in Site Settings (editors etc.), not
 * just administrators.
 *
 * @package Antropomorf\Admin
 */
class SiteSettingsMenu
{
  private const CAPABILITY = 'edit_theme_options';

  /**
   * Public so other Admin classes hanging their own submenu off this same
   * parent (e.g. SettingsPage) can reference it without duplicating the
   * literal string.
   */
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
   * Icon and menu position match ptsussis-theme's original
   * add_menu_page('Site Settings', ...) call (includes/site-settings.php) —
   * this is that same menu item, now living in the shared plugin instead of
   * one theme.
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

    // Same slug as the parent — WordPress collapses this into the parent
    // menu's own link instead of adding a redundant duplicate entry, exactly
    // like core's own Tools/Settings menus do for their first submenu.
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
   * heading plus the same Settings API form glue the tabbed page uses,
   * minus the tab strip. A module that needs fully custom markup (e.g. a
   * future Umami iframe page) can pass its own 'render' callback instead
   * of 'option_group'/'page_slug' and bypass this. SupportGenix's ticket
   * page deliberately does NOT use this mechanism — it's its own
   * top-level menu, see SupportGenix\Provider's docblock for why.
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
   * (register_setting/add_settings_section/add_settings_field) — the
   * admin_init-timed counterpart to addMenu()'s render-time filter reads.
   * Mirrors Admin\SettingsPage::registerTabSettings() for the other menu.
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
