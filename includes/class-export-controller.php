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
			fputs( $handle, "\xEF\xBB\xBF" );

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

			fclose( $handle );
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
	 * @param array $data Normalized report from Environment_Report::get_report().
	 * @return string Full plain-text report.
	 */
	public function build_txt_output( array $data ) {
		$sep = str_repeat( '=', 45 );
		$nl  = PHP_EOL;

		$env = $data['environment'];
		$php = $data['php'];
		$srv = $data['server'];

		$out  = $sep . $nl;
		$out .= 'SITE INFO SCOUT -- SITE REPORT' . $nl;
		$out .= 'Generated : ' . $data['generated_at'] . $nl;
		$out .= $sep . $nl . $nl;

		// ── Environment ────────────────────────────────────────────────────
		$out .= '-- ENVIRONMENT --' . $nl;
		$out .= $this->txt_row( 'WordPress Version',   $env['wp_version'] );
		$out .= $this->txt_row( 'PHP Version',         $php['version'] );
		$out .= $this->txt_row( 'PHP Architecture',    $php['architecture'] );
		$out .= $this->txt_row( 'Site URL',            $env['wp_site_url'] );
		$out .= $this->txt_row( 'WordPress URL',       $env['wp_home_url'] );
		$out .= $this->txt_row( 'Multisite',           $env['is_multisite'] ? 'Yes' : 'No' );
		$out .= $this->txt_row( 'WP_DEBUG',            $env['wp_debug'] ? 'Enabled (WARNING)' : 'Disabled' );
		$out .= $this->txt_row( 'WP_DEBUG_LOG',        $env['wp_debug_log'] ? 'Enabled' : 'Disabled' );
		$out .= $this->txt_row( 'SCRIPT_DEBUG',        $env['script_debug'] ? 'Enabled' : 'Disabled' );
		$out .= $this->txt_row( 'DISABLE_WP_CRON',     $env['wp_cron_disabled'] ? 'Enabled' : 'Disabled' );
		$out .= $this->txt_row( 'WP Memory Limit',     $env['wp_memory_limit'] );
		$out .= $this->txt_row( 'WP Max Memory Limit', $env['wp_max_memory_limit'] );
		$out .= $this->txt_row( 'PHP Memory Limit',    $php['memory_limit'] );
		$out .= $this->txt_row( 'PHP Max Execution',   $php['max_execution'] . 's' );
		$out .= $this->txt_row( 'PHP Max Input Vars',  $php['max_input_vars'] );
		$out .= $this->txt_row( 'PHP Post Max Size',   $php['post_max_size'] );
		$out .= $this->txt_row( 'PHP Upload Max Size', $php['upload_max_size'] );
		$out .= $this->txt_row( 'PHP Display Errors',  $php['display_errors'] );
		$out .= $this->txt_row( 'Server Software',     $srv['software'] );
		$out .= $this->txt_row( 'PHP SAPI',            $srv['php_sapi'] );
		$out .= $nl;

		// ── Active theme ───────────────────────────────────────────────────
		$out .= '-- ACTIVE THEME --' . $nl;
		$out .= $this->txt_row( 'Name',         $data['theme']['name'] );
		$out .= $this->txt_row( 'Version',      $data['theme']['version'] );
		$out .= $this->txt_row( 'Slug',         $data['theme']['template'] );
		$parent_str = null !== $data['theme']['parent_name']
			? $data['theme']['parent_name'] . ' v' . $data['theme']['parent_version']
			: 'None';
		$out .= $this->txt_row( 'Parent Theme', $parent_str );
		$out .= $nl;

		// ── Active plugins ─────────────────────────────────────────────────
		$plugin_count = count( $data['plugins'] );
		$out .= '-- ACTIVE PLUGINS (' . $plugin_count . ') --' . $nl;
		$i = 1;
		foreach ( $data['plugins'] as $plugin ) {
			$out .= $i . '. ' . $plugin['name'] . ' -- v' . $plugin['version'] . $nl;
			$i++;
		}
		$out .= $nl;

		$out .= $sep . $nl;
		$out .= 'Generated by Site Info Scout (Zignites)' . $nl;
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
