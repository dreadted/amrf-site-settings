<?php

namespace Antropomorf\Swish;

if (!defined('ABSPATH')) {
  exit;
}

/**
 * Entry point for the Swish module — the "Swish" tab on the Forms page
 * (Provider), QR generation (QrCodeGenerator, via Repository::sanitize()),
 * and sitewide "#swish" link handling (FrontendProvider).
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
