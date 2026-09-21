<?php

namespace Antropomorf\Umami;

if (!defined('ABSPATH')) {
  exit;
}

/**
 * Storage/defaults/sanitization for Umami settings: 'host', 'site' (data-website-id), 'id' (share-iframe ID).
 *
 * @package Antropomorf\Umami
 */
class Repository
{
  public const OPTION_NAME = 'amrf_umami';

  /** Legacy theme options, migrated once on activation. See migrateFromThemeIfNeeded(). */
  private const LEGACY_SITE_OPTION = 'umami_site';
  private const LEGACY_ID_OPTION = 'umami_id';

  /** Allowed 'host' values. Umami sends data to whichever host the tracker script loaded from. */
  public const HOSTS = ['umami.antropomorf.se', 'eu.umami.is'];

  /** Fallback used whenever 'button_selectors' is empty — see getButtonSelectors(). */
  public const DEFAULT_BUTTON_SELECTORS = [
    'a[class*="btn--"]',
    'button[class*="btn--"]',
    '.cta',
    'a.wp-element-button',
    'button.wp-element-button',
    '.ff-btn-submit',
  ];

  /**
   * @return array<string, string|string[]>
   */
  public static function getDefaults(): array
  {
    return [
      'host' => 'umami.antropomorf.se',
      'site' => '',
      'id' => '',
      'button_selectors' => [],
    ];
  }

  /**
   * @return array<string, string|string[]>
   */
  public static function getSettings(): array
  {
    $stored = get_option(self::OPTION_NAME, []);
    return wp_parse_args(is_array($stored) ? $stored : [], self::getDefaults());
  }

  /**
   * Effective button-tracking selector list: the stored override if the
   * admin has set one, otherwise DEFAULT_BUTTON_SELECTORS. Clearing the
   * admin field back to empty reverts to the defaults, it does not disable
   * auto-discovery.
   *
   * @return string[]
   */
  public static function getButtonSelectors(): array
  {
    $selectors = self::getSettings()['button_selectors'];
    return !empty($selectors) ? $selectors : self::DEFAULT_BUTTON_SELECTORS;
  }

  /**
   * @param mixed $input Raw POSTed value for this option.
   * @return array<string, string|string[]>
   */
  public static function sanitize($input): array
  {
    $host = is_array($input) ? ($input['host'] ?? '') : '';
    $button_selectors_raw = is_array($input) ? ($input['button_selectors'] ?? '') : '';
    $button_selectors = array_values(array_filter(array_map(
      'sanitize_text_field',
      preg_split('/\r\n|\r|\n/', (string) $button_selectors_raw)
    ), fn ($line) => $line !== ''));

    return [
      'host' => in_array($host, self::HOSTS, true) ? $host : self::getDefaults()['host'],
      'site' => sanitize_text_field(is_array($input) ? ($input['site'] ?? '') : ''),
      'id' => sanitize_text_field(is_array($input) ? ($input['id'] ?? '') : ''),
      'button_selectors' => $button_selectors,
    ];
  }

  /**
   * Copies amrf-theme's own umami_site/umami_id options over on first
   * activation, so a site migrating from that theme's built-in Umami
   * settings doesn't need its real tracking IDs re-entered by hand. No-ops
   * if this plugin's own option already holds data, or neither legacy
   * option has a value — safe to call on every activation.
   *
   * @return void
   */
  public static function migrateFromThemeIfNeeded(): void
  {
    $existing = get_option(self::OPTION_NAME, []);
    if (!empty($existing)) {
      return;
    }

    $legacy_site = get_option(self::LEGACY_SITE_OPTION, '');
    $legacy_id = get_option(self::LEGACY_ID_OPTION, '');
    if (empty($legacy_site) && empty($legacy_id)) {
      return;
    }

    update_option(self::OPTION_NAME, self::sanitize([
      'site' => $legacy_site,
      'id' => $legacy_id,
    ]));
  }
}
