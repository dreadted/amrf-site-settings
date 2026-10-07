<?php

/**
 * Public, non-namespaced function API for themes to consume — required
 * directly since the autoloader only maps class names, not plain functions.
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * This site's business/contact settings. Every field key always present,
 * defaulted to ''.
 *
 * @return array<string, string>
 */
function amrf_get_site_settings(): array
{
	return \Antropomorf\SiteSettings\Repository::getSettings();
}

/**
 * This site's Swish tab settings (number/amount/message + their own
 * "editable after scanning" toggles). Every field key always present,
 * defaulted to ''.
 *
 * @return array<string, string>
 */
function amrf_get_swish_settings(): array
{
	return \Antropomorf\Swish\Repository::getSettings();
}

/**
 * The form chosen as Default Contact Form, or 0 for "None".
 *
 * @return int
 */
function amrf_get_default_contact_form_id(): int
{
	return \Antropomorf\ContactForm\Repository::getDefaultContactFormId();
}

/**
 * Swish's own deep-link URL, or '' if no number is set.
 *
 * @param string $swish_number
 * @param string $amount
 * @param bool   $amount_editable
 * @param string $message
 * @param bool   $message_editable
 */
function amrf_build_swish_url(
	string $swish_number,
	string $amount = '',
	bool $amount_editable = true,
	string $message = '',
	bool $message_editable = true
): string {
	return \Antropomorf\Swish\DeepLink::buildUrl($swish_number, $amount, $amount_editable, $message, $message_editable);
}

/**
 * Loads the script and styles behind the ContactLinks markup contract
 * (ROT13 email links, desktop copy buttons) on the current page.
 *
 * @return void
 */
function amrf_enqueue_contact_links(): void
{
	\Antropomorf\SiteSettings\ContactLinks::enqueue();
}

/**
 * The Support Genix Lite ticket portal page, which amrf hides from and locks for non-administrators.
 *
 * @return int Page ID, 0 if Support Genix Lite has no ticket page set.
 */
function amrf_get_ticket_page_id(): int
{
	return \Antropomorf\SupportGenix\Provider::ticketPageId();
}

/**
 * The contact shortcut's address, which opens the front page with the contact modal.
 *
 * @return string Empty when no shortcut is set.
 */
function amrf_get_contact_shortcut_url(): string
{
	$slug = \Antropomorf\ContactForm\Shortcut::slug();

	return $slug !== '' ? \Antropomorf\ContactForm\Shortcut::url($slug) : '';
}
