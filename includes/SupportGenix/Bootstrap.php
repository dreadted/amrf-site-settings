<?php

namespace Antropomorf\SupportGenix;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Bootstrap
 *
 * Entry point for the Support Genix module, called once from amrf-admin.php
 * alongside the other modules' Bootstrap::register() calls.
 *
 * @package Antropomorf\SupportGenix
 */
class Bootstrap
{
    public static function register(): void
    {
        new Provider();
    }
}
