<?php

namespace Antropomorf\SiteSettings;

if (!defined('ABSPATH')) {
    exit;
}

// Feeds this plugin's data into The SEO Framework, which owns all meta/OG/canonical/sitemap output. No-ops if TSF isn't active.
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

        add_action('update_option_' . Repository::OPTION_NAME, [$this, 'syncHomepageFields'], 10, 2);
        add_action('update_option_' . Repository::OPTION_NAME, [$this, 'disableConflictingKnowledgeGraph'], 10, 2);
        add_filter('the_seo_framework_schema_graph_data', [$this, 'injectJsonLd']);
    }

    public function syncHomepageFields(array $old, array $new): void
    {
        if (
            ($old['seo_title'] ?? '') === ($new['seo_title'] ?? '')
            && ($old['meta_description'] ?? '') === ($new['meta_description'] ?? '')
            && ($old['share_image'] ?? '') === ($new['share_image'] ?? '')
        ) {
            return;
        }

        $imageUrl = $new['share_image'] ?? '';

        \The_SEO_Framework\Data\Plugin::update_option([
            'homepage_title' => $new['seo_title'] ?? '',
            'homepage_description' => $new['meta_description'] ?? '',
            'homepage_social_image_url' => $imageUrl,
            'homepage_social_image_id' => $imageUrl ? attachment_url_to_postid($imageUrl) : 0,
        ]);
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

        return array_merge($graph, $this->buildJsonLdNodes(Repository::getSettings()));
    }

    // Zero, one, or two Schema.org entities (Organization/Person).
    private function buildJsonLdNodes(array $settings): array
    {
        $orgId = home_url('/') . '#organization';
        $personId = home_url('/') . '#person';

        $hasOrg = $settings['business_name'] && $settings['business_type'];
        $hasPerson = (bool) $settings['person_name'];

        if (!$hasOrg && !$hasPerson) {
            return [];
        }

        $sameAs = array_values(array_filter([
            $settings['facebook_url'],
            $settings['instagram_url'],
            $settings['x_url'],
        ]));

        $organization = null;
        if ($hasOrg) {
            $organization = [
                '@type' => $settings['business_type'],
                '@id' => $orgId,
                'name' => $settings['business_name'],
                'url' => home_url('/'),
            ];

            $imageUrl = $this->resolveImageUrl($settings);
            if ($imageUrl) {
                $organization['image'] = $imageUrl;
            }
            if ($settings['street'] && $settings['city']) {
                $organization['address'] = [
                    '@type' => 'PostalAddress',
                    'streetAddress' => $settings['street'],
                    'postalCode' => $settings['postal_code'],
                    'addressLocality' => $settings['city'],
                    'addressRegion' => $settings['region'],
                    'addressCountry' => $settings['country'],
                ];
            }
            if ($settings['latitude'] && $settings['longitude']) {
                $organization['geo'] = [
                    '@type' => 'GeoCoordinates',
                    'latitude' => (float) $settings['latitude'],
                    'longitude' => (float) $settings['longitude'],
                ];
            }
            if ($settings['phone']) {
                $organization['telephone'] = $settings['phone'];
            }
            // No 'email' -- it's obfuscated everywhere else it's displayed,
            // shouldn't leak in plain text here.
            if ($sameAs) {
                $organization['sameAs'] = $sameAs;
            }
            if ($hasPerson) {
                $organization['founder'] = ['@id' => $personId];
            }
        }

        $person = null;
        if ($hasPerson) {
            $person = [
                '@type' => 'Person',
                '@id' => $personId,
                'name' => $settings['person_name'],
            ];

            if ($settings['job_title']) {
                $person['jobTitle'] = $settings['job_title'];
            }
            if ($hasOrg) {
                $person['worksFor'] = ['@id' => $orgId];
            }
        }

        return array_values(array_filter([$organization, $person]));
    }

    private function resolveImageUrl(array $settings): string
    {
        if (is_singular() && has_post_thumbnail()) {
            $thumbnail = get_the_post_thumbnail_url(null, 'large');
            if ($thumbnail) {
                return $thumbnail;
            }
        }

        return $settings['share_image'];
    }
}
