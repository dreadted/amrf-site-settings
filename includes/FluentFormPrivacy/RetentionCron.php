<?php

namespace Antropomorf\FluentFormPrivacy;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class RetentionCron
 *
 * !!! STUB — NOT THE ORIGINAL IMPLEMENTATION !!!
 *
 * An accidental `rm -rf` deleted the working copy this class lived in
 * (2026-09-04), and it was never read in full during the session that was
 * lost — only referenced (FluentFormPrivacy\Bootstrap::register() calling
 * `new RetentionCron()`). Rather than fabricate GDPR-retention logic from
 * guesswork, this stub does NOTHING — it exists only so the plugin doesn't
 * fatal-error on the missing class. It does NOT delete old FluentForm
 * submissions per Repository::getRetentionDays()/getFormIds() the way the
 * real class did.
 *
 * This needs a real rewrite: a daily cron (wp_schedule_event) that, for
 * each form ID in Repository::getFormIds(), deletes FluentForm entries
 * older than Repository::getRetentionDays() when that value is > 0.
 *
 * @package Antropomorf\FluentFormPrivacy
 */
class RetentionCron
{
    public function __construct()
    {
        // Intentionally inert — see class docblock.
    }
}
