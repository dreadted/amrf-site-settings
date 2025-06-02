<?php

/** 
 * Plugin Name:     Admin Panel Settings
 * Description:     Customize admin panel settings for different user roles.
 * Version:     		1.0.0
 * Author:     			Christofer Laurin
 * Author URI:			https://github.com/dreadted/
 * Text Domain:			amrf-admin
 * Domain Path:     /languages
 */

namespace Antropomorf;

if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly
}

/**
 * Get the full version string, including the plugin version and a hash of the file.
 * 
 * @param string $file_path Path to the file whose hash is to be included.
 * @return string Combined version string including the plugin version and file hash.
 */
function get_version($file_path = null)
{
	$version = get_plugin_info('Version');
	$hash = $file_path ? get_file_hash($file_path) : '';
	return $version . $hash;
}

/**
 * Retrieve specific plugin information based on a given parameter.
 *
 * @param string $parameter The parameter to retrieve from the plugin header (e.g., 'Version').
 * @return string|null The corresponding value from the plugin header, or null if not found.
 */
function get_plugin_info(string $parameter)
{
	$file_content = file_get_contents(__FILE__);
	$pattern = '/' . $parameter . ':\s+(\d+\.\d+\.\d+|\w+)/';
	if (preg_match($pattern, $file_content, $matches))
		return $matches[1];
}

/**
 * Get an abbreviated hash of the file content.
 *
 * @param string $file_path Path to the file to hash.
 * @return string|null A substring of the MD5 hash of the file, or null if the file doesn't exist.
 */
function get_file_hash($file_path)
{
	if (file_exists($file_path)) {
		$file_hash = hash_file('md5', $file_path);
		return "-" . substr($file_hash, 0, 6);
	}
}

/**
 * Display the plugin version in the footer of the admin settings page.
 *
 * This function checks if the current screen is the plugin's settings page and,
 * if so, outputs the plugin version in the footer.
 *
 * @return void
 */
function a_show_version_in_footer()
{
	// Check if we are on your plugin's settings page by checking the current screen
	$screen = get_current_screen();
	error_log($screen->id);
	if ($screen && $screen->id === 'settings_page_amrf-admin-settings') { // Replace with your menu slug
		echo '<div style=" font-size:12px; text-align:right; color:#666;">';
		echo 'v' . get_version(__FILE__);
		echo '</div>';
	}
}
// add_action('in_admin_footer', __NAMESPACE__ . '\\show_version_in_footer');

function show_version_in_footer($footer_text)
{
	// Check if we are on your plugin's settings page by checking the current screen
	$screen = get_current_screen();
	error_log($screen->id);
	if ($screen && $screen->id === 'settings_page_amrf-admin-settings') {
		$version_text = '<strong>Admin Panel Settings</strong> v' . get_version(__FILE__);
		return $version_text;
	}
	return $footer_text;
}
add_filter('admin_footer_text', __NAMESPACE__ . '\\show_version_in_footer');

/**
 * Add the 'Settings' link to the plugin action links on the Plugins page.
 *
 * @param array $links Existing action links for the plugin.
 * @return array Modified action links including the 'Settings' link.
 */
function amrf_admin_settings_link($links)
{
	$settings_link = '<a href="' . admin_url('options-general.php?page=amrf-admin-settings') . '">' . esc_html__('Settings', 'amrf-admin') . '</a>';
	array_unshift($links, $settings_link);
	return $links;
}
add_filter('plugin_action_links_' . plugin_basename(__FILE__), __NAMESPACE__ . '\\amrf_admin_settings_link');

/* AdminPanelSettings
------------------------------------------------------------ */
class AdminPanelSettings
{
	private $options;
	private $default_settings;
	private $all_menu_items = [];
	private $tabs = [];

