<?php

namespace Antropomorf\Utilities;

if (!defined('ABSPATH')) {
	exit;
}

class MenuScanner
{
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
					// Skip separators and empty items
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
						// Skip separators and empty items
						if (empty($item[2]) || strpos($item[2], 'separator') !== false) {
							continue;
						}

						// Only process core WordPress submenu items (those ending with .php)
						if (strpos($item[2], '.php') === false) continue;

							$subname = self::getCleanMenuName($item[0]);
							// Prefix submenu item name with its parent menu name
							$parent_name = '';
							foreach ($menu as $top) {
								if (! empty($top[2]) && $top[2] === $parent) {
									$parent_name = self::getCleanMenuName($top[0]);
									break;
								}
							}
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

	public static function scanAdminPages(): array
	{
		global $menu, $submenu;

		$pages = ['profile.php'];
		foreach ($menu as $item) {
			if (! empty($item[2])) {
				$pages[] = $item[2];
			}
		}
		foreach ($submenu as $items) {
			foreach ($items as $item) {
				if (! empty($item[2])) {
					$pages[] = $item[2];
				}
			}
		}
		$pages = array_unique($pages);
		sort($pages);
		return $pages;
	}

	private static function getCleanMenuName($menu_title)
	{
		// Remove all <span>...</span> and their contents (including nested spans)
		$clean = preg_replace('/<span\b[^>]*>.*?<\/span>/si', '', $menu_title);

		// Remove any other HTML tags just in case
		$clean = wp_strip_all_tags($clean);

		// Decode HTML entities
		$clean = html_entity_decode($clean, ENT_QUOTES | ENT_HTML5, 'UTF-8');

		// Trim whitespace
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
