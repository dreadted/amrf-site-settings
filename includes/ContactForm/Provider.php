<?php

namespace Antropomorf\ContactForm;

if (!defined('ABSPATH')) {
  exit;
}

/**
 * Class Provider
 *
 * Registers the "Contact Forms" and "GDPR" tabs onto amrf_forms_tabs (see
 * Forms\Menu, the shared "Forms" page). Both tabs share one option
 * (Repository::OPTION_NAME) via one option_group.
 *
 * @package Antropomorf\ContactForm
 */
class Provider
{
  private const OPTION_GROUP = 'amrf_contact_form_group';

  /** Internal Settings API page slugs — never shown as an admin menu item. */
  private const CONTACT_PAGE_SLUG = 'amrf-forms-contact';
  private const GDPR_PAGE_SLUG = 'amrf-forms-gdpr';

  public function __construct()
  {
    add_filter('amrf_forms_tabs', [$this, 'registerTabs']);

    // wp-admin/options.php hardcodes manage_options to save any Settings
    // API form, regardless of what capability reached the page.
    add_filter('option_page_capability_' . self::OPTION_GROUP, function () {
      return 'edit_theme_options';
    });

    add_action('admin_enqueue_scripts', [$this, 'enqueueSwitchStyles']);
  }

  /**
   * Shared .switch/.slider styles (assets/css/amrf-admin-settings.css),
   * enqueued unconditionally — cheap, scoped class names.
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
   * @param array $tabs Tabs registered so far by other callbacks on this filter.
   * @return array Tabs with 'contact-forms' and 'gdpr' appended.
   */
  public function registerTabs(array $tabs): array
  {
    $tabs['contact-forms'] = [
      'label' => __('Contact Forms', 'amrf-admin'),
      'option_group' => self::OPTION_GROUP,
      'page_slug' => self::CONTACT_PAGE_SLUG,
      'show_reset' => false,
      'register' => [$this, 'registerContactFormsFields'],
    ];

    $tabs['gdpr'] = [
      'label' => __('GDPR', 'amrf-admin'),
      'option_group' => self::OPTION_GROUP,
      'page_slug' => self::GDPR_PAGE_SLUG,
      'show_reset' => false,
      'register' => [$this, 'registerGdprFields'],
    ];

    return $tabs;
  }

  /**
   * Called via the 'contact-forms' tab's 'register' callback, on admin_init.
   * register_setting() is repeated in registerGdprFields() too — a harmless
   * repeat call, and either tab can load first.
   *
   * @return void
   */
  public function registerContactFormsFields(): void
  {
    register_setting(self::OPTION_GROUP, Repository::OPTION_NAME, [Repository::class, 'sanitize']);

    add_settings_section('contact_form_section', '', '__return_false', self::CONTACT_PAGE_SLUG);

    add_settings_field(
      'default_contact_form_id',
      __('Default Contact Form', 'amrf-admin'),
      [$this, 'renderDefaultContactFormField'],
      self::CONTACT_PAGE_SLUG,
      'contact_form_section'
    );
    add_settings_field(
      'enable_consistent_styling',
      __('Apply Consistent Contact Form Styling', 'amrf-admin'),
      [$this, 'renderConsistentStylingField'],
      self::CONTACT_PAGE_SLUG,
      'contact_form_section'
    );
    add_settings_field(
      'altcha_enabled',
      __('Enable ALTCHA Spam Protection', 'amrf-admin'),
      [$this, 'renderAltchaEnabledField'],
      self::CONTACT_PAGE_SLUG,
      'contact_form_section'
    );
  }

  /**
   * Called via the 'gdpr' tab's 'register' callback, on admin_init.
   *
   * @return void
   */
  public function registerGdprFields(): void
  {
    register_setting(self::OPTION_GROUP, Repository::OPTION_NAME, [Repository::class, 'sanitize']);

    // No section title needed — the tab itself is already labeled "GDPR".
    add_settings_section('gdpr_section', '', '__return_false', self::GDPR_PAGE_SLUG);

    add_settings_field(
      'contact_form_ids',
      __('Contact Forms Subject to Retention', 'amrf-admin'),
      [$this, 'renderContactFormIdsField'],
      self::GDPR_PAGE_SLUG,
      'gdpr_section'
    );
    add_settings_field(
      'retention_days',
      __('Delete submissions after this many days', 'amrf-admin'),
      [$this, 'renderRetentionDaysField'],
      self::GDPR_PAGE_SLUG,
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
   * Same .switch/.slider markup as Settings\Manager::renderCheckbox().
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
   * On by default — exists only to let a site opt out in favor of its own
   * spam protection.
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
   * Same "_submitted" marker + checkbox-list markup as
   * SiteSettings\Provider::renderPageListField().
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
    // Raw string, not getRetentionDays()'s absint() — unset must render
    // blank, not "0".
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
