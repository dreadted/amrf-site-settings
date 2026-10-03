<?php

namespace Antropomorf\FluentCrm;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AnalyticsExclusion
 *
 * Keeps Umami off FluentCRM's public pages (unsubscribe, manage subscription,
 * double opt-in confirmation, view in browser). Inert when FluentCRM isn't active.
 *
 * @package Antropomorf\FluentCrm
 */
class AnalyticsExclusion
{
	public function __construct()
	{
		add_action('plugins_loaded', [$this, 'register']);
	}

	public function register(): void
	{
		if (!defined('FLUENTCRM')) {
			return;
		}

		add_filter('amrf_umami_track_request', [$this, 'skipPublicPages']);
	}

	/**
	 * Their URLs carry the contact's secure_hash, which Umami would record with the query string.
	 *
	 * @param bool $track Whether this request loads the Umami tracker.
	 * @return bool
	 */
	public function skipPublicPages(bool $track): bool
	{
		$param = defined('FLUENTCRM_EXTERNAL_URL_PARAM') ? FLUENTCRM_EXTERNAL_URL_PARAM : 'fluentcrm';

		return isset($_GET[$param]) ? false : $track; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- presence check only.
	}
}
