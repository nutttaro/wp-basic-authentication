# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Overview

WP Basic Authentication is a WordPress plugin that adds HTTP Basic Authentication (like `.htpasswd`) to protect development/demo sites. It supports Docker and Kubernetes environments. Published on wordpress.org as `wp-basic-authentication`.

## Architecture

**Two-class design:**

- `WPBA_Basic_Authentication` (`wp-basic-authentication.php`) — Main plugin class. Intercepts requests on `init` (priority 1) and challenges for HTTP Basic Auth credentials. Conditionally loads admin settings only in `is_admin()` context.
- `WPBA_Setting` (`inc/class-wpba-setting.php`) — WordPress Settings API integration. Renders the admin page under the "Authentication" top-level menu (`dashicons-privacy`). Handles sanitization and password hashing on save.

**Settings stored in a single option:** `wpba_auth_settings` with keys: `enable`, `username`, `password`, `enable_login`, `enable_rest`, `excluded_paths`, `allowed_ips`.

**Version tracking:** `wpba_auth_version` option tracks the installed version for migration purposes.

## Key Behaviors

- Passwords are hashed with `wp_hash_password()` on save and verified with `wp_check_password()` at auth time.
- Automatic migration from plain-text to hashed passwords runs on `plugins_loaded` for upgrades from pre-1.1.0. The hash detection uses `wpba_is_password_hash()` (length >= 34 and starts with `$`).
- Frontend auth, login page auth (`wp-login.php`, `wp-register.php`), and REST API auth are controlled by separate toggles.
- Path exclusion and IP allowlists bypass authentication for matching requests.
- The admin page is gated by `manage_options` capability.

## Known Pitfalls

- **Double-hash prevention:** WordPress can call the `sanitize` callback more than once (e.g., when `update_option` delegates to `add_option`, which re-runs `sanitize_option`). The sanitize method checks `wpba_is_password_hash()` before hashing to prevent double-hashing. This was a real bug fixed in 1.1.2 — do not remove that guard.
- **Page caching:** The plugin does not work when a page cache plugin serves static HTML before PHP runs. The `advanced-cache.php` drop-in would be the standard fix for this.

## Plugin Constants

- `WPBA_PATH` — Plugin directory path
- `WPBA_BASENAME` — Plugin basename (for action links filter)
- `WPBA_PLUGIN_URL` — Plugin URL
- `WPBA_VERSION` — Current version string (keep in sync with the plugin header)

## SVN / Release

The `svn/` directory contains the wordpress.org SVN checkout for plugin distribution (trunk + assets). It is gitignored. Update `svn/trunk/` files and `svn/assets/` screenshots/banners when preparing a release.

Keep `readme.txt` (wordpress.org format) and `README.md` (GitHub format) in sync — they duplicate changelog and FAQ content.

## Version Bumps

When releasing, update the version in **three places**:
1. Plugin header `Version:` in `wp-basic-authentication.php`
2. `WPBA_VERSION` constant in `wp-basic-authentication.php`
3. `Stable tag:` in `readme.txt`

## i18n

Text domain: `wp-basic-authentication`. POT file in `languages/`. Wrap all user-facing strings with `__()` or `esc_html__()`.

## Development

- PHP only — no JS/CSS build step, no `package.json`, no `composer.json`
- No test suite. Manual testing against a running WordPress instance is the only verification method.
- POT file generation requires WP-CLI or a tool like Poedit.

## Code Style

- Tabs for indentation
- No closing `?>` tag
- WordPress coding standards with PHPCS annotations where deviations are intentional (e.g., password input not sanitized)
