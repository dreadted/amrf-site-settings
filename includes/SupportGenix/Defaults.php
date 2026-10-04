<?php

namespace Antropomorf\SupportGenix;

use Antropomorf\SiteSettings\BrandImages;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * An "Apply Defaults" button injected onto Support Genix Lite's OWN settings
 * page that seeds its ticket categories, assignment rule, settings and
 * ticket page once.
 *
 * @package Antropomorf\SupportGenix
 */
class Defaults
{
	/**
	 * Support Genix Lite's own settings page slug — where the "Apply
	 * Defaults" notice/button gets injected. Not a slug this plugin owns.
	 */
	private const THIRD_PARTY_SETTINGS_PAGE = 'support-genix';

	/**
	 * Slug of the front-end page Support Genix Lite serves ticket
	 * submission/management on — created by that plugin itself.
	 */
	private const TICKET_PAGE_SLUG = 'ticket';

	/** Set once "Apply Defaults" has run, which hides the button for good. */
	private const DEFAULTS_APPLIED_OPTION = 'amrf_support_genix_defaults_applied';

	public function __construct()
	{
		add_action('in_admin_header', [$this, 'maybeShowDefaultsButton']);
		add_action('admin_init', [$this, 'handleApplyDefaults']);
		add_action('admin_notices', [$this, 'showDefaultsNotice']);
	}

	/**
	 * Shows the "Apply Defaults" form on Support Genix Lite's OWN settings
	 * page, once, until the defaults have been applied.
	 *
	 * @return void
	 */
	public function maybeShowDefaultsButton(): void
	{
		global $pagenow;

		if ($pagenow !== 'admin.php' || ($_GET['page'] ?? '') !== self::THIRD_PARTY_SETTINGS_PAGE) {
			return;
		}
		if (!current_user_can('manage_options') || get_option(self::DEFAULTS_APPLIED_OPTION)) {
			return;
		}

?>
		<div class="notice notice-info">
			<p><?php esc_html_e("Apply this site's default settings for Support Genix.", 'amrf-admin'); ?></p>
			<p>
			<form method="post" style="display: inline-block; margin-right: 10px;">
				<?php wp_nonce_field('amrf_apply_support_genix_defaults', '_wpnonce'); ?>
				<input type="hidden" name="amrf_apply_support_genix_defaults" value="1">
				<input type="submit" class="button button-primary" value="<?php esc_attr_e('Apply Defaults', 'amrf-admin'); ?>">
			</form>
			</p>
		</div>
<?php
	}

	/**
	 * @return void
	 */
	public function handleApplyDefaults(): void
	{
		if (!isset($_POST['amrf_apply_support_genix_defaults']) || get_option(self::DEFAULTS_APPLIED_OPTION)) {
			return;
		}

		$nonce = isset($_POST['_wpnonce']) ? sanitize_key(wp_unslash($_POST['_wpnonce'])) : '';
		if (!current_user_can('manage_options') || !wp_verify_nonce($nonce, 'amrf_apply_support_genix_defaults')) {
			wp_die(esc_html__('Security check failed', 'amrf-admin'));
		}

		$result = $this->applyDefaultSettings();
		set_transient('amrf_support_genix_defaults_processed', $result ? 'success' : 'error', 60);

		wp_redirect(admin_url('admin.php?page=' . self::THIRD_PARTY_SETTINGS_PAGE));
		exit;
	}

	/**
	 * @return void
	 */
	public function showDefaultsNotice(): void
	{
		$message = get_transient('amrf_support_genix_defaults_processed');
		if (!$message) {
			return;
		}
		delete_transient('amrf_support_genix_defaults_processed');

		if ($message === 'success') {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Support Genix defaults applied successfully!', 'amrf-admin') . '</p></div>';
		} else {
			echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__('There was an error applying the Support Genix defaults.', 'amrf-admin') . '</p></div>';
		}
	}

