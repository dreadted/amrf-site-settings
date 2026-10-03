<?php

namespace Antropomorf\FluentCrm;

use FluentCrm\App\Models\Campaign;
use FluentCrm\App\Services\Helper;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class DefaultCampaignTemplate
 *
 * Fills every new campaign with FluentCRM's default campaign template
 * (the `default_campaign_template_id` option). FluentCRM stores that
 * option but ships its apply-on-create step disabled. Inert when
 * FluentCRM isn't active or no default is set.
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
