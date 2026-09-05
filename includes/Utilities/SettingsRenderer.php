<?php

namespace Antropomorf\Utilities;

if (!defined('ABSPATH')) {
  exit;
}

/**
 * Class SettingsRenderer
 *
 * Renders one tab-based settings page: a nav-tab-wrapper switched by
 * $_GET['tab'], with each tab's Settings API sections/fields underneath.
 * Which tabs exist comes entirely from the filter named in $tabsFilter — this
 * class is instantiated once per tab GROUP, not once for the whole plugin, so
 * Admin\SettingsPage (amrf_admin_settings_tabs, the "Admin Panel Settings"
 * page) and Admin\SiteSettingsMenu (amrf_site_settings_tabs, the "Site
 * Settings" page) each own their own instance instead of sharing one hardcoded
 * to a single filter/page.
 *
 * @package Antropomorf\Utilities
 */
class SettingsRenderer
{
  private string $tabsFilter;
  private string $menuSlug;
  private string $pageTitle;

  /**
   * @param string $tabsFilter Filter name to read this page's tabs from —
   *                            each entry shaped like
   *                            ['label', 'option_group', 'page_slug',
   *                            'show_reset', 'register'].
   * @param string $menuSlug   This page's own admin menu slug, used to build
   *                            each tab's link via menu_page_url() — works
   *                            whether the page lives under Settings
   *                            (options-general.php) or its own top-level
   *                            menu (admin.php), unlike a hardcoded base file.
   * @param string $pageTitle  Heading shown above the tab strip.
   */
  public function __construct(string $tabsFilter, string $menuSlug, string $pageTitle)
  {
    $this->tabsFilter = $tabsFilter;
    $this->menuSlug = $menuSlug;
    $this->pageTitle = $pageTitle;
  }

  /**
   * Render the tab strip and the current tab's Settings API fields.
   *
   * @return void
   */
  public function render(): void
  {
    $tabs = apply_filters($this->tabsFilter, []);
    if (empty($tabs)) {
      return;
    }

    $current_tab = isset($_GET['tab'], $tabs[$_GET['tab']]) ? $_GET['tab'] : array_key_first($tabs);
    $tab = $tabs[$current_tab];

    echo '<div class="wrap">';
    echo '<h1>' . esc_html($this->pageTitle) . '</h1>';
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
   * Shared Settings API form glue — settings_fields()/do_settings_sections()/
   * submit_button() — used both by render() above for a tabbed page, and by
   * standalone single-purpose settings pages that have no tab strip of their
   * own (see Admin\SiteSettingsMenu's amrf_site_settings_pages entries, e.g.
   * ContactForm's Contact Forms page).
   *
   * @param string      $option_group Settings API option group for settings_fields().
   * @param string      $page_slug    Settings API page slug for do_settings_sections().
   * @param bool        $show_reset   Whether to render a "Reset to Defaults" button.
   * @param string|null $current_tab  Tab id to carry in a hidden field, or null
   *                                  when the page has no tabs of its own.
   * @return void
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
