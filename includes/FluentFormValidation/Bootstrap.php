<?php

namespace Antropomorf\FluentFormValidation;

if (!defined('ABSPATH')) {
  exit;
}

/**
 * Entry point for the FluentFormValidation module.
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
