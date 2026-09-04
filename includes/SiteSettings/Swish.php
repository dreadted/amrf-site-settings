<?php

namespace Antropomorf\SiteSettings;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Swish
 *
 * Swish is a nationwide Swedish payment scheme, not specific to any one
 * business that uses it — its own documented "open the app with these
 * details prefilled" deep-link format, ported from ptsussis-theme's
 * ptsussis_build_swish_url() unchanged.
 *
 * @package Antropomorf\SiteSettings
 */
class Swish
{
    /**
     * @param string $swishNumber Site Settings' own swish_number field.
     * @return string Deep link, or '' if no number is set.
     */
    public static function buildUrl(string $swishNumber): string
    {
        return $swishNumber !== ''
            ? 'https://app.swish.nu/1/p/sw/?sw=' . rawurlencode($swishNumber) . '&cur=SEK&edit=amt,msg'
            : '';
    }
}
