<?php

namespace Antropomorf\Hardening;

if (!defined('ABSPATH')) {
  exit;
}

/**
 * Class Repository
 *
 * Storage/defaults/sanitization for the opinionated, behavior-changing
 * hardening toggles (see Provider's docblock for why these four are
 * toggles while the rest of the ported hardening is unconditional).
 * Defaults to true for all four — per-site opt-OUT, not opt-in: the
 * person building this plugin reports 9 of 10 sites they build want this
 * behavior, so a freshly-activated site matches that common case without
 * any configuration.
 *
 * @package Antropomorf\Hardening
 */
class Repository
{
  public const OPTION_NAME = 'amrf_hardening';

  /**
   * @return array<string, bool>
   */
  public static function getDefaults(): array
  {
    return [
      'disable_author_archives' => true,
      'redirect_404_to_home' => true,
      'remove_jquery_migrate' => true,
      'disable_generated_image_sizes' => true,
    ];
  }

  /**
   * @return array<string, bool>
   */
  public static function getSettings(): array
  {
    $stored = get_option(self::OPTION_NAME, []);
    return wp_parse_args(is_array($stored) ? $stored : [], self::getDefaults());
  }

  /**
   * @param mixed $input Raw POSTed value for this option.
   * @return array<string, bool>
   */
  public static function sanitize($input): array
  {
    $output = [];
    foreach (array_keys(self::getDefaults()) as $key) {
      $output[$key] = is_array($input) && !empty($input[$key]);
    }
    return $output;
  }
}
