<?php
/**
 * Admin Page class for Site Info Scout.
 *
 * @package Zignites\SiteInfoScout
 */

namespace Zignites\SiteInfoScout;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders the Site Info Scout admin screen.
 *
 * This class is presentation-only. All data collection is delegated to
 * Environment_Report and Health_Checks. All output is escaped at the
 * template layer. No data collection or side effects occur here.
 */
class Admin_Page {

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
	 * @param Environment_Report $environment_report Environment report instance.
	 * @param Health_Checks      $health_checks       Health checks instance.
	 * @param Export_Controller  $export_controller   Export controller instance.
	 */
	public function __construct(
		Environment_Report $environment_report,
		Health_Checks $health_checks,
		Export_Controller $export_controller
	) {
		$this->environment_report = $environment_report;
		$this->health_checks      = $health_checks;
		$this->export_controller  = $export_controller;
	}

	// ── Public render entry point ──────────────────────────────────────────

	/**
	 * Renders the full admin page. Registered as the menu page callback.
	 *
	 * @since 1.0.0
	 */
	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'site-info-scout' ) );
		}

		$report          = $this->environment_report->get_report();
		$flags           = $this->health_checks->evaluate( $report );
		$score           = $this->health_checks->get_health_score( $report );
		$insights        = $this->health_checks->get_insights( $report );
		$plain_text      = $this->export_controller->build_txt_output( $report );
		$support_summary = $this->export_controller->build_support_summary_output( $report, $flags, $insights );

		// Supply the report text and i18n strings to admin.js.
		wp_localize_script(
			'zigsiteinfoscout-admin',
			'zigsiteinfoscoutData',
			array(
				'report'         => $plain_text,
				'supportSummary' => $support_summary,
				'i18n'           => array(
					'copied'          => __( 'Report copied to clipboard!', 'site-info-scout' ),
					'copyFailed'      => __( 'Copy failed. Please use the Download TXT button instead.', 'site-info-scout' ),
					'summaryCopied'   => __( 'Support summary copied to clipboard!', 'site-info-scout' ),
					'summaryCopyFail' => __( 'Copy failed. Please try again.', 'site-info-scout' ),
				),
			)
		);

		// Build a human-friendly display timestamp using the site's date/time
		// settings and timezone (wp_date() automatically applies WP timezone).
		$ts_unix           = strtotime( $report['generated_at'] );
		$generated_display = sprintf(
			/* translators: 1: Formatted date. 2: Formatted time. 3: Timezone string e.g. UTC or America/New_York. */
			__( '%1$s at %2$s (%3$s)', 'site-info-scout' ),
			wp_date( get_option( 'date_format', 'F j, Y' ), $ts_unix ),
			wp_date( get_option( 'time_format', 'g:i a' ), $ts_unix ),
			wp_timezone_string()
		);

		// Generate nonce-protected export URLs.
		$txt_url = wp_nonce_url(
			admin_url( 'admin-post.php?action=zigsiteinfoscout_export_txt' ),
			'zigsiteinfoscout_export_txt'
		);
		$csv_url = wp_nonce_url(
			admin_url( 'admin-post.php?action=zigsiteinfoscout_export_csv' ),
			'zigsiteinfoscout_export_csv'
		);

		?>
		<div class="wrap zigsiteinfoscout-wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<?php $this->render_health_score( $score ); ?>
			<p class="zigsiteinfoscout-tagline">
				<?php esc_html_e( 'A read-only support snapshot of this WordPress site. No data is sent externally.', 'site-info-scout' ); ?>
				&mdash;
				<span class="zigsiteinfoscout-generated">
					<?php printf(
						/* translators: %s: Human-friendly date, time, and timezone string. */
						esc_html__( 'Report generated: %s', 'site-info-scout' ),
						esc_html( $generated_display )
					); ?>
				</span>
			</p>

			<?php $this->render_health_flags( $flags ); ?>
			<?php $this->render_insights( $insights ); ?>

			<div class="zigsiteinfoscout-grid">
				<?php $this->render_environment_card( $report ); ?>
				<?php $this->render_theme_card( $report ); ?>
			</div>

			<?php $this->render_plugins_table( $report ); ?>
			<?php $this->render_export_bar( $txt_url, $csv_url ); ?>
		</div>
		<?php
	}

	// ── Private render sections ────────────────────────────────────────────

	/**
	 * Renders the site health score badge.
	 *
	 * @param int $score Calculated score from Health_Checks::get_health_score().
	 */
	private function render_health_score( $score ) {
		if ( $score >= 80 ) {
			$score_class = 'zigsiteinfoscout-score--good';
			$score_label = __( 'Good', 'site-info-scout' );
		} elseif ( $score >= 50 ) {
			$score_class = 'zigsiteinfoscout-score--fair';
			$score_label = __( 'Fair', 'site-info-scout' );
		} else {
			$score_class = 'zigsiteinfoscout-score--poor';
			$score_label = __( 'Needs Attention', 'site-info-scout' );
		}
		?>
		<div class="zigsiteinfoscout-score-bar">
			<span class="zigsiteinfoscout-score-label">
				<?php esc_html_e( 'Health Score:', 'site-info-scout' ); ?>
			</span>
			<span class="zigsiteinfoscout-score-badge <?php echo esc_attr( $score_class ); ?>">
				<?php
				printf(
					/* translators: 1: Numeric score 0-100. 2: Score label e.g. Good, Fair, Needs Attention. */
					esc_html__( '%1$d / 100 — %2$s', 'site-info-scout' ),
					intval( $score ),
					esc_html( $score_label )
				);
				?>
			</span>
		</div>
		<?php
	}

	/**
	 * Renders the Insights & Recommendations section.
	 *
	 * Skipped entirely when no insights are generated (healthy site).
	 *
	 * @param array $insights Actionable insights from Health_Checks::get_insights().
	 */
	private function render_insights( array $insights ) {
		if ( empty( $insights ) ) {
			return;
		}
		?>
		<div class="zigsiteinfoscout-card zigsiteinfoscout-card--full zigsiteinfoscout-insights">
			<h2><?php esc_html_e( 'Insights &amp; Recommendations', 'site-info-scout' ); ?></h2>
			<?php foreach ( $insights as $insight ) :
				$notice_class = ( 'warning' === $insight['severity'] ) ? 'notice-warning' : 'notice-info';
				?>
				<div class="notice <?php echo esc_attr( $notice_class ); ?> zigsiteinfoscout-notice zigsiteinfoscout-insight-item">
					<p><?php echo esc_html( $insight['message'] ); ?></p>
				</div>
			<?php endforeach; ?>
		</div>
		<?php
	}

	/**
	 * Renders the health flags section.
	 *
	 * Uses native WordPress .notice classes so no custom warning CSS is needed.
	 *
	 * @param array $flags Evaluated health flags from Health_Checks::evaluate().
	 */
	private function render_health_flags( array $flags ) {
		if ( empty( $flags ) ) {
			?>
			<div class="notice notice-success zigsiteinfoscout-notice">
				<p>
					<strong><?php esc_html_e( 'All checks passed.', 'site-info-scout' ); ?></strong>
					<?php esc_html_e( 'No common issues were detected on this site.', 'site-info-scout' ); ?>
				</p>
			</div>
			<?php
			return;
		}

		foreach ( $flags as $flag ) {
			$notice_class = ( 'warning' === $flag['severity'] ) ? 'notice-warning' : 'notice-info';
			?>
			<div class="notice <?php echo esc_attr( $notice_class ); ?> zigsiteinfoscout-notice">
				<p>
					<strong><?php echo esc_html( $flag['label'] ); ?>:</strong>
					<?php echo esc_html( $flag['message'] ); ?>
				</p>
			</div>
			<?php
		}
	}

	/**
	 * Renders the WordPress + PHP environment card.
	 *
	 * @param array $report Full report array.
	 */
	private function render_environment_card( array $report ) {
		$env = $report['environment'];
		$php = $report['php'];
		$srv = $report['server'];
		?>
		<div class="zigsiteinfoscout-card">
			<h2><?php esc_html_e( 'Environment', 'site-info-scout' ); ?></h2>
			<table class="widefat zigsiteinfoscout-table">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Setting', 'site-info-scout' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Value', 'site-info-scout' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php $this->env_row( __( 'WordPress Version', 'site-info-scout' ), $env['wp_version'] ); ?>
					<?php $this->env_row( __( 'PHP Version', 'site-info-scout' ), $php['version'] ); ?>
					<?php $this->env_row(
						__( 'PHP Architecture', 'site-info-scout' ),
						$php['architecture'],
						'',
						__( '32-bit or 64-bit build of the PHP installation on this server.', 'site-info-scout' )
					); ?>
					<?php $this->env_row( __( 'Site URL', 'site-info-scout' ), $env['wp_site_url'] ); ?>
					<?php $this->env_row( __( 'WordPress URL', 'site-info-scout' ), $env['wp_home_url'] ); ?>
					<?php $this->env_row( __( 'Multisite', 'site-info-scout' ), $env['is_multisite'] ? __( 'Yes', 'site-info-scout' ) : __( 'No', 'site-info-scout' ) ); ?>
					<?php
					$this->env_row(
						__( 'WP_DEBUG', 'site-info-scout' ),
						$env['wp_debug'] ? __( 'Enabled', 'site-info-scout' ) : __( 'Disabled', 'site-info-scout' ),
						$env['wp_debug'] ? 'zigsiteinfoscout-val--warning' : ''
					);
					?>
					<?php
					$this->env_row(
						__( 'WP_DEBUG_LOG', 'site-info-scout' ),
						$env['wp_debug_log'] ? __( 'Enabled', 'site-info-scout' ) : __( 'Disabled', 'site-info-scout' ),
						$env['wp_debug_log'] ? 'zigsiteinfoscout-val--warning' : ''
					);
					?>
					<?php $this->env_row(
						__( 'SCRIPT_DEBUG', 'site-info-scout' ),
						$env['script_debug'] ? __( 'Enabled', 'site-info-scout' ) : __( 'Disabled', 'site-info-scout' ),
						'',
						__( 'When enabled, WordPress loads unminified CSS/JS assets. Disable on production sites.', 'site-info-scout' )
					); ?>
					<?php
					$this->env_row(
						__( 'DISABLE_WP_CRON', 'site-info-scout' ),
						$env['wp_cron_disabled'] ? __( 'Yes', 'site-info-scout' ) : __( 'No', 'site-info-scout' ),
						$env['wp_cron_disabled'] ? 'zigsiteinfoscout-val--info' : ''
					);
					?>
					<?php $this->env_row( __( 'WP Memory Limit', 'site-info-scout' ), $env['wp_memory_limit'] ); ?>
					<?php $this->env_row(
						__( 'WP Max Memory Limit', 'site-info-scout' ),
						$env['wp_max_memory_limit'],
						'',
						__( 'Hard ceiling set by the host. WordPress and plugins cannot allocate memory beyond this value.', 'site-info-scout' )
					); ?>
					<?php $this->env_row( __( 'PHP Memory Limit', 'site-info-scout' ), $php['memory_limit'] ); ?>
					<?php $this->env_row( __( 'PHP Max Execution', 'site-info-scout' ), $php['max_execution'] . 's' ); ?>
					<?php $this->env_row( __( 'PHP Max Input Vars', 'site-info-scout' ), $php['max_input_vars'] ); ?>
					<?php $this->env_row( __( 'PHP Post Max Size', 'site-info-scout' ), $php['post_max_size'] ); ?>
					<?php $this->env_row( __( 'PHP Upload Max', 'site-info-scout' ), $php['upload_max_size'] ); ?>
					<?php $this->env_row( __( 'PHP Display Errors', 'site-info-scout' ), $php['display_errors'] ); ?>
					<?php $this->env_row( __( 'Server Software', 'site-info-scout' ), $srv['software'] ); ?>
					<?php $this->env_row(
						__( 'PHP SAPI', 'site-info-scout' ),
						$srv['php_sapi'],
						'',
						__( 'How PHP connects to the web server (e.g. fpm-fcgi, apache2handler, cli).', 'site-info-scout' )
					); ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * Renders the active theme summary card.
	 *
	 * @param array $report Full report array.
	 */
	private function render_theme_card( array $report ) {
		$theme = $report['theme'];
		?>
		<div class="zigsiteinfoscout-card">
			<h2><?php esc_html_e( 'Active Theme', 'site-info-scout' ); ?></h2>
			<table class="widefat zigsiteinfoscout-table">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Setting', 'site-info-scout' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Value', 'site-info-scout' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php $this->env_row( __( 'Theme Name', 'site-info-scout' ), $theme['name'] ); ?>
					<?php $this->env_row( __( 'Version', 'site-info-scout' ), $theme['version'] ); ?>
					<?php $this->env_row( __( 'Slug', 'site-info-scout' ), $theme['template'] ); ?>
					<?php
					if ( null !== $theme['parent_name'] ) {
						$this->env_row( __( 'Parent Theme', 'site-info-scout' ), $theme['parent_name'] );
						$this->env_row( __( 'Parent Version', 'site-info-scout' ), $theme['parent_version'] );
					} else {
						$this->env_row( __( 'Parent Theme', 'site-info-scout' ), __( 'None (standalone)', 'site-info-scout' ) );
					}
					?>
				</tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * Renders the full active plugins table.
	 *
	 * @param array $report Full report array.
	 */
	private function render_plugins_table( array $report ) {
		$plugins = $report['plugins'];
		$count   = count( $plugins );
		?>
		<div class="zigsiteinfoscout-card zigsiteinfoscout-card--full">
			<h2>
				<?php
				/* translators: %d: Number of active plugins. */
				printf( esc_html__( 'Active Plugins (%d)', 'site-info-scout' ), absint( $count ) );
				?>
			</h2>

			<?php if ( empty( $plugins ) ) : ?>
				<p><?php esc_html_e( 'No active plugins found.', 'site-info-scout' ); ?></p>
			<?php else : ?>
				<table class="wp-list-table widefat fixed striped zigsiteinfoscout-table">
					<thead>
						<tr>
							<th scope="col" class="column-num"><?php esc_html_e( '#', 'site-info-scout' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Plugin Name', 'site-info-scout' ); ?></th>
							<th scope="col" class="column-version"><?php esc_html_e( 'Version', 'site-info-scout' ); ?></th>
							<th scope="col"><?php esc_html_e( 'File', 'site-info-scout' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $plugins as $index => $plugin ) : ?>
							<tr>
								<td><?php echo esc_html( $index + 1 ); ?></td>
								<td><?php echo esc_html( $plugin['name'] ); ?></td>
								<td><?php echo esc_html( $plugin['version'] ); ?></td>
								<td><?php echo esc_html( $plugin['file'] ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Renders the export action bar.
	 *
	 * @param string $txt_url Nonce-protected URL for TXT export.
	 * @param string $csv_url Nonce-protected URL for CSV export.
	 */
	private function render_export_bar( $txt_url, $csv_url ) {
		?>
		<div class="zigsiteinfoscout-card zigsiteinfoscout-card--full zigsiteinfoscout-export-bar">
			<h2><?php esc_html_e( 'Export Report', 'site-info-scout' ); ?></h2>
			<p><?php esc_html_e( 'Download or copy the site report to share with your support team.', 'site-info-scout' ); ?></p>

			<div class="zigsiteinfoscout-export-actions">
				<a href="<?php echo esc_url( $txt_url ); ?>" class="button button-primary">
					<?php esc_html_e( 'Download TXT Report', 'site-info-scout' ); ?>
				</a>

				<button
					type="button"
					id="zigsiteinfoscout-summary-btn"
					class="button button-secondary"
				>
					<?php esc_html_e( 'Copy Support Summary', 'site-info-scout' ); ?>
				</button>

				<button
					type="button"
					id="zigsiteinfoscout-copy-btn"
					class="button button-secondary"
				>
					<?php esc_html_e( 'Copy Full Report', 'site-info-scout' ); ?>
				</button>

				<a href="<?php echo esc_url( $csv_url ); ?>" class="button button-secondary">
					<?php esc_html_e( 'Export CSV Inventory', 'site-info-scout' ); ?>
				</a>
			</div>

			<div
				id="zigsiteinfoscout-summary-feedback"
				class="zigsiteinfoscout-copy-feedback"
				aria-live="polite"
				aria-atomic="true"
			></div>

			<div
				id="zigsiteinfoscout-copy-feedback"
				class="zigsiteinfoscout-copy-feedback"
				aria-live="polite"
				aria-atomic="true"
			></div>
		</div>
		<?php
	}

	// ── Private template helpers ───────────────────────────────────────────

	/**
	 * Outputs a single two-column table row (label th, value td).
	 *
	 * Both label and value are escaped inside this method.
	 * Never pass pre-escaped strings to this method.
	 *
	 * @param string $label       Already-translated row label. Escaped internally.
	 * @param string $value       Raw row value. Escaped internally.
	 * @param string $value_class Optional CSS class for the value <td>.
	 * @param string $help        Optional short help text shown below the label.
	 */
	private function env_row( $label, $value, $value_class = '', $help = '' ) {
		$class_attr = $value_class ? ' class="' . esc_attr( $value_class ) . '"' : '';
		?>
		<tr>
			<th scope="row">
				<?php echo esc_html( $label ); ?>
				<?php if ( $help ) : ?>
					<span class="zigsiteinfoscout-field-desc"><?php echo esc_html( $help ); ?></span>
				<?php endif; ?>
			</th>
			<td<?php echo $class_attr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Value is esc_attr'd above. ?>><?php echo esc_html( $value ); ?></td>
		</tr>
		<?php
	}
}
