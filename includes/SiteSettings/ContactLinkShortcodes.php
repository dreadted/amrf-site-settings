<?php

namespace Antropomorf\SiteSettings;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class ContactLinkShortcodes
 *
 * [amrf_email_link] / [amrf_phone_link] — clickable versions of the same
 * Site Settings email/phone fields SettingShortcode outputs as plain text,
 * for use in editorial page content (e.g. a Privacy Policy page's own
 * "Contact us" section) that wants the exact same behavior ptsussis-theme's
 * own footer already gives these two fields, rather than a dead mailto:/
 * tel: link or a second, drifting reimplementation of it.
 *
 * Deliberately reuses that theme's own markup contract verbatim — class
 * names, data-* attributes, the .email-link-text/.site-footer-copy-text
 * child span — rather than inventing a separate one: ptsussis-theme's
 * blocks/footer/index.js already runs on every page (the footer template
 * part is in every template) and scans the whole document with a plain
 * querySelectorAll, not one scoped to .site-footer, so markup emitted here
 * anywhere in a page's content is picked up and enhanced identically, no
 * extra script needed. This IS a real coupling to that theme's specific
 * class names (blocks/footer/index.php + index.js + style.css) — if those
 * ever get renamed there, update both call sites, the same as any other
 * cross-repo contract in this project.
 *
 * No icon (ptsussis_icon() is a theme function this plugin has no business
 * calling directly) — a bare link/button reads fine outside the footer's
 * own icon row.
 *
 * @package Antropomorf\SiteSettings
 */
class ContactLinkShortcodes
{
	public function __construct()
	{
		add_shortcode('amrf_email_link', [$this, 'renderEmailLink']);
		add_shortcode('amrf_phone_link', [$this, 'renderPhoneLink']);
	}

	/**
	 * Same client-side-assembled-address technique as ptsussis-theme's
	 * footer: a plain "mailto:" href or raw address in the markup is
	 * trivial for scraper bots to harvest, JS execution isn't. The parts
	 * are ROT13-encoded and the link starts hidden; the theme script
	 * decodes them and unhides it. No no-JS fallback, by design.
	 */
	public function renderEmailLink(): string
	{
		$email = Repository::getSettings()['email'];
		if ($email === '' || strpos($email, '@') === false) {
			return '';
		}

		[$user, $domain] = array_pad(explode('@', $email, 2), 2, '');

		$placeholder = esc_html__('Email', 'amrf-admin');

		return '<a href="#" class="email-link" hidden data-user="' . esc_attr(str_rot13($user)) . '" data-domain="' . esc_attr(str_rot13($domain)) . '">'
			. '<span class="email-link-text">' . $placeholder . '</span>'
			. '</a>';
	}

	public function renderPhoneLink(): string
	{
		$phone = Repository::getSettings()['phone'];
		if ($phone === '') {
			return '';
		}

		$phone_href = preg_replace('/[^0-9+]/', '', $phone);
		$copied_label = esc_attr__('Copied!', 'amrf-admin');
		// Word joiner after the hyphen: U+2011 is missing from the theme font.
		$phone_text = str_replace([' ', '-'], ['&nbsp;', '-&#8288;'], esc_html($phone));

		return '<a href="' . esc_attr('tel:' . $phone_href) . '" class="site-footer-copy-link" data-copy-value="' . esc_attr($phone) . '" data-copied-label="' . $copied_label . '">'
			. '<span class="site-footer-copy-text">' . $phone_text . '</span>'
			. '</a>';
	}
}
