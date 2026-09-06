<?php

namespace Antropomorf\SiteSettings;

if (!defined('ABSPATH')) {
  exit;
}

/**
 * Class Provider
 *
 * Registers the SEO/Business & Contact/Address/Social Media tabs onto the
 * amrf_site_settings_tabs registry (see Admin\SiteSettingsMenu). All four
 * tabs save into the same option (Repository::OPTION_NAME) via one
 * option_group, each with its own page_slug so do_settings_sections() only
 * pulls that tab's section.
 *
 * @package Antropomorf\SiteSettings
 */
class Provider
{
  private const OPTION_GROUP = 'amrf_site_settings_group';

  /** AJAX action + nonce action for the "discourage search engines" toggle. */
  private const AJAX_ACTION = 'amrf_toggle_search_engine_visibility';

  /** Guards register_setting()/add_settings_section()/add_settings_field() against running once per tab. */
  private static bool $registered = false;

  public function __construct()
  {
    add_filter('amrf_site_settings_tabs', [$this, 'registerTabs']);
    add_action('admin_enqueue_scripts', [$this, 'enqueueMediaScript']);
    add_action('admin_enqueue_scripts', [$this, 'enqueueSwitchStyles']);
    add_action('admin_enqueue_scripts', [$this, 'enqueueSearchVisibilityScript']);
    add_action('wp_ajax_' . self::AJAX_ACTION, [$this, 'ajaxToggleSearchEngineVisibility']);

    // wp-admin/options.php hardcodes manage_options to save any Settings
    // API form, regardless of what capability reached the page.
    add_filter('option_page_capability_' . self::OPTION_GROUP, function () {
      return 'edit_theme_options';
    });
  }

  /**
   * @param array $tabs Tabs registered so far by other callbacks on this filter.
   * @return array Tabs with one entry per Repository section appended.
   */
  public function registerTabs(array $tabs): array
  {
    foreach (Repository::getSections() as $section_key => $section_label) {
      $tabs[$section_key] = [
        'label' => $section_label,
        'option_group' => self::OPTION_GROUP,
        'page_slug' => self::pageSlug($section_key),
        'show_reset' => false,
        'register' => [$this, 'register'],
      ];
    }

    return $tabs;
  }

  /**
   * Called via each tab's 'register' callback from the tabs registry
   * (Admin\SiteSettingsMenu::registerSettings(), on admin_init) — registers
   * everything in one pass regardless of which tab triggered it first, then
   * no-ops on the remaining 3 calls.
   *
   * @return void
   */
  public function register(): void
  {
    if (self::$registered) {
      return;
    }
    self::$registered = true;

    register_setting(
      self::OPTION_GROUP,
      Repository::OPTION_NAME,
      [Repository::class, 'sanitize']
    );

    foreach (Repository::getSections() as $section_key => $section_label) {
      $page_slug = self::pageSlug($section_key);
      $section_id = 'site_settings_section_' . $section_key;
      add_settings_section($section_id, '', '__return_false', $page_slug);

      // Rendered first, ahead of getFields() below — WP's own
      // "discourage search engines" setting, not one of this plugin's
      // fields, but it decides whether the rest of this tab does anything.
      if ($section_key === 'seo') {
        add_settings_field(
          'site_settings_discourage_search_engines',
          __('Discourage search engines from indexing this site', 'amrf-admin'),
          [$this, 'renderDiscourageSearchEnginesField'],
          $page_slug,
          $section_id
        );
      }

      foreach (Repository::getFields() as $field_key => $field) {
        [$label, $type, $field_section] = $field;
        if ($field_section !== $section_key) {
          continue;
        }

        add_settings_field(
          'site_settings_' . $field_key,
          $label,
          function () use ($field_key, $type) {
            $this->renderField($field_key, $type);
          },
          $page_slug,
          $section_id
        );
      }
    }
  }

  /**
   * @param string $section_key One of Repository::getSections()'s keys.
   * @return string Settings API page slug for that section's own tab.
   */
  private static function pageSlug(string $section_key): string
  {
    return 'amrf-site-settings-' . $section_key;
  }

