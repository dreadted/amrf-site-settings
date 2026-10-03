<?php

namespace Antropomorf\Branding;

if (!defined('ABSPATH')) {
  exit;
}

/**
 * Class Provider
 *
 * Prints a stylized console.log badge on every front-end page load, colored
 * with the theme's --amrf-primary-color, --amrf-secondary-color and
 * --amrf-text-color.
 *
 * @package Antropomorf\Branding
 */
class Provider
{
  private const SCRIPT_HANDLE = 'amrf-console-header';

  public function __construct()
  {
    add_action('wp_enqueue_scripts', [$this, 'enqueueConsoleHeader']);
  }

  /**
   * @return void
   */
  public function enqueueConsoleHeader(): void
  {
    wp_enqueue_script(
      self::SCRIPT_HANDLE,
      AMRF_ADMIN_PLUGIN_URL . 'assets/js/amrf-console-header.js',
      [],
      filemtime(AMRF_ADMIN_PLUGIN_DIR . '/assets/js/amrf-console-header.js'),
      ['strategy' => 'defer', 'in_footer' => true]
    );

    $theme = wp_get_theme();

    wp_localize_script(self::SCRIPT_HANDLE, 'amrfBranding', [
      'author' => $theme->get('Author'),
      'version' => $theme->get('Version'),
    ]);
  }
}
