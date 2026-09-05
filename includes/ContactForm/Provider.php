<?php

namespace Antropomorf\ContactForm;

if (!defined('ABSPATH')) {
  exit;
}

/**
 * Class Provider
 *
 * Registers the "Contact Forms" page onto the amrf_site_settings_pages
 * registry (see Admin\SiteSettingsMenu, the shared "Site Settings" top-
 * level menu) — its own page rather than a tab, same as it was under this
 * module's previous "GDPR" name. Capability: edit_theme_options, same as
 * the menu's own default.
 *
 * Two sections: a general one (which FluentForm form the sitewide
 * "#kontakt" lightbox — see Modal — opens, and whether this plugin's own
 * FluentForm consistency CSS is applied sitewide), and a "GDPR" one
 * (unchanged from before: which forms the retention cron and personal-
 * data export/erase requests apply to, and for how long) — see Modal's
 * docblock and Repository's docblock for the reasoning behind each.
 *
 * @package Antropomorf\ContactForm
 */
class Provider
{
  private const OPTION_GROUP = 'amrf_contact_form_group';

  /**
   * Kept at its original 'amrf-site-settings-gdpr' value rather than
   * renamed to match this page's new "Contact Forms" title — a site's
   * saved amrf_admin_settings[user_group_settings][$role][allowed_menu_items]
   * (Settings\Repository) may already list this exact slug for a role
   * that was explicitly granted access to it; renaming the slug would
   * silently revoke that access on upgrade (the item just vanishes from
   * an already-saved allow-list, no error, no migration path back). The
   * slug is never shown to a site visitor or editor, only the page_title/
   * menu_title below are, so nothing is actually lost by keeping it.
   */
  private const PAGE_SLUG = 'amrf-site-settings-gdpr';

  public function __construct()
  {
    add_filter('amrf_site_settings_pages', [$this, 'registerPages']);

    // wp-admin/options.php hardcodes manage_options as the capability
    // required to actually SAVE a Settings API form, regardless of what
    // capability the page itself needed to be reached — see the identical
    // fix/comment in SiteSettings\Provider for the full explanation.
    add_filter('option_page_capability_' . self::OPTION_GROUP, function () {
      return 'edit_theme_options';
    });

    add_action('admin_enqueue_scripts', [$this, 'enqueueSwitchStyles']);
  }

