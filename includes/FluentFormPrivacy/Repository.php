<?php

namespace Antropomorf\FluentFormPrivacy;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Repository
 *
 * Storage/defaults/sanitization for which FluentForm forms this site's
 * privacy handling (retention cron, export/erase requests) applies to, and
 * for how long submissions are kept. Generalized from ptsussis-theme's
 * includes/gdpr.php, which hardcoded a single form_id = 1 constant — a
 * shared plugin can't assume every site only ever has one form.
 *
 * @package Antropomorf\FluentFormPrivacy
 */
class Repository
{
    public const OPTION_NAME = 'amrf_fluentform_privacy';

    /**
     * @return array<string, string>
     */
    public static function getDefaults(): array
    {
        return [
            'retention_days' => '',
            'form_ids' => '',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function getSettings(): array
    {
        $stored = get_option(self::OPTION_NAME, []);
        return wp_parse_args(is_array($stored) ? $stored : [], self::getDefaults());
    }

    /**
     * @param mixed $input Raw POSTed value for this option.
     * @return array<string, string>
     */
    public static function sanitize($input): array
    {
        return [
            'retention_days' => (string) absint(is_array($input) ? ($input['retention_days'] ?? '') : ''),
            'form_ids' => sanitize_text_field(is_array($input) ? ($input['form_ids'] ?? '') : ''),
        ];
    }

    /**
     * @return int[] Deduped, positive form IDs parsed from the comma-
     *               separated 'form_ids' setting, in the order entered.
     */
    public static function getFormIds(): array
    {
        $raw = self::getSettings()['form_ids'];
        if ($raw === '') {
            return [];
        }

        $ids = array_map('absint', explode(',', $raw));
        return array_values(array_unique(array_filter($ids)));
    }

    public static function getRetentionDays(): int
    {
        return absint(self::getSettings()['retention_days']);
    }
}
