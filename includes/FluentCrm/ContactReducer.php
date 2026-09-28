<?php

namespace Antropomorf\FluentCrm;

use FluentCrm\App\Models\CampaignEmail;
use FluentCrm\App\Models\CampaignUrlMetric;
use FluentCrm\App\Models\FunnelMetric;
use FluentCrm\App\Models\FunnelSubscriber;
use FluentCrm\App\Models\Lists;
use FluentCrm\App\Models\Subscriber;
use FluentCrm\App\Models\SubscriberMeta;
use FluentCrm\App\Models\SubscriberNote;
use FluentCrm\App\Models\SubscriberPivot;
use FluentCrm\App\Models\Tag;

if (!defined('ABSPATH')) {
  exit;
}

/**
 * Class ContactReducer
 *
 * Strips an unsubscribed contact down to what's still needed: the email,
 * status, source, dates, unsubscribe reason, system-log notes (consent and
 * unsubscribe records) and the "Do not contact" tag. Runs on every
 * unsubscribe, and from PrivacyEraser. Inert when FluentCRM isn't active.
 *
 * @package Antropomorf\FluentCrm
 */
class ContactReducer
{
  private const KEPT_META_KEYS = ['unsubscribe_reason'];

  public function __construct()
  {
    add_action('plugins_loaded', [$this, 'register']);
  }

  public function register(): void
  {
    if (!defined('FLUENTCRM')) {
      return;
    }

    add_action('fluent_crm/subscriber_status_changed', [$this, 'reduceUnsubscribed'], 20, 3);
  }

  /**
   * @param Subscriber $subscriber
   */
  public function reduceUnsubscribed($subscriber, string $oldStatus, string $newStatus): void
  {
    if ($newStatus === 'unsubscribed') {
      // Lists are kept so a confirmed re-subscription gets the newsletter again.
      self::reduce((int) $subscriber->id, true);
    }
  }

  public static function reduce(int $id, bool $keepLists): void
  {
    // Query-builder update, not updateStatus(): status-change hooks would log a misleading unsubscribe note.
    Subscriber::where('id', $id)->update([
      'user_id' => null,
      'contact_owner' => null,
      'company_id' => null,
      'prefix' => null,
      'first_name' => null,
      'last_name' => null,
      'timezone' => null,
      'address_line_1' => null,
      'address_line_2' => null,
      'postal_code' => null,
      'city' => null,
      'state' => null,
      'country' => null,
      'ip' => null,
      'latitude' => null,
      'longitude' => null,
      'total_points' => 0,
      'life_time_value' => 0,
      'phone' => null,
      'avatar' => null,
      'date_of_birth' => null,
      'last_activity' => null,
      'status' => 'unsubscribed',
      'updated_at' => current_time('mysql'),
    ]);

    $pivot = SubscriberPivot::where('subscriber_id', $id)
      ->where(function ($query) {
        $query->where('object_type', '!=', Tag::class)->orWhere('object_id', '!=', DoNotContact::getTagId());
      });
    if ($keepLists) {
      $pivot->where('object_type', '!=', Lists::class);
    }
    $pivot->delete();

    SubscriberMeta::where('subscriber_id', $id)->whereNotIn('key', self::KEPT_META_KEYS)->delete();
    SubscriberNote::where('subscriber_id', $id)->where('type', '!=', 'system_log')->delete();
    CampaignEmail::where('subscriber_id', $id)->delete();
    CampaignUrlMetric::where('subscriber_id', $id)->delete();
    FunnelMetric::where('subscriber_id', $id)->delete();
    FunnelSubscriber::where('subscriber_id', $id)->delete();
  }
}
