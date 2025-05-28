<?php

/** 
 * Plugin Name:     Admin Panel Settings
 * Description:     Customize admin panel settings for different user roles.
 * Version:     		0.1.0
 * Author:     			Christofer Laurin
 * Author URI:			https://github.com/dreadted/
 * Text Domain:			amrf-admin
 * Domain Path:     /languages
 */

namespace Antropomorf;

if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly
}

// Add the settings link to the Plugins page
add_filter('plugin_action_links_' . plugin_basename(__FILE__), __NAMESPACE__ . '\\amrf_admin_settings_link');

function amrf_admin_settings_link($links)
{
	$settings_link = '<a href="' . admin_url('options-general.php?page=amrf-admin-settings') . '">' . esc_html__('Settings', 'amrf-admin') . '</a>';
	array_unshift($links, $settings_link);
	return $links;
}

class AdminPanelSettings
{
	private $options;
	private $default_settings;
	private $all_menu_items = [];
	private $all_admin_pages = [];

	public function __construct()
	{

		// Load plugin text domain
		load_plugin_textdomain('amrf-admin-settings', false, dirname(plugin_basename(__FILE__)) . '/languages');

		$this->default_settings = [
			'add_page_editor_link' => true,
			'minimum_password_length' => 20,
			'prevent_password_change' => true,
			'hide_application_passwords' => true,
			'remove_admin_bar_items' => true,
			'remove_dashboard_widgets' => true,
			'user_group_settings' => [
				'editor' => [
					'login_redirect_url' => home_url(),
					'admin_default_page' => 'profile.php',
					'allowed_menu_items' => [
						'#builder_active',
						'fluent_forms',
						'nav-menus.php',
						'profile.php',
						'rank-math',
						'support-tickets',
						'umami-analytics',
						'upload.php',
					],
				],
			]
		];

		add_action('admin_menu', [$this, 'add_admin_menu']);
		add_action('admin_init', [$this, 'page_init']);
		add_action('admin_menu', [$this, 'scan_admin_menu_items']);
		add_action('admin_menu', [$this, 'scan_admin_pages']);
		add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
	}

	public function add_admin_menu()
	{
		add_options_page(
			__('Admin Panel Settings', 'amrf-admin'),
			__('Admin Panel Settings', 'amrf-admin'),
			'manage_options',
			'amrf-admin-settings',
			[$this, 'create_admin_page']
		);
	}

	public function create_admin_page()
	{
		$this->options = get_option('amrf_admin_settings', $this->default_settings);

		$roles = $this->get_editable_roles();
		unset($roles['administrator']);

		$tabs = ['general' => __('General', 'amrf-admin')];
		foreach ($roles as $role_slug => $role_info) {
			$tabs[$role_slug] = $role_info['name'];
		}
		$current_tab = isset($_GET['tab'], $tabs[$_GET['tab']]) ? $_GET['tab'] : 'general';

?>
		<div class="wrap">
			<h1><?php _e('Admin Panel Settings', 'amrf-admin'); ?></h1>
			<h2 class="nav-tab-wrapper">
				<?php foreach ($tabs as $tab => $label) : ?>
					<a href="<?php echo esc_url(add_query_arg(['page' => 'amrf-admin-settings', 'tab' => $tab], admin_url('options-general.php'))); ?>" class="nav-tab <?php echo $current_tab === $tab ? 'nav-tab-active' : ''; ?>">
						<?php echo esc_html($label); ?>
					</a>
				<?php endforeach; ?>
			</h2>
			<form method="post" action="options.php">
				<?php
				settings_fields('amrf_admin_settings_group');
				do_settings_sections('amrf-admin-settings-' . $current_tab);
				submit_button();
				?>
			</form>
		</div>
<?php
	}

	public static function on_plugin_activation()
	{
		$current_settings = get_option('amrf_admin_settings', []);
		$instance = new self();
		$merged_settings = self::recursive_merge_missing($instance->default_settings, $current_settings);
		update_option('amrf_admin_settings', $merged_settings);
	}

	private static function recursive_merge_missing(array $default, array $current): array
	{
		foreach ($default as $key => $value) {
			if (!array_key_exists($key, $current)) {
				$current[$key] = $value;
			} elseif (is_array($value) && is_array($current[$key])) {
				$current[$key] = self::recursive_merge_missing($value, $current[$key]);
			}
		}
		return $current;
	}

