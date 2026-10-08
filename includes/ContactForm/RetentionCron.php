<?php

namespace Antropomorf\ContactForm;

use FluentForm\App\Services\Submission\SubmissionService;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Daily cleanup of old FluentForm submissions and form-view statistics — FluentForm's free tier
 * saves its own per-form "auto_delete_days" setting but never acts on it.
 * Newsletter consent is logged in FluentCRM, so opt-in submissions expire too.
 *
 * @package Antropomorf\ContactForm
 */
class RetentionCron
{
	public const HOOK = 'amrf_fluentform_retention_cleanup';

	public function __construct()
	{
		register_activation_hook(AMRF_ADMIN_PLUGIN_FILE, [self::class, 'scheduleOnActivation']);
		register_deactivation_hook(AMRF_ADMIN_PLUGIN_FILE, [self::class, 'unscheduleOnDeactivation']);
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

	public static function unscheduleOnDeactivation(): void
	{
		wp_clear_scheduled_hook(self::HOOK);
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
		if (!class_exists(SubmissionService::class)) {
			return;
		}

		$days = GdprRepository::getRetentionDays();
		if ($days < 1) {
			return;
		}

		$form_ids = GdprRepository::getContactFormIds();
		if (empty($form_ids)) {
			return;
		}

		global $wpdb;
		$placeholders = implode(',', array_fill(0, count($form_ids), '%d'));

		$rows = $wpdb->get_results($wpdb->prepare(
			"SELECT id, form_id FROM {$wpdb->prefix}fluentform_submissions WHERE form_id IN ($placeholders) AND created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
			array_merge($form_ids, [$days])
		));

		$expired = [];
		foreach ($rows as $row) {
			$expired[(int) $row->form_id][] = (int) $row->id;
		}

		// Also clears entry_details, submission_meta and logs, which hold copies of the email.
		$submissions = new SubmissionService();
		foreach ($expired as $form_id => $ids) {
			$submissions->deleteEntries($ids, $form_id);
		}

		// Form-view statistics log every visitor's IP, whether they submit or not.
		$wpdb->query($wpdb->prepare(
			"DELETE FROM {$wpdb->prefix}fluentform_form_analytics WHERE form_id IN ($placeholders) AND created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
			array_merge($form_ids, [$days])
		));
	}
}
