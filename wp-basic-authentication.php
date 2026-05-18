<?php
/**
 * Plugin Name:       WP Basic Authentication
 * Plugin URI:        https://wordpress.org/plugins/wp-basic-authentication/
 * Description:       Basic Authentication for protected your development WordPress site like .htpasswd
 * Version:           1.2.0
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
define('WPBA_VERSION', '1.2.0');

require_once WPBA_PATH . 'inc/helpers.php';

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
			add_action('admin_notices', [$this, 'environment_type_notice']);
		} else {
			$enable_frontend = $this->options['enable'] ?? 0;
			$enable_login = $this->options['enable_login'] ?? 0;
			$enable_rest = $this->options['enable_rest'] ?? 0;

			if ($enable_login && $this->is_login_page()) {
				add_action('init', [$this, 'basic_auth_handler'], 1);
			}

			if ($enable_rest && $this->is_rest_request()) {
				add_action('init', [$this, 'basic_auth_handler'], 1);
			}

			if ($enable_frontend && !$this->is_login_page() && !$this->is_rest_request()) {
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
		if ($this->is_ip_allowed()) {
			return;
		}

		if ($this->is_path_excluded()) {
			return;
		}

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

		$is_password_hashed = wpba_is_password_hash($password);

		$is_authenticated = false;

		// First check username
		if (hash_equals($username, $provided_username)) {
			if ($is_password_hashed) {
				$is_authenticated = wp_check_password($provided_password, $password);
			} else {
				$is_authenticated = hash_equals($password, $provided_password);
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
			'enable_rest' => 0,
			'excluded_paths' => '',
			'allowed_ips' => '',
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

			if (!wpba_is_password_hash($password)) {
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
	 * Check if the current request is a REST API request.
	 *
	 * @return bool
	 */
	private function is_rest_request(): bool
	{
		$request_uri = isset($_SERVER['REQUEST_URI']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'])) : '';
		$rest_prefix = function_exists('rest_get_url_prefix') ? rest_get_url_prefix() : 'wp-json';
		return strpos($request_uri, '/' . $rest_prefix . '/') !== false
			|| substr($request_uri, -strlen('/' . $rest_prefix)) === '/' . $rest_prefix;
	}

	/**
	 * Check if the remote IP is in the allowed list.
	 *
	 * @return bool
	 */
	private function is_ip_allowed(): bool
	{
		$allowed_ips = $this->options['allowed_ips'] ?? '';
		if (empty($allowed_ips)) {
			return false;
		}

		$remote_ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '';
		$ips = array_filter(array_map('trim', explode("\n", $allowed_ips)));

		return in_array($remote_ip, $ips, true);
	}

	/**
	 * Check if the current request path is in the excluded list.
	 * Supports exact match and prefix wildcard (e.g. /api/*).
	 *
	 * @return bool
	 */
	private function is_path_excluded(): bool
	{
		$excluded_paths = $this->options['excluded_paths'] ?? '';
		if (empty($excluded_paths)) {
			return false;
		}

		$request_uri = isset($_SERVER['REQUEST_URI']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'])) : '';
		$request_path = wp_parse_url($request_uri, PHP_URL_PATH);

		$paths = array_filter(array_map('trim', explode("\n", $excluded_paths)));

		foreach ($paths as $path) {
			if (substr($path, -1) === '*') {
				$prefix = substr($path, 0, -1);
				if (strpos($request_path, $prefix) === 0) {
					return true;
				}
			} elseif (rtrim($request_path, '/') === rtrim($path, '/')) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Show admin notice when plugin is active on a production environment.
	 *
	 * @return void
	 */
	public function environment_type_notice(): void
	{
		if (!function_exists('wp_get_environment_type')) {
			return;
		}

		$env_type = wp_get_environment_type();
		$enable_frontend = $this->options['enable'] ?? 0;
		$enable_login = $this->options['enable_login'] ?? 0;
		$enable_rest = $this->options['enable_rest'] ?? 0;

		if ($env_type === 'production' && ($enable_frontend || $enable_login || $enable_rest)) {
			echo '<div class="notice notice-warning is-dismissible">';
			echo '<p>' . esc_html__('WP Basic Authentication is active on a production environment. This plugin is typically intended for development or staging sites.', 'wp-basic-authentication') . '</p>';
			echo '</div>';
		}
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