	public function page_init()
	{
		register_setting(
			'amrf_admin_settings_group',
			'amrf_admin_settings',
			[$this, 'sanitize']
		);

		// General Settings Section
		add_settings_section(
			'general_settings_section',
			__('General Settings', 'amrf-admin'),
			[$this, 'print_general_section_info'],
			'amrf-admin-settings-general'
		);

		add_settings_field(
			'add_page_editor_link',
			__('Add Page Editor Link', 'amrf-admin'),
			[$this, 'add_page_editor_link_callback'],
			'amrf-admin-settings-general',
			'general_settings_section'
		);

		add_settings_field(
			'minimum_password_length',
			__('Minimum Password Length', 'amrf-admin'),
			[$this, 'minimum_password_length_callback'],
			'amrf-admin-settings-general',
			'general_settings_section'
		);

		add_settings_field(
			'prevent_password_change',
			__('Prevent Non-Admins from Changing Passwords', 'amrf-admin'),
			[$this, 'prevent_password_change_callback'],
			'amrf-admin-settings-general',
			'general_settings_section'
		);

		add_settings_field(
			'hide_application_passwords',
			__('Hide Application Passwords for Non-Admins', 'amrf-admin'),
			[$this, 'hide_application_passwords_callback'],
			'amrf-admin-settings-general',
			'general_settings_section'
		);

		add_settings_field(
			'remove_admin_bar_items',
			__('Remove Admin Bar Items for Non-Admins', 'amrf-admin'),
			[$this, 'remove_admin_bar_items_callback'],
			'amrf-admin-settings-general',
			'general_settings_section'
		);

		add_settings_field(
			'remove_dashboard_widgets',
			__('Remove Default Dashboard Widgets', 'amrf-admin'),
			[$this, 'remove_dashboard_widgets_callback'],
			'amrf-admin-settings-general',
			'general_settings_section'
		);

		// User Role Settings Sections and Fields
		$roles = $this->get_editable_roles();
		foreach ($roles as $role_slug => $role_info) {
			if ('administrator' === $role_slug) {
				continue;
			}

			add_settings_section(
				'user_role_section_' . $role_slug,
				sprintf(__('%s Settings', 'amrf-admin'), $role_info['name']),
				[$this, 'print_user_role_section_info'],
				'amrf-admin-settings-' . $role_slug
			);

			add_settings_field(
				'user_role_' . $role_slug . '_settings',
				sprintf(__('%s Settings', 'amrf-admin'), $role_info['name']),
				[$this, 'user_role_settings_callback'],
				'amrf-admin-settings-' . $role_slug,
				'user_role_section_' . $role_slug,
				['role' => $role_slug]
			);
		}
	}

	public function sanitize($input)
	{
		$current = get_option('amrf_admin_settings', []);

		// General settings
		if (isset($input['add_page_editor_link'])) {
			$current['add_page_editor_link'] = true;
		} else {
			$current['add_page_editor_link'] = false;
		}
		if (isset($input['minimum_password_length'])) {
			$current['minimum_password_length'] = absint($input['minimum_password_length']);
		}
		if (isset($input['prevent_password_change'])) {
			$current['prevent_password_change'] = true;
		} else {
			$current['prevent_password_change'] = false;
		}
		if (isset($input['hide_application_passwords'])) {
			$current['hide_application_passwords'] = true;
		} else {
			$current['hide_application_passwords'] = false;
		}
		if (isset($input['remove_admin_bar_items'])) {
			$current['remove_admin_bar_items'] = true;
		} else {
			$current['remove_admin_bar_items'] = false;
		}
		if (isset($input['remove_dashboard_widgets'])) {
			$current['remove_dashboard_widgets'] = true;
		} else {
			$current['remove_dashboard_widgets'] = false;
		}

		// User role settings
		if (!empty($input['user_group_settings']) && is_array($input['user_group_settings'])) {
			foreach ($input['user_group_settings'] as $role => $settings) {
				if (!isset($current['user_group_settings'][$role])) {
					$current['user_group_settings'][$role] = [];
				}
				if (isset($settings['login_redirect_url'])) {
					$current['user_group_settings'][$role]['login_redirect_url'] = esc_url_raw($settings['login_redirect_url']);
				}
				if (isset($settings['admin_default_page'])) {
					$current['user_group_settings'][$role]['admin_default_page'] = sanitize_text_field($settings['admin_default_page']);
				}
				if (!empty($settings['allowed_menu_items']) && is_array($settings['allowed_menu_items'])) {
					$current['user_group_settings'][$role]['allowed_menu_items'] = [];
					foreach ($settings['allowed_menu_items'] as $item) {
						$current['user_group_settings'][$role]['allowed_menu_items'][] = sanitize_text_field($item);
					}
				}
			}
		}

		return $current;
	}

