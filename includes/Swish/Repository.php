<?php

namespace Antropomorf\Swish;

if (!defined('ABSPATH')) {
  exit;
}

/**
 * Class Repository
 *
 * Storage, defaults, and sanitization for the Swish tab's own settings,
 * which also drive QrCodeGenerator.
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
      // Not form fields — written only by QrCodeGenerator::maybeRegenerate().
      'qr_url' => '',
      'qr_source_hash' => '',
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
   * One option, one page, one form — no "{key}_submitted" marker needed
   * since every call here is this form's full submission; an absent
   * checkbox plainly means "unchecked".
   *
   * @param mixed $input Raw POSTed value for this option.
   * @return array<string, string>
   */
  public static function sanitize($input): array
  {
    $current = self::getSettings();
    $input = is_array($input) ? $input : [];

    $output = $current;
    $output['number'] = sanitize_text_field((string) ($input['number'] ?? ''));
    $output['amount'] = self::normalizeAmount((string) ($input['amount'] ?? ''));
    $output['amount_editable'] = !empty($input['amount_editable']) ? '1' : '';
    $output['message'] = sanitize_text_field((string) ($input['message'] ?? ''));
    $output['message_editable'] = !empty($input['message_editable']) ? '1' : '';

    return array_merge($output, QrCodeGenerator::maybeRegenerate($current, $output));
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
