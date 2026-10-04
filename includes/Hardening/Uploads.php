<?php

namespace Antropomorf\Hardening;

use enshrined\svgSanitize\Sanitizer;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * The Images tab's hardening: WebP output and conversion with duplicate
 * blocking, sanitized SVG uploads for administrators, restricted media
 * deletion, and no core default image sizes.
 *
 * @package Antropomorf\Hardening
 */
class Uploads
{
	private const UPLOAD_HASH_META_KEY = '_amrf_upload_hash';
	// WordPress's own built-in sizes — a theme's own add_image_size() registrations are left alone.
	private const CORE_DEFAULT_IMAGE_SIZES = ['thumbnail', 'medium', 'medium_large', 'large', '1536x1536', '2048x2048'];

	// Set by convertImageUploadToWebp(), consumed by recordUploadHash() on the same request.
	private ?string $pendingUploadHash = null;

	/**
	 * @param array $settings Repository::getSettings().
	 */
	public function __construct(array $settings)
	{
		// Unconditional: forces every image editor call (uploads, wp_generate_attachment_metadata(),
		// wp media regenerate, …) to output WebP at the configured quality, regardless of the
		// toggles below — a no-op when nothing is actually generated.
		add_filter('image_editor_output_format', [$this, 'forceWebpOutputFormat'], 10, 3);
		add_filter('wp_editor_set_quality', [$this, 'applyWebpQuality'], 10, 2);

		if ($settings['restrict_media_deletion']) {
			add_filter('map_meta_cap', [$this, 'restrictMediaDeletion'], 10, 4);
		}

		if ($settings['allow_svg_uploads']) {
			add_filter('upload_mimes', [$this, 'allowSvgMimeType']);
			add_filter('wp_check_filetype_and_ext', [$this, 'checkSvgFiletype'], 10, 4);
			add_filter('wp_handle_upload_prefilter', [$this, 'sanitizeUploadedSvg']);
			add_filter('wp_handle_sideload_prefilter', [$this, 'sanitizeUploadedSvg']);
		}

		if ($settings['disable_generated_image_sizes']) {
			add_filter('wp_img_tag_add_decoding_attr', '__return_false');
			add_filter('intermediate_image_sizes_advanced', [$this, 'removeCoreDefaultImageSizes']);
		}

		if ($settings['convert_uploads_to_webp']) {
			add_filter('wp_handle_upload', [$this, 'convertImageUploadToWebp'], 10, 2);
			add_action('add_attachment', [$this, 'recordUploadHash']);
		}
	}

	// WordPress ties attachment deletion to the generic 'delete_posts' cap, not post ownership.
	public function restrictMediaDeletion(array $caps, string $cap, int $user_id, array $args): array
	{
		if ($cap !== 'delete_post' || user_can($user_id, 'manage_options')) {
			return $caps;
		}

		$post = get_post($args[0] ?? 0);

		if (!$post || $post->post_type !== 'attachment' || (int) $post->post_author === $user_id) {
			return $caps;
		}

		return ['do_not_allow'];
	}

	/**
	 * SVG is active content, so uploads are sanitized and limited to manage_options;
	 * unfiltered_html would let Editors in too on single-site installs.
	 *
	 * @param array $mimes
	 * @return array
	 */
	public function allowSvgMimeType(array $mimes): array
	{
		if (current_user_can('manage_options')) {
			$mimes['svg'] = 'image/svg+xml';
		}
		return $mimes;
	}

	/**
	 * @param array|false $data
	 * @param string $file
	 * @param string $filename
	 * @param array $mimes
	 * @return array|false
	 */
	public function checkSvgFiletype($data, $file, $filename, $mimes)
	{
		if (!empty($data['ext']) && !empty($data['type'])) {
			return $data;
		}

		$filetype = wp_check_filetype($filename, $mimes);
		if ($filetype['ext'] === 'svg') {
			$data['ext'] = 'svg';
			$data['type'] = 'image/svg+xml';
		}

		return $data;
	}

	/**
	 * @param array $file
	 * @return array
	 */
	public function sanitizeUploadedSvg(array $file): array
	{
		$is_svg = ($file['type'] ?? '') === 'image/svg+xml' || preg_match('/\.svg$/i', $file['name'] ?? '');

		if (!$is_svg) {
			return $file;
		}

		if (!current_user_can('manage_options')) {
			$file['error'] = __('You are not allowed to upload SVG files.', 'amrf-admin');
			return $file;
		}

		$content = file_get_contents($file['tmp_name']);

		if ($content === false || stripos($content, '<svg') === false) {
			$file['error'] = __('This file does not look like a valid SVG.', 'amrf-admin');
			return $file;
		}

		$sanitized = $this->sanitizeSvgMarkup($content);

		if ($sanitized === null) {
			$file['error'] = __('This SVG could not be sanitized and was rejected.', 'amrf-admin');
			return $file;
		}

		file_put_contents($file['tmp_name'], $sanitized);

		return $file;
	}