	public function print_general_section_info()
	{
		echo '<p>' . esc_html__('Configure general admin panel settings.', 'amrf-admin') . '</p>';
	}

	public function print_user_role_section_info()
	{
		// no output
	}

	private function render_checkbox_setting(string $key, string $description)
	{
		$checked = isset($this->options[$key]) ? $this->options[$key] : $this->default_settings[$key];
		echo '<label class="switch">';
		echo '<input type="checkbox" id="' . $key . '" name="amrf_admin_settings[' . $key . ']" value="1" ' . checked(1, $checked, false) . ' />';
		echo '<span class="slider round"></span>';
		echo '</label>';
		echo '<p class="description">' . esc_html__($description, 'amrf-admin') . '</p>';
	}

	public function add_page_editor_link_callback()
	{
		$this->render_checkbox_setting('add_page_editor_link',  __('Adds a link to the front page editor in the admin menu.'));
	}

	public function minimum_password_length_callback()
	{
		$value = isset($this->options['minimum_password_length']) ? $this->options['minimum_password_length'] : $this->default_settings['minimum_password_length'];
		echo '<input type="number" id="minimum_password_length" name="amrf_admin_settings[minimum_password_length]" value="' . esc_attr($value) . '" min="8" />';
		echo '<p class="description">' . esc_html__('Minimum required characters for user passwords.', 'amrf-admin') . '</p>';
	}

	public function prevent_password_change_callback()
	{
		$this->render_checkbox_setting('prevent_password_change', __('Prevents non-admin users from changing their passwords.', 'amrf-admin'));
	}

	public function hide_application_passwords_callback()
	{
		$this->render_checkbox_setting('hide_application_passwords', __('Hides application passwords section for non-admin users.', 'amrf-admin'));
	}

	public function remove_admin_bar_items_callback()
	{
		$this->render_checkbox_setting('remove_admin_bar_items', __('Removes comments and new content links from admin bar for non-admins.', 'amrf-admin'));
	}

	public function remove_dashboard_widgets_callback()
	{
		$this->render_checkbox_setting('remove_dashboard_widgets', __('Removes default dashboard widgets (Activity, Quick Draft, etc.).', 'amrf-admin'));
	}

