<?php

namespace Antropomorf\FluentCrm;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Keeps "Force Subscribe" off on every FluentForm → FluentCRM feed, so an unsubscribed
 * address is only re-subscribed by its owner's double opt-in click (see ConsentLog).
 *
 * @package Antropomorf\FluentCrm
 */
class ForceSubscribeGuard
{
	private const FEED_META_KEY = 'fluentcrm_feeds';

	public function __construct()
	{
		add_action('plugins_loaded', [$this, 'register']);
	}

	public function register(): void
	{
		if (!defined('FLUENTCRM')) {
			return;
		}

		add_filter('fluentform/integration_feed_before_parse', [$this, 'guardRunningFeed']);
		add_filter('fluentform/save_integration_value_fluentcrm', [$this, 'guardSavedFeed']);
	}

	/**
	 * Covers feeds saved before this guard existed or written straight to the database.
	 *
	 * @param array $feed {id, meta_key, settings} as FluentForm passes it before processing.
	 * @return array
	 */
	public function guardRunningFeed($feed)
	{
		if (is_array($feed) && ($feed['meta_key'] ?? '') === self::FEED_META_KEY && is_array($feed['settings'] ?? null)) {
			$feed['settings']['force_subscribe'] = false;
		}

		return $feed;
	}

	/**
	 * Keeps the feed editor's checkbox truthful.
	 *
	 * @param array $feed Feed settings about to be saved.
	 * @return array
	 */
	public function guardSavedFeed($feed)
	{
		if (is_array($feed)) {
			$feed['force_subscribe'] = false;
		}

		return $feed;
	}
}
