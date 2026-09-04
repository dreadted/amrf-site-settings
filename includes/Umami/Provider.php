<?php

namespace Antropomorf\Umami;

if (!defined('ABSPATH')) {
  exit;
}

/**
 * Class Provider
 *
 * Registers "Umami Settings" onto the amrf_site_settings_pages registry
 * (see Admin\SiteSettingsMenu, the shared "Site Settings" top-level menu),
 * capability manage_options — matches amrf-theme's original
 * umami_settings_page() (add_options_page(), also manage_options), just
 * consolidated under this plugin's own menu instead of WordPress core's
 * Settings.
 *
 * Also enqueues the front-end tracking script (assets/js/amrf-umami-
 * tracking.js — ported from amrf-theme's assets/scripts.js) and injects
 * the umami_site value + apply_filters('amrf_umami_tracked_buttons', [])
 * the same way enqueue.php's wp_add_inline_script()/wp_localize_script()
 * calls did.
 *
 * The "Analytics" iframe report page is its own top-level menu, not a
 * Site Settings submenu — same edit_posts-vs-parent-capability reasoning
 * that moved SupportGenix's ticket page there: slug/icon/position match
 * amrf-theme's original umami_analytics_menu() exactly, including the
 * slug, so any role already allow-listed for 'umami-analytics' keeps
 * working unchanged.
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
   * Registers the "Analytics" top-level menu — icon and position match
   * amrf-theme's original umami_analytics_menu() (includes/umami.php).
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
   * for this site's umami_id — identical markup/URL-building to the
   * original umami_analytics_page().
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
   * Front-end only (wp_enqueue_scripts, not admin_enqueue_scripts) — same
   * as the original theme's enqueue_frontend_scripts(). No-ops with an
   * empty umami_site the same way the original did (addUmamiTracking()'s
   * own `typeof umamiSite !== 'undefined'` guard means the const not
   * existing at all is already handled, but skipping the inline script
   * entirely when there's nothing to track avoids an empty-string
   * data-website-id attribute).
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
