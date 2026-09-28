<?php

namespace Antropomorf\FluentCrm;

use FluentCrm\App\Services\PermissionManager;

if (!defined('ABSPATH')) {
  exit;
}

/**
 * Class AdminAccess
 *
 * Grants FluentCRM permissions per role in code, instead of FluentCRM's
 * per-user Managers setting stored in the database, and trims FluentCRM's
 * admin UI for non-administrators. Inert when FluentCRM isn't active.
 *
 * @package Antropomorf\FluentCrm
 */
class AdminAccess
{
  private const ROLE_PERMISSIONS = [
    'editor' => [
      'fcrm_view_dashboard',
      'fcrm_read_contacts',
      'fcrm_manage_contacts',
      'fcrm_manage_contacts_delete',
      'fcrm_manage_contact_cats',
      'fcrm_read_emails',
      'fcrm_manage_emails',
      'fcrm_manage_email_templates',
      'fcrm_manage_email_delete',
    ],
  ];

  /** FluentCRM app routes whose dashboard quick links non-administrators don't get. */
  private const HIDDEN_QUICK_LINK_ROUTES = [
    'email/recurring-campaigns',
    'email/sequences',
    'settings/mcp_settings',
  ];

  private const STYLE_HANDLE = 'amrf-fluentcrm-admin';

  public function __construct()
  {
    add_action('plugins_loaded', [$this, 'register']);
  }

  public function register(): void
  {
    if (!defined('FLUENTCRM')) {
      return;
    }

    add_filter('fluent_crm/user_permissions', [$this, 'grantRolePermissions'], 10, 2);
    add_filter('fluent_crm/render_top_menu_bar', [$this, 'isAdministrator']);
    add_filter('fluent_crm/dashboard_data', [$this, 'trimDashboard']);
    add_action('admin_enqueue_scripts', [$this, 'enqueueStyles']);
  }

  public function enqueueStyles(string $hookSuffix): void
  {
    if ($hookSuffix !== 'toplevel_page_fluentcrm-admin' || current_user_can('manage_options')) {
      return;
    }

    wp_enqueue_style(
      self::STYLE_HANDLE,
      AMRF_ADMIN_PLUGIN_URL . 'assets/css/amrf-fluentcrm-admin.css',
      [],
      filemtime(AMRF_ADMIN_PLUGIN_DIR . '/assets/css/amrf-fluentcrm-admin.css')
    );
  }

  /**
   * Replaces, rather than extends, any Managers-screen permissions for mapped roles.
   *
   * @param array    $permissions Permissions resolved by FluentCRM.
   * @param \WP_User $user        User being checked.
   * @return array
   */
  public function grantRolePermissions($permissions, $user)
  {
    if (!$user instanceof \WP_User || $user->has_cap('manage_options')) {
      return $permissions;
    }

    foreach ((array) $user->roles as $role) {
      if (isset(self::ROLE_PERMISSIONS[$role])) {
        return self::ROLE_PERMISSIONS[$role];
      }
    }

    return $permissions;
  }

  public function isAdministrator(): bool
  {
    return current_user_can('manage_options');
  }

  /**
   * @param array $data Dashboard payload from FluentCRM's reports/dashboard-stats.
   * @return array
   */
  public function trimDashboard($data)
  {
    if (!is_array($data) || current_user_can('manage_options')) {
      return $data;
    }

    if (!PermissionManager::currentUserCan('fcrm_read_funnels')) {
      unset($data['stats']['total_automations']);
    }

    if (!empty($data['quick_links']) && is_array($data['quick_links'])) {
      $data['quick_links'] = array_values(array_filter(
        $data['quick_links'],
        fn($link) => !$this->isHiddenQuickLink((string) ($link['url'] ?? ''))
      ));
    }

    return $data;
  }

  private function isHiddenQuickLink(string $url): bool
  {
    $base = fluentcrm_menu_url_base();
    if (str_starts_with($url, $base)) {
      return in_array(substr($url, strlen($base)), self::HIDDEN_QUICK_LINK_ROUTES, true);
    }

    // Other wp-admin targets (e.g. FluentSMTP's settings) need manage_options.
    return str_starts_with($url, admin_url());
  }
}
