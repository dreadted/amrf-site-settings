<?php

namespace Antropomorf\FluentCrm;

if (!defined('ABSPATH')) {
  exit;
}

/**
 * Class SiteLocale
 *
 * Contact notes are stored as text, so they're written in the site's
 * language rather than that of whichever user triggers them.
 *
 * @package Antropomorf\FluentCrm
 */
final class SiteLocale
{
  /**
   * @template T
   * @param callable(): T $callback
   * @return T
   */
  public static function run(callable $callback)
  {
    // The option, not get_locale(), which follows any locale switch already in effect.
    $switched = switch_to_locale(get_option('WPLANG') ?: 'en_US');
    try {
      return $callback();
    } finally {
      if ($switched) {
        restore_previous_locale();
      }
    }
  }
}
