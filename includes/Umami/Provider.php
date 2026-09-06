<?php

namespace Antropomorf\Umami;

if (!defined('ABSPATH')) {
  exit;
}

/**
 * Class Provider
 *
 * Registers "Umami Settings" onto the amrf_site_settings_pages registry
 * (manage_options), enqueues the front-end tracking script with the
 * umami_site value + amrf_umami_tracked_buttons filter injected, and
 * registers the "Analytics" iframe page as its own top-level menu (same
 * edit_posts-vs-parent-capability reasoning as SupportGenix's ticket page).
 *
 * @package Antropomorf\Umami
 */
class Provider
{
  private const PAGE_SLUG = 'amrf-site-settings-umami';
  private const OPTION_GROUP = 'amrf_umami_group';
  private const SCRIPT_HANDLE = 'amrf-umami-tracking';
  private const ANALYTICS_MENU_SLUG = 'umami-analytics';

  public function __construct()
  {
    add_filter('amrf_site_settings_pages', [$this, 'registerPages']);
    add_action('wp_enqueue_scripts', [$this, 'enqueueTrackingScript']);
    add_action('admin_menu', [$this, 'addAnalyticsMenu']);
  }

  /**
   * Registers the "Analytics" top-level menu.
   *
   * @return void
   */
  public function addAnalyticsMenu(): void
  {
    add_menu_page(
      __('Analytics', 'amrf-admin'),
      __('Analytics', 'amrf-admin'),
      'edit_posts',
      self::ANALYTICS_MENU_SLUG,
      [$this, 'renderAnalyticsPage'],
      'dashicons-chart-bar',
      25
    );
  }

  /**
   * Renders an iframe onto Umami's own eu.umami.is/share analytics viewer
   * for this site's umami_id.
   *
   * @return void
   */
  public function renderAnalyticsPage(): void
  {
    $umami_id = Repository::getSettings()['id'];

    if (empty($umami_id)) {
      echo '<div class="notice notice-warning"><p>' . esc_html__('Umami ID is not set. Please configure it in the settings.', 'amrf-admin') . '</p></div>';
      return;
    }

    $site_url = preg_replace('(^https?://)', '', site_url());
    $umami_share_url = 'https://eu.umami.is/share/' . rawurlencode($umami_id) . '/' . rawurlencode($site_url);

    echo '<div class="wrap" style="margin: 0;">';
    printf(
      '<iframe src="%s" style="border:0; height: 100dvh; width: calc(100%% + 20px); margin: 0 0 -65px -20px;"></iframe>',
      esc_url($umami_share_url)
    );
    echo '</div>';
  }

  /**
   * @param array $pages Pages registered so far by other callbacks on this filter.
   * @return array Pages with 'umami' appended.
   */
  public function registerPages(array $pages): array
  {
    $pages['umami'] = [
      'page_title' => __('Umami Settings', 'amrf-admin'),
      'menu_title' => __('Umami', 'amrf-admin'),
      'capability' => 'manage_options',
      'menu_slug' => self::PAGE_SLUG,
      'option_group' => self::OPTION_GROUP,
      'page_slug' => self::PAGE_SLUG,
      'show_reset' => false,
      'register' => [$this, 'register'],
    ];

    return $pages;
  }

  /**
   * Called via this page's 'register' callback from the pages registry, on
   * admin_init.
   *
   * @return void
   */
  public function register(): void
  {
    register_setting(
      self::OPTION_GROUP,
      Repository::OPTION_NAME,
      [Repository::class, 'sanitize']
    );

    add_settings_section('umami_section', '', '__return_false', self::PAGE_SLUG);

    add_settings_field(
      'site',
      __('Umami Site', 'amrf-admin'),
      [$this, 'renderSiteField'],
      self::PAGE_SLUG,
      'umami_section'
    );
    add_settings_field(
      'id',
      __('Umami ID', 'amrf-admin'),
      [$this, 'renderIdField'],
      self::PAGE_SLUG,
      'umami_section'
    );
  }

  public function renderSiteField(): void
  {
    $this->renderField('site');
  }

  public function renderIdField(): void
  {
    $this->renderField('id');
  }

  private function renderField(string $key): void
  {
    $settings = Repository::getSettings();
    $id = Repository::OPTION_NAME . '_' . $key;
    $name = Repository::OPTION_NAME . '[' . $key . ']';

    printf(
      '<input type="text" id="%1$s" name="%2$s" value="%3$s" class="regular-text" />',
      esc_attr($id),
      esc_attr($name),
      esc_attr($settings[$key])
    );
  }

  /**
   * Front-end only. Skips the inline script entirely when umami_site is
   * empty, avoiding an empty-string data-website-id attribute.
   *
   * @return void
   */
  public function enqueueTrackingScript(): void
  {
    $settings = Repository::getSettings();

    wp_enqueue_script(
      self::SCRIPT_HANDLE,
      AMRF_ADMIN_PLUGIN_URL . 'assets/js/amrf-umami-tracking.js',
      [],
      filemtime(AMRF_ADMIN_PLUGIN_DIR . '/assets/js/amrf-umami-tracking.js'),
      ['strategy' => 'defer', 'in_footer' => true]
    );

    if (!empty($settings['site'])) {
      wp_add_inline_script(
        self::SCRIPT_HANDLE,
        'const umamiSite = ' . wp_json_encode($settings['site']) . ';'
      );
    }

    $buttons = apply_filters('amrf_umami_tracked_buttons', []);
    wp_localize_script(self::SCRIPT_HANDLE, 'amrfUmamiButtons', $buttons);
  }
}