	/**
	 * Constructor. Initializes plugin text domain, default settings, and registers WordPress hooks.
	 *
	 * @return void
	 */
	public function __construct()
	{

		// Load plugin text domain
		load_plugin_textdomain('amrf-admin', false, dirname(plugin_basename(__FILE__)) . '/languages');

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
					'rank_math_all_caps' => false,
					'site_menus_cap' => false,
				],
			]
		];

		add_action('admin_menu', [$this, 'add_admin_menu']);
		add_action('admin_init', [$this, 'page_init']);
		add_action('admin_menu', [$this, 'scan_admin_menu_items']);
		add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
	}

	/**
	 * Register the settings page under the 'Settings' menu in the admin dashboard.
	 *
	 * @return void
	 */
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

	/**
	 * Render the main settings page, including tabs for each role and form fields.
	 *
	 * @return void
	 */
	public function create_admin_page()
	{
		$this->scan_admin_menu_items();
		$this->options = get_option('amrf_admin_settings', $this->default_settings);

		$roles = $this->get_editable_roles();
		unset($roles['administrator']);

		$this->tabs = ['general' => __('General', 'amrf-admin')];
		foreach ($roles as $role_slug => $role_info) {
			// Make role name translatable using WordPress core translations
			$this->tabs[$role_slug] = translate_user_role($role_info['name']);
		}
		$current_tab = isset($_GET['tab'], $this->tabs[$_GET['tab']]) ? $_GET['tab'] : 'general';

?>
		<div class="wrap">
			<h1><?php _e('Admin Panel Settings', 'amrf-admin'); ?></h1>
			<h2 class="nav-tab-wrapper">
				<?php foreach ($this->tabs as $tab => $label) : ?>
					<a href="<?php echo esc_url(add_query_arg(['page' => 'amrf-admin-settings', 'tab' => $tab], admin_url('options-general.php'))); ?>" class="nav-tab <?php echo $current_tab === $tab ? 'nav-tab-active' : ''; ?>">
						<?php echo esc_html($label); ?>
					</a>
				<?php endforeach; ?>
			</h2>
			<form method="post" action="options.php">
				<input type="hidden" name="current_tab" value="<?php echo esc_attr($current_tab); ?>">
				<?php
				settings_fields('amrf_admin_settings_group');
				do_settings_sections('amrf-admin-settings-' . $current_tab);
				submit_button();
				?>
			</form>
		</div>
<?php
	}

	/**
	 * Ensure default settings exist during plugin activation.
	 *
	 * @return void
	 */
	public static function on_plugin_activation()
	{
		$current_settings = get_option('amrf_admin_settings', []);
		$instance = new self();
		$merged_settings = self::recursive_merge_missing($instance->default_settings, $current_settings);
		update_option('amrf_admin_settings', $merged_settings);
	}

	/**
	 * Recursively merge default settings into the current settings array for missing keys.
	 *
	 * @param array $default Default settings to use as a base.
	 * @param array $current Current settings to merge defaults into.
	 * @return array Merged settings array.
	 */
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

	/**
	 * Register settings sections, fields, and callbacks for the plugin settings page.
	 *
	 * @return void
	 */
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
				//  translators: %s is the user role name
				sprintf(__('%s Settings', 'amrf-admin'), translate_user_role($role_info['name'])),
				[$this, 'print_user_role_section_info'],
				'amrf-admin-settings-' . $role_slug
			);

			add_settings_field(
				'user_role_' . $role_slug . '_settings',
				sprintf(__('%s Settings', 'amrf-admin'), translate_user_role($role_info['name'])),
				[$this, 'user_role_settings_callback'],
				'amrf-admin-settings-' . $role_slug,
				'user_role_section_' . $role_slug,
				['role' => $role_slug]
			);
		}
	}

	/**
	 * Sanitize settings input before saving to the database.
	 *
	 * @param array $input Raw input values from settings form.
	 * @return array Sanitized settings array.
	 */
	public function sanitize($input)
	{
		$current = get_option('amrf_admin_settings', $this->default_settings);
		$current_tab = $_POST['current_tab'] ?? 'general';

		if ($current_tab === 'general') {
			// Process only general settings
			$current['add_page_editor_link'] = isset($input['add_page_editor_link']);
			$current['minimum_password_length'] = absint($input['minimum_password_length'] ?? $this->default_settings['minimum_password_length']);
			$current['prevent_password_change'] = isset($input['prevent_password_change']);
			$current['hide_application_passwords'] = isset($input['hide_application_passwords']);
			$current['remove_admin_bar_items'] = isset($input['remove_admin_bar_items']);
			$current['remove_dashboard_widgets'] = isset($input['remove_dashboard_widgets']);
		} elseif (array_key_exists($current_tab, $this->get_editable_roles())) {
			$role = $current_tab;

			// Initialize role settings if they don't exist
			if (!isset($current['user_group_settings'][$role])) {
				$current['user_group_settings'][$role] = $this->default_settings['user_group_settings'][$role] ?? [];
			}

			// Only process settings for the current role
			if (!empty($input['user_group_settings'][$role])) {
				$role_settings = $input['user_group_settings'][$role];

				if (isset($role_settings['login_redirect_url'])) {
					$current['user_group_settings'][$role]['login_redirect_url'] = esc_url_raw($role_settings['login_redirect_url']);
				}

				if (isset($role_settings['admin_default_page'])) {
					$current['user_group_settings'][$role]['admin_default_page'] = sanitize_text_field($role_settings['admin_default_page']);
				}

				if (!empty($role_settings['allowed_menu_items']) && is_array($role_settings['allowed_menu_items'])) {
					$current['user_group_settings'][$role]['allowed_menu_items'] = array_map('sanitize_text_field', $role_settings['allowed_menu_items']);
				}

				$current['user_group_settings'][$role]['rank_math_all_caps'] = isset($role_settings['rank_math_all_caps']);

				$current['user_group_settings'][$role]['site_menus_cap'] = isset($role_settings['site_menus_cap']);
			}
		}
		return $current;
	}

	/**
	 * Print the description for the general settings section.
	 *
	 * @return void
	 */
	public function print_general_section_info()
	{
		echo '<p>' . esc_html__('Configure general admin panel settings.', 'amrf-admin') . '</p>';
	}

	/**
	 * Section callback for user role settings; no description output is needed.
	 *
	 * @return void
	 */
	public function print_user_role_section_info()
	{
		// no output
	}

	/**
	 * Render a checkbox input with a slider style and description.
	 *
	 * @param string $key         Setting key used for the input name and id.
	 * @param string $description Description text displayed next to the checkbox.
	 * @return void
	 */
	private function render_checkbox_setting(string $key, string $description, array $path = [])
	{
		// Build the full option path
		$option_path = $this->options;
		$default_path = $this->default_settings;

		foreach ($path as $segment) {
			$option_path = $option_path[$segment] ?? null;
			$default_path = $default_path[$segment] ?? null;
		}

		$checked = isset($option_path[$key]) ? $option_path[$key] : ($default_path[$key] ?? false);

		// Build the name attribute
		$name = 'amrf_admin_settings';
		foreach ($path as $segment) {
			$name .= '[' . esc_attr($segment) . ']';
		}
		$name .= '[' . esc_attr($key) . ']';

		echo '<label class="switch">';
		echo '<input type="checkbox" id="' . esc_attr($key) . '" name="' . $name . '" value="1" ' . checked(1, $checked, false) . ' />';
		echo '<span class="slider round"></span>';
		echo '</label>';
		echo '<p class="description">' . esc_html__($description, 'amrf-admin') . '</p>';
	}

	/**
	 * Callback to render the 'Add Page Editor Link' setting field.
	 *
	 * @return void
	 */
	public function add_page_editor_link_callback()
	{
		$this->render_checkbox_setting('add_page_editor_link',  __('Adds a link to the front page editor in the admin menu.'));
	}

	/**
	 * Callback to render the 'Minimum Password Length' setting field.
	 *
	 * @return void
	 */
	public function minimum_password_length_callback()
	{
		$value = isset($this->options['minimum_password_length']) ? $this->options['minimum_password_length'] : $this->default_settings['minimum_password_length'];
		echo '<input type="number" id="minimum_password_length" name="amrf_admin_settings[minimum_password_length]" value="' . esc_attr($value) . '" min="8" />';
		echo '<p class="description">' . esc_html__('Minimum required characters for user passwords.', 'amrf-admin') . '</p>';
	}

	/**
	 * Callback to render the 'Prevent Non-Admins from Changing Passwords' setting field.
	 *
	 * @return void
	 */
	public function prevent_password_change_callback()
	{
		$this->render_checkbox_setting('prevent_password_change', __('Prevents non-admin users from changing their passwords.', 'amrf-admin'));
	}

	/**
	 * Callback to render the 'Hide Application Passwords for Non-Admins' setting field.
	 *
	 * @return void
	 */
	public function hide_application_passwords_callback()
	{
		$this->render_checkbox_setting('hide_application_passwords', __('Hides application passwords section for non-admin users.', 'amrf-admin'));
	}

	/**
	 * Callback to render the 'Remove Admin Bar Items for Non-Admins' setting field.
	 *
	 * @return void
	 */
	public function remove_admin_bar_items_callback()
	{
		$this->render_checkbox_setting('remove_admin_bar_items', __('Removes comments and new content links from admin bar for non-admins.', 'amrf-admin'));
	}

	/**
	 * Callback to render the 'Remove Default Dashboard Widgets' setting field.
	 *
	 * @return void
	 */
	public function remove_dashboard_widgets_callback()
	{
		$this->render_checkbox_setting('remove_dashboard_widgets', __('Removes default dashboard widgets (Activity, Quick Draft, etc.).', 'amrf-admin'));
	}

	/**
	 * Callback to render the settings fields for a specific user role tab.
	 *
	 * @param array $args Array containing callback arguments, expects 'role' key with role slug.
	 * @return void
	 */
	public function user_role_settings_callback($args)
	{
		$role = $args['role'];
		// Get the default group settings for the current role
		$default_group_settings = $this->default_settings['user_group_settings'][$role] ?? [];

		// Get the current group settings, or use an empty array if none exists
		$group_settings = $this->options['user_group_settings'][$role] ?? [];

		// Merge current group settings with defaults (current overrides default)
		$settings = array_merge($default_group_settings, $group_settings);

		// Now, safely access settings with empty fallbacks
		$redirect_url = $settings['login_redirect_url'] ?? '';
		$default_page = $settings['admin_default_page'] ?? '';
		$allowed_items = $settings['allowed_menu_items'] ?? [];

		echo '<div class="user-role-settings">';

		// Redirect Rules
		echo '<div class="setting-row">';
		echo '<h4>' . esc_html__('Login Redirect', 'amrf-admin') . '</h4>';
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
		echo '<h4>' . esc_html__('Default Admin Page', 'amrf-admin') . '</h4>';
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

		// Rank Math Capabilities (single toggle)
		echo '<div class="setting-row">';
		echo '<h4>' . esc_html__('Rank Math Access', 'amrf-admin') . '</h4>';
		$this->render_checkbox_setting(
			'rank_math_all_caps',
			__('When enabled, this role will have access to all Rank Math features.', 'amrf-admin'),
			['user_group_settings', $role]
		);
		echo '</div>';

		// Site Menus Capability
		echo '<div class="setting-row">';
		echo '<h4>' . esc_html__('Site Menus Access', 'amrf-admin') . '</h4>';
		$this->render_checkbox_setting(
			'site_menus_cap',
			__('Enable to allow this user role to access and edit site menus.', 'amrf-admin'),
			['user_group_settings', $role]
		);
		echo '</div>';

		// Allowed Menu Items
		echo '<div class="setting-row">';
		echo '<h4>' . esc_html__('Allowed Menu Items', 'amrf-admin') . '</h4>';
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

	/**
	 * Enqueue admin-specific CSS and JS for the settings page.
	 *
	 * @param string $hook The current admin page hook suffix.
	 * @return void
	 */
	public function enqueue_admin_scripts($hook)
	{
		if ('settings_page_amrf-admin-settings' !== $hook) {
			return;
		}

		wp_enqueue_style('amrf-admin-settings', plugins_url('amrf-admin-settings.css', __FILE__));
		wp_enqueue_script('amrf-admin-settings', plugins_url('amrf-admin-settings.js', __FILE__), ['jquery'], false, true);
	}

	/**
	 * Retrieve a list of editable user roles filtered by capabilities.
	 *
	 * @return array List of roles as key/value pairs.
	 */
	private function get_editable_roles()
	{
		$roles = wp_roles()->roles;
		return apply_filters('editable_roles', $roles);
	}

	/**
	 * Get the plain menu name, stripping counters and HTML.
	 *
	 * @param string $menu_title
	 * @return string
	 */
	private function get_clean_menu_name($menu_title)
	{
		// Remove all <span>...</span> and their contents (including nested spans)
		$clean = preg_replace('/<span\b[^>]*>.*?<\/span>/si', '', $menu_title);

		// Remove any other HTML tags just in case
		$clean = wp_strip_all_tags($clean);

		// Decode HTML entities
		$clean = html_entity_decode($clean, ENT_QUOTES | ENT_HTML5, 'UTF-8');

		// Trim whitespace
		$clean = trim($clean);

		return $clean;
	}

	/**
	 * Scan the admin menu and submenu items for each role based on capabilities.
	 *
	 * Populates $this->all_menu_items with accessible menu items per role.
	 *
	 * @return void
	 */
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

			// Initialize the role's menu items array if not already set
			if (!isset($this->all_menu_items[$role_slug])) {
				$this->all_menu_items[$role_slug] = [
					'menu_items' => []
				];
			}

			// Track added menu slugs to prevent duplicates
			$added_menu_slugs = [];
			$parent_names = [];

			foreach ($menu as $item) {
				// Skip separators and empty items
				if (empty($item[2])) continue;
				if (strpos($item[2], 'separator') !== false) continue;

				// Check user capability
				if (true || user_can($user, $item[1])) {
					$menu_name = $this->get_clean_menu_name($item[0]);
					$parent_names[$item[2]] = $menu_name;

					// Add menu item if not already added
					if (!in_array($item[2], $added_menu_slugs)) {
						$this->all_menu_items[$role_slug]['menu_items'][] = [
							'name' => $menu_name,
							'slug' => $item[2]
						];
						$added_menu_slugs[] = $item[2];
					}
				}
			}


			// Also check submenu items
			foreach ($submenu as $parent_slug => $items) {
				foreach ($items as $item) {
					// Skip separators and empty items
					if (empty($item[2])) continue;
					if (strpos($item[2], 'separator') !== false) continue;

					// Only process core WordPress submenu items (those ending with .php)
					if (strpos($item[2], '.php') === false) continue;

					// Check if user has capability to access this submenu item
					if (true || user_can($user, $item[1])) {
						$submenu_name = $this->get_clean_menu_name($item[0]);
						$parent_name = isset($parent_names[$parent_slug]) ? $parent_names[$parent_slug] : '';

						// Combine parent and submenu names
						if ($parent_name) {
							$full_name = $parent_name . ' / ' . $submenu_name;
						} else {
							$full_name = $submenu_name;
						}

						// Check if we already have this parent in our list
						$parent_exists = false;
						foreach ($this->all_menu_items[$role_slug]['menu_items'] as $existing_item) {
							if ($existing_item['slug'] === $parent_slug) {
								$parent_exists = true;
								break;
							}
						}

						if (!$parent_exists) {
							// Add parent if not already there (only if it's a core item)
							foreach ($menu as $top_item) {
								if (!empty($top_item[2]) && $top_item[2] === $parent_slug && strpos($top_item[2], '.php') !== false) {
									$this->all_menu_items[$role_slug]['menu_items'][] = [
										'name' => $this->get_clean_menu_name($top_item[0]),
										'slug' => $top_item[2]
									];
									break;
								}
							}
						}

						// Add the submenu item if not already added
						if (!in_array($item[2], $added_menu_slugs)) {
							$this->all_menu_items[$role_slug]['menu_items'][] = [
								'name' => $full_name,
								'slug' => $item[2]
							];
							$added_menu_slugs[] = $item[2];
						}
					}
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
}

// Initialize the plugin
if (is_admin()) {
	new AdminPanelSettings();
}

/**
 * Registers initialization actions for the admin settings
 * 
 * This function hooks into WordPress's 'init' action to set up admin-specific functionality.
 * It retrieves plugin settings and conditionally registers additional actions based on
 * configuration options, such as adding page editor links and removing dashboard widgets.
 * 
 * @since 1.0.0
 * @uses get_option() To retrieve plugin settings
 * @uses add_action() To register additional WordPress hooks
 * @uses empty() To check if specific settings are configured
 * @return void
 */
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
						//  translators: %d is the number indicating minimum password length
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
					//  translators: %d is the number indicating minimum password length
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

	// Handle role specific settings
	if (!empty($settings['user_group_settings'])) {
		$user_group_settings = $settings['user_group_settings'];


		// Helper function for user settings
		function get_user_setting($user, $user_group_settings, $setting_key, $default = null)
		{
			foreach ($user->roles as $role) {
				if (isset($user_group_settings[$role][$setting_key])) {
					return $user_group_settings[$role][$setting_key];
				}
			}
			return $default;
		}


		// Login redirect URL and Admin default page
		add_filter('auth_cookie', function ($cookie, $user_id, $expiration, $scheme, $token) {
			set_transient('user_' . $user_id . '_logging_in', true, 60);
			return $cookie;
		}, 10, 5);

		add_action('admin_init', function () use ($user_group_settings) {
			if (!current_user_can('administrator')) {
				$user = wp_get_current_user();
				$transient_key = 'user_' . $user->ID . '_logging_in';
				$is_logging_in = get_transient($transient_key);

				if ($is_logging_in) {
					delete_transient($transient_key);
					$login_redirect_url = get_user_setting($user, $user_group_settings, 'login_redirect_url');
					if ($login_redirect_url) {
						$redirect = (strpos($login_redirect_url, '.php') === false) ? home_url($login_redirect_url) : admin_url($login_redirect_url);
						wp_safe_redirect($redirect);
						exit;
					}
				} else {
					$admin_default_page = get_user_setting($user, $user_group_settings, 'admin_default_page');
					if ($admin_default_page && get_admin_url() === home_url($_SERVER['REQUEST_URI'])) {
						wp_safe_redirect(admin_url($admin_default_page));
						exit;
					}
				}

				// Rank Math Access
				$rank_math_enabled =  get_user_setting($user, $user_group_settings, 'rank_math_all_caps') ?? false;
				error_log('rank_math_enabled:' . $rank_math_enabled);
				$rank_math_caps = [
					'rank_math_site_analysis',
					'rank_math_onpage_analysis',
					'rank_math_onpage_general',
					'rank_math_onpage_snippet',
					'rank_math_onpage_social',
					'rank_math_titles',
					'rank_math_general',
					'rank_math_sitemap',
					'rank_math_404_monitor',
					'rank_math_link_builder',
					'rank_math_redirections',
					'rank_math_role_manager',
					'rank_math_analytics',
					'rank_math_onpage_advanced',
					'rank_math_content_ai',
					'rank_math_admin_bar',
				];

				if ($rank_math_enabled) {

					// Activate Role Manager
					$modules = get_option('rank_math_modules', []);
					if (!in_array('role-manager', $modules, true)) {
						$modules[] = 'role-manager';
						update_option('rank_math_modules', $modules);
					}

					// Add capabilities
					foreach ($rank_math_caps as $cap) {
						$user->add_cap($cap);
						error_log('Adding ' . $cap . ' for ' . $user->user_login);
					}
				} else {
					// Remove capabilities
					foreach ($rank_math_caps as $cap) {
						$user->remove_cap($cap);
						error_log('Removing ' . $cap . ' for ' . $user->user_login);
					}
				}

				// Site Menus Access
				$site_menus_enabled = get_user_setting($user, $user_group_settings, 'site_menus_cap') ?? false;
				error_log('site_menus_enabled:' . $site_menus_enabled);
				if ($site_menus_enabled) {
					$user->add_cap('edit_theme_options');
					error_log('Adding site menus for ' . $user->user_login);
				} else {
					$user->remove_cap('edit_theme_options');
					error_log('Removing site menus for ' . $user->user_login);
				}
			}
		});


		// Menu item filtering
		add_action('admin_menu', function () use ($user_group_settings) {
			if (!current_user_can('administrator')) {
				global $menu, $submenu;
				$user = wp_get_current_user();
				$allowed_items = get_user_setting($user, $user_group_settings, 'allowed_menu_items');
				$icons_to_preserve = []; // Store parent icons for promoted items

				// First pass: collect parent icons before removing menu items
				foreach ($menu as $key => $item) {
					$menu_slug = $item[2];
					// Check if this menu item has allowed subitems
					if (isset($submenu[$menu_slug])) {
						foreach ($submenu[$menu_slug] as $subitem) {
							if (in_array($subitem[2], $allowed_items)) {
								// Store parent icon for this allowed subitem
								$icons_to_preserve[$subitem[2]] = $item[6] ?? 'dashicons-dashboard';
							}
						}
					}
				}

				// Second pass: remove all menu items that aren't explicitly allowed
				foreach ($menu as $key => $item) {
					$menu_slug = $item[2];
					if (!in_array($menu_slug, $allowed_items)) {
						unset($menu[$key]);
					}
				}

				// Third pass: promote allowed submenu items
				foreach ($submenu as $parent_slug => $subitems) {
					foreach ($subitems as $subitem) {
						$subitem_slug = $subitem[2];
						// If subitem is allowed but parent isn't, promote it
						if (in_array($subitem_slug, $allowed_items) && !in_array($parent_slug, $allowed_items)) {
							$icon = $icons_to_preserve[$subitem_slug] ?? 'dashicons-dashboard';
							// Add as new top-level menu item
							add_menu_page(
								$subitem[0], // Page title
								$subitem[0], // Menu title
								$user->roles[0], // Capability (use the first role)
								$subitem_slug,
								'',
								$icon
							);
						}
					}
				}
			}
		}, 999);
	}
});

/**
 * Add a 'Page Editor' top-level admin menu item linking to the front page editor.
 *
 * @return void
 */
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

// Register activation hook
register_activation_hook(__FILE__, [__NAMESPACE__ . '\\AdminPanelSettings', 'on_plugin_activation']);
