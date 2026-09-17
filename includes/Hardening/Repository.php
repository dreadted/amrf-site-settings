<?php

namespace Antropomorf\Hardening;

if (!defined('ABSPATH')) {
  exit;
}

/**
 * Class Repository
 *
 * Storage/defaults/sanitization for the behavior-changing hardening toggles
 * (see Provider). Most default to true — opt-out, not opt-in; see getDefaults().
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
      'allow_svg_uploads' => true,
      'disable_author_archives' => true,
      'redirect_404_to_home' => true,
      'remove_jquery_migrate' => true,
      'disable_generated_image_sizes' => true,
      'disable_site_search' => false,
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
