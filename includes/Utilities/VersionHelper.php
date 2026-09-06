<?php

namespace Antropomorf\Utilities;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Utility methods for retrieving plugin and file versions.
 *
 * @package Antropomorf\Utilities
 */
class VersionHelper
{
    public static function getVersion(?string $file_path = null): string
    {
        if (! function_exists('get_plugin_data')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        $data = get_plugin_data(AMRF_ADMIN_PLUGIN_FILE);
        return $data['Version'] ?? '';
    }
}
