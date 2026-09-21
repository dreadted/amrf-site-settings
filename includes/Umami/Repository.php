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

  /**
   * @return array<string, string>
   */
  public static function getDefaults(): array
  {
    return [
      'host' => 'umami.antropomorf.se',
      'site' => '',
      'id' => '',
    ];
  }

  /**
   * @return array<string, string>
   */
  public static function getSettings(): array
  {
    $stored = get_option(self::OPTION_NAME, []);
    return wp_parse_args(is_array($stored) ? $stored : [], self::getDefaults());
  }

  /**
   * @param mixed $input Raw POSTed value for this option.
   * @return array<string, string>
   */
  public static function sanitize($input): array
  {
    $host = is_array($input) ? ($input['host'] ?? '') : '';

    return [
      'host' => in_array($host, self::HOSTS, true) ? $host : self::getDefaults()['host'],
      'site' => sanitize_text_field(is_array($input) ? ($input['site'] ?? '') : ''),
      'id' => sanitize_text_field(is_array($input) ? ($input['id'] ?? '') : ''),
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
