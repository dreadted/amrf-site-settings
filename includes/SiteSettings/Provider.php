<?php

namespace Antropomorf\SiteSettings;

if (!defined('ABSPATH')) {
  exit;
}

/**
 * Class Provider
 *
 * Registers the SEO/Business & Contact/Address/Social Media tabs — one per
 * Repository::getSections() entry — onto the amrf_site_settings_tabs
 * registry (see Admin\SiteSettingsMenu, the shared "Site Settings" top-level
 * menu these tabs share a single page under). Same mechanism as the
 * plugin's own General/role tabs on amrf_admin_settings_tabs
 * (Settings\Manager), just a different filter/page — see
 * Admin\SiteSettingsMenu's docblock for why these two tab groups stay
 * separate rather than one overloaded filter.
 *
 * All four tabs save into the SAME option (Repository::OPTION_NAME) via one
 * option_group, but each gets its own page_slug so do_settings_sections()
 * only pulls that one tab's section — sharing an option_group across
 * multiple pages/tabs is an ordinary, supported Settings API pattern.
 *
 * @package Antropomorf\SiteSettings
 */
class Provider
{
  private const OPTION_GROUP = 'amrf_site_settings_group';

  /**
   * Both the AJAX action name and its nonce action — the toggle rendered by
   * renderDiscourageSearchEnginesField() writes straight to WordPress's own
   * blog_public option via admin-ajax.php, not through this tab's normal
   * Settings API save (see that method's docblock for why: it has to apply
   * immediately, without a page reload, so the rest of the tab can lock/
   * unlock right away).
   */
  private const AJAX_ACTION = 'amrf_toggle_search_engine_visibility';

  /**
   * Guards register_setting()/add_settings_section()/add_settings_field()
   * against running once per tab (registerTabs() below returns 4 tabs that
   * all point at register() as their 'register' callback) — same pattern as
   * Settings\Manager::ensureSettingRegistered().
   */
  private static bool $registered = false;

