<?php

namespace Antropomorf\ContactForm;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class RetentionCron
 *
 * Daily cleanup of old FluentForm submissions — FluentForm's free tier
 * saves its own per-form "auto_delete_days" setting but never acts on it.
 *
 * @package Antropomorf\ContactForm
 */
class RetentionCron
{
    public const HOOK = 'amrf_fluentform_retention_cleanup';

    public function __construct()
    {
        register_activation_hook(AMRF_ADMIN_PLUGIN_FILE, [self::class, 'scheduleOnActivation']);
        // Also self-heal on 'init' in case files were deployed without a
        // proper activation cycle — wp_next_scheduled() keeps this a no-op
        // once the event exists.
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
     * No-ops if FluentForm is missing, no forms are configured, or the
     * retention field is blank/0 — blank means "keep forever", not "delete
     * everything".
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

        $form_ids = Repository::getContactFormIds();
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
