<?php

namespace Antropomorf\Swish;

if (!defined('ABSPATH')) {
  exit;
}

/**
 * Class Provider
 *
 * Registers the "Swish" tab onto amrf_forms_tabs (see Forms\Menu, the
 * shared "Forms" page these tabs live under) — number/amount/message plus
 * an "editable after scanning" toggle for each of the latter two. Same
 * mechanism as ContactForm\Provider's own tabs on the same filter.
 *
 * @package Antropomorf\Swish
 */
class Provider
{
  private const OPTION_GROUP = 'amrf_swish_group';
  private const PAGE_SLUG = 'amrf-forms-swish';

  public function __construct()
  {
    add_filter('amrf_forms_tabs', [$this, 'registerTab']);

    // wp-admin/options.php hardcodes manage_options as the capability
    // required to actually SAVE a Settings API form, regardless of what
    // capability the page itself needed to be reached — see the identical
    // fix/comment in SiteSettings\Provider and ContactForm\Provider.
    add_filter('option_page_capability_' . self::OPTION_GROUP, function () {
      return 'edit_theme_options';
    });

    add_action('admin_enqueue_scripts', [$this, 'enqueueSwitchStyles']);
  }

  /**
   * @param array $tabs Tabs registered so far by other callbacks on this filter.
   * @return array Tabs with 'swish' appended.
   */
  public function registerTab(array $tabs): array
  {
    $tabs['swish'] = [
      'label' => __('Swish', 'amrf-admin'),
      'option_group' => self::OPTION_GROUP,
      'page_slug' => self::PAGE_SLUG,
      'show_reset' => false,
      'register' => [$this, 'register'],
    ];

    return $tabs;
  }

  /**
   * Called via this tab's 'register' callback from the tabs registry
   * (Forms\Menu's own SettingsRenderer, on admin_init).
   *
   * @return void
   */
  public function register(): void
  {
    register_setting(self::OPTION_GROUP, Repository::OPTION_NAME, [Repository::class, 'sanitize']);

    add_settings_section('swish_section', '', [$this, 'renderSectionIntro'], self::PAGE_SLUG);

    add_settings_field('swish_number', __('Swish number', 'amrf-admin'), [$this, 'renderNumberField'], self::PAGE_SLUG, 'swish_section');
    add_settings_field('swish_amount', __('Prefilled amount', 'amrf-admin'), [$this, 'renderAmountField'], self::PAGE_SLUG, 'swish_section');
    add_settings_field('swish_message', __('Prefilled message', 'amrf-admin'), [$this, 'renderMessageField'], self::PAGE_SLUG, 'swish_section');
  }

  public function renderSectionIntro(): void
  {
    echo '<p class="description">' . esc_html__(
      'Any link with the address "#swish" anywhere on the site opens this directly in the Swish app on a phone, or shows this QR code on a device that can\'t run it.',
      'amrf-admin'
    ) . '</p>';
  }

  public function renderNumberField(): void
  {
    $settings = Repository::getSettings();
    printf(
      '<input type="text" id="amrf_swish_number" name="%1$s" value="%2$s" class="regular-text" />',
      esc_attr(Repository::OPTION_NAME . '[number]'),
      esc_attr($settings['number'])
    );

    if ($settings['qr_url'] !== '') {
      printf(
        '<p style="margin-top:10px;"><img src="%1$s" alt="%2$s" style="max-width:160px;height:auto;" /></p>',
        esc_url($settings['qr_url']),
        esc_attr__('Current Swish QR code', 'amrf-admin')
      );
    }
  }

  /**
   * Amount + its own "editable after scanning" toggle, side by side — same
   * .switch/.slider markup as every other toggle in this plugin (assets/css/
   * amrf-admin-settings.css).
   *
   * @return void
   */
  public function renderAmountField(): void
  {
    $settings = Repository::getSettings();
    printf(
      '<input type="text" id="amrf_swish_amount" name="%1$s" value="%2$s" class="small-text" inputmode="decimal" />',
      esc_attr(Repository::OPTION_NAME . '[amount]'),
      esc_attr($settings['amount'])
    );
    $this->renderEditableToggle('amount_editable', $settings['amount_editable'] === '1');
    echo '<p class="description">' . esc_html__('Blank = no amount prefilled.', 'amrf-admin') . '</p>';
  }

  public function renderMessageField(): void
  {
    $settings = Repository::getSettings();
    printf(
      '<input type="text" id="amrf_swish_message" name="%1$s" value="%2$s" class="regular-text" />',
      esc_attr(Repository::OPTION_NAME . '[message]'),
      esc_attr($settings['message'])
    );
    $this->renderEditableToggle('message_editable', $settings['message_editable'] === '1');
  }

  /**
   * @param string $key     'amount_editable' or 'message_editable'.
   * @param bool   $checked
   * @return void
   */
  private function renderEditableToggle(string $key, bool $checked): void
  {
    printf(
      ' <label class="switch" style="vertical-align:middle;margin-left:8px;"><input type="checkbox" name="%1$s" value="1" %2$s /><span class="slider round"></span></label> <span class="description">%3$s</span>',
      esc_attr(Repository::OPTION_NAME . '[' . $key . ']'),
      checked($checked, true, false),
      esc_html__('Editable by the payer after scanning', 'amrf-admin')
    );
  }

  /**
   * The shared .switch/.slider toggle styles live in assets/css/amrf-
   * admin-settings.css — enqueued unconditionally here too, same posture
   * as every other module's identical method.
   *
   * @return void
   */
  public function enqueueSwitchStyles(): void
  {
    wp_enqueue_style('amrf-admin-settings', AMRF_ADMIN_PLUGIN_URL . 'assets/css/amrf-admin-settings.css');
  }
}
