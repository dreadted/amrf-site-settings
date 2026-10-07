<?php

namespace Antropomorf\ContactForm;

use Antropomorf\PageQr\Generator;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * The contact shortcut: a reserved slug that redirects to the front page with the contact modal open, plus its QR code.
 *
 * @package Antropomorf\ContactForm
 */
class Shortcut
{
	public const QR_OPTION = 'amrf_contact_shortcut_qr';

	// Matches no element, so the browser stays at the top while the modal opens.
	private const MODAL_FRAGMENT = '#contact-modal';

	private const RESERVED = ['wp-admin', 'wp-content', 'wp-includes', 'wp-json', 'wp-login', 'embed', 'page', 'comments', 'search', 'author', 'category', 'tag', 'type'];

	public function __construct()
	{
		add_filter('wp_unique_post_slug', [$this, 'reserveSlug'], 20, 6);
		add_action('parse_request', [$this, 'redirect']);
		add_action('add_option_' . Repository::OPTION_NAME, [self::class, 'syncQr']);
		add_action('update_option_' . Repository::OPTION_NAME, [self::class, 'syncQr']);
	}

	public static function slug(): string
	{
		return Repository::getSettings()['contact_shortcut_slug'];
	}

	public static function url(string $slug): string
	{
		return trailingslashit(home_url($slug));
	}

	/**
	 * @param string $slug     Raw input.
	 * @param string $previous The stored slug, kept when the input collides.
	 * @return string Sanitized slug, '' to turn the shortcut off.
	 */
	public static function validateSlug(string $slug, string $previous): string
	{
		$slug = sanitize_title($slug);
		if ($slug === '' || $slug === $previous) {
			return $slug;
		}

		$conflict = self::conflict($slug);
		if ($conflict === '') {
			return $slug;
		}

		add_settings_error(
			Repository::OPTION_NAME,
			'contact_shortcut_slug',
			sprintf(
				/* translators: 1: the rejected slug, 2: what already uses it */
				__('The contact shortcut "%1$s" is already in use (%2$s). The previous value was kept.', 'amrf-admin'),
				$slug,
				$conflict
			)
		);

		return $previous;
	}

	/**
	 * Reserves the shortcut the way core keeps sibling slugs apart: a new page or post gets "-2", "-3", ….
	 */
	public function reserveSlug(string $slug, int $postId, string $postStatus, string $postType, int $postParent, string $originalSlug): string
	{
		$reserved = self::slug();
		if ($reserved === '' || $slug !== $reserved || !in_array($postType, ['page', 'post'], true)) {
			return $slug;
		}

		$suffix = 2;
		do {
			$candidate = _truncate_post_slug($originalSlug, 200 - (strlen((string) $suffix) + 1)) . '-' . $suffix;
			$suffix++;
		} while (self::postUsing($candidate, $postId) !== null);

		return $candidate;
	}

	public function redirect(\WP $wp): void
	{
		$slug = self::slug();
		if ($slug === '' || $wp->request !== $slug) {
			return;
		}

		$query = isset($_SERVER['QUERY_STRING']) ? wp_unslash($_SERVER['QUERY_STRING']) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- wp_safe_redirect() sanitizes the URL.

		// The utm_* parameters are left out of LiteSpeed's cache key, so a cached redirect would hand one visitor's query to the next.
		do_action('litespeed_control_set_nocache', 'amrf contact shortcut');
		nocache_headers();
		wp_safe_redirect(home_url('/') . ($query !== '' ? '?' . $query : '') . self::MODAL_FRAGMENT, 302);
		exit;
	}

	/**
	 * Creates, renames or removes the shortcut's QR code to match the stored slug.
	 */
	public static function syncQr(): void
	{
		$stored = get_option(self::QR_OPTION, []);
		$previous = [
			'file' => (string) ($stored['file'] ?? ''),
			'signature' => (string) ($stored['signature'] ?? ''),
		];

		$slug = self::slug();
		if ($slug === '') {
			Generator::deleteFile($previous['file']);
			delete_option(self::QR_OPTION);
			return;
		}

		$record = Generator::ensure(self::qrTarget($slug), $previous);
		if ($record !== null && $record !== $previous) {
			update_option(self::QR_OPTION, $record, false);
		}
	}

	/**
	 * @return string The QR code's file name, or '' if there is none.
	 */
	public static function qrFile(): string
	{
		$file = (string) (get_option(self::QR_OPTION, [])['file'] ?? '');

		return self::slug() !== '' && Generator::exists($file) ? $file : '';
	}

	private static function qrTarget(string $slug): string
	{
		return add_query_arg(['utm_source' => 'qr', 'utm_content' => $slug], self::url($slug));
	}

	/**
	 * @return string What already uses the slug, or '' if it is free.
	 */
	private static function conflict(string $slug): string
	{
		global $wp_rewrite;

		if (in_array($slug, array_merge(self::RESERVED, (array) $wp_rewrite->feeds), true)) {
			return __('reserved by WordPress', 'amrf-admin');
		}

		$post = self::postUsing($slug, 0);
		if ($post !== null) {
			return get_the_title($post);
		}

		global $wpdb;
		$old_owner = (int) $wpdb->get_var($wpdb->prepare(
			"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_wp_old_slug' AND meta_value = %s LIMIT 1",
			$slug
		));

		/* translators: %s: title of the page that used to have this slug */
		return $old_owner ? sprintf(__('a former address of "%s"', 'amrf-admin'), get_the_title($old_owner)) : '';
	}

	private static function postUsing(string $slug, int $excludeId): ?\WP_Post
	{
		global $wpdb;

		$id = (int) $wpdb->get_var($wpdb->prepare(
			"SELECT ID FROM {$wpdb->posts} WHERE post_name = %s AND post_type IN ('page', 'post') AND post_status NOT IN ('auto-draft', 'inherit') AND ID != %d LIMIT 1",
			$slug,
			$excludeId
		));

		return $id ? get_post($id) : null;
	}
}
