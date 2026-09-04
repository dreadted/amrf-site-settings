<?php

namespace Antropomorf\Hardening;

if (!defined('ABSPATH')) {
  exit;
}

/**
 * Class Provider
 *
 * Generalized from amrf-theme's inline functions.php hardening/performance
 * snippets — reusable across projects rather than copy-pasted into every
 * new theme.
 *
 * Split into two groups:
 *
 * - Unconditional, no setting: XML-RPC blocking, generic login error
 *   message, hiding the WP version generator tag, blocking a ?username=
 *   query param at login, and removing the /wp/v2/users REST endpoint.
 *   All near-universal security hardening with no real downside for any
 *   site — same posture as this plugin's existing unconditional update-nag
 *   removal (Hooks\FrontendHooks) and Support Genix promo-banner hiding
 *   (SupportGenix\Provider).
 * - Behind a toggle on the "Hardening" page (its own amrf_site_settings_pages
 *   entry, manage_options): disabling author archives, redirecting 404s to
 *   the homepage, removing jQuery Migrate, and disabling generated image
 *   sizes. These four actually change site behavior in ways that don't
 *   universally apply — a multi-author site wants author archives, an
 *   older plugin/theme's JS might depend on jQuery Migrate's compatibility
 *   shims, some sites want real 404 pages, some want WP's generated
 *   responsive image sizes. Defaulting to true (see Repository) matches
 *   the common case for the sites this plugin is actually built for, but
 *   any one of the four needs to be a per-site opt-out, not baked in.
 *
 * The old theme's force_website_schema_name() (WebSite JSON-LD in
 * wp_head) is deliberately NOT here — that belongs with the planned SEO
 * module's own enable_seo_output toggle, not a new one here, and avoids
 * duplicate structured data on a site that already runs Yoast/RankMath.
 *
 * @package Antropomorf\Hardening
 */
class Provider
{
  private const PAGE_SLUG = 'amrf-site-settings-hardening';
  private const OPTION_GROUP = 'amrf_hardening_group';

  public function __construct()
  {
    $this->registerUnconditionalHardening();
    $this->registerToggleableHardening();

    add_filter('amrf_site_settings_pages', [$this, 'registerPages']);
    add_action('admin_enqueue_scripts', [$this, 'enqueueSwitchStyles']);
  }

  /**
   * @return void
   */
  private function registerUnconditionalHardening(): void
  {
    add_filter('xmlrpc_enabled', '__return_false');

    add_filter('login_errors', function () {
      return __('There is an error.', 'amrf-admin');
    });

    remove_action('wp_head', 'wp_generator');

    add_action('login_init', [$this, 'blockUsernameInLoginUrl']);
    add_filter('rest_endpoints', [$this, 'disableUsersRestEndpoint']);
  }

  /**
   * @return void
   */
  private function registerToggleableHardening(): void
  {
    $settings = Repository::getSettings();

    if ($settings['disable_author_archives']) {
      add_action('template_redirect', [$this, 'disableAuthorArchives']);
    }

    if ($settings['redirect_404_to_home']) {
      add_action('template_redirect', [$this, 'redirect404ToHome']);
    }

    if ($settings['remove_jquery_migrate']) {
      add_filter('wp_default_scripts', [$this, 'removeJqueryMigrate']);
    }

    if ($settings['disable_generated_image_sizes']) {
      add_filter('wp_img_tag_add_decoding_attr', '__return_false');
      add_action('intermediate_image_sizes_advanced', fn () => []);
      add_filter('big_image_size_threshold', '__return_false');
    }
  }

  /**
   * @return void
   */
  public function blockUsernameInLoginUrl(): void
  {
    if (isset($_GET['username'])) {
      wp_redirect(wp_login_url());
      exit;
    }
  }

  /**
   * @param array $endpoints
   * @return array
   */
  public function disableUsersRestEndpoint(array $endpoints): array
  {
    unset($endpoints['/wp/v2/users']);
    return $endpoints;
  }

