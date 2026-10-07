<?php

/**
 * Deletes the plugin's own data when it is deleted from Plugins.
 *
 * Settings the plugin wrote into other plugins (Fluent Forms, FluentSMTP,
 * The SEO Framework) are left as they are.
 *
 * @package Antropomorf
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
	exit;
}

$options = [
	'amrf_admin_settings',
	'amrf_site_settings',
	'amrf_swish_settings',
	'amrf_fluentform_privacy',
	'amrf_hardening',
	'amrf_umami',
	'amrf_support_genix_defaults_applied',
];
foreach ($options as $option) {
	delete_option($option);
}

delete_transient('amrf_theme_css_tokens');
delete_transient('amrf_support_genix_defaults_processed');

delete_post_meta_by_key('_amrf_upload_hash');
delete_post_meta_by_key('_amrf_page_qr');

// Granted per user by the admin panel's role settings; administrators never get them from this plugin.
$granted_caps = [
	'edit_theme_options',
	'fluentform_dashboard_access',
	'fluentform_entries_viewer',
	'fluentform_manage_entries',
];
foreach (get_users(['fields' => 'ID']) as $user_id) {
	delete_transient('amrf_logging_in_' . $user_id);

	$user = new WP_User($user_id);
	if (in_array('administrator', $user->roles, true)) {
		continue;
	}
	foreach ($granted_caps as $cap) {
		if (isset($user->caps[$cap])) {
			$user->remove_cap($cap);
		}
	}
}

$uploads = wp_upload_dir(null, false);
foreach (['amrf-swish', 'qr-links'] as $subdir) {
	$qr_dir = trailingslashit($uploads['basedir']) . $subdir;
	if (is_dir($qr_dir)) {
		foreach (glob($qr_dir . '/*') ?: [] as $file) {
			wp_delete_file($file);
		}
		rmdir($qr_dir);
	}
}
