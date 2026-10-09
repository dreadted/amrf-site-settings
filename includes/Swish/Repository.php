<?php

namespace Antropomorf\Swish;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Storage, defaults, and sanitization for the Swish tab's settings. The QR
 * code is derived from them by QrCodeGenerator and is not stored here.
 *
 * @package Antropomorf\Swish
 */
class Repository
{
	public const OPTION_NAME = 'amrf_swish_settings';

	private const MESSAGE_MAX_LENGTH = 50;
	private const MESSAGE_MAX_BYTES = 90;

	/**
	 * @return array<string, string>
	 */
	public static function getDefaults(): array
	{
		return [
			'number' => '',
			'amount' => '',
			'amount_editable' => '1',
			'message' => '',
			'message_editable' => '1',
		];
	}

	/**
	 * @return array<string, string>
	 */
	public static function getSettings(): array
	{
		$stored = get_option(self::OPTION_NAME, []);
		$defaults = self::getDefaults();
		return array_intersect_key(wp_parse_args(is_array($stored) ? $stored : [], $defaults), $defaults);
	}

	/**
	 * One option, one page, one form — no "{key}_submitted" marker needed
	 * since every call here is this form's full submission; an absent
	 * checkbox plainly means "unchecked".
	 *
	 * @param mixed $input Raw POSTed value for this option.
	 * @return array<string, string>
	 */
	public static function sanitize($input): array
	{
		$input = is_array($input) ? $input : [];

		return [
			'number' => sanitize_text_field((string) ($input['number'] ?? '')),
			'amount' => self::normalizeAmount((string) ($input['amount'] ?? '')),
			'amount_editable' => !empty($input['amount_editable']) ? '1' : '',
			'message' => self::normalizeMessage((string) ($input['message'] ?? '')),
			'message_editable' => !empty($input['message_editable']) ? '1' : '',
		];
	}

	/**
	 * "125,50" / "125.50" / "125" -> "125.50" / "125"; blank or invalid becomes blank.
	 * A Swedish keyboard types a decimal comma, which the QR API and a (float) cast both need as a period.
	 *
	 * @param string $raw
	 */
	public static function normalizeAmount(string $raw): string
	{
		$raw = trim($raw);
		if ($raw === '') {
			return '';
		}

		$normalized = str_replace(',', '.', $raw);
		return preg_match('/^\d+(\.\d{1,2})?$/', $normalized) ? $normalized : '';
	}

	/**
	 * Plain text in a conservative character set, at most 50 characters (Swish's limit).
	 * The 90-byte cap keeps an all-"åäö" message within the QR code's capacity.
	 *
	 * @param string $raw Text or HTML, entities allowed.
	 */
	public static function normalizeMessage(string $raw): string
	{
		$text = html_entity_decode(wp_strip_all_tags($raw), ENT_QUOTES | ENT_HTML5, 'UTF-8');
		$text = strtr($text, ['–' => '-', '—' => '-', '…' => '...', "'" => '', '’' => '', '"' => '', '“' => '', '”' => '']);
		$text = preg_replace('/[^\p{L}\p{N}!?(),.:;-]+/u', ' ', $text) ?? '';
		$text = mb_substr(trim($text), 0, self::MESSAGE_MAX_LENGTH, 'UTF-8');

		return rtrim(mb_strcut($text, 0, self::MESSAGE_MAX_BYTES, 'UTF-8'));
	}
}
