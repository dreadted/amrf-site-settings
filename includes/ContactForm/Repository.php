<?php

namespace Antropomorf\ContactForm;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Repository
 *
 * Storage/defaults/sanitization for the Contact Forms page: which
 * FluentForm form the sitewide "#kontakt" lightbox opens
 * (default_contact_form_id), whether this plugin's own consistency CSS is
 * applied to every FluentForm on the site (enable_consistent_styling),
 * whether sitewide ALTCHA spam protection is on (altcha_enabled, see
 * ContactForm\Altcha — on by default, off is only for a site that wants to
 * run its own spam protection instead), and — under that page's "GDPR"
 * heading — which forms the daily retention cron/personal-data
 * export-erase requests apply to (contact_form_ids, replacing the old
 * free-text 'form_ids' with a checkbox list of forms that actually exist)
 * and for how long (retention_days). Also owns altcha_hmac_key (see
 * getAltchaHmacKey()) — unlike altcha_enabled, not a user-facing setting
 * at all, just stored alongside the rest.
 *
 * OPTION_NAME deliberately keeps its original 'amrf_fluentform_privacy'
 * value rather than renaming to match this module's new ContactForm
 * namespace — renaming it would silently drop every real site's already-
 * saved retention/export settings on upgrade unless migrated, for a purely
 * cosmetic gain (nothing reads the option name directly outside this
 * class). getSettings() below instead migrates the OLD 'form_ids'
 * comma-separated string shape into the new 'contact_form_ids' array
 * shape in place, the same "self-heal on read" approach RetentionCron
 * already uses for its cron schedule.
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
            // On by default — a site that wants to run its own spam
            // protection instead (or none at all) can turn this off, but
            // the point of it needing no configuration is that it works
            // out of the box otherwise.
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

        // Migrate the old free-text 'form_ids' field ("1, 2, 3") into the
        // new 'contact_form_ids' int[] shape the checkbox-list field now
        // uses, once — only when a site still has the legacy key and
        // hasn't been saved under the new shape yet.
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

        // Same "_submitted marker" disambiguation as SiteSettings\Provider's
        // page_list field type: a checkbox/checkbox-list is simply absent
        // from $_POST when every box is unchecked, so its own absence can't
        // tell "this page wasn't submitted" apart from "submitted, all
        // cleared" without an always-present hidden sibling field.
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
     * Auto-generated once per site and stored invisibly, on first use —
     * unlike Cloudflare Turnstile's site key, ALTCHA's secret doesn't need
     * to be human-chosen, memorable, or portable between sites (a
     * challenge is only ever verified by the same site that issued it), so
     * there's nothing a settings field would let a site owner usefully do
     * with it except accidentally leak, weaken, or desync it. Storage
     * intentionally bypasses sanitize()/the Settings API entirely — this
     * key is never part of any form submission.
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
