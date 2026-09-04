<?php

namespace Antropomorf\FluentFormPrivacy;

if (!defined('ABSPATH')) {
  exit;
}

/**
 * Class Provider
 *
 * Registers a "GDPR" submenu page onto the amrf_site_settings_pages registry
 * (see Admin\SiteSettingsMenu, the shared "Site Settings" top-level menu) —
 * its own page rather than a tab. Capability: edit_theme_options, same as
 * the menu's own default (previously manage_options, stricter than the
 * contact-info tabs on that same menu — reversed on request so Editors with
 * the site_menus_cap role toggle can manage retention/export settings too,
 * not just administrators).
 *
 * @package Antropomorf\FluentFormPrivacy
 */
class Provider
{
  private const PAGE_SLUG = 'amrf-site-settings-gdpr';
  private const OPTION_GROUP = 'amrf_fluentform_privacy_group';

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
  }

  /**
   * @param array $pages Pages registered so far by other callbacks on this filter.
   * @return array Pages with 'gdpr' appended.
   */
  public function registerPages(array $pages): array
  {
    $pages['gdpr'] = [
      'page_title' => __('GDPR', 'amrf-admin'),
      'menu_title' => __('GDPR', 'amrf-admin'),
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

    add_settings_section('fluentform_privacy_section', '', '__return_false', self::PAGE_SLUG);

    add_settings_field(
      'form_ids',
      __('Form IDs', 'amrf-admin'),
      [$this, 'renderFormIdsField'],
      self::PAGE_SLUG,
      'fluentform_privacy_section'
    );
    add_settings_field(
      'retention_days',
      __('Delete submissions after this many days', 'amrf-admin'),
      [$this, 'renderRetentionDaysField'],
      self::PAGE_SLUG,
      'fluentform_privacy_section'
    );
  }

  public function renderFormIdsField(): void
  {
    $settings = Repository::getSettings();
    $id = Repository::OPTION_NAME . '_form_ids';
    $name = Repository::OPTION_NAME . '[form_ids]';

    printf(
      '<input type="text" id="%1$s" name="%2$s" value="%3$s" class="regular-text" placeholder="1, 2, 3" /><p class="description">%4$s</p>',
      esc_attr($id),
      esc_attr($name),
      esc_attr($settings['form_ids']),
      esc_html__('Comma-separated FluentForm form IDs this applies to — the daily retention cleanup and personal-data export/erase requests only ever touch these forms.', 'amrf-admin')
    );
  }

  public function renderRetentionDaysField(): void
  {
    $settings = Repository::getSettings();
    $id = Repository::OPTION_NAME . '_retention_days';
    $name = Repository::OPTION_NAME . '[retention_days]';

    printf(
      '<input type="number" id="%1$s" name="%2$s" value="%3$s" min="0" class="small-text" /><p class="description">%4$s</p>',
      esc_attr($id),
      esc_attr($name),
      esc_attr($settings['retention_days']),
      esc_html__('Blank or 0 = keep forever.', 'amrf-admin')
    );
  }
}
