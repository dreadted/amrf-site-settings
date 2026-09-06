<?php

/**
 * Public, non-namespaced function API for themes to consume — required
 * directly since the autoloader only maps class names, not plain functions.
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * This site's business/contact settings. Every field key always present,
 * defaulted to ''.
 *
 * @return array<string, string>
 */
function amrf_get_site_settings(): array
{
    return \Antropomorf\SiteSettings\Repository::getSettings();
}

/**
 * This site's Swish tab settings (number/amount/message + their own
 * "editable after scanning" toggles). Every field key always present,
 * defaulted to ''.
 *
 * @return array<string, string>
 */
function amrf_get_swish_settings(): array
{
    return \Antropomorf\Swish\Repository::getSettings();
}

/**
 * Swish's own deep-link URL, or '' if no number is set.
 *
 * @param string $swish_number
 * @param string $amount
 * @param bool   $amount_editable
 * @param string $message
 * @param bool   $message_editable
 */
function amrf_build_swish_url(
    string $swish_number,
    string $amount = '',
    bool $amount_editable = true,
    string $message = '',
    bool $message_editable = true
): string {
    return \Antropomorf\SiteSettings\Swish::buildUrl($swish_number, $amount, $amount_editable, $message, $message_editable);
}