  /**
   * Renders one field by its declared type. "url" fields render as plain
   * text inputs, not <input type="url"> — an HTML5 url input silently
   * blocks the whole form's submission on one malformed value with no
   * visible error. esc_url_raw() (Repository::sanitize()) still sanitizes.
   *
   * @param string $key  Field key, matches a Repository::getFields() entry.
   * @param string $type Field type, matches a Repository::getFields() entry.
   * @return void
   */
  private function renderField(string $key, string $type): void
  {
    $settings = Repository::getSettings();
    $value = $settings[$key] ?? '';
    $field_id = Repository::OPTION_NAME . '_' . $key;
    $field_name = Repository::OPTION_NAME . '[' . $key . ']';

    if ($type === 'textarea') {
      printf(
        '<textarea id="%1$s" name="%2$s" class="large-text" rows="3">%3$s</textarea>',
        esc_attr($field_id),
        esc_attr($field_name),
        esc_textarea($value)
      );
      return;
    }

    if ($type === 'media') {
      $this->renderMediaField($field_id, $field_name, $value);
      return;
    }

    if ($type === 'checkbox') {
      $this->renderCheckboxField($key, $field_name, $value);
      return;
    }

    if ($type === 'page_list') {
      $this->renderPageListField($key, $field_name, $value);
      return;
    }

    $html_type = $type === 'url' ? 'text' : $type;
    printf(
      '<input type="%1$s" id="%2$s" name="%3$s" value="%4$s" class="regular-text" />',
      esc_attr($html_type),
      esc_attr($field_id),
      esc_attr($field_name),
      esc_attr($value)
    );
  }

  /**
   * Toggle for WP's own blog_public option, surfaced here since it's what
   * Repository::isSeoOutputEnabled() gates the rest of this tab on. Same
   * .switch/.slider markup as renderCheckboxField() but has no "name"
   * attribute — it never goes through $_POST/sanitize(), only
   * amrf-search-visibility-toggle.js via admin-ajax.php
   * (ajaxToggleSearchEngineVisibility()).
   *
   * @return void
   */
  public function renderDiscourageSearchEnginesField(): void
  {
    printf(
      '<label class="switch"><input type="checkbox" id="amrf-discourage-search-engines" %s /><span class="slider round"></span></label>',
      checked(Repository::isSearchEngineDiscouraged(), true, false)
    );
    echo '<p class="description">' . esc_html__(
      'Mirrors the "Discourage search engines from indexing this site" setting under Settings → Reading and applies immediately. While this is on, the rest of this tab is locked, since none of it has any effect until search engines are allowed back in.',
      'amrf-admin'
    ) . '</p>';
  }

  /**
   * AJAX save target for the discourage-search-engines toggle — writes
   * straight to blog_public. Same edit_theme_options gate as the rest of
   * this tab, not core's own manage_options.
   *
   * @return void
   */
  public function ajaxToggleSearchEngineVisibility(): void
  {
    check_ajax_referer(self::AJAX_ACTION, 'nonce');

    if (!current_user_can('edit_theme_options')) {
      wp_send_json_error(['message' => __('You are not allowed to change this setting.', 'amrf-admin')], 403);
    }

    $discourage = !empty($_POST['discourage']);
    update_option('blog_public', $discourage ? '0' : '1');

    wp_send_json_success(['discourage' => $discourage]);
  }

  /**
   * Renders a toggle checkbox, same .switch/.slider markup as
   * Settings\Manager::renderCheckbox(). Also emits a "{$key}_submitted"
   * hidden marker so Repository::sanitize() can tell "submitted unchecked"
   * apart from "a different tab was submitted".
   *
   * @param string $key   Field key.
   * @param string $field_name Full input name (OPTION_NAME[key]).
   * @param string $value Current stored value ('1' or '').
   * @return void
   */
  private function renderCheckboxField(string $key, string $field_name, string $value): void
  {
    // Not "{$field_name}_submitted" -- PHP's form parser ignores anything
    // after a bracket group's final "]", so that would collide with and
    // overwrite option[key] itself. Must be its own key, "option[key_submitted]".
    $submitted_name = Repository::OPTION_NAME . '[' . $key . '_submitted]';

    printf(
      '<input type="hidden" name="%1$s" value="1" />'
        . '<label class="switch"><input type="checkbox" name="%2$s" value="1" %3$s /><span class="slider round"></span></label>',
      esc_attr($submitted_name),
      esc_attr($field_name),
      checked($value, '1', false)
    );
  }

