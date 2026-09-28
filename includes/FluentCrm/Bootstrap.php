<?php

namespace Antropomorf\FluentCrm;

if (!defined('ABSPATH')) {
  exit;
}

/**
 * Entry point for the FluentCrm module.
 *
 * @package Antropomorf\FluentCrm
 */
class Bootstrap
{
  public static function register(): void
  {
    new Provider();
    new PublicStyles();
  }
}
