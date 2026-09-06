<?php

namespace Antropomorf\Branding;

if (!defined('ABSPATH')) {
  exit;
}

/**
 * Entry point for the Branding module.
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
