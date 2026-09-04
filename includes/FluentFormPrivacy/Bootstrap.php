<?php

namespace Antropomorf\FluentFormPrivacy;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Bootstrap
 *
 * Entry point for the FluentForm Privacy module, called once from
 * amrf-admin.php alongside the other modules' Bootstrap::register() calls.
 *
 * @package Antropomorf\FluentFormPrivacy
 */
class Bootstrap
{
    public static function register(): void
    {
        new Provider();
        new RetentionCron();
        new PrivacyRequests();
        new EmailSignoff();
    }
}
