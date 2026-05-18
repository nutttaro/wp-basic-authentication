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
	 * Settings page hook suffix
	 *
	 * @var string
	 */
	private $page_hook;

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
	 *
	 * @return void
	 */
	public function add_settings_page(): void
	{
		$this->page_hook = add_menu_page(
			__('Basic Authentication Settings', 'wp-basic-authentication'),
			__('Authentication', 'wp-basic-authentication'),
			'manage_options',
			'wpba-auth-settings-page',
			[$this, 'create_admin_page'],
			'dashicons-privacy'
		);

		add_action('admin_print_styles-' . $this->page_hook, [$this, 'enqueue_admin_assets']);
	}

	/**
	 * Enqueue admin CSS on the settings page only.
	 *
	 * @return void
	 */
	public function enqueue_admin_assets(): void
	{
		wp_enqueue_style(
			'wpba-admin',
			WPBA_PLUGIN_URL . 'assets/admin.css',
			[],
			WPBA_VERSION
		);
	}

	/**
	 * Options page callback
	 *
	 * @return void
	 */
	public function create_admin_page(): void
	{
		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'wp-basic-authentication'));
		}

		$this->options = get_option('wpba_auth_settings');

		$is_frontend = !empty($this->options['enable']);
		$is_login = !empty($this->options['enable_login']);
		$is_rest = !empty($this->options['enable_rest']);
		$is_active = $is_frontend || $is_login || $is_rest;
		$has_credentials = !empty($this->options['username']) && !empty($this->options['password']);

		$scopes = [];
		if ($is_frontend) {
			$scopes[] = __('Front-end', 'wp-basic-authentication');
		}
		if ($is_login) {
			$scopes[] = __('Login page', 'wp-basic-authentication');
		}
		if ($is_rest) {
			$scopes[] = __('REST API', 'wp-basic-authentication');
		}
		?>
		<div class="wrap wpba-wrap">
			<h1><?php echo esc_html__('Basic Authentication', 'wp-basic-authentication'); ?></h1>

			<?php $this->render_status_banner($is_active, $has_credentials, $scopes); ?>

			<form method="post" action="options.php">
				<?php settings_fields('wpba_auth_settings_group'); ?>

				<div class="wpba-card">
					<h2><?php echo esc_html__('Protection Scope', 'wp-basic-authentication'); ?></h2>
					<table class="form-table" role="presentation">
						<?php do_settings_fields('wpba-auth-settings-page', 'wpba_section_scope'); ?>
					</table>
				</div>

				<div class="wpba-card">
					<h2><?php echo esc_html__('Credentials', 'wp-basic-authentication'); ?></h2>
					<table class="form-table" role="presentation">
						<?php do_settings_fields('wpba-auth-settings-page', 'wpba_section_credentials'); ?>
					</table>
				</div>

				<div class="wpba-card">
					<h2><?php echo esc_html__('Bypass Rules', 'wp-basic-authentication'); ?></h2>
					<table class="form-table" role="presentation">
						<?php do_settings_fields('wpba-auth-settings-page', 'wpba_section_bypass'); ?>
					</table>
				</div>

				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Render the status banner.
	 *
	 * @param bool  $is_active       Whether any protection scope is enabled.
	 * @param bool  $has_credentials Whether credentials are configured.
	 * @param array $scopes          Active scope labels.
	 * @return void
	 */
	private function render_status_banner(bool $is_active, bool $has_credentials, array $scopes): void
	{
		if ($is_active && $has_credentials) {
			$class = 'wpba-status--active';
			$text = esc_html__('Protection active', 'wp-basic-authentication');
			$detail = implode(', ', $scopes);
		} elseif ($is_active && !$has_credentials) {
			$class = 'wpba-status--inactive';
			$text = esc_html__('Missing credentials', 'wp-basic-authentication');
			$detail = esc_html__('Set a username and password below', 'wp-basic-authentication');
		} else {
			$class = 'wpba-status--inactive';
			$text = esc_html__('Protection inactive', 'wp-basic-authentication');
			$detail = esc_html__('Enable at least one scope below', 'wp-basic-authentication');
		}

		echo '<div class="wpba-status ' . esc_attr($class) . '">';
		echo '<span class="wpba-status__dot"></span>';
		echo '<p class="wpba-status__text">' . esc_html($text);
		if ($detail) {
			echo ' <span class="wpba-status__detail">&mdash; ' . esc_html($detail) . '</span>';
		}
		echo '</p>';
		echo '</div>';
	}

	/**
	 * Register and add settings
	 *
	 * @return void
	 */
	public function page_init(): void
	{
		register_setting(
			'wpba_auth_settings_group',
			'wpba_auth_settings',
			[$this, 'sanitize']
		);

		// Section: Protection Scope
		add_settings_section(
			'wpba_section_scope',
			'',
			'__return_false',
			'wpba-auth-settings-page'
		);

		add_settings_field(
			'enable',
			__('Front-end', 'wp-basic-authentication'),
			[$this, 'enable_field'],
			'wpba-auth-settings-page',
			'wpba_section_scope'
		);

		add_settings_field(
			'enable_login',
			__('Login page', 'wp-basic-authentication'),
			[$this, 'enable_login_field'],
			'wpba-auth-settings-page',
			'wpba_section_scope'
		);

		add_settings_field(
			'enable_rest',
			__('REST API', 'wp-basic-authentication'),
			[$this, 'enable_rest_field'],
			'wpba-auth-settings-page',
			'wpba_section_scope'
		);

		// Section: Credentials
		add_settings_section(
			'wpba_section_credentials',
			'',
			'__return_false',
			'wpba-auth-settings-page'
		);

		add_settings_field(
			'username',
			__('Username', 'wp-basic-authentication'),
			[$this, 'username_field'],
			'wpba-auth-settings-page',
			'wpba_section_credentials'
		);

		add_settings_field(
			'password',
			__('Password', 'wp-basic-authentication'),
			[$this, 'password_field'],
			'wpba-auth-settings-page',
			'wpba_section_credentials'
		);

		// Section: Bypass Rules
		add_settings_section(
			'wpba_section_bypass',
			'',
			'__return_false',
			'wpba-auth-settings-page'
		);

		add_settings_field(
			'excluded_paths',
			__('Excluded Paths', 'wp-basic-authentication'),
			[$this, 'excluded_paths_field'],
			'wpba-auth-settings-page',
			'wpba_section_bypass'
		);

		add_settings_field(
			'allowed_ips',
			__('Allowed IPs', 'wp-basic-authentication'),
			[$this, 'allowed_ips_field'],
			'wpba-auth-settings-page',
			'wpba_section_bypass'
		);
	}

	/**
	 * Sanitize POST data from custom settings form
	 *
	 * @param array $input Contains custom settings which are passed when saving the form
	 * @return array Sanitized settings array
	 */
	public function sanitize(array $input): array
	{
		if (!isset($_POST['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'wpba_auth_settings_group-options')) {
			wp_die(esc_html__('Security check failed.', 'wp-basic-authentication'));
		}

		$sanitized_input = [];
		$old_options = get_option('wpba_auth_settings');

		$sanitized_input['enable'] = isset($input['enable'])
			? (int) $input['enable']
			: 0;

		$sanitized_input['username'] = isset($input['username'])
			? sanitize_text_field($input['username'])
			: '';

		if (isset($input['password']) && !empty($input['password'])) {
			// Only hash if not already hashed (prevents double-hashing when
			// update_option delegates to add_option, which re-runs sanitize_option)
			if (wpba_is_password_hash($input['password'])) {
				$sanitized_input['password'] = $input['password'];
			} else {
				$sanitized_input['password'] = wp_hash_password($input['password']);
			}
		} else {
			$sanitized_input['password'] = isset($old_options['password']) ? $old_options['password'] : '';
		}

		$sanitized_input['enable_login'] = isset($input['enable_login'])
			? (int) $input['enable_login']
			: 0;

		$sanitized_input['enable_rest'] = isset($input['enable_rest'])
			? (int) $input['enable_rest']
			: 0;

		$sanitized_input['excluded_paths'] = isset($input['excluded_paths'])
			? sanitize_textarea_field($input['excluded_paths'])
			: '';

		$sanitized_input['allowed_ips'] = isset($input['allowed_ips'])
			? sanitize_textarea_field($input['allowed_ips'])
			: '';

		return $sanitized_input;
	}

	/**
	 * Enable front-end field
	 *
	 * @return void
	 */
	public function enable_field(): void
	{
		echo '<label class="wpba-toggle">';
		echo '<input type="checkbox" id="enable" name="wpba_auth_settings[enable]" value="1" ' . checked($this->options['enable'] ?? 0, 1, false) . ' />';
		echo '<span class="wpba-toggle__track"></span>';
		echo '<span class="wpba-toggle__label">' . esc_html__('Require authentication for all front-end pages', 'wp-basic-authentication') . '</span>';
		echo '</label>';
	}

	/**
	 * Enable login page field
	 *
	 * @return void
	 */
	public function enable_login_field(): void
	{
		echo '<label class="wpba-toggle">';
		echo '<input type="checkbox" id="enable_login" name="wpba_auth_settings[enable_login]" value="1" ' . checked($this->options['enable_login'] ?? 0, 1, false) . ' />';
		echo '<span class="wpba-toggle__track"></span>';
		echo '<span class="wpba-toggle__label">' . esc_html__('Require authentication for wp-login.php', 'wp-basic-authentication') . '</span>';
		echo '</label>';
		printf(
			'<p class="description">' .
				wp_kses(
					/* translators: %s: URL to the plugin FAQ page */
					__( '<strong>Warning</strong>: If you forget your credentials with this enabled, see <a href="%s" target="_blank">FAQs</a> for recovery steps.', 'wp-basic-authentication' ),
					[
						'strong' => [],
						'a' => [
							'href' => [],
							'target' => [],
						],
					]
				) .
				'</p>',
			esc_url('https://wordpress.org/plugins/wp-basic-authentication/#faq')
		);
	}

	/**
	 * Enable REST API field
	 *
	 * @return void
	 */
	public function enable_rest_field(): void
	{
		echo '<label class="wpba-toggle">';
		echo '<input type="checkbox" id="enable_rest" name="wpba_auth_settings[enable_rest]" value="1" ' . checked($this->options['enable_rest'] ?? 0, 1, false) . ' />';
		echo '<span class="wpba-toggle__track"></span>';
		echo '<span class="wpba-toggle__label">' . esc_html__('Require authentication for /wp-json/ endpoints', 'wp-basic-authentication') . '</span>';
		echo '</label>';
	}

	/**
	 * Username field
	 *
	 * @return void
	 */
	public function username_field(): void
	{
		printf(
			'<input type="text" id="username" name="wpba_auth_settings[username]" value="%s" class="regular-text" autocomplete="off" />',
			isset($this->options['username']) ? esc_attr($this->options['username']) : ''
		);
	}

	/**
	 * Password field with visibility toggle
	 *
	 * @return void
	 */
	public function password_field(): void
	{
		$has_password = !empty($this->options['password']);
		echo '<div class="wpba-password-wrapper">';
		echo '<input type="password" id="wpba-password" name="wpba_auth_settings[password]" value="" class="regular-text" autocomplete="new-password" />';
		echo '<button type="button" class="wpba-password-toggle" aria-label="' . esc_attr__('Toggle password visibility', 'wp-basic-authentication') . '">';
		echo '<span class="dashicons dashicons-visibility"></span>';
		echo '</button>';
		echo '</div>';
		if ($has_password) {
			echo '<p class="description">' . esc_html__('A password is set. Leave blank to keep the current password.', 'wp-basic-authentication') . '</p>';
		} else {
			echo '<p class="description">' . esc_html__('The password will be securely hashed before storage.', 'wp-basic-authentication') . '</p>';
		}
		?>
		<script>
		(function() {
			var btn = document.querySelector('.wpba-password-toggle');
			if (!btn) return;
			btn.addEventListener('click', function() {
				var input = document.getElementById('wpba-password');
				var icon = btn.querySelector('.dashicons');
				if (input.type === 'password') {
					input.type = 'text';
					icon.className = 'dashicons dashicons-hidden';
				} else {
					input.type = 'password';
					icon.className = 'dashicons dashicons-visibility';
				}
			});
		})();
		</script>
		<?php
	}

	/**
	 * Excluded paths field
	 *
	 * @return void
	 */
	public function excluded_paths_field(): void
	{
		echo '<textarea id="excluded_paths" name="wpba_auth_settings[excluded_paths]" rows="4" cols="50" class="large-text code">' . esc_textarea($this->options['excluded_paths'] ?? '') . '</textarea>';
		echo '<p class="description">' . esc_html__('One path per line. Supports suffix wildcards, e.g. /health or /api/webhooks/*', 'wp-basic-authentication') . '</p>';
	}

	/**
	 * Allowed IPs field
	 *
	 * @return void
	 */
	public function allowed_ips_field(): void
	{
		echo '<textarea id="allowed_ips" name="wpba_auth_settings[allowed_ips]" rows="4" cols="50" class="large-text code">' . esc_textarea($this->options['allowed_ips'] ?? '') . '</textarea>';
		echo '<p class="description">' . esc_html__('One IP address per line. Requests from these IPs skip authentication entirely.', 'wp-basic-authentication') . '</p>';
	}
}
