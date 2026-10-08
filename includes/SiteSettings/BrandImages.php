<?php

namespace Antropomorf\SiteSettings;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Icon and logo URLs supplied by the theme through the amrf_brand_images filter.
 *
 * @package Antropomorf\SiteSettings
 */
class BrandImages
{
	private const KEYS = ['icon_svg', 'icon_ico', 'icon_192', 'icon_512', 'apple_touch_icon', 'logo'];

	/**
	 * @param string $key One of self::KEYS.
	 * @return string Empty when the theme supplies none.
	 */
	public static function url(string $key): string
	{
		$urls = (array) apply_filters('amrf_brand_images', []);

		return in_array($key, self::KEYS, true) ? (string) ($urls[$key] ?? '') : '';
	}

	/**
	 * The theme icon best suited to a core Site Icon size, falling back to the next one supplied.
	 *
	 * @param int $size Pixels, as passed to get_site_icon_url().
	 * @return string Empty when the theme supplies no icon.
	 */
	public static function forSize(int $size): string
	{
		if ($size <= 64) {
			$keys = ['icon_svg', 'icon_192', 'icon_512'];
		} elseif ($size === 180) {
			$keys = ['apple_touch_icon', 'icon_192', 'icon_512'];
		} elseif ($size <= 192) {
			$keys = ['icon_192', 'icon_512', 'icon_svg'];
		} else {
			$keys = ['icon_512', 'icon_192', 'icon_svg'];
		}

		foreach ($keys as $key) {
			$url = self::url($key);
			if ($url) {
				return $url;
			}
		}

		return '';
	}

	/**
	 * @param string $url
	 * @return string Path relative to the site root, or empty when the URL is on another host or outside it.
	 */
	public static function homePath(string $url): string
	{
		$parts = wp_parse_url($url) ?: [];
		$home = wp_parse_url(home_url()) ?: [];
		$homePath = trailingslashit($home['path'] ?? '');
		$path = $parts['path'] ?? '';

		// Scheme is ignored: under wp-cli, theme URLs can be http while home_url() is https.
		if ($url === '' || ($parts['host'] ?? '') !== ($home['host'] ?? '') || !str_starts_with($path, $homePath)) {
			return '';
		}

		return substr($path, strlen($homePath));
	}

	/**
	 * @param string $url An uploads or wp-content URL.
	 * @return string Existing file on disk, or empty when the URL points elsewhere.
	 */
	public static function localPath(string $url): string
	{
		$uploads = wp_upload_dir(null, false);
		$roots = [
			$uploads['baseurl'] => $uploads['basedir'],
			content_url() => WP_CONTENT_DIR,
		];
		// Scheme is ignored: under wp-cli, theme URLs can be http while home_url() is https.
		$target = preg_replace('#^https?:#', '', (string) strtok($url, '?#'));

		foreach ($roots as $baseUrl => $baseDir) {
			$base = trailingslashit(preg_replace('#^https?:#', '', $baseUrl));
			if ($url === '' || !str_starts_with($target, $base)) {
				continue;
			}

			$relative = rawurldecode(substr($target, strlen($base)));
			$path = trailingslashit($baseDir) . $relative;

			return !str_contains($relative, '..') && is_file($path) ? $path : '';
		}

		return '';
	}
}
