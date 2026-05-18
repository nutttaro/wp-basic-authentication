<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * @package WP_Basic_Authentication
 */

if (!defined('ABSPATH')) {
	exit;
}

if (!defined('WP_UNINSTALL_PLUGIN')) {
	exit;
}

delete_option('wpba_auth_settings');
delete_option('wpba_auth_version');
