<?php

namespace Antropomorf\Swish;

if (!defined('ABSPATH')) {
  exit;
}

/**
 * Class Repository
 *
 * Storage, defaults, and sanitization for the Swish tab's settings. The QR
 * code is derived from them by QrCodeGenerator and is not stored here.
 *
 * @package Antropomorf\Swish
 */
class Repository
{
  public const OPTION_NAME = 'amrf_swish_settings';

  /**
   * @return array<string, string>
   */
  public static function getDefaults(): array
  {
    return [
      'number' => '',
      'amount' => '',
      'amount_editable' => '1',
      'message' => '',
      'message_editable' => '1',
    ];
  }

  /**
   * @return array<string, string>
   */
  public static function getSettings(): array
  {
    $stored = get_option(self::OPTION_NAME, []);
    $defaults = self::getDefaults();
    return array_intersect_key(wp_parse_args(is_array($stored) ? $stored : [], $defaults), $defaults);
  }

  /**
   * One option, one page, one form — no "{key}_submitted" marker needed
   * since every call here is this form's full submission; an absent
   * checkbox plainly means "unchecked".
   *
   * @param mixed $input Raw POSTed value for this option.
   * @return array<string, string>
   */
  public static function sanitize($input): array
  {
    $input = is_array($input) ? $input : [];

    return [
      'number' => sanitize_text_field((string) ($input['number'] ?? '')),
      'amount' => self::normalizeAmount((string) ($input['amount'] ?? '')),
      'amount_editable' => !empty($input['amount_editable']) ? '1' : '',
      'message' => sanitize_text_field((string) ($input['message'] ?? '')),
      'message_editable' => !empty($input['message_editable']) ? '1' : '',
    ];
  }

  /**
   * "125,50" / "125.50" / "125" -> "125.50" / "125" ; blank stays blank.
   * Swish amounts are decimal, and a Swedish keyboard's numeric input
   * naturally produces a comma decimal separator, which the QR API (and a
   * plain (float) cast) both need as a period instead.
   *
   * @param string $raw
   */
  private static function normalizeAmount(string $raw): string
  {
    $raw = trim($raw);
    if ($raw === '') {
      return '';
    }

    $normalized = str_replace(',', '.', $raw);
    return preg_match('/^\d+(\.\d{1,2})?$/', $normalized) ? $normalized : '';
  }
}
