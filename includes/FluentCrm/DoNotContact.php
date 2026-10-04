<?php

namespace Antropomorf\FluentCrm;

use FluentCrm\App\Models\Subscriber;
use FluentCrm\App\Models\SubscriberNote;
use FluentCrm\App\Models\Tag;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * "Do not contact": tagging unsubscribes, no status change re-subscribes while tagged, and opt-ins send no confirmation.
 *
 * @package Antropomorf\FluentCrm
 */
class DoNotContact
{
	private const TAG_SLUG = 'do-not-contact';

	public function __construct()
	{
		add_action('plugins_loaded', [$this, 'register']);
	}

	public function register(): void
	{
		if (!defined('FLUENTCRM')) {
			return;
		}

		// Created up front so it's there to pick in FluentCRM's tag list.
		add_action('admin_init', [self::class, 'getTagId']);
		add_action('fluent_crm/contact_added_to_tags', [$this, 'unsubscribeTagged'], 10, 2);
		add_action('fluent_crm/subscriber_status_changed', fn($subscriber, $old, $new) => SiteLocale::run(fn() => $this->keepTaggedUnsubscribed($subscriber, $old, $new)), 5, 3);
	}

	/**
	 * @return int The tag's ID, created if missing.
	 */
	public static function getTagId(): int
	{
		$tag = Tag::where('slug', self::TAG_SLUG)->first();
		if (!$tag) {
			$tag = Tag::create([
				'title' => SiteLocale::run(fn() => __('Do not contact', 'amrf-admin')),
				'slug' => self::TAG_SLUG,
			]);
		}

		return (int) $tag->id;
	}

	public static function isTagged(Subscriber $subscriber): bool
	{
		return $subscriber->hasAnyTagId([self::getTagId()]);
	}

	public static function tag(Subscriber $subscriber): void
	{
		$subscriber->attachTags([self::getTagId()]);
	}

	/** Unsubscribes and strips a tagged contact, e.g. after a form or status change wrote data back onto it. */
	public static function enforce(Subscriber $subscriber): void
	{
		// Also set in memory, so a caller that saves this instance afterwards doesn't write the old status back.
		$subscriber->status = 'unsubscribed';
		ContactReducer::reduce((int) $subscriber->id, false);
	}

	/**
	 * @param Subscriber $subscriber
	 * @param int[] $tagIds Newly attached tag IDs.
	 */
	public function unsubscribeTagged($subscriber, $tagIds): void
	{
		if (in_array(self::getTagId(), array_map('intval', (array) $tagIds), true) && $subscriber->status !== 'unsubscribed') {
			$subscriber->updateStatus('unsubscribed');
		}
	}

	/**
	 * @param Subscriber $subscriber
	 */
	public function keepTaggedUnsubscribed($subscriber, string $oldStatus, string $newStatus): void
	{
		if (!in_array($newStatus, ['subscribed', 'pending'], true) || !self::isTagged($subscriber)) {
			return;
		}

		self::enforce($subscriber);
		SubscriberNote::create([
			'subscriber_id' => $subscriber->id,
			'type' => 'system_log',
			'title' => __('Subscription blocked', 'amrf-admin'),
			'description' => __('The contact is tagged "Do not contact", so its status was set back to unsubscribed. Remove the tag first to subscribe the contact again.', 'amrf-admin'),
		]);
	}
}