	/**
	 * Allowlist-sanitizes SVG markup and drops remote references; null if rejected.
	 *
	 * @param string $content
	 * @return string|null
	 */
	private function sanitizeSvgMarkup(string $content): ?string
	{
		$sanitizer = new Sanitizer();
		$sanitizer->removeRemoteReferences(true);
		$clean = $sanitizer->sanitize($content);

		return $clean === false ? null : $clean;
	}

	// Scoped to 'upload' context — sideloads are typically admin-triggered, not a direct user action.
	public function convertImageUploadToWebp(array $upload, string $context = 'upload'): array
	{
		if (
			$context !== 'upload'
			|| empty($upload['type'])
			|| strpos($upload['type'], 'image/') !== 0
		) {
			return $upload;
		}

		// Hashed before any processing (and before the webp-skip below), so re-uploading
		// the same source file is caught regardless of its format.
		$hash = hash_file('sha256', $upload['file']);
		$duplicate_id = $hash !== false ? $this->findAttachmentByHash($hash) : null;

		if ($duplicate_id !== null) {
			if (file_exists($upload['file'])) {
				unlink($upload['file']);
			}
			$upload['error'] = sprintf(
				// translators: %d is the existing attachment's post ID.
				__('This image is identical to an existing upload (attachment #%d) and was not saved again.', 'amrf-admin'),
				$duplicate_id
			);
			return $upload;
		}

		$this->pendingUploadHash = $hash !== false ? $hash : null;

		// Already webp — nothing to convert, but the dedup hash above still applies.
		if ($upload['type'] === 'image/webp') {
			return $upload;
		}

		$processed = $this->convertToWebp($upload['file']);

		if ($processed === null) {
			return $upload;
		}

		$upload['url'] = str_replace(basename($upload['file']), basename($processed), $upload['url']);
		$upload['file'] = $processed;
		$upload['type'] = 'image/webp';

		return $upload;
	}

	/**
	 * @return int|null Attachment ID with a matching stored hash, or null if none.
	 */
	private function findAttachmentByHash(string $hash): ?int
	{
		$matches = get_posts([
			'post_type' => 'attachment',
			'post_status' => 'inherit',
			'meta_key' => self::UPLOAD_HASH_META_KEY,
			'meta_value' => $hash,
			'fields' => 'ids',
			'posts_per_page' => 1,
			'no_found_rows' => true,
		]);

		return $matches ? (int) $matches[0] : null;
	}

	// Fires right after wp_insert_attachment() — the only point where we have both the hash and the new attachment ID.
	public function recordUploadHash(int $attachment_id): void
	{
		if ($this->pendingUploadHash === null) {
			return;
		}

		update_post_meta($attachment_id, self::UPLOAD_HASH_META_KEY, $this->pendingUploadHash);
		$this->pendingUploadHash = null;
	}

	// Full resolution; the front-end sizes come from the theme's add_image_size().
	private function convertToWebp(string $file_path): ?string
	{
		$editor = wp_get_image_editor($file_path);
		if (is_wp_error($editor)) {
			error_log('amrf-site-settings: could not load image editor for ' . $file_path . ': ' . $editor->get_error_message());
			return null;
		}

		$editor->set_quality(Repository::getSettings()['webp_quality']);

		$info = pathinfo($file_path);
		// wp_unique_filename avoids collisions with an existing file that
		// already has this same base name but a different original extension.
		$webp_filename = wp_unique_filename($info['dirname'], $info['filename'] . '.webp');
		$webp_path = $info['dirname'] . '/' . $webp_filename;

		$saved = $editor->save($webp_path, 'image/webp');
		if (is_wp_error($saved)) {
			error_log('amrf-site-settings: could not save webp for ' . $file_path . ': ' . $saved->get_error_message());
			return null;
		}

		if ($file_path !== $webp_path && file_exists($file_path)) {
			unlink($file_path);
		}

		return $webp_path;
	}

	/**
	 * @param array<string, array<string, mixed>> $sizes
	 * @return array<string, array<string, mixed>>
	 */
	public function removeCoreDefaultImageSizes(array $sizes): array
	{
		foreach (self::CORE_DEFAULT_IMAGE_SIZES as $name) {
			unset($sizes[$name]);
		}

		return $sizes;
	}

	/**
	 * Forces every raster size WP_Image_Editor generates — uploads, regenerated
	 * attachment metadata, `wp media regenerate` — to be saved as WebP.
	 *
	 * @param array<string, string> $output_format
	 * @return array<string, string>
	 */
	// $filename is null on some WP_Image_Editor code paths (e.g. make_subsize()) — unused here regardless.
	public function forceWebpOutputFormat(array $output_format, ?string $filename, string $mime_type): array
	{
		if ($mime_type !== 'image/webp') {
			$output_format[$mime_type] = 'image/webp';
		}

		return $output_format;
	}

	public function applyWebpQuality(int $quality, string $mime_type): int
	{
		return $mime_type === 'image/webp' ? Repository::getSettings()['webp_quality'] : $quality;
	}
}
