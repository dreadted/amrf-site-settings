<?php

namespace Antropomorf\PageQr;

use Antropomorf\SiteSettings\BrandImages;
use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * PNG QR code per eligible page, in uploads/qr-links/ named after its address.
 * The code itself is encoded by chillerlan/php-qrcode and drawn with Imagick.
 *
 * @package Antropomorf\PageQr
 */
class Generator
{
	public const META_KEY = '_amrf_page_qr';
	public const UPLOAD_SUBDIR = 'qr-links';

	// Bump to regenerate every code after a change to the drawing below.
	private const RENDER_VERSION = 1;

	// Version 8 fits a 49-character slug on a short domain; every code then shares one module size.
	private const MIN_VERSION = 8;
	private const CANVAS_SIZE = 1197;
	private const QUIET_ZONE = 4;
	private const CIRCLE_RATIO = 0.24;
	private const LOGO_RATIO = 0.72;

	/**
	 * Creates, renames or removes the page's code so it matches its current address and eligibility.
	 */
	public static function sync(\WP_Post $page): void
	{
		if (!self::isEligible($page)) {
			self::remove($page->ID);
			return;
		}

		$record = self::ensure(self::targetUrl($page), self::meta($page->ID));
		if ($record !== null) {
			update_post_meta($page->ID, self::META_KEY, $record);
		}
	}

	public static function remove(int $pageId): void
	{
		self::deleteFile(self::meta($pageId)['file']);
		delete_post_meta($pageId, self::META_KEY);
	}

	/**
	 * @return string The code's file name, or '' if the page has none.
	 */
	public static function file(int $pageId): string
	{
		$file = self::meta($pageId)['file'];

		return self::exists($file) ? $file : '';
	}

	/**
	 * Creates the code for any URL unless an up-to-date one exists, and removes the previous file if it was renamed.
	 *
	 * @param string                                 $url
	 * @param array{file: string, signature: string} $previous
	 * @return array{file: string, signature: string}|null Null if the code could not be created.
	 */
	public static function ensure(string $url, array $previous): ?array
	{
		$file = self::filename($url);
		$logo = self::logoPath();
		$record = [
			'file' => $file,
			'signature' => md5(implode('|', [$url, self::RENDER_VERSION, $logo, $logo !== '' ? filemtime($logo) : 0])),
		];

		if ($previous === $record && self::exists($file)) {
			return $record;
		}

		if (!self::render($url, $logo, self::filePath($file))) {
			return null;
		}

		if ($previous['file'] !== $file) {
			self::deleteFile($previous['file']);
		}

		return $record;
	}

	public static function exists(string $file): bool
	{
		return $file !== '' && file_exists(self::filePath($file));
	}

	public static function deleteFile(string $file): void
	{
		if ($file !== '') {
			wp_delete_file(self::filePath($file));
		}
	}

	public static function fileUrl(string $file): string
	{
		$upload_dir = wp_upload_dir(null, false);

		return trailingslashit($upload_dir['baseurl']) . self::UPLOAD_SUBDIR . '/' . $file;
	}

	public static function filePath(string $file): string
	{
		return self::dir() . '/' . $file;
	}

	/**
	 * Published pages except the ticket page; sites narrow this with the amrf_page_qr_eligible filter.
	 */
	public static function isEligible(\WP_Post $page): bool
	{
		$eligible = $page->post_type === 'page'
			&& $page->post_status === 'publish'
			&& $page->ID !== amrf_get_ticket_page_id();

		return (bool) apply_filters('amrf_page_qr_eligible', $eligible, $page);
	}

	/**
	 * @return array{file: string, signature: string}
	 */
	private static function meta(int $pageId): array
	{
		$meta = get_post_meta($pageId, self::META_KEY, true);
		$meta = is_array($meta) ? $meta : [];

		return [
			'file' => (string) ($meta['file'] ?? ''),
			'signature' => (string) ($meta['signature'] ?? ''),
		];
	}

	private static function targetUrl(\WP_Post $page): string
	{
		return add_query_arg('utm_source', 'qr', get_permalink($page));
	}