	/**
	 * Seeds Support Genix Lite's own tables/option with this site's
	 * defaults, once. Table/column names and values belong to that
	 * plugin's own schema.
	 *
	 * @return bool
	 */
	private function applyDefaultSettings(): bool
	{
		global $wpdb;

		$wpdb->query('START TRANSACTION');

		try {
			$deleted_roles = $wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$wpdb->prefix}apbd_wps_role WHERE slug != %s",
					'administrator'
				)
			);
			$deleted_access = $wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$wpdb->prefix}apbd_wps_role_access WHERE role_slug != %s",
					'administrator'
				)
			);
			if (false === $deleted_roles || false === $deleted_access) {
				throw new \Exception('Failed to delete support roles: ' . $wpdb->last_error);
			}

			$this->removeSupportRoles();
			$this->setupDefaultTicketRules();
			$this->setupDefaultTicketCategories();
			$this->updateSupportGenixSettings();

			update_option(self::DEFAULTS_APPLIED_OPTION, true);

			$wpdb->query('COMMIT');
			return true;
		} catch (\Exception $e) {
			$wpdb->query('ROLLBACK');
			error_log('Support Genix Default Settings: ' . $e->getMessage());
			return false;
		}
	}

	private function removeSupportRoles(): void
	{
		foreach (['awps-support-manager', 'awps-support-agent'] as $role) {
			if (get_role($role)) {
				remove_role($role);
			}
		}
	}

	private function setupDefaultTicketRules(): void
	{
		global $wpdb;
		$table_name = $wpdb->prefix . 'apbd_wps_ticket_assign_rule';

		if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
			throw new \Exception('Ticket assignment rules table does not exist');
		}

		// DELETE, not TRUNCATE: TRUNCATE commits implicitly and would defeat the rollback.
		if (false === $wpdb->query("DELETE FROM $table_name")) {
			throw new \Exception('Failed to clear ticket assignment rules: ' . $wpdb->last_error);
		}

		$result = $wpdb->insert(
			$table_name,
			[
				'id' => 1,
				'cat_ids' => '0',
				'rule_type' => 'A',
				'rule_id' => '1',
				'status' => 'A',
			],
			['%d', '%s', '%s', '%s', '%s']
		);

		if (false === $result) {
			throw new \Exception('Failed to insert ticket assignment rule: ' . $wpdb->last_error);
		}
	}

	private function setupDefaultTicketCategories(): void
	{
		global $wpdb;
		$table_name = $wpdb->prefix . 'apbd_wps_ticket_category';

		if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
			throw new \Exception('Ticket categories table does not exist');
		}

		if (false === $wpdb->query("DELETE FROM $table_name")) {
			throw new \Exception('Failed to clear ticket categories: ' . $wpdb->last_error);
		}

		$categories = [
			['id' => 1, 'title' => 'task'],
			['id' => 2, 'title' => 'bug'],
			['id' => 3, 'title' => 'request'],
			['id' => 4, 'title' => 'other'],
		];

		foreach ($categories as $category) {
			$result = $wpdb->insert($table_name, $category, ['%d', '%s']);
			if (false === $result) {
				throw new \Exception('Failed to insert ticket category: ' . $wpdb->last_error);
			}
		}
	}

	private function updateSupportGenixSettings(): void
	{
		$lang = Provider::resolveLanguageKey();

		$current_settings = get_option('support-genix_o_Apbd_wps_settings', []);

		$updated_settings = [
			'client_role' => 'editor',
			'disable_guest_ticket_creation' => 'Y',
			'disable_guest_email_to_ticket_creation' => 'Y',
			'footer_cp_text' => [$lang => ''],
			'ticket_page' => [$lang => $this->ensureTicketPage($lang)],
			// Support Genix Lite's wizard-completed step; may need bumping if it adds steps.
			'setup_wizard_step' => 4,
			'setup_wizard_finished' => true,
		];

		$favicon = BrandImages::url('icon_svg');
		if ($favicon) {
			$updated_settings['app_favicon'] = $favicon;
		}
		$logo = BrandImages::url('logo');
		if ($logo) {
			$updated_settings['app_logo'] = [$lang => $logo];
		}

		$merged_settings = array_merge($current_settings, $updated_settings);
		update_option('support-genix_o_Apbd_wps_settings', $merged_settings, true);

		// update_option() also returns false when the value is unchanged, so check what's stored.
		$stored = get_option('support-genix_o_Apbd_wps_settings', []);
		foreach ($updated_settings as $key => $value) {
			if (($stored[$key] ?? null) !== $value) {
				throw new \Exception('Failed to update support-genix settings');
			}
		}
	}

	/**
	 * Finds or creates the front-end ticket portal page and returns its ID
	 * for the 'ticket_page' setting. Page shape matches what the plugin's
	 * own setup wizard produces.
	 *
	 * @param string $lang Resolved language key — passed in so this reads
	 *                      back under the exact key it's about to write under.
	 * @return int Page ID.
	 */
	private function ensureTicketPage(string $lang): int
	{
		$configured_id = Provider::configuredTicketPageId($lang);
		if ($configured_id && get_post($configured_id)) {
			return $configured_id;
		}

		$existing = get_page_by_path(self::TICKET_PAGE_SLUG);
		if ($existing) {
			return $existing->ID;
		}

		$page_id = wp_insert_post(
			[
				'post_type' => 'page',
				'post_title' => __('Ticket', 'amrf-admin'),
				'post_name' => self::TICKET_PAGE_SLUG,
				'post_content' => '<!-- wp:shortcode -->[supportgenix]<!-- /wp:shortcode -->',
				'post_status' => 'publish',
				'comment_status' => 'closed',
				'ping_status' => 'closed',
			],
			true
		);

		if (is_wp_error($page_id)) {
			throw new \Exception('Failed to create ticket page: ' . $page_id->get_error_message());
		}

		return $page_id;
	}
}
