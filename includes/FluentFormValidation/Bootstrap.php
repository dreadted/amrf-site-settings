<?php

namespace Antropomorf\FluentFormValidation;

if (!defined('ABSPATH')) {
  exit;
}

/**
 * Class Bootstrap
 *
 * Entry point for the FluentFormValidation module, called once from
 * amrf-admin.php alongside the other modules' Bootstrap::register() calls.
 *
 * @package Antropomorf\FluentFormValidation
 */
class Bootstrap
{
  public static function register(): void
  {
    new Provider();
  }
}