  /**
   * A checkbox list of every published page (title + ID), same
   * "{key}_submitted" marker mechanism as renderCheckboxField().
   *
   * @param string $key
   * @param string $field_name
   * @param string $value Comma-separated page IDs.
   * @return void
   */
  private function renderPageListField(string $key, string $field_name, string $value): void
  {
    $selected = array_map('absint', array_filter(explode(',', $value)));
    // Published only — WP's own sitemap always filters to publish anyway.
    $pages = get_pages(['sort_column' => 'post_title', 'post_status' => 'publish']);
    $submitted_name = Repository::OPTION_NAME . '[' . $key . '_submitted]';

    printf('<input type="hidden" name="%s" value="1" />', esc_attr($submitted_name));

    if (empty($pages)) {
      echo '<p class="description">' . esc_html__('No pages found.', 'amrf-admin') . '</p>';
      return;
    }

    // Same .menu-items-container/.menu-item-checkbox markup as Allowed
    // Menu Items (Settings\Manager::userRoleSettingsCallback()).
    echo '<div class="menu-items-container">';
    foreach ($pages as $page) {
      printf(
        '<div class="menu-item-checkbox"><input type="checkbox" name="%1$s[]" value="%2$d" %3$s /><label>%4$s <code>(ID: %2$d)</code></label></div>',
        esc_attr($field_name),
        $page->ID,
        checked(in_array($page->ID, $selected, true), true, false),
        esc_html($page->post_title)
      );
    }
    echo '</div>';
  }

  private function renderMediaField(string $field_id, string $field_name, string $value): void
  {
    ?>
    <div class="amrf-media-field">
      <img
        src="<?php echo esc_url($value); ?>"
        alt=""
        class="amrf-media-field__preview"
        style="display:<?php echo $value ? 'block' : 'none'; ?>;max-width:200px;max-height:200px;margin-bottom:8px;"
      />
      <input
        type="hidden"
        id="<?php echo esc_attr($field_id); ?>"
        name="<?php echo esc_attr($field_name); ?>"
        value="<?php echo esc_attr($value); ?>"
        class="amrf-media-field__input"
      />
      <p>
        <button
          type="button"
          class="button amrf-media-field__choose"
          data-title="<?php echo esc_attr__('Select image', 'amrf-admin'); ?>"
          data-button="<?php echo esc_attr__('Use this image', 'amrf-admin'); ?>"
        ><?php esc_html_e('Choose image', 'amrf-admin'); ?></button>
        <button
          type="button"
          class="button amrf-media-field__remove"
          style="display:<?php echo $value ? 'inline-block' : 'none'; ?>;"
        ><?php esc_html_e('Remove image', 'amrf-admin'); ?></button>
      </p>
    </div>
    <?php
  }

  /**
   * Enqueued unconditionally on the "Site Settings" page rather than
   * tracking which tab actually has the media field. Hook suffix is
   * "toplevel_page_amrf-site-settings" since this page shares its slug
   * with the top-level menu (Admin\SiteSettingsMenu::addMenu()).
   *
   * @param string $hook Current admin page hook suffix.
   * @return void
   */
  public function enqueueMediaScript(string $hook): void
  {
    if ($hook !== 'toplevel_page_amrf-site-settings') {
      return;
    }

    wp_enqueue_media();

    wp_enqueue_script(
      'amrf-media-field',
      AMRF_ADMIN_PLUGIN_URL . 'assets/js/amrf-media-field.js',
      ['jquery'],
      filemtime(AMRF_ADMIN_PLUGIN_DIR . '/assets/js/amrf-media-field.js'),
      true
    );
  }

  /**
   * Shared .switch/.slider styles, enqueued unconditionally here too so
   * this tab's toggles render correctly.
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

  /**
   * Only the SEO tab needs this script — checks $_GET['tab'] too, since
   * every tab shares the same hook suffix.
   *
   * @param string $hook Current admin page hook suffix.
   * @return void
   */
  public function enqueueSearchVisibilityScript(string $hook): void
  {
    if ($hook !== 'toplevel_page_amrf-site-settings') {
      return;
    }

    $current_tab = isset($_GET['tab']) && is_string($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : array_key_first(Repository::getSections());
    if ($current_tab !== 'seo') {
      return;
    }

    wp_enqueue_script(
      'amrf-search-visibility-toggle',
      AMRF_ADMIN_PLUGIN_URL . 'assets/js/amrf-search-visibility-toggle.js',
      ['jquery'],
      filemtime(AMRF_ADMIN_PLUGIN_DIR . '/assets/js/amrf-search-visibility-toggle.js'),
      true
    );

    wp_localize_script('amrf-search-visibility-toggle', 'amrfSearchVisibility', [
      'action' => self::AJAX_ACTION,
      'nonce' => wp_create_nonce(self::AJAX_ACTION),
    ]);
  }
}
