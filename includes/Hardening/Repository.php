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

  // One shared option split across two Settings API groups (Hardening's "Images"/"Frontend" tabs).
  public const OPTION_GROUP_IMAGES = 'amrf_hardening_images_group';
  public const OPTION_GROUP_FRONTEND = 'amrf_hardening_frontend_group';

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
      'restrict_media_deletion' => false,
      'allow_svg_uploads' => true,
      'disable_author_archives' => true,
      'redirect_404_to_home' => true,
      'remove_jquery_migrate' => true,
      'disable_generated_image_sizes' => true,
      'disable_site_search' => false,
      'restrict_site_to_logged_in' => false,
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

  // Merges into existing settings rather than overwriting — each tab's form only posts its own fields.
  public static function sanitize($input): array
  {
    $defaults = self::getDefaults();
    $output = self::getSettings();

    $option_page = isset($_POST['option_page']) ? sanitize_key(wp_unslash($_POST['option_page'])) : '';
    $scope = match ($option_page) {
      self::OPTION_GROUP_IMAGES => [
        'restrict_media_deletion',
        'allow_svg_uploads',
        'disable_generated_image_sizes',
        'optimize_non_admin_image_uploads',
        'optimize_non_admin_image_uploads_width',
        'optimize_non_admin_image_uploads_height',
      ],
      self::OPTION_GROUP_FRONTEND => [
        'disable_author_archives',
        'redirect_404_to_home',
        'disable_site_search',
        'restrict_site_to_logged_in',
        'remove_jquery_migrate',
      ],
      default => array_keys($defaults),
    };

    foreach ($scope as $key) {
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
