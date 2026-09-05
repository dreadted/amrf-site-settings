<?php

namespace Antropomorf\Swish;

if (!defined('ABSPATH')) {
  exit;
}

/**
 * Class FrontendProvider
 *
 * Sitewide "#swish" link handling — ported from ptsussis-theme's
 * blocks/cta/index.js, generalized: that version swapped a specific
 * data-swish-trigger button (a Gutenberg CTA block's own rendering);
 * amrf-theme has no block/CTA system to hang that off of, so this instead
 * scans the whole rendered page for plain <a href="#swish"> anywhere in
 * content/menus/widgets and swaps those. One global config object, not
 * per-element data attributes — there's only ever one Swish account per
 * site.
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
      [],
      filemtime(AMRF_ADMIN_PLUGIN_DIR . '/assets/js/amrf-swish.js'),
      true
    );

    wp_localize_script(self::SCRIPT_HANDLE, 'amrfSwish', [
      'swishUrl' => \Antropomorf\SiteSettings\Swish::buildUrl(
        $settings['number'],
        $settings['amount'],
        $settings['amount_editable'] === '1',
        $settings['message'],
        $settings['message_editable'] === '1'
      ),
      'qrSrc' => $settings['qr_url'],
      'qrAlt' => $settings['number'],
    ]);
  }
}
