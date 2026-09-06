<?php

namespace Antropomorf\ContactForm;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Repository
 *
 * Storage/defaults/sanitization for the Contact Forms + GDPR settings:
 * default contact form, consistency CSS, ALTCHA spam protection
 * (ContactForm\Altcha), retention scope/duration, and the ALTCHA HMAC key
 * (getAltchaHmacKey()).
 *
 * OPTION_NAME stays 'amrf_fluentform_privacy' to avoid dropping existing
 * sites' saved settings on upgrade.
 *
 * @package Antropomorf\ContactForm
 */
class Repository
{
    public const OPTION_NAME = 'amrf_fluentform_privacy';

    /**
     * @return array{default_contact_form_id: string, enable_consistent_styling: bool, altcha_enabled: bool, contact_form_ids: int[], retention_days: string}
     */
    public static function getDefaults(): array
    {
        return [
            'default_contact_form_id' => '1',
            'enable_consistent_styling' => false,
            // On by default; a site can opt out for its own spam protection.
            'altcha_enabled' => true,
            'contact_form_ids' => [],
            'retention_days' => '',
        ];
    }

    /**
     * @return array{default_contact_form_id: string, enable_consistent_styling: bool, altcha_enabled: bool, contact_form_ids: int[], retention_days: string}
     */
    public static function getSettings(): array
    {
        $stored = get_option(self::OPTION_NAME, []);
        $stored = is_array($stored) ? $stored : [];

        // One-time migration: legacy comma-separated 'form_ids' -> int[] 'contact_form_ids'.
        if (!array_key_exists('contact_form_ids', $stored) && array_key_exists('form_ids', $stored)) {
            $ids = array_map('absint', explode(',', (string) $stored['form_ids']));
            $stored['contact_form_ids'] = array_values(array_unique(array_filter($ids)));
            unset($stored['form_ids']);
            update_option(self::OPTION_NAME, $stored);
        }

        return wp_parse_args($stored, self::getDefaults());
    }

    /**
     * @param mixed $input Raw POSTed value for this option.
     * @return array{default_contact_form_id: string, enable_consistent_styling: bool, altcha_enabled: bool, contact_form_ids: int[], retention_days: string}
     */
    public static function sanitize($input): array
    {
        $input = is_array($input) ? $input : [];
        $current = self::getSettings();

        $output = [
            'default_contact_form_id' => (string) absint($input['default_contact_form_id'] ?? $current['default_contact_form_id']),
            'retention_days' => (string) absint($input['retention_days'] ?? ''),
        ];

        // "_submitted" marker disambiguates "not submitted" from "submitted, all unchecked".
        $output['enable_consistent_styling'] = array_key_exists('enable_consistent_styling_submitted', $input)
            ? !empty($input['enable_consistent_styling'])
            : $current['enable_consistent_styling'];

        $output['altcha_enabled'] = array_key_exists('altcha_enabled_submitted', $input)
            ? !empty($input['altcha_enabled'])
            : $current['altcha_enabled'];

        if (array_key_exists('contact_form_ids_submitted', $input)) {
            $ids = isset($input['contact_form_ids']) && is_array($input['contact_form_ids'])
                ? array_map('absint', $input['contact_form_ids'])
                : [];
            $output['contact_form_ids'] = array_values(array_unique(array_filter($ids)));
        } else {
            $output['contact_form_ids'] = $current['contact_form_ids'];
        }

        return $output;
    }

    /**
     * @return int Positive form ID, or 0 if unset (no form to show in the
     *             "#kontakt" lightbox).
     */
    public static function getDefaultContactFormId(): int
    {
        return absint(self::getSettings()['default_contact_form_id']);
    }

    public static function isConsistentStylingEnabled(): bool
    {
        return self::getSettings()['enable_consistent_styling'];
    }

    public static function isAltchaEnabled(): bool
    {
        return self::getSettings()['altcha_enabled'];
    }

    /**
     * Auto-generated once per site, stored invisibly — bypasses
     * sanitize()/the Settings API since it's never part of a form submission.
     *
     * @return string
     */
    public static function getAltchaHmacKey(): string
    {
        $stored = get_option(self::OPTION_NAME, []);
        $stored = is_array($stored) ? $stored : [];

        if (empty($stored['altcha_hmac_key'])) {
            $stored['altcha_hmac_key'] = bin2hex(random_bytes(32));
            update_option(self::OPTION_NAME, $stored);
        }

        return $stored['altcha_hmac_key'];
    }

    /**
     * @return int[] Form IDs the retention cron and personal-data export/
     *               erase requests apply to.
     */
    public static function getContactFormIds(): array
    {
        return self::getSettings()['contact_form_ids'];
    }

    public static function getRetentionDays(): int
    {
        return absint(self::getSettings()['retention_days']);
    }
}
