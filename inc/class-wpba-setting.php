<?php
/**
 * Settings page class for WP Basic Authentication
 *
 * Handles the admin settings interface for configuring
 * HTTP Basic Authentication credentials and options.
 *
 * @package WP_Basic_Authentication
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
	die('-1');
}

/**
 * Class WPBA_Setting
 *
 * Manages the WordPress admin settings page for Basic Authentication.
 * Provides interface for enabling/disabling authentication, setting credentials,
 * and configuring which pages require authentication.
 */
class WPBA_Setting
{
	/**
	 * Array of custom settings/options
	 *
	 * @var array
	 */
	private $options;

	/**
	 * Constructor
	 */
	public function __construct()
	{
		add_action('admin_menu', [$this, 'add_settings_page']);
		add_action('admin_init', [$this, 'page_init']);
	}

	/**
	 * Add settings page
	 * The page will appear in Admin menu
	 *
	 * @return void
	 */
	public function add_settings_page(): void
	{
		add_menu_page(
			__('Basic Authentication Settings', 'wp-basic-authentication'), // Page title
			__('Authentication', 'wp-basic-authentication'), // Title
			'manage_options', // Capability
			'wpba-auth-settings-page', // Url slug
			[$this, 'create_admin_page'], // Callback
			'dashicons-privacy'
		);
	}

	/**
	 * Options page callback
	 *
	 * @return void
	 */
	public function create_admin_page(): void
	{
		// Check user capabilities
		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'wp-basic-authentication'));
		}

		// Set class property
		$this->options = get_option('wpba_auth_settings');
		?>
        <div class='wrap'>
            <form method='post' action='options.php'>
                <?php
                // This prints out all hidden setting fields
                settings_fields('wpba_auth_settings_group');
                do_settings_sections('wpba-auth-settings-page');
                submit_button();
				?>
            </form>
        </div>
		<?php
	}

	/**
	 * Register and add settings
	 *
	 * @return void
	 */
	public function page_init(): void
	{
		register_setting(
			'wpba_auth_settings_group', // Option group
			'wpba_auth_settings', // Option name
			[$this, 'sanitize'] // Sanitize
		);

		add_settings_section(
			'wpba_auth_settings_section', // ID
			__('Basic HTTP Authentication', 'wp-basic-authentication'), // Title
			[$this, 'wpba_auth_settings_section'], // Callback
			'wpba-auth-settings-page' // Page
		);

		add_settings_field(
			'enable', // ID
			__('Enable', 'wp-basic-authentication'), // Title
			[$this, 'enable_field'], // Callback
			'wpba-auth-settings-page', // Page
			'wpba_auth_settings_section'
		);

		add_settings_field(
			'username', // ID
			__('Username', 'wp-basic-authentication'), // Title
			[$this, 'username_field'], // Callback
			'wpba-auth-settings-page', // Page
			'wpba_auth_settings_section'
		);

		add_settings_field(
			'password',
			__('Password', 'wp-basic-authentication'),
			[$this, 'password_field'],
			'wpba-auth-settings-page',
			'wpba_auth_settings_section'
		);

		add_settings_field(
			'enable_login', // ID
			__('Enable for Login page', 'wp-basic-authentication'), // Title
			[$this, 'enable_login_field'], // Callback
			'wpba-auth-settings-page', // Page
			'wpba_auth_settings_section'
		);
	}

	/**
	 * Sanitize POST data from custom settings form
	 *
	 * Validates and sanitizes user input from the settings form.
	 * Handles password hashing automatically when a new password is provided.
	 * Uses wp_hash_password() for secure password storage.
	 *
	 * @param array $input Contains custom settings which are passed when saving the form
	 * @return array Sanitized settings array
	 */
	public function sanitize(array $input): array
	{
		// Verify nonce
		if (!isset($_POST['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'wpba_auth_settings_group-options')) {
			wp_die(esc_html__('Security check failed.', 'wp-basic-authentication'));
		}

		$sanitized_input = [];
		$old_options = get_option('wpba_auth_settings');

		// Sanitize 'enable'
		$sanitized_input['enable'] = isset($input['enable'])
			? (int) $input['enable']
			: 0;

		// Sanitize 'username'
		$sanitized_input['username'] = isset($input['username'])
			? sanitize_text_field($input['username'])
			: '';

		// Handle 'password'
		if (isset($input['password']) && !empty($input['password'])) {
			// Hash the new password if provided
			$sanitized_input['password'] = wp_hash_password($input['password']);
		} else {
			// Retain the old password if no new password is provided
			$sanitized_input['password'] = $old_options['password'];
		}

		// Sanitize 'enable_login'
		$sanitized_input['enable_login'] = isset($input['enable_login'])
			? (int) $input['enable_login']
			: 0;

		return $sanitized_input;
	}

	/**
	 * Custom settings section text
	 * @return void
	 */
	public function wpba_auth_settings_section(): void {}

	/**
	 * Enable field
	 *
	 * @return void
	 */
	public function enable_field(): void
	{
		echo '<input type="checkbox" id="enable" name="wpba_auth_settings[enable]" value="1" ' . checked($this->options['enable'], 1, false) . ' /> ' . esc_html__('Enable authentication for Front-End', 'wp-basic-authentication');
	}

	/**
	 * Username field
	 *
	 * @return void
	 */
	public function username_field(): void
	{
		printf(
			'<input type="text" id="username" name="wpba_auth_settings[username]" value="%s" />',
			isset($this->options['username']) ? esc_attr($this->options['username']) : ''
		);
	}

	/**
	 * Password field
	 *
	 * @return void
	 */
	public function password_field(): void
	{
		echo '<input type="password" id="password" name="wpba_auth_settings[password]" value=""/>';
		echo '<p class="description">The password will be hashed.</p>';
	}

	/**
	 * Checkbox field
	 *
	 * @return void
	 */
	public function enable_login_field(): void
	{
		echo '<input type="checkbox" id="enable_login" name="wpba_auth_settings[enable_login]" value="1" ' . checked($this->options['enable_login'], 1, false) . ' />';
		printf(
			'<p class="description" id="enable_login-description">' .
				/* translators: %s: URL to the plugin FAQ page */
				_e(
					'<strong>Warning: If enable basic authentication for login page and forgot password, please see <a href="%s" target="_blank">FAQs in plugin page</a>',
					'wp-basic-authentication'
				) .
				'</p>',
			esc_url('https://wordpress.org/plugins/wp-basic-authentication/#faq')
		);
	}
}
