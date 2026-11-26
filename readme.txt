=== Central Connect ===
Contributors: inmotionhosting, imh_brad, joemoto, rramo012, timph, jamesros161
Tags: inmotion, connect, manage
Requires at least: 5.0
Tested up to: 7.2
Requires PHP: 7.4
Stable tag: 2.1.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Connects users and sites with InMotion Central.

== Description ==

Manage all your WordPress websites in a single dashboard. Central
allows you to manage themes, plugins & backups all from within a single interface.
Using our single sign in feature, you can log into central and access any of your connected websites.

== Installation ==

= Minimum Requirements =
* PHP 7.4 or higher
* WordPress 5.0 or higher

= Manually =
1. Upload the entire central-connect folder to the /wp-content/plugins/ directory.
1. Activate the plugin through the Plugins menu in WordPress.

== Changelog ==

= 2.1.0 =

Release Date: November 26th, 2026

* Security: The Central environment-token validation used by `/bgc/v1/auth/` and `wp-login.php` no longer contacts Central for sites that have no API key, for malformed tokens or environment ids, or once a per-client / per-site rate limit is reached. The stored environment id is preferred over the caller-supplied value.
* Security: Removed the 3 second delay on failed token validation; failures are rate limited instead.
* Security: `/bgc/v1/auth/` only issues access tokens for administrator accounts and no longer falls back to the default administrator when an unknown `user_id` is supplied.
* Security: `/bgc/v1/plugins/` only accepts `files` entries that are installed plugin basenames.
* Security: Access tokens presented through the `X-BGC-Auth` header or the `wp-login.php` `has_access_token` flow are only accepted for users who can manage options.
* Update: Requires PHP 7.4 or higher. The plugin header, readme and the activation check now agree (previously PHP 5.4 in the readme and 5.6 at runtime).
* Update: Tested up to WordPress 7.2.
* Bug Fix: PHP 8.6 compatibility: the filesystem helper constructor no longer returns a value, and API key checks pass explicit `trim()` characters.
* Bug Fix: The minimum requirements notice displayed raw `<code>` markup.
* Update: Addressed WordPress Plugin Check findings: direct file access guards, removed the `Domain Path` header for a folder that does not exist, and prefixed hook names.
* Update: The `bgc_remote_validate` action is deprecated in favor of `central_connect_remote_validate`; existing listeners still run and receive a deprecation notice. The version check's fallback init action is now `central_connect_init`.
* Update: Added a PHPUnit (WordPress test library) suite.
* Update: Added PHPCS (WordPress Coding Standards and PHPCompatibility), Semgrep and Conftest checks, GitHub Actions CI for PHP 7.4 through 8.6, and a WordPress.org deploy script.
* Update: Backfilled the missing 2.0.2 changelog entry. Version 2.0.7 was never released; its changes ship in 2.1.0.

= 2.0.6 =

Release Date: June 17th, 2026

* Bug Fix: Fixed sanitization of nonce to use `sanitize_key()` instead of `sanitize_text_field()`.
* Bug Fix: Set an allowlist for options that can be changed
* Bug Fix: Fixed the inverted nonce check.
* Bug Fix: Added explicit `permission_callback` for auth route.
* Update: Use `wp_die()` for better error output in dashboard when nonce fails.

= 2.0.5 =

Release Date: March 4th, 2024

* Bug Fix: Fixing PHP errors for various PHP version old and new.

= 2.0.4 =

Release Date: January 12th, 2024

* Update: Removing submenu and connect notice. 

= 2.0.3 =

Release Date: April 4th, 2023

* Bug Fix: Resolve correct class names for repo changes. 

= 2.0.2 =

Release Date: January 9th, 2023

* Update: Updated plugin for wp.org submission.
* Update: First WordPress.org release as Central Connect (formerly BoldGrid Connect).

= 2.0.1 =

Release Date: June 28th, 2022

* Bug Fix: Fixes Central API server not being filtered correctly. 

= 2.0.0 =

Release Date: Not published on WordPress.org

* Feature:  Single signin through Central.
* Feature:  New rest endpoints.

= 1.0.2 =

Release Date: October 15th, 2019

* Update:  Updated dependency boldgrid/library to 2.10.4.

= 1.0.1 =

Release Date: September 11th, 2019

* Bug fix: Fixed conflict between tgmpa plugin installer and the custom update classes.
* Bug fix: Added composer hook for library build.
* Update:  Updated dependencies.

= 1.0.0 =

Release Date: August 13th, 2018

* Initial release.
