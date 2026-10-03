<?php

namespace Antropomorf\Swish;

use Antropomorf\Utilities\CachePurge;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Entry point for the Swish module — the "Swish" tab on the Forms page
 * (Provider), QR generation after each save (QrCodeGenerator),
 * and sitewide "#swish" link handling (FrontendProvider).
 *
 * @package Antropomorf\Swish
 */
class Bootstrap
{
	public static function register(): void
	{
		new Provider();
		new FrontendProvider();
		QrCodeGenerator::register();

		CachePurge::onOptionChange(Repository::OPTION_NAME);
	}
}
