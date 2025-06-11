<?php

namespace Antropomorf\Utilities;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Utility methods for retrieving plugin and file versions.
 */
class VersionHelper
{
    /**
     * Get the plugin version from its header.
     *
     * @return string Plugin version.
     */
    public static function getPluginVersion(): string
    {
        if ( ! function_exists( 'get_plugin_data' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        $data = get_plugin_data( AMRF_ADMIN_PLUGIN_FILE );
        return $data['Version'] ?? '';
    }

    /**
     * Get the modification time of a file.
     *
     * @param string $file_path Path to the file.
     * @return string File modification time or empty string if unavailable.
     */
    public static function getFileVersion( string $file_path ): string
    {
        return file_exists( $file_path ) ? (string) filemtime( $file_path ) : '';
    }

    /**
     * Get combined version string including plugin version and optional file version.
     *
     * @param string|null $file_path Optional file path to append modification time.
     * @return string Combined version string.
     */
    public static function getVersion( ?string $file_path = null ): string
    {
        $version = self::getPluginVersion();
        if ( $file_path ) {
            $file_version = self::getFileVersion( $file_path );
            if ( $file_version !== '' ) {
                $version .= '-' . $file_version;
            }
        }
        return $version;
    }
}