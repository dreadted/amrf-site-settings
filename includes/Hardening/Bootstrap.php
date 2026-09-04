<?php

namespace Antropomorf\Hardening;

if (!defined('ABSPATH')) {
  exit;
}

/**
 * Class Bootstrap
 *
 * Entry point for the Hardening module, called once from amrf-admin.php
 * alongside the other modules' Bootstrap::register() calls.
 *
 * @package Antropomorf\Hardening
 */
class Bootstrap
{
  public static function register(): void
  {
    new Provider();
  }
}
