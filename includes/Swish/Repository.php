<?php

namespace Antropomorf\Swish;

if (!defined('ABSPATH')) {
  exit;
}

/**
 * Class Repository
 *
 * Storage, defaults, and sanitization for the Swish tab's own settings —
 * split out of SiteSettings\Repository's old 'swish_number' Business field
 * into its own option, since this now also drives QrCodeGenerator (amount/
 * message actually change what gets generated, not just displayed).
 *
 * @package Antropomorf\Swish
 */
class Repository
{
  public const OPTION_NAME = 'amrf_swish_settings';

  /**
   * The option this field used to live in, before the Swish tab existed —
   * read once, lazily, to carry an already-configured number over. See
   * getSettings()'s own comment for why this is a lazy read-time check
   * rather than a register_activation_hook callback like SiteSettings\
   * Repository::migrateFromThemeIfNeeded(): that hook never fires for an
   * already-active plugin just being updated in place, which is exactly
   * how this ships to every site already running this plugin.
   */
  private const LEGACY_OPTION_NAME = \Antropomorf\SiteSettings\Repository::OPTION_NAME;
  private const LEGACY_FIELD_KEY = 'swish_number';

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
    $stored = get_option(self::OPTION_NAME, null);

    if ($stored === null) {
      $legacy = get_option(self::LEGACY_OPTION_NAME, []);
      $migrated = self::getDefaults();
      if (is_array($legacy) && !empty($legacy[self::LEGACY_FIELD_KEY])) {
        $migrated['number'] = (string) $legacy[self::LEGACY_FIELD_KEY];
      }
      update_option(self::OPTION_NAME, $migrated);
      return $migrated;
    }

    return wp_parse_args(is_array($stored) ? $stored : [], self::getDefaults());
  }

  public static function getNumber(): string
  {
    return self::getSettings()['number'];
  }

  public static function getAmount(): string
  {
    return self::getSettings()['amount'];
  }

  public static function isAmountEditable(): bool
  {
    return self::getSettings()['amount_editable'] === '1';
  }

  public static function getMessage(): string
  {
    return self::getSettings()['message'];
  }

  public static function isMessageEditable(): bool
  {
    return self::getSettings()['message_editable'] === '1';
  }

  public static function getQrUrl(): string
  {
    return self::getSettings()['qr_url'];
  }

  /**
   * One option, one page, one form — unlike SiteSettings\Repository::
   * sanitize()/ContactForm\Repository::sanitize(), this option is never
   * shared across multiple tabs/pages, so there's no "was this checkbox's
   * OWN tab even submitted" ambiguity to resolve with a "{key}_submitted"
   * hidden marker: every call to this method IS this one form's full
   * submission, so a checkbox simply absent from $input plainly means
   * "unchecked," nothing more to disambiguate.
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