	/**
	 * qr-<host>[-<path with slashes as hyphens>].png, e.g. qr-example.com-about-team.png.
	 */
	private static function filename(string $url): string
	{
		$parts = wp_parse_url($url) ?: [];
		$path = trim(rawurldecode($parts['path'] ?? ''), '/');
		$name = 'qr-' . ($parts['host'] ?? '') . ($path !== '' ? '-' . str_replace('/', '-', $path) : '');

		// Not sanitize_file_name(): it adds "_" after dotted host parts it takes for extensions.
		return preg_replace('/[^a-z0-9.-]+/', '-', strtolower(remove_accents($name))) . '.png';
	}

	private static function dir(): string
	{
		$upload_dir = wp_upload_dir(null, false);

		return trailingslashit($upload_dir['basedir']) . self::UPLOAD_SUBDIR;
	}

	/**
	 * The theme's 512 px icon on disk, or '' if it has none under wp-content.
	 */
	private static function logoPath(): string
	{
		$url = BrandImages::url('icon_512');
		$content = content_url();

		// Scheme is ignored: under wp-cli, theme URLs can be http while content_url() is https.
		$relative = preg_replace('#^https?:#', '', $url);
		$base = preg_replace('#^https?:#', '', $content);
		if ($url === '' || !str_starts_with($relative, $base . '/')) {
			return '';
		}

		$path = WP_CONTENT_DIR . substr($relative, strlen($base));

		return is_file($path) ? $path : '';
	}

	private static function render(string $url, string $logo, string $path): bool
	{
		if (!class_exists('Imagick') || !wp_mkdir_p(dirname($path))) {
			return false;
		}

		try {
			$options = new QROptions([
				'versionMin' => self::MIN_VERSION,
				'eccLevel' => EccLevel::H,
				'addQuietzone' => false,
			]);
			$matrix = (new QRCode($options))->addByteSegment($url)->getQRMatrix();
			$modules = $matrix->getSize();

			$pixels = [];
			for ($y = 0; $y < $modules; $y++) {
				for ($x = 0; $x < $modules; $x++) {
					$pixels[] = $matrix->check($x, $y) ? 0 : 255;
				}
			}

			$scale = intdiv(self::CANVAS_SIZE, $modules + 2 * self::QUIET_ZONE);
			$codeSize = $modules * $scale;
			$offset = intdiv(self::CANVAS_SIZE - $codeSize, 2);

			$code = new \Imagick();
			$code->newImage($modules, $modules, 'white');
			$code->importImagePixels(0, 0, $modules, $modules, 'I', \Imagick::PIXEL_CHAR, $pixels);
			// Nearest-neighbour scaling keeps module edges sharp.
			$code->sampleImage($codeSize, $codeSize);

			$canvas = new \Imagick();
			$canvas->newImage(self::CANVAS_SIZE, self::CANVAS_SIZE, 'white');
			$canvas->compositeImage($code, \Imagick::COMPOSITE_OVER, $offset, $offset);

			if ($logo !== '') {
				self::addLogo($canvas, $logo, $codeSize);
			}

			$canvas->setImageFormat('png');
			$canvas->stripImage();

			return $canvas->writeImage($path);
		} catch (\Throwable $e) {
			return false;
		}
	}

	private static function addLogo(\Imagick $canvas, string $logo, int $codeSize): void
	{
		$center = self::CANVAS_SIZE / 2;
		$radius = $codeSize * self::CIRCLE_RATIO / 2;

		$circle = new \ImagickDraw();
		$circle->setFillColor('white');
		$circle->circle($center, $center, $center + $radius, $center);
		$canvas->drawImage($circle);

		$size = (int) round(2 * $radius * self::LOGO_RATIO);
		$mark = new \Imagick($logo);
		$mark->resizeImage($size, $size, \Imagick::FILTER_LANCZOS, 1);
		$position = (int) round($center - $size / 2);
		$canvas->compositeImage($mark, \Imagick::COMPOSITE_OVER, $position, $position);
	}
}
