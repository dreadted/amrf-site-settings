<?php

namespace Antropomorf\Umami;

if (!defined('ABSPATH')) {
  exit;
}

/**
 * Entry point for the Umami module.
 *
 * @package Antropomorf\Umami
 */
class Bootstrap
{
  public static function register(): void
  {
    register_activation_hook(AMRF_ADMIN_PLUGIN_FILE, [Repository::class, 'migrateFromThemeIfNeeded']);

    new Provider();
  }
}
