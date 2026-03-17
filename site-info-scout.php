<?php
/**
 * Plugin Name:       Site Info Scout
 * Plugin URI:        https://wordpress.org/plugins/site-info-scout/
 * Description:       Generate a support-ready site report with environment details, active plugins, theme info, and diagnostic flags.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Zignites
 * Author URI:        https://zignites.com/
 * Text Domain:       site-info-scout
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package Zignites\SiteInfoScout
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ── Constants ──────────────────────────────────────────────────────────────

/** Plugin version. */
define( 'ZIGSITEINFOSCOUT_VERSION', '1.0.0' );

/** Absolute path to the plugin directory (with trailing slash). */
define( 'ZIGSITEINFOSCOUT_DIR', plugin_dir_path( __FILE__ ) );

/** Public URL to the plugin directory (with trailing slash). */
define( 'ZIGSITEINFOSCOUT_URL', plugin_dir_url( __FILE__ ) );

/** Plugin basename, e.g. site-info-scout/site-info-scout.php. */
define( 'ZIGSITEINFOSCOUT_BASENAME', plugin_basename( __FILE__ ) );

/** Admin menu page slug. */
define( 'ZIGSITEINFOSCOUT_MENU_SLUG', 'site-info-scout' );

/**
 * Threshold for the "high plugin count" health flag.
 * Sites with this many or more active plugins receive a warning.
 */
define( 'ZIGSITEINFOSCOUT_HIGH_PLUGIN_THRESHOLD', 20 );

// ── Load files ─────────────────────────────────────────────────────────────

require_once ZIGSITEINFOSCOUT_DIR . 'includes/helpers.php';
require_once ZIGSITEINFOSCOUT_DIR . 'includes/class-environment-report.php';
require_once ZIGSITEINFOSCOUT_DIR . 'includes/class-health-checks.php';
require_once ZIGSITEINFOSCOUT_DIR . 'includes/class-export-controller.php';
require_once ZIGSITEINFOSCOUT_DIR . 'includes/class-admin-page.php';
require_once ZIGSITEINFOSCOUT_DIR . 'includes/class-plugin.php';

// ── Bootstrap ──────────────────────────────────────────────────────────────

add_action( 'plugins_loaded', array( 'Zignites\\SiteInfoScout\\Plugin', 'init' ) );
