<?php

namespace Antropomorf\Branding;

if (!defined('ABSPATH')) {
  exit;
}

/**
 * Class Provider
 *
 * Prints a stylized console.log badge (site host, theme version, author) on
 * every front-end page load. Colors come from the amrf_site_colors filter
 * so a theme can declare them once for every consumer.
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
    $colors = apply_filters('amrf_site_colors', [
      'primary' => '#1976d2',
      'secondary' => '#1976d2',
    ]);

    wp_localize_script(self::SCRIPT_HANDLE, 'amrfBranding', [
      'author' => $theme->get('Author'),
      'version' => $theme->get('Version'),
      'primary' => $colors['primary'],
      'dark' => $colors['secondary'],
    ]);
  }
}
