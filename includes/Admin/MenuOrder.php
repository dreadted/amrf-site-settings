<?php

namespace Antropomorf\Admin;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * For non-administrators, moves FluentCRM and Fluent Forms from the top of
 * the admin menu to right after Pages.
 *
 * @package Antropomorf\Admin
 */
class MenuOrder
{
	private const ANCHOR_SLUG = 'edit.php?post_type=page';

	/** Top-level menu slugs placed after the anchor, in this order. */
	private const MOVED_SLUGS = ['fluentcrm-admin', 'fluent_forms'];

	public function __construct()
	{
		add_filter('custom_menu_order', [$this, 'isNonAdministrator']);
		add_filter('menu_order', [$this, 'reorder']);
	}

	public function isNonAdministrator($custom): bool
	{
		return $custom || !current_user_can('manage_options');
	}

	/**
	 * @param array $order Top-level menu slugs in display order.
	 * @return array
	 */
	public function reorder($order)
	{
		if (!is_array($order) || current_user_can('manage_options')) {
			return $order;
		}

		$moved = array_values(array_intersect(self::MOVED_SLUGS, $order));
		$rest = array_values(array_diff($order, $moved));
		$anchor = array_search(self::ANCHOR_SLUG, $rest, true);

		if (!$moved || $anchor === false) {
			return $order;
		}

		array_splice($rest, $anchor + 1, 0, $moved);

		return $rest;
	}
}
