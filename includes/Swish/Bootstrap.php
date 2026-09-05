<?php

namespace Antropomorf\Swish;

if (!defined('ABSPATH')) {
  exit;
}

/**
 * Class Bootstrap
 *
 * Entry point for the Swish module, called once from amrf-site-settings.php
 * alongside the other modules' Bootstrap::register() calls. Covers the
 * "Swish" tab on the Forms page (Provider), QR generation/caching
 * (QrCodeGenerator, invoked from Repository::sanitize() — nothing to
 * register here directly), and the sitewide "#swish" link handling
 * (FrontendProvider).
 *
 * @package Antropomorf\Swish
 */
class Bootstrap
{
  public static function register(): void
  {
    new Provider();
    new FrontendProvider();
  }
}
