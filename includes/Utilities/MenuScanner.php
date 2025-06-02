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
        foreach ($roles as $slug => $info) {
            if ($slug === 'administrator') {
                continue;
            }
            $user = new \WP_User(0);
            $user->add_role($slug);
            $all[$slug] = ['menu_items' => []];
            foreach ($menu as $item) {
                if (empty($item[2]) || strpos($item[2], 'separator') !== false) {
                    continue;
                }
                if (user_can($user, $item[1])) {
                    $name = wp_strip_all_tags($item[0]);
                    if ($item[2] === 'edit-comments.php') {
                        $name = 'Comments';
                    }
                    $all[$slug]['menu_items'][] = ['name' => trim($name), 'slug' => $item[2]];
                }
            }
            foreach ($submenu as $parent => $items) {
                foreach ($items as $item) {
                    if (empty($item[2]) || strpos($item[2], 'separator') !== false) {
                        continue;
                    }
                    if (! user_can($user, $item[1])) {
                        continue;
                    }
                    $subname = wp_strip_all_tags($item[0]);
                    $exists = false;
                    foreach ($all[$slug]['menu_items'] as $existing) {
                        if ($existing['slug'] === $parent) {
                            $exists = true;
                            break;
                        }
                    }
                    if (! $exists) {
                        foreach ($menu as $top) {
                            if (! empty($top[2]) && $top[2] === $parent) {
                                $all[$slug]['menu_items'][] = ['name' => wp_strip_all_tags($top[0]), 'slug' => $top[2]];
                                break;
                            }
                        }
                    }
                    $all[$slug]['menu_items'][] = ['name' => $subname, 'slug' => $item[2]];
                }
            }
        }
        $common = [
            'rank-math' => 'Rank Math',
            'fluent_forms' => 'Fluent Forms',
            'support-tickets' => 'Support Tickets',
            'umami-analytics' => 'Umami Analytics',
            '#builder_active' => __('Page Builder', AMRF_ADMIN_TEXT_DOMAIN),
        ];
        foreach ($common as $slug => $name) {
            foreach ($all as $role => $data) {
                $found = false;
                foreach ($data['menu_items'] as $item) {
                    if ($item['slug'] === $slug) {
                        $found = true;
                        break;
                    }
                }
                if (! $found) {
                    $all[$role]['menu_items'][] = ['name' => $name, 'slug' => $slug];
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
}