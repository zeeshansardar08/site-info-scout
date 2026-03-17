<?php
/**
 * Plugin orchestrator class for Site Info Scout.
 *
 * @package Zignites\SiteInfoScout
 */

namespace Zignites\SiteInfoScout;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Core plugin class.
 *
 * Wires all class dependencies, registers WordPress hooks, and manages
 * the plugin lifecycle via a singleton. Instantiated on plugins_loaded.
 */
class Plugin {

	/**
	 * @var Plugin|null Singleton instance.
	 */
	private static $instance = null;

	/**
	 * @var Admin_Page
	 */
	private $admin_page;

	/**
	 * @var Environment_Report
	 */
	private $environment_report;

	/**
	 * @var Health_Checks
	 */
	private $health_checks;

	/**
	 * @var Export_Controller
	 */
	private $export_controller;

	/**
	 * Private constructor — use Plugin::init() to get the instance.
	 */
	private function __construct() {
		$this->environment_report = new Environment_Report();
		$this->health_checks      = new Health_Checks();
		$this->export_controller  = new Export_Controller( $this->environment_report );
		$this->admin_page         = new Admin_Page(
			$this->environment_report,
			$this->health_checks,
			$this->export_controller
		);

		$this->register_hooks();
	}

	// ── Lifecycle ──────────────────────────────────────────────────────────

	/**
	 * Initializes the plugin singleton. Called on plugins_loaded.
	 *
	 * @return Plugin
	 */
	public static function init() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Returns the singleton instance (null before plugins_loaded fires).
	 *
	 * @return Plugin|null
	 */
	public static function get_instance() {
		return self::$instance;
	}

	// ── Hook registration ──────────────────────────────────────────────────

	/**
	 * Registers all WordPress action hooks for the plugin.
	 */
	private function register_hooks() {
		add_action( 'init',                 array( $this, 'load_textdomain' ) );
		add_action( 'admin_menu',           array( $this, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );

		// Export handlers — admin_post_{action} fires on admin-post.php.
		add_action(
			'admin_post_zigsiteinfoscout_export_txt',
			array( $this->export_controller, 'handle_txt_export' )
		);
		add_action(
			'admin_post_zigsiteinfoscout_export_csv',
			array( $this->export_controller, 'handle_csv_export' )
		);
	}

	// ── Public hook callbacks ──────────────────────────────────────────────

	/**
	 * Loads the plugin text domain for translations.
	 *
	 * The path is relative to WP_PLUGIN_DIR, as required by
	 * load_plugin_textdomain().
	 */
	public function load_textdomain() {
		load_plugin_textdomain(
			'site-info-scout',
			false,
			dirname( ZIGSITEINFOSCOUT_BASENAME ) . '/languages'
		);
	}

	/**
	 * Registers the admin Tools menu page.
	 *
	 * The returned hook suffix is stored and used to scope asset enqueuing.
	 */
	public function register_menu() {
		add_management_page(
			__( 'Site Info Scout', 'site-info-scout' ),
			__( 'Site Info Scout', 'site-info-scout' ),
			'manage_options',
			ZIGSITEINFOSCOUT_MENU_SLUG,
			array( $this->admin_page, 'render' )
		);
	}

	/**
	 * Enqueues admin CSS and JS, scoped exclusively to the plugin screen.
	 *
	 * The hook suffix for a Tools sub-page follows the pattern:
	 * tools_page_{$menu_slug}, e.g. tools_page_site-info-scout.
	 *
	 * @param string $hook_suffix The current admin page hook suffix.
	 */
	public function enqueue_assets( $hook_suffix ) {
		if ( 'tools_page_' . ZIGSITEINFOSCOUT_MENU_SLUG !== $hook_suffix ) {
			return;
		}

		wp_enqueue_style(
			'zigsiteinfoscout-admin',
			ZIGSITEINFOSCOUT_URL . 'assets/css/admin.css',
			array(),
			ZIGSITEINFOSCOUT_VERSION
		);

		// Load JS in the footer so DOM is ready and wp_localize_script()
		// called from Admin_Page::render() is included before printing.
		wp_enqueue_script(
			'zigsiteinfoscout-admin',
			ZIGSITEINFOSCOUT_URL . 'assets/js/admin.js',
			array(),
			ZIGSITEINFOSCOUT_VERSION,
			true
		);
	}
}
