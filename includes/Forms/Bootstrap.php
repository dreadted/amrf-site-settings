<?php

namespace Antropomorf\Forms;

if (!defined('ABSPATH')) {
  exit;
}

/**
 * Class Bootstrap
 *
 * Entry point for the Forms module, called once from amrf-site-settings.php
 * alongside the other modules' Bootstrap::register() calls. Only owns the
 * "Forms" page shell (Menu) — ContactForm\Bootstrap and Swish\Bootstrap
 * each register their own tabs onto it independently.
 *
 * @package Antropomorf\Forms
 */
class Bootstrap
{
  public static function register(): void
  {
    new Menu();
  }
}
