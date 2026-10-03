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
	 * @param string $key One of self::KEYS.
	 * @return string Path relative to the site root, or empty when the image is elsewhere.
	 */
	public static function homePath(string $key): string
	{
		$url = wp_parse_url(self::url($key)) ?: [];
		$home = wp_parse_url(home_url()) ?: [];
		$homePath = trailingslashit($home['path'] ?? '');
		$path = $url['path'] ?? '';

		// Scheme is ignored: under wp-cli, theme URLs can be http while home_url() is https.
		if (($url['host'] ?? '') !== ($home['host'] ?? '') || !str_starts_with($path, $homePath)) {
			return '';
		}

		return substr($path, strlen($homePath));
	}
}