	public function user_role_settings_callback($args)
	{
		$role = $args['role'];
		$settings = isset($this->options['user_group_settings'][$role]) ? $this->options['user_group_settings'][$role] : ($this->default_settings['user_group_settings'][$role] ?? []);

		echo '<div class="user-role-settings">';

		// Redirect Rules
		echo '<div class="setting-row">';
		echo '<h4>Login Redirect</h4>';
		$redirect_url = $settings['login_redirect_url'] ?? $this->default_settings['user_group_settings'][$role]['login_redirect_url'] ?? '';
		echo '<select name="amrf_admin_settings[user_group_settings][' . esc_attr($role) . '][login_redirect_url]">';
		echo '<option value="">-- ' . esc_html__('Select Redirect URL', 'amrf-admin') . ' --</option>';
		echo '<option value="/" ' . selected($redirect_url, '/', false) . '>-- ' . esc_html__('Front Page', 'amrf-admin') . ' --</option>';
		foreach ($this->all_menu_items[$role]['menu_items']  as $item) {
			$page = (strpos($item['slug'], 'php') === false) ? esc_attr('admin.php?page=' . $item['slug']) : esc_attr($item['slug']);
			echo '<option value="' . $page . '" ' . selected($redirect_url, $page, false) . '>' . esc_html($item['name']) . '</option>';
		}
		echo '</select>';
		echo '<p class="description">' . esc_html__('URL to redirect this user role to after login.', 'amrf-admin') . '</p>';
		echo '</div>';

		// Admin Default Page
		echo '<div class="setting-row">';
		echo '<h4>Default Admin Page</h4>';
		$default_page = $settings['admin_default_page'] ?? $this->default_settings['user_group_settings'][$role]['admin_default_page'] ?? '';
		$allowed_items = $settings['allowed_menu_items'] ?? $this->default_settings['user_group_settings'][$role]['allowed_menu_items'] ?? [];
		$filtered_menu_items = array_filter($this->all_menu_items[$role]['menu_items'], function ($item) use ($allowed_items) {
			return in_array($item['slug'], $allowed_items);
		});
		echo '<select name="amrf_admin_settings[user_group_settings][' . esc_attr($role) . '][admin_default_page]">';
		echo '<option value="">-- ' . esc_html__('Select Default Page', 'amrf-admin') . ' --</option>';
		foreach ($filtered_menu_items as $item) {
			$page = (strpos($item['slug'], 'php') === false) ? esc_attr('admin.php?page=' . $item['slug']) : esc_attr($item['slug']);
			echo '<option value="' . $page . '" ' . selected($default_page, $page, false) . '>' . esc_html($item['name']) . '</option>';
		}
		echo '</select>';
		echo '<p class="description">' . esc_html__('Default page this user role sees when accessing /wp-admin/ (must be one of the allowed menu items below).', 'amrf-admin') . '</p>';
		echo '</div>';

		// Allowed Menu Items
		echo '<div class="setting-row">';
		echo '<h4>Allowed Menu Items</h4>';
		$allowed_items = $settings['allowed_menu_items'] ?? $this->default_settings['user_group_settings'][$role]['allowed_menu_items'] ?? [];

		echo '<div class="menu-items-container">';

		if (!empty($this->all_menu_items[$role]['menu_items'])) {
			foreach ($this->all_menu_items[$role]['menu_items'] as $item) {
				$checked = in_array($item['slug'], $allowed_items) ? 'checked' : '';
				echo '<div class="menu-item-checkbox">';
				echo '<input type="checkbox" id="' . esc_attr($role) . '_' . esc_attr(sanitize_title($item['slug'])) . '" name="amrf_admin_settings[user_group_settings][' . esc_attr($role) . '][allowed_menu_items][]" value="' . esc_attr($item['slug']) . '" ' . $checked . ' />';
				echo '<label for="' . esc_attr($role) . '_' . esc_attr(sanitize_title($item['slug'])) . '">' . esc_html($item['name']) . ' <code>' . esc_html($item['slug']) . '</code></label>';
				echo '</div>';
			}
		} else {
			echo '<p>' . esc_html__('No menu items available for this role based on its capabilities.', 'amrf-admin') . '</p>';
		}

		echo '</div>';
		echo '<p class="description">' . esc_html__('Select which admin menu items should be visible to this user role.', 'amrf-admin') . '</p>';
		echo '</div>';

		echo '</div>';
	}

	public function enqueue_admin_scripts($hook)
	{
		if ('settings_page_amrf-admin-settings' !== $hook) {
			return;
		}

		wp_enqueue_style('amrf-admin-settings', plugins_url('amrf-admin-settings.css', __FILE__));
		wp_enqueue_script('amrf-admin-settings', plugins_url('amrf-admin-settings.js', __FILE__), ['jquery'], false, true);
	}

	private function get_editable_roles()
	{
		$roles = wp_roles()->roles;
		return apply_filters('editable_roles', $roles);
	}

