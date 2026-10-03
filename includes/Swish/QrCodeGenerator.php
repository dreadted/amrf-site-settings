<?php

namespace Antropomorf\Swish;

if (!defined('ABSPATH')) {
  exit;
}

/**
 * Class QrCodeGenerator
 *
 * Creates the Swish QR code via Swish's QR API after each save, in uploads/
 * under a name derived from the settings, so nothing else is stored.
 *
 * Requested as SVG, not PNG/JPG: recoloring is then a plain DOM edit (see
 * applyBlackStyle()) instead of a pixel operation that depends on the
 * server's ImageMagick version.
 *
 * @package Antropomorf\Swish
 */
class QrCodeGenerator
{
  private const API_URL = 'https://mpc.getswish.net/qrg-swish/api/v1/prefilled';

  private const UPLOAD_SUBDIR = 'amrf-swish';
  private const FILENAME_PREFIX = 'swish-qr-';

  public static function register(): void
  {
    // Both fire once per save, after the value is stored; update only when it changed.
    add_action('add_option_' . Repository::OPTION_NAME, [self::class, 'onSave']);
    add_action('update_option_' . Repository::OPTION_NAME, [self::class, 'onSave']);
  }

  public static function onSave(): void
  {
    self::ensure(Repository::getSettings());
  }

  /**
   * Creates the QR code for these settings unless it already exists, and
   * removes codes for earlier settings.
   *
   * @param array<string, string> $settings
   * @return bool False if the code could not be created.
   */
  public static function ensure(array $settings): bool
  {
    if ($settings['number'] === '') {
      self::deleteFiles();
      return true;
    }

    $path = self::path($settings);
    if (file_exists($path)) {
      return true;
    }

    $svg = self::fetch($settings);
    if ($svg === null || !wp_mkdir_p(dirname($path)) || file_put_contents($path, $svg) === false) {
      return false;
    }

    self::deleteFiles(basename($path));
    return true;
  }

  /**
   * @param array<string, string> $settings
   * @return string The QR code's URL, or '' if there is none for these settings.
   */
  public static function url(array $settings): string
  {
    $path = self::path($settings);
    if ($settings['number'] === '' || !file_exists($path)) {
      return '';
    }

    $upload_dir = wp_upload_dir(null, false);
    return trailingslashit($upload_dir['baseurl']) . self::UPLOAD_SUBDIR . '/' . basename($path);
  }

  /**
   * @param array<string, string> $settings
   */
  private static function path(array $settings): string
  {
    return self::dir() . '/' . self::FILENAME_PREFIX . self::hash($settings) . '.svg';
  }

  private static function dir(): string
  {
    $upload_dir = wp_upload_dir(null, false);
    return trailingslashit($upload_dir['basedir']) . self::UPLOAD_SUBDIR;
  }

  /**
   * @param string|null $keep File name to leave in place.
   */
  private static function deleteFiles(?string $keep = null): void
  {
    foreach (glob(self::dir() . '/' . self::FILENAME_PREFIX . '*.svg') ?: [] as $file) {
      if (basename($file) !== $keep) {
        wp_delete_file($file);
      }
    }
  }

  /**
   * @param array<string, string> $settings
   * @return string|null Recolored SVG markup, or null if the request failed.
   */
  private static function fetch(array $settings): ?string
  {
    $body = [
      'format' => 'svg',
      // Deliberately non-editable: this is the site's own receiving
      // account, not something a scanned code should let the payer
      // redirect elsewhere.
      'payee' => ['value' => $settings['number'], 'editable' => false],
    ];

    if ($settings['amount'] !== '') {
      $body['amount'] = ['value' => (float) $settings['amount'], 'editable' => $settings['amount_editable'] === '1'];
    }

    if ($settings['message'] !== '') {
      $body['message'] = ['value' => $settings['message'], 'editable' => $settings['message_editable'] === '1'];
    }

    $response = wp_remote_post(self::API_URL, [
      'headers' => ['Content-Type' => 'application/json'],
      'body' => wp_json_encode($body),
      'timeout' => 15,
    ]);

    if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
      return null;
    }

    $svg = wp_remote_retrieve_body($response);
    if ($svg === '') {
      return null;
    }

    return self::applyBlackStyle($svg);
  }

  /**
   * Swish's API always returns its own brand gradient, with no request
   * parameter to change it. The returned SVG separates its logo artwork
   * (many gradients, an Illustrator export) from the scannable QR pattern,
   * which shares one simple two-stop gradient, `id="grad"` — blacking out
   * just those two stops recolors the QR pattern without touching the logo.
   *
   * Falls back to the original colored markup if the response isn't
   * parseable XML or Swish ever renames that gradient.
   *
   * @param string $svg
   * @return string SVG markup, restyled if possible.
   */
  private static function applyBlackStyle(string $svg): string
  {
    $dom = new \DOMDocument();
    $previous = libxml_use_internal_errors(true);
    $loaded = $dom->loadXML($svg);
    libxml_use_internal_errors($previous);

    if (!$loaded) {
      return $svg;
    }

    // local-name() instead of a plain tag/attribute selector — sidesteps
    // needing to register the SVG default namespace on DOMXPath just for
    // one query.
    $xpath = new \DOMXPath($dom);
    $stops = $xpath->query("//*[local-name()='linearGradient'][@id='grad']/*[local-name()='stop']");

    foreach ($stops as $stop) {
      $stop->setAttribute('stop-color', '#000000');
    }

    $result = $dom->saveXML();
    return $result !== false ? $result : $svg;
  }

  /**
   * @param array<string, string> $settings
   */
  private static function hash(array $settings): string
  {
    return md5(implode('|', [
      $settings['number'],
      $settings['amount'],
      $settings['amount_editable'],
      $settings['message'],
      $settings['message_editable'],
    ]));
  }
}