  /**
   * The shared .switch/.slider toggle styles live in assets/css/amrf-
   * admin-settings.css, otherwise only loaded on the Admin Panel Settings/
   * Site Settings/Hardening pages — enqueued here too, unconditionally,
   * same posture as Hardening\Provider's own identical method (cheap,
   * scoped class names, no per-page hook check needed).
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
   * @param array $pages Pages registered so far by other callbacks on this filter.
   * @return array Pages with 'contact-forms' appended.
   */
  public function registerPages(array $pages): array
  {
    $pages['contact-forms'] = [
      'page_title' => __('Contact Forms', 'amrf-admin'),
      'menu_title' => __('Contact Forms', 'amrf-admin'),
      'capability' => 'edit_theme_options',
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

    add_settings_section('contact_form_section', '', '__return_false', self::PAGE_SLUG);

    add_settings_field(
      'default_contact_form_id',
      __('Default Contact Form', 'amrf-admin'),
      [$this, 'renderDefaultContactFormField'],
      self::PAGE_SLUG,
      'contact_form_section'
    );
    add_settings_field(
      'enable_consistent_styling',
      __('Apply Consistent Contact Form Styling', 'amrf-admin'),
      [$this, 'renderConsistentStylingField'],
      self::PAGE_SLUG,
      'contact_form_section'
    );
    add_settings_field(
      'altcha_enabled',
      __('Enable ALTCHA Spam Protection', 'amrf-admin'),
      [$this, 'renderAltchaEnabledField'],
      self::PAGE_SLUG,
      'contact_form_section'
    );
    // A real (non-empty) section title prints as its own <h2> via
    // do_settings_sections() — this is the visible "GDPR" heading the
    // feature asked for, not just a docblock label.
    add_settings_section('gdpr_section', __('GDPR', 'amrf-admin'), '__return_false', self::PAGE_SLUG);

    add_settings_field(
      'contact_form_ids',
      __('Contact Forms Subject to Retention', 'amrf-admin'),
      [$this, 'renderContactFormIdsField'],
      self::PAGE_SLUG,
      'gdpr_section'
    );
    add_settings_field(
      'retention_days',
      __('Delete submissions after this many days', 'amrf-admin'),
      [$this, 'renderRetentionDaysField'],
      self::PAGE_SLUG,
      'gdpr_section'
    );
  }

  /**
   * @return array<int, object{id: int, title: string}> Every FluentForm
   *         form that actually exists and is published, id ascending. []
   *         if FluentForm itself isn't installed/active.
   */
  private function getPublishedForms(): array
  {
    if (!shortcode_exists('fluentform')) {
      return [];
    }

    global $wpdb;
    return $wpdb->get_results(
      "SELECT id, title FROM {$wpdb->prefix}fluentform_forms WHERE status = 'published' ORDER BY id ASC"
    );
  }

  public function renderDefaultContactFormField(): void
  {
    $forms = $this->getPublishedForms();
    $current = Repository::getDefaultContactFormId();
    $id = Repository::OPTION_NAME . '_default_contact_form_id';
    $name = Repository::OPTION_NAME . '[default_contact_form_id]';

    if (empty($forms)) {
      echo '<p class="description">' . esc_html__('No FluentForm forms found.', 'amrf-admin') . '</p>';
      return;
    }

    printf('<select id="%1$s" name="%2$s">', esc_attr($id), esc_attr($name));
    foreach ($forms as $form) {
      printf(
        '<option value="%1$d" %2$s>%3$s (ID: %1$d)</option>',
        $form->id,
        selected($current, $form->id, false),
        esc_html($form->title)
      );
    }
    echo '</select>';
    echo '<p class="description">' . esc_html__('The form the sitewide "#kontakt" link/button opens in a lightbox.', 'amrf-admin') . '</p>';
  }

  /**
   * Same .switch/.slider toggle markup as Settings\Manager::renderCheckbox()
   * and Hardening\Provider::renderCheckbox() — amrf-admin-settings.css
   * (already enqueued for this page, see registerPages()) styles it
   * identically wherever it's used.
   *
   * @return void
   */
  public function renderConsistentStylingField(): void
  {
    $enabled = Repository::isConsistentStylingEnabled();
    $name = Repository::OPTION_NAME . '[enable_consistent_styling]';
    $submitted_name = Repository::OPTION_NAME . '[enable_consistent_styling_submitted]';

    printf('<input type="hidden" name="%s" value="1" />', esc_attr($submitted_name));
    printf(
      '<label class="switch"><input type="checkbox" name="%1$s" value="1" %2$s /><span class="slider round"></span></label><p class="description">%3$s</p>',
      esc_attr($name),
      checked($enabled, true, false),
      esc_html__('Maps FluentForm\'s own color/border-radius variables to this site\'s theme colors and applies additional styling fixes, on every FluentForm on the site.', 'amrf-admin')
    );
  }

  /**
   * On by default (Repository::getDefaults()) — this toggle exists only
   * for a site that wants to run its own spam protection instead (or
   * none at all), not to make ALTCHA opt-in. The signing secret itself
   * (Repository::getAltchaHmacKey()) has no field here at all — see that
   * method's own docblock for why.
   *
   * @return void
   */
  public function renderAltchaEnabledField(): void
  {
    $enabled = Repository::isAltchaEnabled();
    $name = Repository::OPTION_NAME . '[altcha_enabled]';
    $submitted_name = Repository::OPTION_NAME . '[altcha_enabled_submitted]';

    printf('<input type="hidden" name="%s" value="1" />', esc_attr($submitted_name));
    printf(
      '<label class="switch"><input type="checkbox" name="%1$s" value="1" %2$s /><span class="slider round"></span></label><p class="description">%3$s</p>',
      esc_attr($name),
      checked($enabled, true, false),
      esc_html__('Adds invisible, no-configuration spam protection to every FluentForm on the site. Turn off if this site already handles spam protection another way (e.g. its own plugin).', 'amrf-admin')
    );
  }

  /**
   * Same "_submitted marker" + .menu-items-container/.menu-item-checkbox
   * markup as SiteSettings\Provider::renderPageListField() — reusing the
   * exact class names/structure gets an identical look for free (CSS
   * already ships in assets/css/amrf-admin-settings.css).
   *
   * @return void
   */
  public function renderContactFormIdsField(): void
  {
    $forms = $this->getPublishedForms();
    $selected = Repository::getContactFormIds();
    $field_name = Repository::OPTION_NAME . '[contact_form_ids]';
    $submitted_name = Repository::OPTION_NAME . '[contact_form_ids_submitted]';

    printf('<input type="hidden" name="%s" value="1" />', esc_attr($submitted_name));

    if (empty($forms)) {
      echo '<p class="description">' . esc_html__('No FluentForm forms found.', 'amrf-admin') . '</p>';
      return;
    }

    echo '<div class="menu-items-container">';
    foreach ($forms as $form) {
      printf(
        '<div class="menu-item-checkbox"><input type="checkbox" name="%1$s[]" value="%2$d" %3$s /><label>%4$s <code>(ID: %2$d)</code></label></div>',
        esc_attr($field_name),
        $form->id,
        checked(in_array((int) $form->id, $selected, true), true, false),
        esc_html($form->title)
      );
    }
    echo '</div>';
    echo '<p class="description">' . esc_html__('The daily retention cleanup and personal-data export/erase requests only ever touch these forms.', 'amrf-admin') . '</p>';
  }

  public function renderRetentionDaysField(): void
  {
    // Repository::getSettings()'s raw string, not getRetentionDays()'s
    // absint() — an unset value must render as a blank field, not "0",
    // the same distinction the option's own default ('') vs. an explicit
    // 0 preserves.
    $days = Repository::getSettings()['retention_days'];
    $id = Repository::OPTION_NAME . '_retention_days';
    $name = Repository::OPTION_NAME . '[retention_days]';

    printf(
      '<input type="number" id="%1$s" name="%2$s" value="%3$s" min="0" class="small-text" /><p class="description">%4$s</p>',
      esc_attr($id),
      esc_attr($name),
      esc_attr($days),
      esc_html__('Blank or 0 = keep forever.', 'amrf-admin')
    );
  }
}
