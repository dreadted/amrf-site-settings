<?php

namespace Antropomorf\SiteSettings;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * [amrf_site_setting field="email"]: an identity or contact field in page content, so it can't drift from the setting.
 * SEO and social fields are excluded; field="domain" gives the site's host name.
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
