<?php

namespace Antropomorf\Swish;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Swish QR codes from Swish's API, stored in uploads/ under a settings-derived name: the site's own code
 * after each save, and per-link codes (LinkQr) on first request.
 * SVG, not PNG, so recoloring is a DOM edit (applyBlackStyle()) independent of ImageMagick.
 *
 * @package Antropomorf\Swish
 */
class QrCodeGenerator
{
	private const API_URL = 'https://mpc.getswish.net/qrg-swish/api/v1/prefilled';

	private const UPLOAD_SUBDIR = 'amrf-swish';
	private const FILENAME_PREFIX = 'swish-qr-';
	private const LINK_FILENAME_PREFIX = 'swish-qr-link-';

	public static function register(): void
	{
		// Both fire once per save, after the value is stored; update only when it changed.
		add_action('add_option_' . Repository::OPTION_NAME, [self::class, 'onAdd']);
		add_action('update_option_' . Repository::OPTION_NAME, [self::class, 'onUpdate']);
	}

	public static function onAdd(): void
	{
		self::ensure(Repository::getSettings());
	}

	/**
	 * Link codes embed the number, so a new number makes them all stale.
	 *
	 * @param mixed $oldValue The option's previous value.
	 */
	public static function onUpdate($oldValue): void
	{
		$settings = Repository::getSettings();

		if ((is_array($oldValue) ? (string) ($oldValue['number'] ?? '') : '') !== $settings['number']) {
			self::deleteFiles(self::LINK_FILENAME_PREFIX);
		}

		self::ensure($settings);
	}

	/**
	 * Creates the site's own QR code for these settings unless it already exists, and
	 * removes its codes for earlier settings.
	 *
	 * @param array<string, string> $settings
	 * @return bool False if the code could not be created.
	 */
	public static function ensure(array $settings): bool
	{
		if ($settings['number'] === '') {
			self::deleteFiles(self::FILENAME_PREFIX);
			return true;
		}

		$path = self::path(self::FILENAME_PREFIX, $settings);
		if (file_exists($path)) {
			return true;
		}

		if (!self::create($settings, $path)) {
			return false;
		}

		self::deleteFiles(self::FILENAME_PREFIX, basename($path));
		return true;
	}

	/**
	 * Creates a link's QR code unless it already exists; other codes are left alone.
	 *
	 * @param array<string, string> $params Same keys as Repository::getSettings().
	 * @return bool False if the code could not be created.
	 */
	public static function ensureLink(array $params): bool
	{
		if ($params['number'] === '') {
			return false;
		}

		$path = self::path(self::LINK_FILENAME_PREFIX, $params);

		return file_exists($path) || self::create($params, $path);
	}

	/**
	 * @param array<string, string> $settings
	 * @return string The site's QR code's URL, or '' if there is none for these settings.
	 */
	public static function url(array $settings): string
	{
		return self::fileUrl(self::FILENAME_PREFIX, $settings);
	}

	/**
	 * @param array<string, string> $params Same keys as Repository::getSettings().
	 * @return string The link's QR code's URL, or '' if it hasn't been created yet.
	 */
	public static function linkUrl(array $params): string
	{
		return self::fileUrl(self::LINK_FILENAME_PREFIX, $params);
	}

	/**
	 * @param array<string, string> $settings
	 */
	private static function fileUrl(string $prefix, array $settings): string
	{
		$path = self::path($prefix, $settings);
		if ($settings['number'] === '' || !file_exists($path)) {
			return '';
		}

		$upload_dir = wp_upload_dir(null, false);
		return trailingslashit($upload_dir['baseurl']) . self::UPLOAD_SUBDIR . '/' . basename($path);
	}

	/**
	 * Written under a temporary name first, so a concurrent request never serves a partial file.
	 *
	 * @param array<string, string> $settings
	 */
	private static function create(array $settings, string $path): bool
	{
		$svg = self::fetch($settings);
		if ($svg === null || !wp_mkdir_p(dirname($path))) {
			return false;
		}

		$temp = $path . '.' . wp_generate_password(8, false) . '.tmp';
		if (file_put_contents($temp, $svg) === false || !rename($temp, $path)) {
			wp_delete_file($temp);
			return false;
		}

		return true;
	}

	/**
	 * @param array<string, string> $settings
	 */
	private static function path(string $prefix, array $settings): string
	{
		return self::dir() . '/' . $prefix . self::hash($settings) . '.svg';
	}

	private static function dir(): string
	{
		$upload_dir = wp_upload_dir(null, false);
		return trailingslashit($upload_dir['basedir']) . self::UPLOAD_SUBDIR;
	}

	/**
	 * The site's codes start with a hex hash, which never matches LINK_FILENAME_PREFIX.
	 *
	 * @param string      $prefix FILENAME_PREFIX or LINK_FILENAME_PREFIX.
	 * @param string|null $keep   File name to leave in place.
	 */
	private static function deleteFiles(string $prefix, ?string $keep = null): void
	{
		$pattern = $prefix === self::FILENAME_PREFIX ? $prefix . '[0-9a-f]*.svg' : $prefix . '*.svg';

		foreach (glob(self::dir() . '/' . $pattern) ?: [] as $file) {
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
	 * Swish always returns its brand gradient; only the QR pattern uses `id="grad"`, so blacking it out keeps the logo.
	 * Returns the original markup if the SVG doesn't parse or the gradient is renamed.
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
