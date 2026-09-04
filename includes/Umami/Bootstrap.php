<?php

namespace Antropomorf\Umami;

if (!defined('ABSPATH')) {
  exit;
}

/**
 * Class Bootstrap
 *
 * Entry point for the Umami module, called once from amrf-admin.php
 * alongside the other modules' Bootstrap::register() calls.
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
