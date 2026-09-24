<?php

namespace Antropomorf\SiteSettings;

if (!defined('ABSPATH')) {
    exit;
}

// Organization/Person Schema.org nodes, shared by the TSF and standalone SEO outputs.
class JsonLd
{
    // Zero, one, or two Schema.org entities (Organization/Person).
    public static function buildNodes(array $settings): array
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

            $imageUrl = self::resolveImageUrl($settings);
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

    public static function resolveImageUrl(array $settings): string
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
