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
   * Legacy field, migrated lazily on read (not an activation hook, since
   * that never fires for an already-active plugin updated in place).
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
   * True while sanitize() is already running — guards against infinite
   * recursion: update_option() for this option re-enters sanitize() via
   * WP's "sanitize_option_..." filter, and the migration write in
   * getSettings() below would otherwise call update_option() again forever.
   * Do not remove without understanding this. sanitize() also never calls
   * getSettings() itself, as defense in depth on top of this flag.
   */
  private static bool $sanitizing = false;

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

      self::$sanitizing = true;
      update_option(self::OPTION_NAME, $migrated);
      self::$sanitizing = false;

      return $migrated;
    }

    return wp_parse_args(is_array($stored) ? $stored : [], self::getDefaults());
  }

  /**
   * The stored option's raw value, defaulted — but never migrating (no
   * update_option() side effect) and never going through getSettings()
   * itself. sanitize() uses this instead of getSettings() for its "current
   * value" precisely to stay out of the recursion trap described above.
   *
   * @return array<string, string>
   */
  private static function getStoredSettings(): array
  {
    $stored = get_option(self::OPTION_NAME, []);
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
   * One option, one page, one form — no "{key}_submitted" marker needed
   * since every call here is this form's full submission; an absent
   * checkbox plainly means "unchecked".
   *
   * @param mixed $input Raw POSTed value for this option.
   * @return array<string, string>
   */
  public static function sanitize($input): array
  {
    // Re-entered via getSettings()'s migration write (see $sanitizing) —
    // $input is already the fully-formed migrated array, not raw $_POST.
    if (self::$sanitizing) {
      return is_array($input) ? wp_parse_args($input, self::getDefaults()) : self::getDefaults();
    }

    $current = self::getStoredSettings();
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
