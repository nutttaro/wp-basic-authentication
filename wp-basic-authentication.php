<?php
/**
 * Plugin Name:       WP Basic Authentication
 * Plugin URI:        https://wordpress.org/plugins/wp-basic-authentication/
 * Description:       Basic Authentication for protected your development WordPress site like .htpasswd
 * Version:           1.1.0
 * Requires at least: 5.7
 * Requires PHP:      7.4
 * Tested up to:      6.9
 * Author:            NuttTaro
 * Author URI:        https://nutttaro.com
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wp-basic-authentication
 * Domain Path:       /languages
 *
 * @package WP_Basic_Authentication
 */

if (!defined('ABSPATH')) {
	die('-1');
}

// Define constants.
define('WPBA_PATH', plugin_dir_path(__FILE__));
define('WPBA_BASENAME', plugin_basename(__FILE__));
define('WPBA_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WPBA_VERSION', '1.1.0');

/**
 * Class WPBA_Basic_Authentication
 *
 * Main plugin class that handles HTTP Basic Authentication
 * for WordPress frontend and login pages.
 *
 * Features:
 * - Password hashing using wp_hash_password()
 * - Automatic migration from plain-text to hashed passwords
 * - Separate controls for frontend and login page authentication
 * - Secure credential comparison using hash_equals() and wp_check_password()
 *
 * @since 1.0.0
 */
