<?php

namespace Antropomorf\Hardening;

if (!defined('ABSPATH')) {
  exit;
}

/**
 * Entry point for the Hardening module.
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
