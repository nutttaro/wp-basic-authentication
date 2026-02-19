=== WP Basic Authentication ===
Contributors: nutttaro
Donate link: https://coff.ee/nutttaro
Tags: authentication, basic-auth, protected
Requires at least: 5.7
Tested up to: 6.9
Stable tag: 1.1.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Basic Authentication for protected your development WordPress site like .htpasswd

== Description ==

WP Basic Authentication is a plugin for protected your development WordPress site like .htpasswd and support Docker and Kubernetes (K8s)

Features:

* Easy for setting Basic Authentication
* Basic Authentication works like .htpasswd
* Protected development website or demo website without .htpasswd
* Support Docker and Kubernetes (K8s)
* The plugin is lightweight.

== Installation ==
1. Upload `wp-basic-authentication.zip` to the install plugin page
1. Activate the plugin through the 'Plugins' menu in WordPress
1. Go to *Authentication* in the left-hand menu to start setting the plugin

== Frequently Asked Questions ==

= How to setting the plugin? =

Go to *Authentication* in the left-hand menu to start setting the plugin

= How to disable Authentication if I forgot the credentials =

Rename or delete 'wp-basic-authentication' directory in plugins directory via FTP or commend line

= Password Migration (v1.1.0+) =

If you're updating from a version older than 1.1.0, your existing password will be automatically migrated to the new secure hashed format. You don't need to change your password - the plugin will handle this automatically and show you a success message in the WordPress admin.

== Screenshots ==

1. Basic Authentication dialog
1. Screenshot of the menu page for Featured Posts Setting page.

== Changelog ==

= 1.1.0 =
* Hash password with automatic migration from older versions
* Automatic password migration for users updating from pre-1.1.0 versions
* Tested up to WordPress 6.9

= 1.0.3 =
* Tested up to WordPress 6.5
* Separate protected frontend and wp-login page

= 1.0.2 =
* Tested up to WordPress 6.1.1
* Add Tip me on ko-fi

= 1.0.1 =
* Tested up to WordPress 5.8.1

= 1.0.0 =
* Initial Release
