<?php

namespace Antropomorf\Utilities;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class MenuScanner
 *
 * Scans registered WordPress admin menus and submenus to collect menu item metadata.
 *
 * @package Antropomorf\Utilities
 */
class MenuScanner
{
	/**
	 * @param array $roles List of roles to include in the scan.
	 * @return array Menu items organized by role.
	 */
	public static function scanMenuItems(array $roles): array
	{
		global $menu, $submenu;

		$all = [];

		foreach ($roles as $role_slug => $info) {
			if ($role_slug === 'administrator') {
				continue;
			}

			$all[$role_slug] = ['menu_items' => []];

			if (!is_null($menu) && is_array($menu)) {
				foreach ($menu as $item) {
					if (empty($item[2]) || strpos($item[2], 'separator') !== false) {
						continue;
					}
					$name = self::getCleanMenuName($item[0]);
					if (!self::slugExists($all[$role_slug]['menu_items'], $item[2])) {
						$all[$role_slug]['menu_items'][] = ['name' => trim($name), 'slug' => $item[2]];
					}
				}
			}

			if (!is_null($submenu) && is_array($submenu)) {
				foreach ($submenu as $parent => $items) {
					foreach ($items as $item) {
						if (empty($item[2]) || strpos($item[2], 'separator') !== false) {
							continue;
						}

						$parent_name = '';
						foreach ($menu as $top) {
							if (! empty($top[2]) && $top[2] === $parent) {
								$parent_name = self::getCleanMenuName($top[0]);
								break;
							}
						}

						// Third-party entries: only admin pages under a visible top-level menu, not external (upsell) links.
						$is_core_or_own = strpos($item[2], '.php') !== false || strpos($item[2], 'amrf-') === 0;
						if (!$is_core_or_own && ($parent_name === '' || preg_match('#^https?://#i', $item[2]))) {
							continue;
						}

						$subname = self::getCleanMenuName($item[0]);
						if ($parent_name !== '') {
							$subname = $parent_name . ' / ' . $subname;
						}

						$exists = false;
						foreach ($all[$role_slug]['menu_items'] as $existing) {
							if ($existing['slug'] === $parent) {
								$exists = true;
								break;
							}
						}

						if (! $exists) {
							foreach ($menu as $top) {
								if (! empty($top[2]) && $top[2] === $parent) {
									$all[$role_slug]['menu_items'][] = ['name' => self::getCleanMenuName($top[0]), 'slug' => $top[2]];
									break;
								}
							}
						}

						if (!self::slugExists($all[$role_slug]['menu_items'], $item[2])) {
							$all[$role_slug]['menu_items'][] = ['name' => $subname, 'slug' => $item[2]];
						}
					}
				}
			}
		}

		foreach ($all as $role => $data) {
			usort($all[$role]['menu_items'], fn($a, $b) => strcmp($a['name'], $b['name']));
		}

		return $all;
	}

	private static function getCleanMenuName($menu_title)
	{
		$clean = preg_replace('/<span\b[^>]*>.*?<\/span>/si', '', $menu_title);
		$clean = wp_strip_all_tags($clean);
		$clean = html_entity_decode($clean, ENT_QUOTES | ENT_HTML5, 'UTF-8');
		$clean = trim($clean);
		return $clean;
	}

	private static function slugExists(array $menuItems, string $slug): bool
	{
		foreach ($menuItems as $item) {
			if ($item['slug'] === $slug) {
				return true;
			}
		}
		return false;
	}
}