	public function scan_admin_menu_items()
	{
		global $menu, $submenu;

		$this->all_menu_items = [];
		$roles = $this->get_editable_roles();

		foreach ($roles as $role_slug => $role_info) {
			if ($role_slug === 'administrator') continue;

			// Create a temporary user with this role to check capabilities
			$user = new \WP_User(0);
			$user->add_role($role_slug);

			$this->all_menu_items[$role_slug] = [
				'menu_items' => []
			];

			foreach ($menu as $item) {
				// Skip separators and empty items
				if (empty($item[2])) continue;
				if (strpos($item[2], 'separator') !== false) continue;

				// Check if user has capability to access this menu item
				if (user_can($user, $item[1])) {
					// Clean up the menu name (might contain HTML)
					$menu_name = wp_strip_all_tags($item[0]);

					if ($item[2] === 'edit-comments.php') {
						$menu_name = 'Comments';
					} else {
						// Optionally clean other menu items here
						$menu_name = trim($menu_name);
					}

					$this->all_menu_items[$role_slug]['menu_items'][] = [
						'name' => $menu_name,
						'slug' => $item[2]
					];
				}
			}

			// Also check submenu items
			foreach ($submenu as $parent_slug => $items) {
				foreach ($items as $item) {
					// Skip separators and empty items
					if (empty($item[2])) continue;
					if (strpos($item[2], 'separator') !== false) continue;

					// Check if user has capability to access this submenu item
					if (user_can($user, $item[1])) {
						// Clean up the submenu name (might contain HTML)
						$submenu_name = wp_strip_all_tags($item[0]);

						// Check if we already have this parent in our list
						$parent_exists = false;
						foreach ($this->all_menu_items[$role_slug]['menu_items'] as $existing_item) {
							if ($existing_item['slug'] === $parent_slug) {
								$parent_exists = true;
								break;
							}
						}

						if (!$parent_exists) {
							// Add parent if not already there
							foreach ($menu as $top_item) {
								if (!empty($top_item[2]) && $top_item[2] === $parent_slug) {
									$this->all_menu_items[$role_slug]['menu_items'][] = [
										'name' => wp_strip_all_tags($top_item[0]),
										'slug' => $top_item[2]
									];
									break;
								}
							}
						}

						// Add the submenu item
						$this->all_menu_items[$role_slug]['menu_items'][] = [
							'name' => $submenu_name,
							'slug' => $item[2]
						];
					}
				}
			}
		}

		// Add some common items that might not be visible currently
		$common_items = [
			'rank-math' => 'Rank Math',
			'fluent_forms' => 'Fluent Forms',
			'support-tickets' => 'Support Tickets',
			'umami-analytics' => 'Umami Analytics',
			'#builder_active' => __('Page Builder', 'amrf-admin')
		];

		foreach ($common_items as $slug => $name) {
			foreach ($this->all_menu_items as $role_slug => $role_data) {
				$exists = false;
				foreach ($role_data['menu_items'] as $item) {
					if ($item['slug'] === $slug) {
						$exists = true;
						break;
					}
				}
				if (!$exists) {
					$this->all_menu_items[$role_slug]['menu_items'][] = [
						'name' => $name,
						'slug' => $slug
					];
				}
			}
		}

		// Sort menu items by name for each role
		foreach ($this->all_menu_items as $role_slug => $role_data) {
			usort($this->all_menu_items[$role_slug]['menu_items'], function ($a, $b) {
				return strcmp($a['name'], $b['name']);
			});
		}
	}

	public function scan_admin_pages()
	{
		global $menu, $submenu;

		$this->all_admin_pages = ['profile.php'];

		// Get all top level menu pages
		foreach ($menu as $item) {
			if (!empty($item[2])) {
				$this->all_admin_pages[] = $item[2];
			}
		}

		// Get all submenu pages
		foreach ($submenu as $items) {
			foreach ($items as $item) {
				if (!empty($item[2])) {
					$this->all_admin_pages[] = $item[2];
				}
			}
		}

		$this->all_admin_pages = array_unique($this->all_admin_pages);
		sort($this->all_admin_pages);
	}
}
// Initialize the plugin
if (is_admin()) {
	new AdminPanelSettings();
}

