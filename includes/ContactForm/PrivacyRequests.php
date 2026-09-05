<?php

namespace Antropomorf\ContactForm;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class PrivacyRequests
 *
 * Registers FluentForm submissions with WordPress's own personal-data
 * export/erase tools (Tools > Export/Erase Personal Data) — FluentForm
 * never does this itself, confirmed by reading its source: it registers no
 * wp_privacy_personal_data_exporters/_erasers callback at all.
 *
 * Ported from ptsussis-theme's includes/gdpr.php, generalized: that version
 * read hardcoded response keys (email/names/subject/message) matching one
 * specific form's own field structure. A shared plugin can't assume every
 * FluentForm form uses the same field names, so this dumps every field a
 * submission actually contains (titleized for display) rather than a fixed
 * set of labels, and finds the matching email by scanning all field values
 * for anything email-shaped instead of a hardcoded 'email' key.
 *
 * @package Antropomorf\ContactForm
 */
class PrivacyRequests
{
    private const GROUP_ID = 'amrf-fluentform';
    private const PER_PAGE = 50;

    public function __construct()
    {
        add_filter('wp_privacy_personal_data_exporters', [$this, 'registerExporter']);
        add_filter('wp_privacy_personal_data_erasers', [$this, 'registerEraser']);
    }

    public function registerExporter(array $exporters): array
    {
        $exporters[self::GROUP_ID] = [
            'exporter_friendly_name' => __('Contact form submissions', 'amrf-admin'),
            'callback' => [$this, 'exportPersonalData'],
        ];
        return $exporters;
    }

    public function registerEraser(array $erasers): array
    {
        $erasers[self::GROUP_ID] = [
            'eraser_friendly_name' => __('Contact form submissions', 'amrf-admin'),
            'callback' => [$this, 'erasePersonalData'],
        ];
        return $erasers;
    }

    /**
     * FluentForm stores each submission's field values as a JSON blob in
     * its own `response` column, not individual named columns.
     */
    private function decodeResponse(string $response_json): ?array
    {
        $data = json_decode($response_json, true);
        return is_array($data) ? $data : null;
    }

    /**
     * Scans every field value (recursing into nested arrays, e.g. a "name"
     * field with first/last sub-fields) for anything that's a valid email
     * address, rather than assuming a fixed 'email' key exists.
     */
    private function findEmail(array $data): ?string
    {
        foreach ($data as $value) {
            if (is_string($value) && is_email($value)) {
                return $value;
            }
            if (is_array($value)) {
                $nested = $this->findEmail($value);
                if ($nested !== null) {
                    return $nested;
                }
            }
        }
        return null;
    }

    /**
     * Turns a decoded response into the {name, value} pairs
     * wp_privacy_personal_data_exporters expects, titleizing each raw field
     * key ("first_name" -> "First Name") since there's no per-form label
     * mapping to draw human-readable names from generically.
     *
     * @param array<string, mixed> $data
     * @return array<int, array{name: string, value: string}>
     */
    private function flattenForExport(array $data, string $prefix = ''): array
    {
        $items = [];
        foreach ($data as $key => $value) {
            $label = ucwords(str_replace(['_', '-'], ' ', (string) $key));
            if (is_array($value)) {
                $items = array_merge($items, $this->flattenForExport($value, $prefix . $label . ' '));
                continue;
            }
            $items[] = ['name' => trim($prefix . $label), 'value' => (string) $value];
        }
        return $items;
    }

    /**
     * @return array{data: array, done: bool}
     */
    public function exportPersonalData(string $email_address, int $page = 1): array
    {
        $form_ids = Repository::getContactFormIds();
        if (empty($form_ids)) {
            return ['data' => [], 'done' => true];
        }

        global $wpdb;
        $offset = ($page - 1) * self::PER_PAGE;
        $placeholders = implode(',', array_fill(0, count($form_ids), '%d'));

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT id, response, ip, created_at FROM {$wpdb->prefix}fluentform_submissions WHERE form_id IN ($placeholders) ORDER BY id ASC LIMIT %d OFFSET %d",
            array_merge($form_ids, [self::PER_PAGE, $offset])
        ));

        $export_items = [];
        foreach ($rows as $row) {
            $data = $this->decodeResponse($row->response);
            if (!$data) {
                continue;
            }

            $found_email = $this->findEmail($data);
            if ($found_email === null || strcasecmp($found_email, $email_address) !== 0) {
                continue;
            }

            $fields = $this->flattenForExport($data);
            $fields[] = ['name' => __('IP address', 'amrf-admin'), 'value' => $row->ip];
            $fields[] = ['name' => __('Submitted', 'amrf-admin'), 'value' => $row->created_at];

            $export_items[] = [
                'group_id' => self::GROUP_ID,
                'group_label' => __('Contact form submissions', 'amrf-admin'),
                'item_id' => self::GROUP_ID . '-' . $row->id,
                'data' => $fields,
            ];
        }

        return [
            'data' => $export_items,
            'done' => count($rows) < self::PER_PAGE,
        ];
    }

    /**
     * Deletes the matching row outright rather than anonymizing it in place
     * — nothing about a private contact-form entry needs a public presence
     * to survive an erasure request the way, say, a comment thread might.
     *
     * @return array{items_removed: bool, items_retained: bool, messages: array, done: bool}
     */
    public function erasePersonalData(string $email_address, int $page = 1): array
    {
        $form_ids = Repository::getContactFormIds();
        if (empty($form_ids)) {
            return ['items_removed' => false, 'items_retained' => false, 'messages' => [], 'done' => true];
        }

        global $wpdb;
        $offset = ($page - 1) * self::PER_PAGE;
        $placeholders = implode(',', array_fill(0, count($form_ids), '%d'));

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT id, response FROM {$wpdb->prefix}fluentform_submissions WHERE form_id IN ($placeholders) ORDER BY id ASC LIMIT %d OFFSET %d",
            array_merge($form_ids, [self::PER_PAGE, $offset])
        ));

        $items_removed = false;
        foreach ($rows as $row) {
            $data = $this->decodeResponse($row->response);
            if (!$data) {
                continue;
            }

            $found_email = $this->findEmail($data);
            if ($found_email === null || strcasecmp($found_email, $email_address) !== 0) {
                continue;
            }

            $wpdb->delete("{$wpdb->prefix}fluentform_submissions", ['id' => $row->id], ['%d']);
            $items_removed = true;
        }

        return [
            'items_removed' => $items_removed,
            'items_retained' => false,
            'messages' => [],
            'done' => count($rows) < self::PER_PAGE,
        ];
    }
}
