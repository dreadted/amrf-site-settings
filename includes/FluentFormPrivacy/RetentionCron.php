<?php

namespace Antropomorf\FluentFormPrivacy;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class RetentionCron
 *
 * Daily cleanup of old FluentForm submissions — a real replacement for
 * FluentForm's own per-form "auto_delete_days" setting, which the free
 * plugin saves but never actually reads back to delete anything (confirmed
 * directly against its source: wp-content/plugins/fluentform, grepped for
 * every reference to that meta key — only ever written/removed).
 *
 * @package Antropomorf\FluentFormPrivacy
 */
class RetentionCron
{
    public const HOOK = 'amrf_fluentform_retention_cleanup';

    public function __construct()
    {
        register_activation_hook(AMRF_ADMIN_PLUGIN_FILE, [self::class, 'scheduleOnActivation']);
        // Also self-heal on every normal load, not just plugin activation —
        // this codebase is routinely deployed by syncing files directly onto
        // a running site (see docker-bind-mount-rm-danger memory) rather than
        // through WordPress's own deactivate/activate cycle, so the
        // activation hook alone silently leaves this cron unscheduled after
        // a file-level restore or sync. wp_next_scheduled() guard keeps this
        // a no-op on every request where the event is already scheduled.
        add_action('init', [self::class, 'scheduleOnActivation']);
        add_action(self::HOOK, [$this, 'run']);
    }

    public static function scheduleOnActivation(): void
    {
        if (!wp_next_scheduled(self::HOOK)) {
            wp_schedule_event(time(), 'daily', self::HOOK);
        }
    }

    /**
     * No-ops entirely if FluentForm is missing, no forms are configured, or
     * the retention field is blank/0 — an empty setting means "keep
     * forever", not "delete everything immediately", so this must fail safe
     * toward doing nothing.
     *
     * @return void
     */
    public function run(): void
    {
        if (!shortcode_exists('fluentform')) {
            return;
        }

        $days = Repository::getRetentionDays();
        if ($days < 1) {
            return;
        }

        $form_ids = Repository::getFormIds();
        if (empty($form_ids)) {
            return;
        }

        global $wpdb;
        $placeholders = implode(',', array_fill(0, count($form_ids), '%d'));

        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->prefix}fluentform_submissions WHERE form_id IN ($placeholders) AND created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
            array_merge($form_ids, [$days])
        ));
    }
}
