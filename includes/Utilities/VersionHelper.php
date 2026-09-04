<?php

namespace Antropomorf\Utilities;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class VersionHelper
 *
 * RECONSTRUCTED (2026-09-04) after an accidental `rm -rf` deleted the
 * working copy — never read in full, only referenced as
 * VersionHelper::getVersion(). This implementation reads the Version
 * header the standard WordPress way; low-risk to have inferred since the
 * plugin header itself is the authoritative source either way.
 *
 * @package Antropomorf\Utilities
 */
class VersionHelper
{
	/**
	 * @return string
	 */
	public static function getVersion(): string
	{
		if (!function_exists('get_file_data')) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		$data = get_file_data(AMRF_ADMIN_PLUGIN_FILE, ['Version' => 'Version']);
		return $data['Version'] ?? '';
	}
}
