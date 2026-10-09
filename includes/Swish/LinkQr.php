<?php

namespace Antropomorf\Swish;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Per-link Swish details for "#swish" links that carry their own amount and message.
 * A link's QR code is created on its first request through a signed URL, so no list of links in use is needed.
 *
 * @package Antropomorf\Swish
 */
class LinkQr
{
	private const QUERY_VAR = 'amrf_swish_qr';

	public static function register(): void
	{
		add_action('parse_request', [self::class, 'serve']);
	}

	/**
	 * The data attributes amrf-swish.js reads from a "#swish" link.
	 *
	 * @param string $amount          Amount in SEK ("125,50" allowed), or '' for none.
	 * @param string $message         Message (HTML entities allowed), or '' for none.
	 * @param bool   $amountEditable  Whether the payer can change the amount after scanning.
	 * @param bool   $messageEditable Whether the payer can change the message after scanning.
	 * @return array<string, string> Attribute name => unescaped value, empty if no Swish number is set.
	 */
	public static function attributes(string $amount, string $message, bool $amountEditable, bool $messageEditable): array
	{
		$params = self::params($amount, $message, $amountEditable ? '1' : '', $messageEditable ? '1' : '');
		if ($params['number'] === '') {
			return [];
		}

		return [
			'data-swish-url' => DeepLink::buildUrl(
				$params['number'],
				$params['amount'],
				$params['amount_editable'] === '1',
				$params['message'],
				$params['message_editable'] === '1'
			),
			'data-swish-qr' => QrCodeGenerator::linkUrl($params) ?: self::signedUrl($params),
		];
	}

	/**
	 * Creates the requested code if needed and redirects to it; 404 for a bad signature or a failed request.
	 *
	 * @return void
	 */
	public static function serve(): void
	{
		if (self::query(self::QUERY_VAR) === '') {
			return;
		}

		$params = self::params(
			self::query('amt'),
			self::query('msg'),
			self::query('ae') === '1' ? '1' : '',
			self::query('me') === '1' ? '1' : ''
		);

		do_action('litespeed_control_set_nocache', 'amrf swish link qr');
		nocache_headers();

		if ($params['number'] === '' || !hash_equals(self::sign($params), self::query('sig')) || !QrCodeGenerator::ensureLink($params)) {
			status_header(404);
			exit;
		}

		wp_redirect(QrCodeGenerator::linkUrl($params), 302);
		exit;
	}

	// The HMAC signature, not a nonce, protects this public GET.
	private static function query(string $key): string
	{
		$value = $_GET[$key] ?? ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		return is_string($value) ? sanitize_text_field(wp_unslash($value)) : '';
	}

	/**
	 * @return array<string, string> Same keys as Repository::getSettings(), with the site's own number.
	 */
	private static function params(string $amount, string $message, string $amountEditable, string $messageEditable): array
	{
		return [
			'number' => Repository::getSettings()['number'],
			'amount' => Repository::normalizeAmount($amount),
			'amount_editable' => $amountEditable,
			'message' => Repository::normalizeMessage($message),
			'message_editable' => $messageEditable,
		];
	}

	/**
	 * The number is signed but left out of the URL, so a new number invalidates every earlier URL.
	 *
	 * @param array<string, string> $params
	 */
	private static function signedUrl(array $params): string
	{
		return add_query_arg(
			rawurlencode_deep([
				self::QUERY_VAR => '1',
				'amt' => $params['amount'],
				'ae' => $params['amount_editable'],
				'msg' => $params['message'],
				'me' => $params['message_editable'],
				'sig' => self::sign($params),
			]),
			home_url('/')
		);
	}

	/**
	 * @param array<string, string> $params
	 */
	private static function sign(array $params): string
	{
		return wp_hash((string) wp_json_encode($params), 'auth');
	}
}
