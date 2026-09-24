<?php

namespace Antropomorf\SiteSettings;

use The_SEO_Framework\Helper\Query;

if (!defined('ABSPATH')) {
    exit;
}

// Site Settings as front-page fallback for The SEO Framework; any value filled in within TSF wins. No-ops if TSF isn't active.
class SeoFrameworkIntegration
{
    public function __construct()
    {
        // Deferred: tsf() isn't guaranteed defined yet at this plugin's own top-level load order.
        add_action('plugins_loaded', [$this, 'registerIfActive']);
    }

    public function registerIfActive(): void
    {
        if (!function_exists('tsf')) {
            return;
        }

        add_action('update_option_' . Repository::OPTION_NAME, [$this, 'disableConflictingKnowledgeGraph'], 10, 2);
        add_filter('the_seo_framework_schema_graph_data', [$this, 'injectJsonLd']);
        add_filter('the_seo_framework_title_from_custom_field', [$this, 'fallbackTitle'], 10, 2);
        add_filter('the_seo_framework_custom_field_description', [$this, 'fallbackDescription'], 10, 2);
        add_filter('the_seo_framework_generated_image_details', [$this, 'fallbackImage'], 10, 4);
    }

    public function fallbackTitle($title, $args = null): string
    {
        return $this->fallback((string) $title, 'seo_title', $args);
    }

    public function fallbackDescription($desc, $args = null): string
    {
        return $this->fallback((string) $desc, 'meta_description', $args);
    }

    // Generated (not custom) so TSF's own custom image still wins; prepended so it beats the featured image.
    public function fallbackImage($details, $args = null, $single = false, $context = 'social'): array
    {
        $details = (array) $details;
        if ('organization' === $context || !Repository::isSeoOutputEnabled() || !$this->isFrontPage($args)) {
            return $details;
        }

        $url = Repository::getSettings()['share_image'];
        if (!$url) {
            return $details;
        }

        $image = ['url' => $url, 'id' => attachment_url_to_postid($url)];
        $src = $image['id'] ? wp_get_attachment_image_src($image['id'], 'full') : false;
        if ($src) {
            $image['width'] = $src[1];
            $image['height'] = $src[2];
            $image['alt'] = (string) get_post_meta($image['id'], '_wp_attachment_image_alt', true);
        }

        return $single ? [$image] : [$image, ...$details];
    }

    private function fallback(string $value, string $key, $args): string
    {
        if ('' !== $value || !Repository::isSeoOutputEnabled() || !$this->isFrontPage($args)) {
            return $value;
        }

        return Repository::getSettings()[$key];
    }

    private function isFrontPage($args): bool
    {
        if (!is_array($args)) {
            return Query::is_real_front_page();
        }

        return empty($args['tax']) && empty($args['pta'])
            && Query::is_real_front_page_by_id((int) ($args['id'] ?? 0));
    }

    // TSF's own Organization/Person node always duplicates injectJsonLd()'s once this plugin's JSON-LD is on.
    public function disableConflictingKnowledgeGraph(array $old, array $new): void
    {
        if (empty($new['enable_seo_output']) || !\The_SEO_Framework\Data\Plugin::get_option('knowledge_output')) {
            return;
        }

        \The_SEO_Framework\Data\Plugin::update_option('knowledge_output', 0);
    }

    public function injectJsonLd(array $graph): array
    {
        if (!Repository::isSeoOutputEnabled()) {
            return $graph;
        }

        return array_merge($graph, JsonLd::buildNodes(Repository::getSettings()));
    }
}
