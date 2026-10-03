<?php

namespace Antropomorf\FluentCrm;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class Provider
 *
 * Keeps FluentCRM from setting its contact/campaign identification cookies
 * on visitors. Inert when FluentCRM isn't active.
 *
 * @package Antropomorf\FluentCrm
 */
class Provider
{
	private const BLOCKED_COOKIES = ['fc_hash_secure', 'fc_cid'];

	public function __construct()
	{
		add_action('plugins_loaded', [$this, 'register']);
	}

	public function register(): void
	{
		if (!defined('FLUENTCRM')) {
			return;
		}

		add_filter('fluent_crm/will_use_cookie', '__return_false');
		// Some FluentCRM pages (e.g. unsubscribe) call setcookie() without that filter.
		header_register_callback([$this, 'stripBlockedCookies']);
	}

	public function stripBlockedCookies(): void
	{
		$kept = [];
		$stripped = false;

		foreach (headers_list() as $header) {
			if (stripos($header, 'Set-Cookie:') !== 0) {
				continue;
			}
			if ($this->isBlocked($header)) {
				$stripped = true;
				continue;
			}
			$kept[] = $header;
		}

		if (!$stripped) {
			return;
		}

		header_remove('Set-Cookie');
		foreach ($kept as $header) {
			header($header, false);
		}
	}

	private function isBlocked(string $header): bool
	{
		$name = trim(strtok(substr($header, strlen('Set-Cookie:')), '='));

		return in_array($name, self::BLOCKED_COOKIES, true);
	}
}
