<?php

namespace Antropomorf\Translations;

if (!defined('ABSPATH')) {
  exit;
}

/**
 * Entry point for the Translations module.
 *
 * @package Antropomorf\Translations
 */
class Bootstrap
{
  public static function register(): void
  {
    new Provider();
    new FluentFormAdminStrings();
  }
}
