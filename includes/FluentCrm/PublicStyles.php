<?php

namespace Antropomorf\FluentCrm;

if (!defined('ABSPATH')) {
  exit;
}

/**
 * Class PublicStyles
 *
 * Layout adjustments for FluentCRM's public unsubscribe/preference pages.
 *
 * @package Antropomorf\FluentCrm
 */
class PublicStyles
{
  private const HANDLE = 'amrf-fluentcrm-public';

  private const FLUENTCRM_HANDLES = ['fluentcrm_unsubscribe', 'fluentcrm_public_pref'];

  public function __construct()
  {
    add_action('wp_enqueue_scripts', [$this, 'enqueue'], 20);
  }

  public function enqueue(): void
  {
    $parents = array_values(array_filter(
      self::FLUENTCRM_HANDLES,
      fn(string $handle) => wp_style_is($handle, 'enqueued')
    ));

    if (!$parents) {
      return;
    }

    wp_enqueue_style(
      self::HANDLE,
      AMRF_ADMIN_PLUGIN_URL . 'assets/css/amrf-fluentcrm-public.css',
      $parents,
      filemtime(AMRF_ADMIN_PLUGIN_DIR . '/assets/css/amrf-fluentcrm-public.css')
    );
  }
}