class WPBA_Basic_Authentication
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
		register_activation_hook(__FILE__, [$this, 'set_default_options']);

		// Check for migration from older versions
		add_action('plugins_loaded', [$this, 'check_and_migrate_password']);

		$this->options = get_option('wpba_auth_settings');

		if (is_admin()) {
			require_once WPBA_PATH . 'inc/class-wpba-setting.php';
			new \WPBA_Setting();
		} else {
			$enable_frontend = $this->options['enable'] ?? 0;
			$enable_login = $this->options['enable_login'] ?? 0;

			if ($enable_login && $this->is_login_page()) {
				add_action('init', [$this, 'basic_auth_handler'], 1);
			}

			if ($enable_frontend && !$this->is_login_page()) {
				add_action('init', [$this, 'basic_auth_handler'], 1);
			}
		}

		add_filter('plugin_action_links_' . WPBA_BASENAME, [$this,'add_plugin_donate_link']);
	}

	/**
	 * Basic auth handler
	 *
	 * Checks HTTP Basic Authentication credentials against stored values.
	 * Supports both legacy plain-text passwords (for backward compatibility during migration)
	 * and modern hashed passwords (wp_hash_password).
	 *
	 * @return void
	 */
	public function basic_auth_handler(): void
	{
		$username = $this->options['username'] ?? '';
		$password = $this->options['password'] ?? '';

		if (!$username) {
			return;
		}

		header('Cache-Control: no-cache, must-revalidate, max-age=0');

		$has_supplied_credentials = !(
			empty($_SERVER['PHP_AUTH_USER']) &&
			empty($_SERVER['PHP_AUTH_PW'])
		);

		if (!$has_supplied_credentials) {
			$this->send_unauthorized_response();
		}

		$provided_username = isset($_SERVER['PHP_AUTH_USER']) ? sanitize_text_field(wp_unslash($_SERVER['PHP_AUTH_USER'])) : '';
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- Password must not be sanitized as it would alter the value. Only unslashed for security.
		$provided_password = isset($_SERVER['PHP_AUTH_PW']) ? wp_unslash($_SERVER['PHP_AUTH_PW']) : '';

		// Check if stored password is hashed
		// WordPress password hashes start with $P$ and are typically 34 characters
		// But we should also check for other hash formats that might be longer
		$is_password_hashed = (
			(strlen($password) >= 34 && strpos($password, '$wp') === 0) ||
			(strlen($password) >= 34 && strpos($password, '$P$') === 0) ||
			(strlen($password) >= 34 && strpos($password, '$2y$') === 0) ||
			(strlen($password) >= 34 && strpos($password, '$argon2') === 0)
		);

		$is_authenticated = false;

		// First check username
		if (hash_equals($provided_username, $username)) {
			if ($is_password_hashed) {
				// Compare with hashed password
				$is_authenticated = wp_check_password($provided_password, $password);
			} else {
				// Legacy support for unhashed passwords (for migration period)
				$is_authenticated = hash_equals($provided_password, $password);
			}
		}

		if (!$is_authenticated) {
			$this->send_unauthorized_response();
		}
	}

	/**
	 * Send unauthorized response
	 *
	 * @return void
	 */
	private function send_unauthorized_response(): void
	{
		header('HTTP/1.1 401 Authorization Required');
		header('WWW-Authenticate: Basic realm="Access denied"');
		exit();
	}

	/**
	 * Check login page
	 *
	 * @return bool
	 */
	private function is_login_page(): bool
	{
		if (isset($GLOBALS['pagenow'])) {
			return in_array($GLOBALS['pagenow'], [
				'wp-login.php',
				'wp-register.php',
			]);
		}

		return false;
	}

	/**
	 * Set default options
	 *
	 * @return void
	 */
	public function set_default_options(): void
	{
		$this->options = [
			'enable' => 0,
			'username' => '',
			'password' => '',
			'enable_login' => 0,
		];

		update_option('wpba_auth_settings', $this->options);
		update_option('wpba_auth_version', WPBA_VERSION);
	}

	/**
	 * Check and migrate password from older versions
	 *
	 * This method handles the migration from unhashed passwords (pre-1.1.0)
	 * to hashed passwords (1.1.0+). It:
	 * 1. Checks if the user is updating from a version older than 1.1.0
	 * 2. Detects if the stored password is already hashed
	 * 3. Hashes unhashed passwords automatically using wp_hash_password()
	 * 4. Shows an admin notice about the migration
	 * 5. Updates the stored version number
	 *
	 * @return void
	 */
	public function check_and_migrate_password(): void
	{
		// Ensure options are loaded
		if (empty($this->options)) {
			$this->options = get_option('wpba_auth_settings', []);
		}

		$stored_version = get_option('wpba_auth_version', '0.0.0');

		// Only migrate if coming from a version older than 1.1.0
		if (version_compare($stored_version, '1.1.0', '<') && !empty($this->options['password'])) {
			$password = $this->options['password'];

			// Better detection of hashed passwords
			// WordPress password hashes start with $P$ and are typically 34 characters
			// But we should also check for other hash formats that might be longer
			$is_already_hashed = (
				(strlen($password) >= 34 && strpos($password, '$wp') === 0) ||
				(strlen($password) >= 34 && strpos($password, '$P$') === 0) ||
				(strlen($password) >= 34 && strpos($password, '$2y$') === 0) ||
				(strlen($password) >= 34 && strpos($password, '$argon2') === 0)
			);

			if (!$is_already_hashed) {
				// Hash the existing password
				$this->options['password'] = wp_hash_password($password);

				// Update the options
				update_option('wpba_auth_settings', $this->options);

				// Add admin notice for successful migration
				add_action('admin_notices', function() {
					echo '<div class="notice notice-success is-dismissible">';
					echo '<p>' . esc_html__('WP Basic Authentication: Password has been automatically migrated to the new secure format.', 'wp-basic-authentication') . '</p>';
					echo '</div>';
				});
			}
		}

		// Update version to current
		update_option('wpba_auth_version', WPBA_VERSION);
	}

	/**
	 * Add donate link
	 *
	 * @param array $links Plugin action links
	 * @return array
	 */
	public function add_plugin_donate_link(array $links): array
	{
		$links[] =
			'<a href="https://coff.ee/nutttaro" target="_blank">' .
			__('Donate', 'wp-basic-authentication') .
			'</a>';
		return $links;
	}
}

new \WPBA_Basic_Authentication();
