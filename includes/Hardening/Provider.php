<?php

namespace Antropomorf\Hardening;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Always-on hardening: XML-RPC, login errors, version tag, RSD link, ?username=, /wp/v2/users and emoji.
 * The Hardening page's toggles (Uploads, Frontend) change behavior some sites rely on, so each is an opt-out.
 *
 * @package Antropomorf\Hardening
 */
class Provider
{
	public function __construct()
	{
		$this->registerUnconditionalHardening();

		$settings = Repository::getSettings();
		new Uploads($settings);
		new Frontend($settings);
		new SettingsPage();
	}

	/**
	 * @return void
	 */
	private function registerUnconditionalHardening(): void
	{
		add_filter('xmlrpc_enabled', '__return_false');

		add_filter('login_errors', function () {
			return __('There is an error.', 'amrf-admin');
		});

		remove_action('wp_head', 'wp_generator');

		// EditURI advertises xmlrpc.php's existence even though xmlrpc_enabled
		// blocks it above — no reason to point at it at all.
		remove_action('wp_head', 'rsd_link');

		add_action('login_init', [$this, 'blockUsernameInLoginUrl']);
		add_filter('rest_endpoints', [$this, 'disableUsersRestEndpoint']);

		$this->disableEmojiFallback();
	}

	/**
	 * Core's emoji fallback swaps emoji for images from s.w.org, a third-party request.
	 *
	 * @return void
	 */
	private function disableEmojiFallback(): void
	{
		remove_action('wp_head', 'print_emoji_detection_script', 7);
		remove_action('embed_head', 'print_emoji_detection_script');
		// Unhooking the legacy printer also makes wp_enqueue_emoji_styles() bail.
		remove_action('wp_print_styles', 'print_emoji_styles');
		remove_filter('the_content_feed', 'wp_staticize_emoji');
		remove_filter('comment_text_rss', 'wp_staticize_emoji');
		remove_filter('wp_mail', 'wp_staticize_emoji_for_email');

		// Core adds the admin hooks after plugins load.
		add_action('admin_init', function () {
			remove_action('admin_print_scripts', 'print_emoji_detection_script');
			remove_action('admin_print_styles', 'print_emoji_styles');
		});
	}

	/**
	 * @return void
	 */
	public function blockUsernameInLoginUrl(): void
	{
		if (isset($_GET['username'])) {
			wp_redirect(wp_login_url());
			exit;
		}
	}

	/**
	 * @param array $endpoints
	 * @return array
	 */
	public function disableUsersRestEndpoint(array $endpoints): array
	{
		unset($endpoints['/wp/v2/users']);
		return $endpoints;
	}
}
