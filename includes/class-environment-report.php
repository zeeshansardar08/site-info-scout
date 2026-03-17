<?php
/**
 * Environment Report class for Site Info Scout.
 *
 * @package Zignites\SiteInfoScout
 */

namespace Zignites\SiteInfoScout;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Collects a normalized, read-only snapshot of the site environment.
 *
 * All methods are side-effect free and make no changes to the database,
 * filesystem, or any WordPress settings. Sensitive values such as database
 * credentials, auth keys, and salts are explicitly excluded.
 */
class Environment_Report {

	/**
	 * Returns the full normalized report array.
	 *
	 * @return array {
	 *     @type string $generated_at  MySQL-format timestamp using the site's local time.
	 *     @type array  $environment   WordPress-level environment data.
	 *     @type array  $php           PHP configuration data.
	 *     @type array  $server        Server software data.
	 *     @type array  $plugins       Active plugins list.
	 *     @type array  $theme         Active theme details.
	 * }
	 */
	public function get_report() {
		return array(
			'generated_at' => current_time( 'mysql' ),
			'environment'  => $this->get_wp_data(),
			'php'          => $this->get_php_data(),
			'server'       => $this->get_server_data(),
			'plugins'      => $this->get_plugins_data(),
			'theme'        => $this->get_theme_data(),
		);
	}

	// ── Private collection methods ─────────────────────────────────────────

	/**
	 * Collects WordPress environment data.
	 *
	 * NOTE: ABSPATH, WP_CONTENT_DIR, DB_*, AUTH_* constants and salts are
	 * deliberately excluded from this report.
	 *
	 * @return array
	 */
	private function get_wp_data() {
		return array(
			'wp_version'          => get_bloginfo( 'version' ),
			'wp_site_url'         => get_bloginfo( 'url' ),
			'wp_home_url'         => get_bloginfo( 'wpurl' ),
			'is_multisite'        => is_multisite(),
			'wp_debug'            => defined( 'WP_DEBUG' ) && WP_DEBUG,
			'wp_debug_log'        => defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG,
			'script_debug'        => defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG,
			'wp_cron_disabled'    => defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON,
			'wp_memory_limit'     => defined( 'WP_MEMORY_LIMIT' ) ? WP_MEMORY_LIMIT : ini_get( 'memory_limit' ),
			'wp_max_memory_limit' => defined( 'WP_MAX_MEMORY_LIMIT' ) ? WP_MAX_MEMORY_LIMIT : 'N/A',
			// Directory name only — never the full filesystem path.
			'wp_content_dir'      => 'wp-content',
		);
	}

	/**
	 * Collects PHP INI and runtime configuration data.
	 *
	 * @return array
	 */
	private function get_php_data() {
		return array(
			'version'         => phpversion(),
			'memory_limit'    => ini_get( 'memory_limit' ),
			'max_execution'   => ini_get( 'max_execution_time' ),
			'max_input_vars'  => ini_get( 'max_input_vars' ),
			'post_max_size'   => ini_get( 'post_max_size' ),
			'upload_max_size' => ini_get( 'upload_max_filesize' ),
			'display_errors'  => ini_get( 'display_errors' ),
			// PHP_INT_SIZE is 8 bytes on 64-bit platforms, 4 bytes on 32-bit.
			'architecture'    => ( 8 === PHP_INT_SIZE ) ? '64-bit' : '32-bit',
		);
	}

	/**
	 * Collects server software data.
	 *
	 * SERVER_SOFTWARE is sanitized because it originates from the server
	 * and is not under WordPress control.
	 *
	 * @return array
	 */
	private function get_server_data() {
		$software = isset( $_SERVER['SERVER_SOFTWARE'] )
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated -- isset() check above validates presence.
			? sanitize_text_field( wp_unslash( $_SERVER['SERVER_SOFTWARE'] ) )
			: 'N/A';

		return array(
			'software' => $software,
			'php_sapi'  => php_sapi_name(),
		);
	}

	/**
	 * Collects the list of active plugins with name and version.
	 *
	 * Uses get_plugin_data() which reads plugin file headers; the function
	 * is loaded explicitly if not already available (e.g. on admin-post.php).
	 *
	 * @return array[] Each entry: { name, version, file, active }.
	 */
	private function get_plugins_data() {
		if ( ! function_exists( 'get_plugin_data' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$active_plugins = get_option( 'active_plugins', array() );
		$plugins        = array();

		foreach ( $active_plugins as $plugin_file ) {
			$plugin_path = WP_PLUGIN_DIR . '/' . $plugin_file;

			if ( ! file_exists( $plugin_path ) ) {
				continue;
			}

			// false, false = skip markup, skip translations (performance).
			$data      = get_plugin_data( $plugin_path, false, false );
			$plugins[] = array(
				'name'    => $data['Name'],
				'version' => $data['Version'],
				'file'    => $plugin_file,
				'active'  => true,
			);
		}

		return $plugins;
	}

	/**
	 * Collects active theme data.
	 *
	 * Only the theme slug (get_stylesheet()) is recorded, never the full
	 * filesystem path, to avoid exposing server directory structure.
	 *
	 * @return array { name, version, template, parent_name, parent_version }.
	 */
	private function get_theme_data() {
		$theme  = wp_get_theme();
		$parent = $theme->parent();

		return array(
			'name'           => $theme->get( 'Name' ),
			'version'        => $theme->get( 'Version' ),
			'template'       => get_stylesheet(),
			'parent_name'    => $parent ? $parent->get( 'Name' ) : null,
			'parent_version' => $parent ? $parent->get( 'Version' ) : null,
		);
	}
}
