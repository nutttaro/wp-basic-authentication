<?php
/**
 * Shared helper functions for WP Basic Authentication
 *
 * @package WP_Basic_Authentication
 */

if (!defined('ABSPATH')) {
	die('-1');
}

/**
 * Check if a string looks like a password hash.
 *
 * @param string $value The string to check.
 * @return bool
 */
function wpba_is_password_hash( string $value ): bool {
	return strlen($value) >= 34 && $value[0] === '$';
}
