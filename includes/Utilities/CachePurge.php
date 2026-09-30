<?php

namespace Antropomorf\Utilities;

if (!defined('ABSPATH')) {
    exit;
}

// Page caches keep serving output built from an option's old value until purged; no-op without LiteSpeed Cache.
class CachePurge
{
    public static function onOptionChange(string $option): void
    {
        add_action('add_option_' . $option, [self::class, 'purgeAll']);
        add_action('update_option_' . $option, [self::class, 'purgeAll']);
    }

    public static function purgeAll(): void
    {
        do_action('litespeed_purge_all', 'amrf-site-settings');
    }
}
