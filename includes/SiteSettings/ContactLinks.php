<?php

namespace Antropomorf\SiteSettings;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class ContactLinks
 *
 * Email links with a client-side-assembled address and phone/Swish links that
 * become copy buttons on desktop: [amrf_email_link], [amrf_phone_link] and the
 * script and styles a theme can reuse via amrf_enqueue_contact_links().
 *
 * Markup contract:
 * - `a.amrf-email-link[hidden][data-user][data-domain]` with ROT13-encoded parts
 *   and a `.amrf-email-link-text` child that receives the address.
 * - `a.amrf-copy-link[data-copy-value]` with a `.amrf-copy-text` child.
 *
 * @package Antropomorf\SiteSettings
 */
class ContactLinks
{
	public const HANDLE = 'amrf-contact-links';

	public function __construct()
	{
		add_action('init', [self::class, 'registerAssets']);
		add_shortcode('amrf_email_link', [$this, 'renderEmailLink']);
		add_shortcode('amrf_phone_link', [$this, 'renderPhoneLink']);
	}

	/**
	 * Registered on init, not wp_enqueue_scripts: a block theme renders its
	 * template parts before wp_head.
	 *
	 * @return void
	 */
	public static function registerAssets(): void
	{
		wp_register_style(
			self::HANDLE,
			AMRF_ADMIN_PLUGIN_URL . 'assets/css/amrf-contact-links.css',
			[],
			filemtime(AMRF_ADMIN_PLUGIN_DIR . '/assets/css/amrf-contact-links.css')
		);

		wp_register_script(
			self::HANDLE,
			AMRF_ADMIN_PLUGIN_URL . 'assets/js/amrf-contact-links.js',
			[],
			filemtime(AMRF_ADMIN_PLUGIN_DIR . '/assets/js/amrf-contact-links.js'),
			true
		);

		wp_localize_script(self::HANDLE, 'amrfContactLinksConfig', [
			'copiedLabel' => __('Copied!', 'amrf-admin'),
		]);
	}

	/**
	 * @return void
	 */
	public static function enqueue(): void
	{
		wp_enqueue_style(self::HANDLE);
		wp_enqueue_script(self::HANDLE);
	}

	/**
	 * A plain "mailto:" href is trivial for scraper bots to harvest; the script
	 * decodes the parts and unhides the link. No no-JS fallback, by design.
	 *
	 * @return string
	 */
	public function renderEmailLink(): string
	{
		$email = Repository::getSettings()['email'];
		if ($email === '' || strpos($email, '@') === false) {
			return '';
		}

		self::enqueue();

		[$user, $domain] = array_pad(explode('@', $email, 2), 2, '');

		$placeholder = esc_html__('Email', 'amrf-admin');

		return '<a href="#" class="amrf-email-link" hidden data-user="' . esc_attr(str_rot13($user)) . '" data-domain="' . esc_attr(str_rot13($domain)) . '">'
			. '<span class="amrf-email-link-text">' . $placeholder . '</span>'
			. '</a>';
	}

	/**
	 * @return string
	 */
	public function renderPhoneLink(): string
	{
		$phone = Repository::getSettings()['phone'];
		if ($phone === '') {
			return '';
		}

		self::enqueue();

		$phone_href = preg_replace('/[^0-9+]/', '', $phone);
		// Word joiner after the hyphen: U+2011 is missing from the theme font.
		$phone_text = str_replace([' ', '-'], ['&nbsp;', '-&#8288;'], esc_html($phone));

		return '<a href="' . esc_attr('tel:' . $phone_href) . '" class="amrf-copy-link" data-copy-value="' . esc_attr($phone) . '">'
			. '<span class="amrf-copy-text">' . $phone_text . '</span>'
			. '</a>';
	}
}
