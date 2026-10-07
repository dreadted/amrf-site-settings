<?php

namespace Antropomorf\PageQr;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Last column of the Pages list: a thumbnail that downloads the page's QR code, replacing Comments.
 *
 * @package Antropomorf\PageQr
 */
class AdminColumn
{
	private const COLUMN = 'amrf_qr';
	private const STYLE_HANDLE = 'amrf-page-qr';

	public static function register(): void
	{
		// Late, so columns added by the theme come before it.
		add_filter('manage_page_posts_columns', [self::class, 'addColumn'], PHP_INT_MAX);
		add_action('manage_page_posts_custom_column', [self::class, 'renderColumn'], 10, 2);
		add_action('admin_enqueue_scripts', [self::class, 'enqueueOnPagesList']);
	}

	/**
	 * @param array<string, string> $columns
	 * @return array<string, string>
	 */
	public static function addColumn(array $columns): array
	{
		unset($columns['comments']);
		$columns[self::COLUMN] = 'QR';

		return $columns;
	}

	public static function renderColumn(string $column, int $postId): void
	{
		if ($column !== self::COLUMN) {
			return;
		}

		$page = get_post($postId);
		if (!$page) {
			return;
		}

		// Also catches changes no save of this page reports, e.g. it gaining subpages or a new domain.
		Generator::sync($page);

		$file = Generator::file($postId);
		if ($file === '') {
			return;
		}

		echo self::thumbnail($file, get_permalink($page)); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in thumbnail().
	}

	/**
	 * Thumbnail link that downloads the code, with the address it leads to as tooltip.
	 */
	public static function thumbnail(string $file, string $address): string
	{
		$src = add_query_arg('ver', filemtime(Generator::filePath($file)), Generator::fileUrl($file));

		return sprintf(
			'<a class="amrf-page-qr" href="%1$s" download="%2$s" title="%3$s"><img src="%1$s" alt="%3$s" width="40" height="40" loading="lazy"></a>',
			esc_url($src),
			esc_attr($file),
			esc_attr($address)
		);
	}

	public static function enqueueStyle(): void
	{
		wp_enqueue_style(
			self::STYLE_HANDLE,
			AMRF_ADMIN_PLUGIN_URL . 'assets/css/amrf-page-qr.css',
			[],
			filemtime(AMRF_ADMIN_PLUGIN_DIR . '/assets/css/amrf-page-qr.css')
		);
	}

	public static function enqueueOnPagesList(string $hookSuffix): void
	{
		if ($hookSuffix !== 'edit.php' || get_current_screen()?->post_type !== 'page') {
			return;
		}

		self::enqueueStyle();
	}
}
