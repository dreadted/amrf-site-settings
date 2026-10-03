<?php

namespace Antropomorf\SiteSettings;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class SettingShortcode
 *
 * [amrf_site_setting field="email"] — quotes one Site Settings field
 * inline in a page's own content, so text like a Privacy Policy page's
 * contact details stays in sync with the admin-configured value instead
 * of a hand-typed copy that silently drifts whenever the setting changes.
 * Restricted to the identity/contact fields a page's body content would
 * plausibly reference — never the SEO/social fields (getFields()'s "seo"/
 * "social" sections), which aren't meant to appear as inline prose.
 * field="domain" gives the site's own host name (e.g. "example.se").
 *
 * @package Antropomorf\SiteSettings
 */
class SettingShortcode
{
	private const ALLOWED_FIELDS = [
		'business_name',
		'person_name',
		'job_title',
		'email',
		'phone',
		'street',
		'postal_code',
		'city',
		'region',
		'country',
	];

	public function __construct()
	{
		add_shortcode('amrf_site_setting', [$this, 'render']);
	}

	/**
	 * @param array<string, mixed>|string $atts
	 */
	public function render($atts): string
	{
		$atts = shortcode_atts(['field' => ''], $atts);
		$field = (string) $atts['field'];

		if ($field === 'domain') {
			return esc_html(preg_replace('/^www\./', '', (string) wp_parse_url(home_url(), PHP_URL_HOST)));
		}

		if (!in_array($field, self::ALLOWED_FIELDS, true)) {
			return '';
		}

		$value = esc_html(Repository::getSettings()[$field] ?? '');

		return $field === 'person_name' ? str_replace(' ', '&nbsp;', $value) : $value;
	}
}
