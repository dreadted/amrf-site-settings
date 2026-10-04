<?php

namespace Antropomorf\Swish;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Sitewide "#swish" links, configured by one global object since a site has one Swish account.
 *
 * @package Antropomorf\Swish
 */
class FrontendProvider
{
	private const SCRIPT_HANDLE = 'amrf-swish';

	public function __construct()
	{
		add_action('wp_enqueue_scripts', [$this, 'enqueueAssets']);
	}

	/**
	 * No-ops entirely on a site that hasn't configured a Swish number —
	 * no point shipping the script/style at all.
	 *
	 * @return void
	 */
	public function enqueueAssets(): void
	{
		$settings = Repository::getSettings();
		if ($settings['number'] === '') {
			return;
		}

		wp_enqueue_style(
			self::SCRIPT_HANDLE,
			AMRF_ADMIN_PLUGIN_URL . 'assets/css/amrf-swish.css',
			[],
			filemtime(AMRF_ADMIN_PLUGIN_DIR . '/assets/css/amrf-swish.css')
		);

		wp_enqueue_script(
			self::SCRIPT_HANDLE,
			AMRF_ADMIN_PLUGIN_URL . 'assets/js/amrf-swish.js',
			[\Antropomorf\SiteSettings\ContactLinks::HANDLE],
			filemtime(AMRF_ADMIN_PLUGIN_DIR . '/assets/js/amrf-swish.js'),
			true
		);

		wp_localize_script(self::SCRIPT_HANDLE, 'amrfSwish', [
			'swishUrl' => DeepLink::buildUrl(
				$settings['number'],
				$settings['amount'],
				$settings['amount_editable'] === '1',
				$settings['message'],
				$settings['message_editable'] === '1'
			),
			'qrSrc' => QrCodeGenerator::url($settings),
			'qrAlt' => $settings['number'],
		]);
	}
}
