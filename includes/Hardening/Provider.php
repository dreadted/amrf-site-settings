<?php

namespace Antropomorf\Hardening;

use Antropomorf\Utilities\SettingsRenderer;

if (!defined('ABSPATH')) {
  exit;
}

/**
 * Class Provider
 *
 * Security/performance hardening, split into two groups:
 *
 * - Unconditional: XML-RPC blocking, generic login error message, hiding
 *   the WP version generator tag and RSD link, blocking ?username= at
 *   login, and removing the /wp/v2/users REST endpoint. Near-universal,
 *   no downside.
 * - Toggleable, on the "Hardening" page (manage_options): allowing admins
 *   to upload sanitized SVGs, disabling author archives (and, tied to that
 *   same toggle, WP's own users sitemap — pointless and actively leaks
 *   usernames once author archives are gone), redirecting 404s to the
 *   homepage, removing jQuery Migrate, and disabling generated image
 *   sizes — these change behavior some sites rely on, so each defaults to
 *   true but stays a per-site opt-out.
 *
 * @package Antropomorf\Hardening
 */
class Provider
{
  private const PAGE_SLUG = 'amrf-site-settings-hardening';
  private const TABS_FILTER = 'amrf_hardening_tabs';
  private const TAB_PAGE_SLUG_IMAGES = 'amrf-site-settings-hardening-images';
  private const TAB_PAGE_SLUG_FRONTEND = 'amrf-site-settings-hardening-frontend';
  private const UPLOAD_HASH_META_KEY = '_amrf_upload_hash';

  private SettingsRenderer $renderer;

  // Set by optimizeNonAdminImageUpload(), consumed by recordUploadHash() on the same request.
  private ?string $pendingUploadHash = null;

  public function __construct()
  {
    $this->renderer = new SettingsRenderer(self::TABS_FILTER, self::PAGE_SLUG, __('Hardening', 'amrf-admin'));

    $this->registerUnconditionalHardening();
    $this->registerToggleableHardening();

    add_filter('amrf_site_settings_pages', [$this, 'registerPages']);
    add_filter(self::TABS_FILTER, [$this, 'registerTabs']);
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

    // EditURI advertises xmlrpc.php's existence even though xmlrpc_enabled
    // blocks it above — no reason to point at it at all.
    remove_action('wp_head', 'rsd_link');

    add_action('login_init', [$this, 'blockUsernameInLoginUrl']);
    add_filter('rest_endpoints', [$this, 'disableUsersRestEndpoint']);
  }

  /**
   * @return void
   */
  private function registerToggleableHardening(): void
  {
    $settings = Repository::getSettings();

    if ($settings['allow_svg_uploads']) {
      add_filter('upload_mimes', [$this, 'allowSvgMimeType']);
      add_filter('wp_check_filetype_and_ext', [$this, 'checkSvgFiletype'], 10, 4);
      add_filter('wp_handle_upload_prefilter', [$this, 'sanitizeUploadedSvg']);
    }

    if ($settings['disable_author_archives']) {
      add_action('template_redirect', [$this, 'disableAuthorArchives']);
      // A users sitemap only ever points at author archives — pointless,
      // and actively leaks usernames, once those archives are disabled.
      add_filter('wp_sitemaps_add_provider', [$this, 'removeUsersSitemapProvider'], 10, 2);
    }

    if ($settings['redirect_404_to_home']) {
      add_action('template_redirect', [$this, 'redirect404ToHome']);
    }

    if ($settings['remove_jquery_migrate']) {
      add_filter('wp_default_scripts', [$this, 'removeJqueryMigrate']);
    }

    if ($settings['disable_generated_image_sizes']) {
      add_filter('wp_img_tag_add_decoding_attr', '__return_false');
      add_action('intermediate_image_sizes_advanced', fn() => []);
      add_filter('big_image_size_threshold', '__return_false');
    }

    if ($settings['disable_site_search']) {
      add_action('parse_query', [$this, 'disableSiteSearch']);
    }

    if ($settings['optimize_non_admin_image_uploads']) {
      add_filter('wp_handle_upload', [$this, 'optimizeNonAdminImageUpload'], 10, 2);
      add_action('add_attachment', [$this, 'recordUploadHash']);
    }
  }

  /**
   * SVGs can carry <script>, event-handler attributes, and external
   * references — treated as active content, not a plain image format, so
   * this whole feature is gated to administrators (manage_options) and
   * every uploaded file is sanitized before WordPress stores it.
   * Deliberately not `unfiltered_html`: on a non-multisite install
   * WordPress grants that capability to Editors too by default, so it
   * doesn't actually distinguish admin from editor.
   *
   * @param array $mimes
   * @return array
   */
  public function allowSvgMimeType(array $mimes): array
  {
    if (current_user_can('manage_options')) {
      $mimes['svg'] = 'image/svg+xml';
    }
    return $mimes;
  }

