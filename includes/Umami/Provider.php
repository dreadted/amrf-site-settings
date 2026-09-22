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
 * umami_site value + button-tracking config injected, and registers the
 * "Analytics" iframe page as its own top-level menu (same
 * edit_posts-vs-parent-capability reasoning as SupportGenix's ticket page).
 *
 * @package Antropomorf\Umami
 */
class Provider
{
  private const PAGE_SLUG = 'amrf-site-settings-umami';
  private const OPTION_GROUP = 'amrf_umami_group';
  private const SCRIPT_HANDLE = 'amrf-umami-tracking';
  private const TRACKER_HANDLE = 'amrf-umami-tracker';
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
   * Renders an iframe onto the selected Umami server's /share analytics
   * viewer for this site's umami_id.
   *
   * @return void
   */
  public function renderAnalyticsPage(): void
  {
    $settings = Repository::getSettings();
    $umami_id = $settings['id'];

    if (empty($umami_id)) {
      echo '<div class="notice notice-warning"><p>' . esc_html__('Umami ID is not set. Please configure it in the settings.', 'amrf-admin') . '</p></div>';
      return;
    }

    $site_url = preg_replace('(^https?://)', '', site_url());
    $umami_share_url = 'https://' . $settings['host'] . '/share/' . rawurlencode($umami_id) . '/' . rawurlencode($site_url);

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
      'host',
      __('Umami Server', 'amrf-admin'),
      [$this, 'renderHostField'],
      self::PAGE_SLUG,
      'umami_section'
    );
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
    add_settings_field(
      'button_selectors',
      __('Button Selectors', 'amrf-admin'),
      [$this, 'renderButtonSelectorsField'],
      self::PAGE_SLUG,
      'umami_section'
    );
  }

  public function renderHostField(): void
  {
    $settings = Repository::getSettings();
    $id = Repository::OPTION_NAME . '_host';
    $name = Repository::OPTION_NAME . '[host]';

    $labels = [
      'umami.antropomorf.se' => __('Egen installation (umami.antropomorf.se)', 'amrf-admin'),
      'eu.umami.is' => __('Umami Cloud (eu.umami.is)', 'amrf-admin'),
    ];

    printf('<select id="%1$s" name="%2$s">', esc_attr($id), esc_attr($name));
    foreach (Repository::HOSTS as $host) {
      printf(
        '<option value="%1$s" %2$s>%3$s</option>',
        esc_attr($host),
        selected($settings['host'], $host, false),
        esc_html($labels[$host])
      );
    }
    echo '</select>';
  }

  public function renderSiteField(): void
  {
    $this->renderField('site');
  }

  public function renderIdField(): void
  {
    $this->renderField('id');
  }

  /**
   * One CSS selector per line, auto-tracked and named after each matched
   * element's own visible text. Always shows the effective list (stored
   * override, or DEFAULT_BUTTON_SELECTORS when empty) — clearing the field
   * and saving reverts to the defaults rather than disabling tracking.
   *
   * @return void
   */
  public function renderButtonSelectorsField(): void
  {
    $id = Repository::OPTION_NAME . '_button_selectors';
    $name = Repository::OPTION_NAME . '[button_selectors]';
    $value = implode("\n", Repository::getButtonSelectors());

    printf(
      '<textarea id="%1$s" name="%2$s" rows="6" cols="50" class="large-text code">%3$s</textarea>',
      esc_attr($id),
      esc_attr($name),
      esc_textarea($value)
    );
    echo '<p class="description">' . esc_html__('One CSS selector per line. Matched elements are tracked automatically, named after their own visible text. Leave empty to use the built-in defaults.', 'amrf-admin') . '</p>';
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
   * Front-end only. Skips self::TRACKER_HANDLE entirely when umami_site is
   * empty (avoiding an empty-string data-website-id) or the visitor is
   * logged in.
   *
   * The tracker is enqueued as a real <script defer> tag rather than
   * injected via JS after DOMContentLoaded — that used to arrive so late
   * that the theme's <link rel="preconnect"> to this same host went unused
   * (connection long idle by the time the request happened). A real tag
   * lets the browser's preload scanner find it during initial HTML parsing,
   * close enough to the preconnect for it to matter.
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

    // amrf_umami_tracked_buttons: optional {selector, name}[] override, checked
    // before the generic button_selectors sweep — lets a theme pin an exact
    // event name onto a specific element instead of relying on its own text.
    $button_overrides = apply_filters('amrf_umami_tracked_buttons', []);

    wp_add_inline_script(
      self::SCRIPT_HANDLE,
      'const amrfUmamiButtonSelectors = ' . wp_json_encode(Repository::getButtonSelectors()) . ';'
      . 'const amrfUmamiButtonOverrides = ' . wp_json_encode($button_overrides) . ';'
      . 'const amrfUmamiPageTitle = ' . wp_json_encode(wp_get_document_title()) . ';'
    );

    if (empty($settings['site']) || is_user_logged_in()) {
      return;
    }

    wp_enqueue_script(
      self::TRACKER_HANDLE,
      'https://' . $settings['host'] . '/script.js',
      [],
      null,
      ['strategy' => 'defer', 'in_footer' => false]
    );

    add_filter('script_loader_tag', function (string $tag, string $handle) use ($settings): string {
      if ($handle !== self::TRACKER_HANDLE) {
        return $tag;
      }

      // Without data-host-url, Umami infers its /api/send target from the
      // script tag's own src — breaks if a cache plugin rewrites src to a
      // same-origin copy (e.g. LiteSpeed's JS localization).
      return str_replace(
        '<script ',
        '<script data-website-id="' . esc_attr($settings['site']) . '" data-host-url="https://' . esc_attr($settings['host']) . '" ',
        $tag
      );
    }, 10, 2);
  }
}