// Implement the actual functionality based on settings
add_action('init', function () {
	$settings = get_option('amrf_admin_settings', []);

	// Add link to page editor if enabled
	if (!empty($settings['add_page_editor_link'])) {
		add_action('admin_menu', 'Antropomorf\add_custom_page_to_menu');
	}

	// Remove default dashboard widgets if enabled
	if (!empty($settings['remove_dashboard_widgets'])) {
		add_action('wp_dashboard_setup', function () {
			remove_meta_box('dashboard_activity', 'dashboard', 'normal');
			remove_meta_box('dashboard_right_now', 'dashboard', 'normal');
			remove_meta_box('dashboard_quick_press', 'dashboard', 'side');
			remove_meta_box('dashboard_recent_comments', 'dashboard', 'normal');
			remove_meta_box('dashboard_primary', 'dashboard', 'side');
			remove_meta_box('dashboard_secondary', 'dashboard', 'side');
		});
	}

	// Password length enforcement
	if (!empty($settings['minimum_password_length'])) {
		$minimum_password_length = absint($settings['minimum_password_length']);

		add_action('user_profile_update_errors', function ($errors, $update, $user) use ($minimum_password_length) {
			if (!empty($_POST['pass1']) && strlen($_POST['pass1']) < $minimum_password_length) {
				$errors->add(
					'pass',
					sprintf(
						__('ERROR: Password must be at least %d characters long.', 'amrf-admin'),
						$minimum_password_length
					)
				);
			}
			return $errors;
		}, 10, 3);

		add_action('password_reset', function ($user, $new_pass) use ($minimum_password_length) {
			if (strlen($new_pass) < $minimum_password_length) {
				wp_die(sprintf(
					__('ERROR: Password must be at least %d characters long.', 'amrf-admin'),
					$minimum_password_length
				));
			}
		}, 10, 2);
	}

	// Prevent password changes for non-admins if enabled
	if (!empty($settings['prevent_password_change'])) {
		add_filter('show_password_fields', function ($show, $profile_user) {
			if (!in_array('administrator', $profile_user->roles)) {
				return false;
			}
			return $show;
		}, 10, 2);

		add_filter('allow_password_reset', function ($allow, $user_id) {
			$user = get_userdata($user_id);
			if (!in_array('administrator', $user->roles)) {
				return false;
			}
			return $allow;
		}, 10, 2);
	}

	// Hide application passwords for non-admins if enabled
	if (!empty($settings['hide_application_passwords'])) {
		add_action('admin_init', function () {
			if (!current_user_can('administrator')) {
				remove_action('application_passwords_section', ['WP_Application_Passwords', 'render_section']);
				remove_action('application_passwords', ['WP_Application_Passwords', 'render_app_passwords']);
				add_filter('wp_is_application_passwords_available', '__return_false');
			}
		});
	}

	// Remove admin bar items for non-admins if enabled
	if (!empty($settings['remove_admin_bar_items'])) {
		add_action('wp_before_admin_bar_render', function () {
			if (!current_user_can('administrator')) {
				global $wp_admin_bar;
				$wp_admin_bar->remove_menu('comments');
				$wp_admin_bar->remove_menu('new-content');
			}
		});
	}

	// User role specific settings
	if (!empty($settings['user_group_settings'])) {
		$user_group_settings = $settings['user_group_settings'];


		// Login redirect logic
		add_filter('login_redirect', function ($redirect_to, $request, $user) use ($user_group_settings) {
			if (is_wp_error($user) || !isset($user->roles)) {
				return $redirect_to;
			}

			if (is_array($user->roles)) {
				foreach ($user->roles as $role) {
					if (isset($user_group_settings[$role]['login_redirect_url'])) {
						$url =  $user_group_settings[$role]['login_redirect_url'];
						$redirect = (strpos($url, 'php') === false) ? home_url() : '/wp-admin/' .  $url;
						return $redirect;
					}
				}
			}
			return $redirect_to;
		}, 10, 3);

		// Admin default page redirect
		add_action('admin_init', function () use ($user_group_settings) {
			if (!current_user_can('administrator')) {
				$user = wp_get_current_user();

				foreach ($user->roles as $role) {
					if (isset($user_group_settings[$role]['admin_default_page'])) {

						$default_page = $user_group_settings[$role]['admin_default_page'];
						if (!empty($default_page) && get_admin_url() === home_url($_SERVER['REQUEST_URI'])) {
							wp_safe_redirect(admin_url($default_page));
							exit;
						}
						break;
					}
				}
			}
		});

		// Menu item filtering
		add_action('admin_menu', function () use ($user_group_settings) {
			if (!current_user_can('administrator')) {
				global $menu;
				$user = wp_get_current_user();
				$allowed_items = [];

				foreach ($user->roles as $role) {
					if (isset($user_group_settings[$role]['allowed_menu_items'])) {
						$allowed_items = $user_group_settings[$role]['allowed_menu_items'];
						break;
					}
				}

				foreach ($menu as $key => $item) {
					$menu_slug = $item[2];
					$is_allowed = false;

					// Check each allowed item to see if it appears in the menu slug
					foreach ($allowed_items as $allowed_item) {
						if (strpos($menu_slug, $allowed_item) !== false) {
							$is_allowed = true;
							break;
						}
					}

					if (!$is_allowed) {
						unset($menu[$key]);
					}
				}
			}
		}, 999);
	}
});

// Original function for adding page editor link
function add_custom_page_to_menu()
{
	$front_page_id = get_option('page_on_front');
	$page = get_post($front_page_id);
	if ($page) {
		$url = home_url('/#builder_active');
		add_menu_page(
			'themify-editor',
			__('Page Editor', 'amrf-admin'),
			'edit_posts',
			$url,
			'',
			'dashicons-edit',
			6
		);
	}
}

register_activation_hook(__FILE__, [__NAMESPACE__ . '\\AdminPanelSettings', 'on_plugin_activation']);
