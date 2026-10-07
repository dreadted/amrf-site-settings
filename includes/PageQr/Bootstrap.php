<?php

namespace Antropomorf\PageQr;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Entry point for per-page QR codes: kept in sync on save (Generator) and
 * offered for download in the Pages list (AdminColumn).
 *
 * @package Antropomorf\PageQr
 */
class Bootstrap
{
	public static function register(): void
	{
		add_action('wp_after_insert_post', [self::class, 'onSave'], 10, 4);
		add_action('before_delete_post', [self::class, 'onDelete'], 10, 2);
		add_action('update_option_page_on_front', [self::class, 'onFrontPageChange'], 10, 2);

		AdminColumn::register();
	}

	/**
	 * A page's parent can change whether that parent is eligible (e.g. it becomes a hub), so both are synced.
	 */
	public static function onSave(int $postId, \WP_Post $post, bool $update, ?\WP_Post $postBefore): void
	{
		if ($post->post_type !== 'page' || wp_is_post_revision($post) || wp_is_post_autosave($post)) {
			return;
		}

		Generator::sync($post);

		$parents = array_unique(array_filter([$post->post_parent, $postBefore->post_parent ?? 0]));
		foreach ($parents as $parentId) {
			$parent = get_post($parentId);
			if ($parent) {
				Generator::sync($parent);
			}
		}
	}

	public static function onDelete(int $postId, \WP_Post $post): void
	{
		if ($post->post_type === 'page') {
			Generator::remove($postId);
		}
	}

	/**
	 * @param mixed $oldValue
	 * @param mixed $newValue
	 */
	public static function onFrontPageChange($oldValue, $newValue): void
	{
		foreach ([(int) $oldValue, (int) $newValue] as $pageId) {
			$page = $pageId ? get_post($pageId) : null;
			if ($page) {
				Generator::sync($page);
			}
		}
	}
}
