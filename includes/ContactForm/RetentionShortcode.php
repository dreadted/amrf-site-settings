<?php

namespace Antropomorf\ContactForm;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class RetentionShortcode
 *
 * [amrf_contact_retention] — the "Delete submissions after this many days"
 * GDPR setting (Repository::getRetentionDays()), rendered as a
 * grammatically correct noun phrase for use in a Privacy Policy page's own
 * content, so that copy can never drift out of sync with the
 * admin-configured value the way a hand-typed number would.
 *
 * @package Antropomorf\ContactForm
 */
class RetentionShortcode
{
	public function __construct()
	{
		add_shortcode('amrf_contact_retention', [$this, 'render']);
	}

	/**
	 * "for up to 90 days" / "for up to 1 day" / "until further notice" (0 =
	 * keep forever, see Repository::getRetentionDays()'s own doc comment —
	 * a plain "%d days" would misleadingly read "for up to 0 days" there).
	 */
	public function render(): string
	{
		$days = Repository::getRetentionDays();

		if ($days < 1) {
			return esc_html__('until further notice', 'amrf-admin');
		}

		return esc_html(sprintf(
			/* translators: %d: number of days */
			_n('for up to %d day', 'for up to %d days', $days, 'amrf-admin'),
			$days
		));
	}
}
