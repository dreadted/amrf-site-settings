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
     * @param string $swishNumber     Swish\Repository's own 'number' field.
     * @param string $amount          Prefilled amount, or '' for none.
     * @param bool   $amountEditable  Whether the payer can change $amount after scanning.
     * @param string $message         Prefilled message, or '' for none.
     * @param bool   $messageEditable Whether the payer can change $message after scanning.
     * @return string Deep link, or '' if no number is set.
     */
    public static function buildUrl(
        string $swishNumber,
        string $amount = '',
        bool $amountEditable = true,
        string $message = '',
        bool $messageEditable = true
    ): string {
        if ($swishNumber === '') {
            return '';
        }

        $url = 'https://app.swish.nu/1/p/sw/?sw=' . rawurlencode($swishNumber) . '&cur=SEK';

        $editable = [];

        if ($amount !== '') {
            $url .= '&amt=' . rawurlencode($amount);
            if ($amountEditable) {
                $editable[] = 'amt';
            }
        }

        if ($message !== '') {
            $url .= '&msg=' . rawurlencode($message);
            if ($messageEditable) {
                $editable[] = 'msg';
            }
        }

        if (!empty($editable)) {
            $url .= '&edit=' . implode(',', $editable);
        }

        return $url;
    }
}
