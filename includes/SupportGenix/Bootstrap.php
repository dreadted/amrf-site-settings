<?php

namespace Antropomorf\SupportGenix;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Entry point for the Support Genix module.
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
