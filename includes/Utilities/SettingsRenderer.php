<?php

namespace Antropomorf\Utilities;

if (!defined('ABSPATH')) {
  exit;
}

/**
 * Renders one tab-based settings page: a nav-tab-wrapper switched by
 * $_GET['tab'], with each tab's Settings API fields underneath. Instantiated
 * once per tab group (e.g. once for Admin Panel Settings, once for Site
 * Settings) rather than once for the whole plugin.
 *
 * @package Antropomorf\Utilities
 */
class SettingsRenderer
{
  private string $tabsFilter;
  private string $menuSlug;
  private string $pageTitle;

  /**
   * @param string $tabsFilter Filter to read this page's tabs from — each
   *                            entry shaped like ['label', 'option_group',
   *                            'page_slug', 'show_reset', 'register'].
   * @param string $menuSlug   This page's own admin menu slug.
   * @param string $pageTitle  Heading shown above the tab strip.
   */
  public function __construct(string $tabsFilter, string $menuSlug, string $pageTitle)
  {
    $this->tabsFilter = $tabsFilter;
    $this->menuSlug = $menuSlug;
    $this->pageTitle = $pageTitle;
  }

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
