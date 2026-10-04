<?php

namespace Antropomorf\FluentCrm;

use FluentCrm\App\Models\Campaign;
use FluentCrm\App\Services\Helper;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Applies FluentCRM's default campaign template to new campaigns; FluentCRM stores the option but never applies it.
 *
 * @package Antropomorf\FluentCrm
 */
class DefaultCampaignTemplate
{
	public function __construct()
	{
		add_action('plugins_loaded', [$this, 'register']);
	}

	public function register(): void
	{
		if (!defined('FLUENTCRM')) {
			return;
		}

		add_action('fluent_crm/campaign_created', [$this, 'apply']);
	}

	public function apply(Campaign $campaign): void
	{
		$template = Helper::getDefaultCampaignTemplate();
		if (!$template || $campaign->email_body) {
			return;
		}

		Helper::applyTemplateToCampaign($campaign, $template);
	}
}
