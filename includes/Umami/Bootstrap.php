<?php

namespace Antropomorf\Umami;

use Antropomorf\Utilities\CachePurge;

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

    CachePurge::onOptionChange(Repository::OPTION_NAME);
  }
}
