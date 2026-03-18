<?php
/**
 * Export Controller class for Site Info Scout.
 *
 * @package Zignites\SiteInfoScout
 */

namespace Zignites\SiteInfoScout;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles TXT and CSV report downloads via admin-post.php.
 *
 * Security model for both handlers:
 *   1. current_user_can( 'manage_options' ) — capability check (always first).
 *   2. check_admin_referer()                — nonce verification (always second).
 *   3. nocache_headers()                    — prevent response caching.
 *   4. Output headers + body + exit.
 *
 * Nothing is written to the server filesystem; output goes to php://output.
 */
class Export_Controller {

	/**
	 * @var Environment_Report
	 */
	private $report;

	/**
	 * @param Environment_Report $report Environment report instance.
	 */
	public function __construct( Environment_Report $report ) {
		$this->report = $report;
	}

	// ── Public export handlers ─────────────────────────────────────────────

	/**
	 * Handles plain-text report download.
	 *
	 * Hooked to: admin_post_zigsiteinfoscout_export_txt
	 *
	 * @since 1.0.0
	 */
	public function handle_txt_export() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die(
				esc_html__( 'You do not have permission to export reports.', 'site-info-scout' ),
				'',
				array( 'response' => 403 )
			);
		}

		check_admin_referer( 'zigsiteinfoscout_export_txt' );

		$data   = $this->report->get_report();
		$output = $this->build_txt_output( $data );

		nocache_headers();
		header( 'Content-Type: text/plain; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="site-info-scout-' . gmdate( 'Y-m-d' ) . '.txt"' );
		header( 'Content-Length: ' . strlen( $output ) );

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Plain-text file download, not rendered as HTML.
		echo $output;
		exit;
	}

	/**
	 * Handles CSV plugin inventory download.
	 *
	 * Hooked to: admin_post_zigsiteinfoscout_export_csv
	 *
	 * @since 1.0.0
	 */
	public function handle_csv_export() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die(
				esc_html__( 'You do not have permission to export reports.', 'site-info-scout' ),
				'',
				array( 'response' => 403 )
			);
		}

		check_admin_referer( 'zigsiteinfoscout_export_csv' );

		$data = $this->report->get_report();

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="site-info-scout-plugins-' . gmdate( 'Y-m-d' ) . '.csv"' );

		$handle = fopen( 'php://output', 'w' );

		if ( $handle ) {
			// UTF-8 BOM — required for correct character rendering in Excel on Windows.
			fputs( $handle, "\xEF\xBB\xBF" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fputs -- Writing to php://output stream for download; WP_Filesystem does not support streaming to output.

			// Header row.
			fputcsv( $handle, array( 'Type', 'Name', 'Version', 'File / Slug' ) );

			// One row per active plugin.
			foreach ( $data['plugins'] as $plugin ) {
				fputcsv( $handle, array( 'Plugin', $plugin['name'], $plugin['version'], $plugin['file'] ) );
			}

			// Active theme row.
			fputcsv( $handle, array(
				'Theme',
				$data['theme']['name'],
				$data['theme']['version'],
				$data['theme']['template'],
			) );

			// Parent theme row (only when a parent is present).
			if ( null !== $data['theme']['parent_name'] ) {
				fputcsv( $handle, array(
					'Parent Theme',
					$data['theme']['parent_name'],
					$data['theme']['parent_version'],
					'',
				) );
			}

			fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Closing php://output stream handle; WP_Filesystem does not support streaming to output.
		}

		exit;
	}

	// ── Report builder ─────────────────────────────────────────────────────

	/**
	 * Builds the formatted plain-text report string.
	 *
	 * This method is public so Admin_Page can call it to supply the
	 * clipboard copy text without duplicating the formatting logic.
	 *
	 * @since 1.0.0
	 * @param array $data Normalized report from Environment_Report::get_report().
	 * @return string Full plain-text report.
	 */
	public function build_txt_output( array $data ) {
		$sep = str_repeat( '=', 45 );
		$nl  = PHP_EOL;

		$env      = $data['environment'];
		$php      = $data['php'];
		$srv      = $data['server'];
		$timezone = wp_timezone_string();

		$out  = $sep . $nl;
		$out .= __( 'SITE INFO SCOUT -- SITE REPORT', 'site-info-scout' ) . $nl;
		/* translators: 1: Date/time string. 2: Timezone identifier e.g. America/New_York. */
		$out .= sprintf( __( 'Generated  : %1$s (%2$s)', 'site-info-scout' ), $data['generated_at'], $timezone ) . $nl;
		$out .= $sep . $nl . $nl;

		// ── Environment ────────────────────────────────────────────────────
		$out .= __( '-- ENVIRONMENT --', 'site-info-scout' ) . $nl;
		$out .= $this->txt_row( __( 'WordPress Version',   'site-info-scout' ), $env['wp_version'] );
		$out .= $this->txt_row( __( 'PHP Version',         'site-info-scout' ), $php['version'] );
		$out .= $this->txt_row( __( 'PHP Architecture',    'site-info-scout' ), $php['architecture'] );
		$out .= $this->txt_row( __( 'Site URL',            'site-info-scout' ), $env['wp_site_url'] );
		$out .= $this->txt_row( __( 'WordPress URL',       'site-info-scout' ), $env['wp_home_url'] );
		$out .= $this->txt_row( __( 'Multisite',           'site-info-scout' ), $env['is_multisite'] ? __( 'Yes', 'site-info-scout' ) : __( 'No', 'site-info-scout' ) );
		$out .= $this->txt_row( __( 'WP_DEBUG',            'site-info-scout' ), $env['wp_debug'] ? __( 'Enabled (WARNING)', 'site-info-scout' ) : __( 'Disabled', 'site-info-scout' ) );
		$out .= $this->txt_row( __( 'WP_DEBUG_LOG',        'site-info-scout' ), $env['wp_debug_log'] ? __( 'Enabled', 'site-info-scout' ) : __( 'Disabled', 'site-info-scout' ) );
		$out .= $this->txt_row( __( 'SCRIPT_DEBUG',        'site-info-scout' ), $env['script_debug'] ? __( 'Enabled', 'site-info-scout' ) : __( 'Disabled', 'site-info-scout' ) );
		$out .= $this->txt_row( __( 'DISABLE_WP_CRON',     'site-info-scout' ), $env['wp_cron_disabled'] ? __( 'Enabled', 'site-info-scout' ) : __( 'Disabled', 'site-info-scout' ) );
		$out .= $this->txt_row( __( 'WP Memory Limit',     'site-info-scout' ), $env['wp_memory_limit'] );
		$out .= $this->txt_row( __( 'WP Max Memory Limit', 'site-info-scout' ), $env['wp_max_memory_limit'] );
		$out .= $this->txt_row( __( 'PHP Memory Limit',    'site-info-scout' ), $php['memory_limit'] );
		$out .= $this->txt_row( __( 'PHP Max Execution',   'site-info-scout' ), $php['max_execution'] . 's' );
		$out .= $this->txt_row( __( 'PHP Max Input Vars',  'site-info-scout' ), $php['max_input_vars'] );
		$out .= $this->txt_row( __( 'PHP Post Max Size',   'site-info-scout' ), $php['post_max_size'] );
		$out .= $this->txt_row( __( 'PHP Upload Max Size', 'site-info-scout' ), $php['upload_max_size'] );
		$out .= $this->txt_row( __( 'PHP Display Errors',  'site-info-scout' ), $php['display_errors'] );
		$out .= $this->txt_row( __( 'Server Software',     'site-info-scout' ), $srv['software'] );
		$out .= $this->txt_row( __( 'PHP SAPI',            'site-info-scout' ), $srv['php_sapi'] );
		$out .= $nl;

		// ── Active theme ───────────────────────────────────────────────────
		$out .= __( '-- ACTIVE THEME --', 'site-info-scout' ) . $nl;
		$out .= $this->txt_row( __( 'Name',         'site-info-scout' ), $data['theme']['name'] );
		$out .= $this->txt_row( __( 'Version',      'site-info-scout' ), $data['theme']['version'] );
		$out .= $this->txt_row( __( 'Slug',         'site-info-scout' ), $data['theme']['template'] );
		$parent_str = null !== $data['theme']['parent_name']
			? $data['theme']['parent_name'] . ' v' . $data['theme']['parent_version']
			: __( 'None', 'site-info-scout' );
		$out .= $this->txt_row( __( 'Parent Theme', 'site-info-scout' ), $parent_str );
		$out .= $nl;

		// ── Active plugins ─────────────────────────────────────────────────
		$plugin_count = count( $data['plugins'] );
		/* translators: %d: Number of active plugins. */
		$out .= sprintf( __( '-- ACTIVE PLUGINS (%d) --', 'site-info-scout' ), $plugin_count ) . $nl;
		$i = 1;
		foreach ( $data['plugins'] as $plugin ) {
			$out .= $i . '. ' . $plugin['name'] . ' -- v' . $plugin['version'] . $nl;
			$i++;
		}
		$out .= $nl;

		$out .= $sep . $nl;
		$out .= __( 'Generated by Site Info Scout (Zignites)', 'site-info-scout' ) . $nl;
		$out .= $sep . $nl;

		return $out;
	}

	// ── Private helpers ────────────────────────────────────────────────────

	/**
	 * Formats a single label-value row for plain-text output.
	 *
	 * @param string $label Row label.
	 * @param mixed  $value Row value.
	 * @return string
	 */
	private function txt_row( $label, $value ) {
		return sprintf( '%-25s: %s', $label, (string) $value ) . PHP_EOL;
	}
}
