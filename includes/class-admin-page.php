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

		$report     = $this->environment_report->get_report();
		$flags      = $this->health_checks->evaluate( $report );
		$plain_text = $this->export_controller->build_txt_output( $report );

		// Supply the report text and i18n strings to admin.js.
		wp_localize_script(
			'zigsiteinfoscout-admin',
			'zigsiteinfoscoutData',
			array(
				'report' => $plain_text,
				'i18n'   => array(
					'copied'     => __( 'Report copied to clipboard!', 'site-info-scout' ),
					'copyFailed' => __( 'Copy failed. Please use the Download TXT button instead.', 'site-info-scout' ),
				),
			)
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
			<p class="zigsiteinfoscout-tagline">
				<?php esc_html_e( 'A read-only support snapshot of this WordPress site. No data is sent externally.', 'site-info-scout' ); ?>
				&mdash;
				<span class="zigsiteinfoscout-generated">
					<?php printf(
						/* translators: %s: Date and time the report was generated. */
						esc_html__( 'Report generated: %s', 'site-info-scout' ),
						esc_html( $report['generated_at'] )
					); ?>
				</span>
			</p>

			<?php $this->render_health_flags( $flags ); ?>

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
					<?php $this->env_row( __( 'PHP Architecture', 'site-info-scout' ), $php['architecture'] ); ?>
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
					<?php $this->env_row( __( 'SCRIPT_DEBUG', 'site-info-scout' ), $env['script_debug'] ? __( 'Enabled', 'site-info-scout' ) : __( 'Disabled', 'site-info-scout' ) ); ?>
					<?php
					$this->env_row(
						__( 'DISABLE_WP_CRON', 'site-info-scout' ),
						$env['wp_cron_disabled'] ? __( 'Yes', 'site-info-scout' ) : __( 'No', 'site-info-scout' ),
						$env['wp_cron_disabled'] ? 'zigsiteinfoscout-val--info' : ''
					);
					?>
					<?php $this->env_row( __( 'WP Memory Limit', 'site-info-scout' ), $env['wp_memory_limit'] ); ?>
					<?php $this->env_row( __( 'WP Max Memory Limit', 'site-info-scout' ), $env['wp_max_memory_limit'] ); ?>
					<?php $this->env_row( __( 'PHP Memory Limit', 'site-info-scout' ), $php['memory_limit'] ); ?>
					<?php $this->env_row( __( 'PHP Max Execution', 'site-info-scout' ), $php['max_execution'] . 's' ); ?>
					<?php $this->env_row( __( 'PHP Max Input Vars', 'site-info-scout' ), $php['max_input_vars'] ); ?>
					<?php $this->env_row( __( 'PHP Post Max Size', 'site-info-scout' ), $php['post_max_size'] ); ?>
					<?php $this->env_row( __( 'PHP Upload Max', 'site-info-scout' ), $php['upload_max_size'] ); ?>
					<?php $this->env_row( __( 'PHP Display Errors', 'site-info-scout' ), $php['display_errors'] ); ?>
					<?php $this->env_row( __( 'Server Software', 'site-info-scout' ), $srv['software'] ); ?>
					<?php $this->env_row( __( 'PHP SAPI', 'site-info-scout' ), $srv['php_sapi'] ); ?>
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
				<button
					type="button"
					id="zigsiteinfoscout-copy-btn"
					class="button button-secondary"
				>
					<?php esc_html_e( 'Copy Report to Clipboard', 'site-info-scout' ); ?>
				</button>

				<a href="<?php echo esc_url( $txt_url ); ?>" class="button button-primary">
					<?php esc_html_e( 'Download TXT Report', 'site-info-scout' ); ?>
				</a>

				<a href="<?php echo esc_url( $csv_url ); ?>" class="button button-secondary">
					<?php esc_html_e( 'Export CSV Inventory', 'site-info-scout' ); ?>
				</a>
			</div>

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
	 * @param string $label     Already-translated row label. Escaped internally.
	 * @param string $value     Raw row value. Escaped internally.
	 * @param string $value_class Optional CSS class for the value <td>.
	 */
	private function env_row( $label, $value, $value_class = '' ) {
		$class_attr = $value_class ? ' class="' . esc_attr( $value_class ) . '"' : '';
		?>
		<tr>
			<th scope="row"><?php echo esc_html( $label ); ?></th>
			<td<?php echo $class_attr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Value is esc_attr'd above. ?>><?php echo esc_html( $value ); ?></td>
		</tr>
		<?php
	}
}
