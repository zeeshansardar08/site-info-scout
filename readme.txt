=== Site Info Scout ===
Contributors: zignites
Tags: diagnostics, site info, support, environment, debug
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Generate a support-ready site report with environment details, active plugins, theme info, and diagnostic flags.

== Description ==

Site Info Scout is a lightweight, admin-only diagnostic plugin for WordPress. It generates a clean, support-ready snapshot of your site environment in seconds — without changing your site's front-end behavior or running risky operations.

**What it shows you:**

* WordPress version, PHP version, and key server details
* PHP configuration values: memory limit, max execution time, upload limits
* Active plugins with names and version numbers
* Active theme and parent theme information
* Debug constants and cron status
* Health flags for common support issues

**Health flags include:**

* PHP version below the recommended minimum
* WP_DEBUG enabled on a production site
* WP_DEBUG_LOG active (log file may contain sensitive data)
* DISABLE_WP_CRON enabled without a server-side cron configured
* Unusually high number of active plugins
* Low PHP memory limit

**Export options:**

* Download a plain-text (.txt) report for email or ticket submission
* Export a CSV inventory of active plugins and theme for spreadsheet use
* Copy the full report to clipboard with one click (HTTPS and HTTP fallback)

**What it never does:**

* Makes remote requests or sends data to external servers
* Modifies your database, files, or site settings
* Runs on the front end or for non-administrator users
* Exposes database credentials, authentication keys, salts, or other secrets

Site Info Scout is designed to make support and troubleshooting faster for administrators, developers, and support engineers who need a reliable, standardized site snapshot.

== Installation ==

1. Upload the `site-info-scout` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin from the WordPress Plugins screen.
3. Go to **Tools → Site Info Scout** to view your site report.

No configuration is required. The report is generated on demand when you visit the plugin screen.

== Frequently Asked Questions ==

= Will this plugin affect my site's performance? =

No. The report is only generated when you visit the Site Info Scout admin page or trigger an export action. It does not run on the front end, does not add queries to regular page loads, and does not use background tasks or scheduled cron jobs.

= Is any data sent to external servers? =

No. Site Info Scout makes no remote requests of any kind. All data is collected locally and displayed within your WordPress admin area only.

= Can my site visitors see this information? =

No. The plugin is accessible only to WordPress administrators with the `manage_options` capability. There is no front-end output of any kind.

= What sensitive information is excluded from the report? =

The report never includes database credentials, WordPress secret keys and salts, authentication cookies, API tokens, full filesystem paths, error log file contents, or any personally identifiable user information.

= Is it safe to share the generated report with a support team? =

Yes, with normal care. The TXT and CSV exports are designed for support use and exclude all sensitive values. Review the report before sharing and remove any values you prefer to keep private.

= Does this plugin work on WordPress Multisite? =

Yes. The plugin installs and operates on individual sites within a Multisite network. The admin screen is accessible per-site under Tools → Site Info Scout when the plugin is active for that site.

= How do I trigger the copy-to-clipboard feature on an HTTP site? =

The copy button uses a textarea fallback automatically when the Clipboard API is not available (which requires HTTPS). The fallback works in all modern browsers on HTTP as well.

== Screenshots ==

1. Main Site Info Scout dashboard showing the environment summary, health flags, and export actions.
2. Active plugins inventory table listing all active plugins with name, version, and file path.

== Changelog ==

= 1.0.0 =
* Initial release.
* Environment summary: WordPress version, PHP version, site URL, multisite status, memory limits, debug constants, cron disabled status, and server software.
* Active plugins inventory with name and version for all active plugins.
* Active theme details including parent theme detection.
* Health flags: PHP version, WP_DEBUG, WP_DEBUG_LOG, DISABLE_WP_CRON, high plugin count, and low memory limit.
* Copy report to clipboard with Clipboard API and execCommand fallback.
* TXT plain-text report download.
* CSV plugin and theme inventory export with UTF-8 BOM for correct Excel rendering.
* Admin-only access enforced via manage_options capability.
* Read-only — no writes to database, filesystem, or site settings.
* Zero remote requests.

== Upgrade Notice ==

= 1.0.0 =
Initial release.
