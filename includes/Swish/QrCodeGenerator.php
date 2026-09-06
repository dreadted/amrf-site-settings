<?php

namespace Antropomorf\Swish;

if (!defined('ABSPATH')) {
  exit;
}

/**
 * Class QrCodeGenerator
 *
 * Generates this site's Swish QR code via Swish's own unauthenticated QR
 * API, caching the result to uploads/ as a single fixed-filename .svg —
 * regenerated only when Repository::sanitize() detects the source settings
 * actually changed.
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
  private const FILENAME = 'swish-qr.svg';

  /**
   * @param array<string, string> $before Settings as they were before this save.
   * @param array<string, string> $after  Settings as sanitize() just computed them
   *                                      (qr_url/qr_source_hash keys not set yet).
   * @return array{qr_url: string, qr_source_hash: string} Merge this into
   *         the option being saved.
   */
  public static function maybeRegenerate(array $before, array $after): array
  {
    $hash = self::hash($after);

    if ($after['number'] === '') {
      // Nothing to generate without a number — clear any stale QR rather
      // than leaving a code for a number that's no longer configured.
      return ['qr_url' => '', 'qr_source_hash' => $hash];
    }

    if ($hash === $before['qr_source_hash'] && $before['qr_url'] !== '') {
      return ['qr_url' => $before['qr_url'], 'qr_source_hash' => $hash];
    }

    $url = self::generate($after);

    if ($url === null) {
      add_settings_error(
        Repository::OPTION_NAME,
        'amrf_swish_qr_failed',
        __('The Swish QR code could not be regenerated — the previous one (if any) is still in use. Try saving again.', 'amrf-admin')
      );
      // Deliberately keep the OLD hash, not the new one: the settings did
      // change, the cached QR is now stale relative to them, and keeping
      // the old hash means the next save (even with identical values)
      // retries instead of silently treating this as "already up to date."
      return ['qr_url' => $before['qr_url'], 'qr_source_hash' => $before['qr_source_hash']];
    }

    return ['qr_url' => $url, 'qr_source_hash' => $hash];
  }

  /**
   * @param array<string, string> $settings
   * @return string|null The final, cache-busted image URL, or null on
   *                      any failure (network or a response the write
   *                      to uploads/ couldn't complete).
   */
  private static function generate(array $settings): ?string
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

    return self::save(self::applyBlackStyle($svg));
  }

  /**
   * @param string $svg Final SVG markup to write to uploads/.
   * @return string|null Cache-busted URL, or null if the write failed.
   */
  private static function save(string $svg): ?string
  {
    $upload_dir = wp_upload_dir();
    if (!empty($upload_dir['error'])) {
      return null;
    }

    $dir = trailingslashit($upload_dir['basedir']) . self::UPLOAD_SUBDIR;
    wp_mkdir_p($dir);

    $destination = trailingslashit($dir) . self::FILENAME;
    if (file_put_contents($destination, $svg) === false) {
      return null;
    }

    $url = trailingslashit($upload_dir['baseurl']) . self::UPLOAD_SUBDIR . '/' . self::FILENAME;

    // Fixed filename, always overwritten — a plain URL would otherwise
    // keep serving a browser/CDN-cached copy from before this save.
    return add_query_arg('v', substr(md5($svg), 0, 8), $url);
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