  public function __construct()
  {
    add_filter('amrf_site_settings_tabs', [$this, 'registerTabs']);
    add_action('admin_enqueue_scripts', [$this, 'enqueueMediaScript']);
    add_action('admin_enqueue_scripts', [$this, 'enqueueSwitchStyles']);
    add_action('admin_enqueue_scripts', [$this, 'enqueueSearchVisibilityScript']);
    add_action('wp_ajax_' . self::AJAX_ACTION, [$this, 'ajaxToggleSearchEngineVisibility']);

    // wp-admin/options.php (where every Settings API form posts to) hardcodes
    // manage_options as the capability required to actually SAVE, regardless
    // of what capability the page itself needed to be reached — completely
    // separate from Admin\SiteSettingsMenu's own edit_theme_options gate on
    // the menu. Without this filter, a user who can see this tab (granted
    // edit_theme_options via the site_menus_cap role toggle) still gets
    // "you are not allowed to manage options for this site" on submit.
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

      // Rendered first, ahead of every field from getFields() below — this
      // is WordPress's own site-wide "discourage search engines" setting
      // (blog_public), not one of this plugin's fields, but it belongs at
      // the top of the SEO tab specifically since it's what makes the rest
      // of this tab's output meaningful or moot. See
      // renderDiscourageSearchEnginesField()'s own docblock.
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
   * Renders one field by its declared type — text/email/number inputs,
   * a textarea, or the media picker. "url" fields deliberately render as
   * a plain text input, not <input type="url"> — an HTML5 url input
   * silently blocks the *entire* form's submission if any one of them
   * holds a non-empty value without a URL scheme, with no visible error
   * if that field happens to be scrolled out of view. Server-side
   * esc_url_raw() (Repository::sanitize()) still handles the actual
   * sanitization either way.
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
   * Toggle for WordPress's own blog_public option (Settings > Reading's
   * "Discourage search engines from indexing this site"), surfaced here
   * too since it's what Repository::isSeoOutputEnabled() gates the rest of
   * this tab's output on — a site owner flipping this on should see, right
   * here, that everything else below just went inert, not discover it by
   * noticing meta tags disappeared from view-source.
   *
   * Same .switch/.slider markup and label-wrapping as renderCheckboxField()
   * so it's visually indistinguishable from Enable SEO Output, but it's
   * deliberately NOT one of Repository::getFields() — it has no "name"
   * attribute at all, so it never round-trips through $_POST/sanitize() on
   * a normal Save. amrf-search-visibility-toggle.js drives it entirely via
   * admin-ajax.php (ajaxToggleSearchEngineVisibility()), applying the
   * change immediately and locking/unlocking every other field on this tab
   * to match — no Save click needed, and no stale "saved" state possible.
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
   * AJAX save target for renderDiscourageSearchEnginesField()'s toggle —
   * writes straight to WordPress's own blog_public option instead of going
   * through Repository::sanitize()/this tab's option_group, since this
   * isn't one of this plugin's own settings. Same edit_theme_options gate
   * as the rest of this tab (Provider's own option_page_capability filter),
   * not manage_options as Settings > Reading itself requires, so a role
   * granted access to this page doesn't hit a mismatched capability wall
   * flipping the exact setting this tab tells them about.
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
   * Renders a toggle checkbox, same .switch/.slider markup used by
   * Settings\Manager::renderCheckbox() and Hardening\Provider::renderCheckbox()
   * so it looks identical everywhere in the plugin.
   *
   * Also emits a "{$key}_submitted" hidden marker: a checkbox is simply
   * absent from $_POST when unchecked, and this option is shared across
   * four tabs that each submit only their own fields — without this marker
   * Repository::sanitize() can't tell "the SEO tab was submitted with this
   * unchecked" apart from "a different tab was submitted, leave this
   * untouched" (see sanitize()'s own comment on this).
   *
   * @param string $key   Field key.
   * @param string $field_name Full input name (OPTION_NAME[key]).
   * @param string $value Current stored value ('1' or '').
   * @return void
   */
  private function renderCheckboxField(string $key, string $field_name, string $value): void
  {
    // Deliberately NOT "{$field_name}_submitted" -- appending outside the
    // closing "]" doesn't do what it looks like it does. PHP's form
    // parser ignores everything after a bracket group's final "]" that
    // isn't itself a new "[...]", so name="option[key]_submitted" is
    // parsed as if "_submitted" weren't there at all: it collides with
    // and silently overwrites option[key] itself (confirmed by testing
    // parse_str() directly) -- which is exactly why this toggle refused
    // to ever save as unchecked. The submitted-marker has to be its own
    // distinct array key, "option[key_submitted]".
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
   * A checkbox list of every published page, its title alongside its own
   * database ID (per-page-ID visibility was requested explicitly, rather
   * than a page-title-only picker) — same "{key}_submitted" marker
   * mechanism as renderCheckboxField(), for the same reason: with nothing
   * checked, the whole field is simply absent from $_POST, which
   * Repository::sanitize() needs to be able to tell apart from "a
   * different tab was submitted".
   *
   * @param string $key
   * @param string $field_name
   * @param string $value Comma-separated page IDs.
   * @return void
   */
  private function renderPageListField(string $key, string $field_name, string $value): void
  {
    $selected = array_map('absint', array_filter(explode(',', $value)));
    // Published only, deliberately -- WordPress's own sitemap always
    // filters to post_status=publish regardless of this selection, so a
    // draft/private page picked here would just silently never appear in
    // the actual sitemap, making the toggle look broken when it isn't.
    $pages = get_pages(['sort_column' => 'post_title', 'post_status' => 'publish']);
    $submitted_name = Repository::OPTION_NAME . '[' . $key . '_submitted]';

    printf('<input type="hidden" name="%s" value="1" />', esc_attr($submitted_name));

    if (empty($pages)) {
      echo '<p class="description">' . esc_html__('No pages found.', 'amrf-admin') . '</p>';
      return;
    }

    // Same .menu-items-container/.menu-item-checkbox markup as Allowed
    // Menu Items (Settings\Manager::userRoleSettingsCallback()) — the CSS
    // for both already ships in assets/css/amrf-admin-settings.css, so
    // reusing the exact class names/structure gets an identical look for
    // free instead of styling a second, slightly-different list.
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
   * Only needed on the plugin's own "Site Settings" page (every SEO/Business/
   * Address/Social tab renders there) — a 'media' field might not even be on
   * the currently viewed tab, but enqueuing wp_enqueue_media() unconditionally
   * there is the same cost the original ptsussis-theme page paid, and far
   * simpler than tracking exactly which tab needs it per request.
   *
   * Hook suffix: this page is registered as the FIRST submenu under the new
   * top-level "Site Settings" menu, with the same slug as the menu itself
   * (Admin\SiteSettingsMenu::addMenu()) — WordPress's naming convention for
   * that case is "toplevel_page_{slug}", same idea as the "settings_page_"
   * prefix a submenu-under-Settings gets.
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
   * The shared .switch/.slider toggle styles live in assets/css/amrf-
   * admin-settings.css, otherwise only loaded on the Admin Panel Settings
   * page (SettingsPage::enqueueAssets()) — enqueued here too,
   * unconditionally, same posture as Hardening\Provider's own switch-
   * styles enqueue, so the enable_seo_output toggle actually looks like a
   * toggle here instead of a bare checkbox.
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
   * Only the SEO tab has renderDiscourageSearchEnginesField()'s toggle (and
   * only its fields need locking), so this checks $_GET['tab'] on top of
   * enqueueMediaScript()'s hook-suffix check rather than reusing that
   * method outright — every tab shares the same physical admin page/hook
   * suffix (SettingsRenderer switches tabs via $_GET, not separate pages),
   * so the hook check alone can't tell them apart.
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
