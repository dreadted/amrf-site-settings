<?php

namespace Antropomorf\Forms;

if (!defined('ABSPATH')) {
  exit;
}

/**
 * Entry point for the Forms module — owns the "Forms" page shell (Menu);
 * ContactForm and Swish register their own tabs onto it independently.
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