  /**
   * @return void
   */
  public function disableAuthorArchives(): void
  {
    if (is_author()) {
      wp_redirect(home_url());
      exit;
    }
  }

  /**
   * @return void
   */
  public function redirect404ToHome(): void
  {
    if (!is_user_logged_in() && is_404()) {
      wp_safe_redirect(home_url(), 301);
      exit;
    }
  }

  /**
   * @param \WP_Scripts $scripts
   * @return void
   */
  public function removeJqueryMigrate($scripts): void
  {
    if (is_admin() || !isset($scripts->registered['jquery'])) {
      return;
    }

    $script = $scripts->registered['jquery'];
    if ($script->deps) {
      $script->deps = array_diff($script->deps, ['jquery-migrate']);
    }
  }

  /**
   * @param array $pages Pages registered so far by other callbacks on this filter.
   * @return array Pages with 'hardening' appended.
   */
  public function registerPages(array $pages): array
  {
    $pages['hardening'] = [
      'page_title' => __('Hardening', 'amrf-admin'),
      'menu_title' => __('Hardening', 'amrf-admin'),
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

    add_settings_section('hardening_section', '', '__return_false', self::PAGE_SLUG);

    $fields = [
      'disable_author_archives' => [
        __('Disable author archives', 'amrf-admin'),
        __('Redirects author archive pages to the homepage — mainly useful on single-author sites, or to avoid leaking usernames via author URLs.', 'amrf-admin'),
      ],
      'redirect_404_to_home' => [
        __('Redirect 404 pages to the homepage', 'amrf-admin'),
        __('Applies to logged-out visitors only. Turn off if this site should show a real 404 page instead.', 'amrf-admin'),
      ],
      'remove_jquery_migrate' => [
        __('Remove jQuery Migrate', 'amrf-admin'),
        __('Turn off if an older plugin or theme on this site depends on jQuery Migrate\'s compatibility shims.', 'amrf-admin'),
      ],
      'disable_generated_image_sizes' => [
        __('Disable generated image sizes', 'amrf-admin'),
        __('Stops WordPress from generating additional (responsive) image sizes and auto-scaling large uploads. Turn off if this site relies on WordPress\'s own generated image sizes.', 'amrf-admin'),
      ],
    ];

    foreach ($fields as $key => [$label, $description]) {
      add_settings_field(
        $key,
        $label,
        function () use ($key, $description) {
          $this->renderCheckbox($key, $description);
        },
        self::PAGE_SLUG,
        'hardening_section'
      );
    }
  }

  /**
   * Same .switch/.slider toggle markup as Settings\Manager::renderCheckbox()
   * (Admin Panel Settings' own toggles), styled by assets/css/amrf-admin-
   * settings.css — reused here rather than reinvented, so a Hardening
   * toggle looks identical to every other toggle in the plugin.
   *
   * @param string $key
   * @param string $description
   * @return void
   */
  private function renderCheckbox(string $key, string $description): void
  {
    $settings = Repository::getSettings();
    $name = Repository::OPTION_NAME . '[' . $key . ']';

    printf(
      '<label class="switch"><input type="checkbox" name="%1$s" value="1" %2$s /><span class="slider round"></span></label><p class="description">%3$s</p>',
      esc_attr($name),
      checked(!empty($settings[$key]), true, false),
      esc_html($description)
    );
  }

  /**
   * The shared .switch/.slider toggle styles live in assets/css/amrf-
   * admin-settings.css, otherwise only loaded on the Admin Panel Settings
   * page (SettingsPage::enqueueAssets()) — enqueued here too,
   * unconditionally, same posture as SupportGenix\Provider's own small
   * shared-CSS enqueue (cheap, scoped class names, no per-page hook check
   * needed).
   *
   * @return void
   */
  public function enqueueSwitchStyles(): void
  {
    wp_enqueue_style(
      'amrf-admin-settings',
      AMRF_ADMIN_PLUGIN_URL . 'assets/css/amrf-admin-settings.css'
    );
  }
}
