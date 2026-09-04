<?php

namespace Antropomorf\Branding;

if (!defined('ABSPATH')) {
  exit;
}

/**
 * Class Bootstrap
 *
 * Entry point for the Branding module, called once from amrf-admin.php
 * alongside the other modules' Bootstrap::register() calls.
 *
 * @package Antropomorf\Branding
 */
class Bootstrap
{
  public static function register(): void
  {
    new Provider();
  }
}