  /**
   * @param array|false $data
   * @param string $file
   * @param string $filename
   * @param array $mimes
   * @return array|false
   */
  public function checkSvgFiletype($data, $file, $filename, $mimes)
  {
    if (!empty($data['ext']) && !empty($data['type'])) {
      return $data;
    }

    $filetype = wp_check_filetype($filename, $mimes);
    if ($filetype['ext'] === 'svg') {
      $data['ext'] = 'svg';
      $data['type'] = 'image/svg+xml';
    }

    return $data;
  }

  /**
   * @param array $file
   * @return array
   */
  public function sanitizeUploadedSvg(array $file): array
  {
    $is_svg = $file['type'] === 'image/svg+xml' || preg_match('/\.svg$/i', $file['name'] ?? '');

    if (!$is_svg) {
      return $file;
    }

    if (!current_user_can('manage_options')) {
      $file['error'] = __('You are not allowed to upload SVG files.', 'amrf-admin');
      return $file;
    }

    $content = file_get_contents($file['tmp_name']);

    if ($content === false || stripos($content, '<svg') === false) {
      $file['error'] = __('This file does not look like a valid SVG.', 'amrf-admin');
      return $file;
    }

    $sanitized = $this->sanitizeSvgMarkup($content);

    if ($sanitized === null) {
      $file['error'] = __('This SVG could not be sanitized and was rejected.', 'amrf-admin');
      return $file;
    }

    file_put_contents($file['tmp_name'], $sanitized);

    return $file;
  }

  /**
   * Strips executable/active content from an SVG's markup: <script>,
   * event-handler attributes (onload, onclick, …), javascript: URIs, and
   * <foreignObject> (arbitrary embedded HTML). Returns null if the file
   * isn't parseable XML at all.
   *
   * @param string $content
   * @return string|null
   */
  private function sanitizeSvgMarkup(string $content): ?string
  {
    $previous = libxml_use_internal_errors(true);
    $doc = new \DOMDocument();
    $loaded = $doc->loadXML($content, LIBXML_NONET | LIBXML_NOENT);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);

    if (!$loaded) {
      return null;
    }

    $xpath = new \DOMXPath($doc);

    foreach (iterator_to_array($xpath->query('//*[local-name()="script"] | //*[local-name()="foreignObject"]')) as $node) {
      $node->parentNode->removeChild($node);
    }

    foreach (iterator_to_array($xpath->query('//@*')) as $attr) {
      $name = strtolower($attr->nodeName);
      $value = trim($attr->nodeValue);

      $is_event_handler = str_starts_with($name, 'on');
      $is_script_uri = ($name === 'href' || $name === 'xlink:href' || $name === 'src')
        && preg_match('/^\s*javascript:/i', $value);

      if ($is_event_handler || $is_script_uri) {
        $attr->ownerElement->removeAttributeNode($attr);
      }
    }

