<?php

namespace Antropomorf\Branding;

if (!defined('ABSPATH')) {
  exit;
}

/**
 * Class Provider
 *
 * Generalized from amrf-theme's assets/scripts.js consoleHeader() — a
 * stylized "%c" console.log badge showing the site host, theme version,
 * and author, on every front-end page load.
 *
 * The original fetched the theme's own style.css over the network and
 * regex-matched "Author:"/"Version:"/"--primary:"/"--dark:" out of the raw
 * text — fragile (breaks if that header/CSS-variable formatting ever
 * shifts) and needless: WordPress already knows the active theme's Author/
 * Version via wp_get_theme(), no fetch() required. Colors come from
 * apply_filters('amrf_site_colors', [...]) instead of scraping CSS custom
 * properties — same mechanism SupportGenix\Provider already uses, so a
 * theme declares its colors once and every consumer shares them.
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
