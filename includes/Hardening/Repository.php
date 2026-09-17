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

  // Sanitized as absint(), not bool — every other key in getDefaults() is a toggle.
  private const INT_KEYS = [
    'optimize_non_admin_image_uploads_width',
    'optimize_non_admin_image_uploads_height',
  ];

  /**
   * @return array<string, bool|int>
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
      'optimize_non_admin_image_uploads' => false,
      'optimize_non_admin_image_uploads_width' => 1200,
      'optimize_non_admin_image_uploads_height' => 1200,
    ];
  }

  /**
   * @return array<string, bool|int>
   */
  public static function getSettings(): array
  {
    $stored = get_option(self::OPTION_NAME, []);
    return wp_parse_args(is_array($stored) ? $stored : [], self::getDefaults());
  }

  /**
   * @param mixed $input Raw POSTed value for this option.
   * @return array<string, bool|int>
   */
  public static function sanitize($input): array
  {
    $defaults = self::getDefaults();
    $output = [];

    foreach (array_keys($defaults) as $key) {
      if (in_array($key, self::INT_KEYS, true)) {
        $value = is_array($input) && isset($input[$key]) ? absint($input[$key]) : 0;
        $output[$key] = $value > 0 ? $value : $defaults[$key];
        continue;
      }

      $output[$key] = is_array($input) && !empty($input[$key]);
    }

    return $output;
  }
}