    return $doc->saveXML();
  }

  // Scoped to 'upload' context — sideloads are typically admin-triggered, not a direct non-admin action.
  public function optimizeNonAdminImageUpload(array $upload, string $context = 'upload'): array
  {
    if (
      current_user_can('manage_options')
      || $context !== 'upload'
      || empty($upload['type'])
      || strpos($upload['type'], 'image/') !== 0
    ) {
      return $upload;
    }

    // Hashed before any processing, so re-uploading the same source file is
    // caught regardless of resize/quality settings changing later.
    $hash = hash_file('sha256', $upload['file']);
    $duplicate_id = $hash !== false ? $this->findAttachmentByHash($hash) : null;

    if ($duplicate_id !== null) {
      if (file_exists($upload['file'])) {
        unlink($upload['file']);
      }
      $upload['error'] = sprintf(
        // translators: %d is the existing attachment's post ID.
        __('This image is identical to an existing upload (attachment #%d) and was not saved again.', 'amrf-admin'),
        $duplicate_id
      );
      return $upload;
    }

    $processed = $this->convertToOptimizedWebp($upload['file']);

    if ($processed === null) {
      return $upload;
    }

    $this->pendingUploadHash = $hash !== false ? $hash : null;

    $upload['url'] = str_replace(basename($upload['file']), basename($processed), $upload['url']);
    $upload['file'] = $processed;
    $upload['type'] = 'image/webp';

    return $upload;
  }

  /**
   * @return int|null Attachment ID with a matching stored hash, or null if none.
   */
  private function findAttachmentByHash(string $hash): ?int
  {
    $matches = get_posts([
      'post_type' => 'attachment',
      'post_status' => 'inherit',
      'meta_key' => self::UPLOAD_HASH_META_KEY,
      'meta_value' => $hash,
      'fields' => 'ids',
      'posts_per_page' => 1,
      'no_found_rows' => true,
    ]);

    return $matches ? (int) $matches[0] : null;
  }

  // Fires right after wp_insert_attachment() — the only point where we have both the hash and the new attachment ID.
  public function recordUploadHash(int $attachment_id): void
  {
    if ($this->pendingUploadHash === null) {
      return;
    }

    update_post_meta($attachment_id, self::UPLOAD_HASH_META_KEY, $this->pendingUploadHash);
    $this->pendingUploadHash = null;
  }

  private function convertToOptimizedWebp(string $file_path): ?string
  {
    $editor = wp_get_image_editor($file_path);
    if (is_wp_error($editor)) {
      return null;
    }

    $settings = Repository::getSettings();

    // No-ops (keeps the original size) when the image already fits within
    // these dimensions — resize() with $crop=false never upscales.
    $editor->resize(
      $settings['optimize_non_admin_image_uploads_width'],
      $settings['optimize_non_admin_image_uploads_height'],
      false
    );
    $editor->set_quality(60);

    $info = pathinfo($file_path);
    // wp_unique_filename avoids collisions with an existing file that
    // already has this same base name but a different original extension.
    $webp_filename = wp_unique_filename($info['dirname'], $info['filename'] . '.webp');
    $webp_path = $info['dirname'] . '/' . $webp_filename;

    $saved = $editor->save($webp_path, 'image/webp');
    if (is_wp_error($saved)) {
      return null;
    }

    if ($file_path !== $webp_path && file_exists($file_path)) {
      unlink($file_path);
    }

    return $webp_path;
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
   * Returning anything that isn't a WP_Sitemaps_Provider instance drops
   * the provider entirely (see WP_Sitemaps_Registry::add_provider()) —
   * /wp-sitemap-users-1.xml becomes a genuine 404 rather than just being
   * hidden from the index, so there's nothing left to find by guessing
   * the URL either.
   *
   * @param \WP_Sitemaps_Provider|null $provider
   * @param string $name
   * @return \WP_Sitemaps_Provider|null
   */
  public function removeUsersSitemapProvider($provider, string $name)
  {
    return $name === 'users' ? null : $provider;
  }

  /**
   * @return void
   */
  public function redirect404ToHome(): void
  {
    if (is_user_logged_in() || !is_404()) {
      return;
    }

    // WP's own sitemap routes report is_404() true before their own
    // template_redirect renderer runs — don't redirect those away.
    if (get_query_var('sitemap') || get_query_var('sitemap-stylesheet')) {
      return;
    }

    wp_safe_redirect(home_url(), 301);
    exit;
  }

  // Turns every front-end search into a genuine 404 instead of real results.
  public function disableSiteSearch($query): void
  {
    if (!$query->is_search() || is_admin()) {
      return;
    }

    $query->is_search = false;
    $query->query_vars['s'] = false;
    $query->query['s'] = false;
    $query->set_404();
    status_header(404);
    nocache_headers();
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
      'register' => [$this, 'registerHardeningPage'],
      'render' => [$this->renderer, 'render'],
    ];

    return $pages;
  }

  public function registerTabs(array $tabs): array
  {
    $tabs['images'] = [
      'label' => __('Images', 'amrf-admin'),
      'option_group' => Repository::OPTION_GROUP_IMAGES,
      'page_slug' => self::TAB_PAGE_SLUG_IMAGES,
      'show_reset' => false,
      'register' => [$this, 'registerImagesTab'],
    ];

    $tabs['frontend'] = [
      'label' => __('Frontend', 'amrf-admin'),
      'option_group' => Repository::OPTION_GROUP_FRONTEND,
      'page_slug' => self::TAB_PAGE_SLUG_FRONTEND,
      'show_reset' => false,
      'register' => [$this, 'registerFrontendTab'],
    ];

    return $tabs;
  }

  // Dispatches to each tab's own 'register' callback — same pattern as SiteSettingsMenu::registerSettings().
  public function registerHardeningPage(): void
  {
    foreach (apply_filters(self::TABS_FILTER, []) as $tab) {
      if (!empty($tab['register']) && is_callable($tab['register'])) {
        call_user_func($tab['register']);
      }
    }
  }

  /**
   * @return void
   */
  public function registerImagesTab(): void
  {
    register_setting(
      Repository::OPTION_GROUP_IMAGES,
      Repository::OPTION_NAME,
      [Repository::class, 'sanitize']
    );

    add_settings_section('hardening_images_section', '', '__return_false', self::TAB_PAGE_SLUG_IMAGES);

    // Registered first so it renders at the top of the tab, ahead of the
    // $fields loop below.
    add_settings_field(
      'optimize_non_admin_image_uploads',
      __('Optimize non-admin image uploads', 'amrf-admin'),
      function () {
        $this->renderCheckbox(
          'optimize_non_admin_image_uploads',
          __('Automatically shrinks oversized images and converts them to WebP for every image a non-administrator uploads, and blocks exact duplicates of an already-uploaded image. Administrators are unaffected.', 'amrf-admin')
        );
      },
      self::TAB_PAGE_SLUG_IMAGES,
      'hardening_images_section'
    );

    add_settings_field(
      'optimize_non_admin_image_uploads_dimensions',
      __('Max dimensions (px)', 'amrf-admin'),
      function () {
        $this->renderImageUploadDimensionsFields();
      },
      self::TAB_PAGE_SLUG_IMAGES,
      'hardening_images_section'
    );

    $fields = [
      'allow_svg_uploads' => [
        __('Allow SVG uploads', 'amrf-admin'),
        __('Lets administrators upload SVG files through the Media Library — every file is sanitized (scripts, event handlers, and embedded HTML stripped) before it\'s stored.', 'amrf-admin'),
      ],
      'disable_generated_image_sizes' => [
        __('Disable generated image sizes', 'amrf-admin'),
        __('Stops WordPress from generating additional (responsive) image sizes and auto-scaling large uploads. Turn off if this site relies on WordPress\'s own generated image sizes.', 'amrf-admin'),
      ],
    ];

    $this->registerCheckboxFields($fields, self::TAB_PAGE_SLUG_IMAGES, 'hardening_images_section');
  }

  /**
   * @return void
   */
  public function registerFrontendTab(): void
  {
    register_setting(
      Repository::OPTION_GROUP_FRONTEND,
      Repository::OPTION_NAME,
      [Repository::class, 'sanitize']
    );

    add_settings_section('hardening_frontend_section', '', '__return_false', self::TAB_PAGE_SLUG_FRONTEND);

    $fields = [
      'disable_author_archives' => [
        __('Disable author archives', 'amrf-admin'),
        __('Redirects author archive pages to the homepage — mainly useful on single-author sites, or to avoid leaking usernames via author URLs.', 'amrf-admin'),
      ],
      'redirect_404_to_home' => [
        __('Redirect 404 pages to the homepage', 'amrf-admin'),
        __('Applies to logged-out visitors only. Turn off if this site should show a real 404 page instead.', 'amrf-admin'),
      ],
      'disable_site_search' => [
        __('Disable site search', 'amrf-admin'),
        __('Turns the built-in WordPress search into a 404 for every visitor — useful while a site is still under construction and shouldn\'t expose a working search box yet.', 'amrf-admin'),
      ],
      'remove_jquery_migrate' => [
        __('Remove jQuery Migrate', 'amrf-admin'),
        __('Turn off if an older plugin or theme on this site depends on jQuery Migrate\'s compatibility shims.', 'amrf-admin'),
      ],
    ];

    $this->registerCheckboxFields($fields, self::TAB_PAGE_SLUG_FRONTEND, 'hardening_frontend_section');
  }

  private function registerCheckboxFields(array $fields, string $page_slug, string $section): void
  {
    foreach ($fields as $key => [$label, $description]) {
      add_settings_field(
        $key,
        $label,
        function () use ($key, $description) {
          $this->renderCheckbox($key, $description);
        },
        $page_slug,
        $section
      );
    }
  }

  /**
   * Same .switch/.slider markup as Settings\Manager::renderCheckbox().
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
   * @return void
   */
  private function renderImageUploadDimensionsFields(): void
  {
    $settings = Repository::getSettings();

    printf(
      '<label>%1$s <input type="number" min="1" name="%2$s[optimize_non_admin_image_uploads_width]" value="%3$d" /></label> '
        . '<label style="margin-left:1em;">%4$s <input type="number" min="1" name="%2$s[optimize_non_admin_image_uploads_height]" value="%5$d" /></label>'
        . '<p class="description">%6$s</p>',
      esc_html__('Width', 'amrf-admin'),
      esc_attr(Repository::OPTION_NAME),
      (int) $settings['optimize_non_admin_image_uploads_width'],
      esc_html__('Height', 'amrf-admin'),
      (int) $settings['optimize_non_admin_image_uploads_height'],
      esc_html__('Images larger than this, in either dimension, are scaled down proportionally (no cropping) before being converted to WebP.', 'amrf-admin')
    );
  }

  /**
   * Shared .switch/.slider styles, enqueued unconditionally — cheap, scoped
   * class names.
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
