<?php

namespace Antropomorf\FluentCrm;

use FluentCrm\App\Models\Subscriber;
use FluentCrm\App\Models\SubscriberNote;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Adds FluentCRM contacts to WP's personal-data erasure, which FluentCRM lacks.
 * Reduced and tagged "Do not contact", not deleted: the remainder proves consent and blocks further emails.
 *
 * @package Antropomorf\FluentCrm
 */
class PrivacyEraser
{
	private const ERASER_ID = 'amrf-fluentcrm';

	public function __construct()
	{
		add_action('plugins_loaded', [$this, 'register']);
	}

	public function register(): void
	{
		if (!defined('FLUENTCRM')) {
			return;
		}

		add_filter('wp_privacy_personal_data_erasers', [$this, 'registerEraser']);
	}

	public function registerEraser(array $erasers): array
	{
		$erasers[self::ERASER_ID] = [
			'eraser_friendly_name' => __('Newsletter subscriber', 'amrf-admin'),
			'callback' => [$this, 'erasePersonalData'],
		];
		return $erasers;
	}

	/**
	 * @return array{items_removed: bool, items_retained: bool, messages: array, done: bool}
	 */
	public function erasePersonalData(string $email_address, int $page = 1): array
	{
		$subscriber = Subscriber::where('email', $email_address)->first();
		if (!$subscriber) {
			return ['items_removed' => false, 'items_retained' => false, 'messages' => [], 'done' => true];
		}

		$id = (int) $subscriber->id;
		ContactReducer::reduce($id, false);
		DoNotContact::tag(Subscriber::find($id));

		SiteLocale::run(fn() => SubscriberNote::create([
			'subscriber_id' => $id,
			'type' => 'system_log',
			'title' => __('Personal data erased', 'amrf-admin'),
			'description' => __('Personal data erased on request. The email address and the consent and unsubscribe records are kept, to block further newsletters and as proof of consent.', 'amrf-admin'),
		]));

		return [
			'items_removed' => true,
			'items_retained' => true,
			'messages' => [__('The newsletter register keeps the email address and the consent and unsubscribe records, to block further newsletters and as proof of consent.', 'amrf-admin')],
			'done' => true,
		];
	}
}
