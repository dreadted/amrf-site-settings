<?php

namespace Antropomorf\ContactForm;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Storage, defaults and sanitizing for the GDPR tab's settings.
 *
 * @package Antropomorf\ContactForm
 */
class GdprRepository
{
	public const OPTION_NAME = 'amrf_fluentform_privacy';

	/**
	 * @return array{contact_form_ids: int[], retention_days: string}
	 */
	public static function getDefaults(): array
	{
		return [
			'contact_form_ids' => [],
			'retention_days' => '',
		];
	}

	/**
	 * @return array{contact_form_ids: int[], retention_days: string}
	 */
	public static function getSettings(): array
	{
		$stored = get_option(self::OPTION_NAME, []);
		return wp_parse_args(is_array($stored) ? $stored : [], self::getDefaults());
	}

	/**
	 * @param mixed $input Raw POSTed value for this option.
	 * @return array{contact_form_ids: int[], retention_days: string}
	 */
	public static function sanitize($input): array
	{
		$input = is_array($input) ? $input : [];
		$current = self::getSettings();

		// "_submitted" marker disambiguates "not submitted" from "submitted, all unchecked".
		if (array_key_exists('contact_form_ids_submitted', $input) || array_key_exists('contact_form_ids', $input)) {
			$ids = isset($input['contact_form_ids']) && is_array($input['contact_form_ids'])
				? array_map('absint', $input['contact_form_ids'])
				: [];
			$contact_form_ids = array_values(array_unique(array_filter($ids)));
		} else {
			$contact_form_ids = $current['contact_form_ids'];
		}

		return [
			'contact_form_ids' => $contact_form_ids,
			'retention_days' => (string) absint($input['retention_days'] ?? $current['retention_days']),
		];
	}

	/**
	 * @param int[] $formIds Forms to add to the retention list.
	 * @return void
	 */
	public static function addContactFormIds(array $formIds): void
	{
		if (empty($formIds)) {
			return;
		}

		$settings = self::getSettings();
		$settings['contact_form_ids'] = array_merge($settings['contact_form_ids'], $formIds);
		update_option(self::OPTION_NAME, self::sanitize($settings));
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
