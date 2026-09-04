<?php

namespace Antropomorf\Umami;

if (!defined('ABSPATH')) {
  exit;
}

/**
 * Class Repository
 *
 * Storage/defaults/sanitization for Umami analytics configuration —
 * generalized from amrf-theme's includes/umami.php, which stored these as
 * two flat, unprefixed WP options (umami_site, umami_id — collision-prone,
 * and read by get_option() calls scattered across enqueue.php/umami.php).
 * Kept as two distinct fields despite the similar names: umami_site is the
 * tracking script's data-website-id, umami_id is the separate ID the
 * Analytics share-iframe URL uses — the original theme code never treated
 * them as the same value, so this doesn't assume they are either.
 *
 * @package Antropomorf\Umami
 */
class Repository
{
  public const OPTION_NAME = 'amrf_umami';

  /**
   * The two legacy theme options this replaces — read once, on activation,
   * to carry existing production values over. See migrateFromThemeIfNeeded().
   */
  private const LEGACY_SITE_OPTION = 'umami_site';
  private const LEGACY_ID_OPTION = 'umami_id';

  /**
   * @return array<string, string>
   */
  public static function getDefaults(): array
  {
    return [
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
    return [
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
