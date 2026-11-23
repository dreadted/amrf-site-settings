<?php

namespace Antropomorf\Hardening;

use Antropomorf\Utilities\CachePurge;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Entry point for the Hardening module.
 *
 * @package Antropomorf\Hardening
 */
class Bootstrap
{
	public static function register(): void
	{
		new Provider();

		CachePurge::onOptionChange(Repository::OPTION_NAME);
	}
}
