<?php
/**
 * Health Checks class for Site Info Scout.
 *
 * @package Zignites\SiteInfoScout
 */

namespace Zignites\SiteInfoScout;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Evaluates local site conditions and returns diagnostic health flags.
 *
 * All checks operate exclusively on data already collected in the report
 * array. No external HTTP requests, no file system writes, no DB changes.
 */
class Health_Checks {

	/**
	 * Runs all checks and returns an array of flag structs.
	 *
	 * An empty return value means all checks passed.
	 *
	 * @since 1.0.0
	 * @param array $report Normalized report from Environment_Report::get_report().
	 * @return array[] Each flag: { id, label, message, severity ('warning'|'info') }.
	 */
	public function evaluate( array $report ) {
		$checks = array(
			$this->check_php_version( $report ),
			$this->check_wp_debug( $report ),
			$this->check_wp_debug_log( $report ),
			$this->check_disable_wp_cron( $report ),
			$this->check_plugin_count( $report ),
			$this->check_memory_limit( $report ),
		);

		// array_filter removes null entries (checks that did not fire).
		return array_values( array_filter( $checks ) );
	}

	/**
	 * Calculates a deterministic 0–100 health score based on detected issues.
	 *
	 * Starts at 100 and deducts points for each condition triggered.
	 * Score is clamped to the 0–100 range.
	 *
	 * Deductions:
	 *   WP_DEBUG enabled        -20
	 *   WP_DEBUG_LOG enabled    -10
	 *   PHP below recommended   -15
	 *   Memory below 64 MB      -15
	 *   Plugin count ≥ threshold -10
	 *   DISABLE_WP_CRON enabled  -10
	 *
	 * @since 1.1.0
	 * @param array $report Normalized report from Environment_Report::get_report().
	 * @return int Score between 0 and 100 inclusive.
	 */
	public function get_health_score( array $report ) {
		$score = 100;
		$min   = zigsiteinfoscout_get_php_min_recommended();

		if ( true === $report['environment']['wp_debug'] ) {
			$score -= 20;
		}
		if ( true === $report['environment']['wp_debug_log'] ) {
			$score -= 10;
		}
		if ( version_compare( $report['php']['version'], $min, '<' ) ) {
			$score -= 15;
		}
		$bytes = zigsiteinfoscout_convert_memory_to_bytes( $report['php']['memory_limit'] );
		if ( $bytes < 67108864 ) { // 64 MB threshold.
			$score -= 15;
		}
		if ( count( $report['plugins'] ) >= ZIGSITEINFOSCOUT_HIGH_PLUGIN_THRESHOLD ) {
			$score -= 10;
		}
		if ( true === $report['environment']['wp_cron_disabled'] ) {
			$score -= 10;
		}

		return max( 0, min( 100, $score ) );
	}

	/**
	 * Generates actionable, plain-language insight messages for the current environment.
	 *
	 * Insights provide verbose developer-friendly explanations, suitable for
	 * display in an "Insights & Recommendations" section and for inclusion in
	 * the Smart Support Summary plain-text output.
	 *
	 * @since 1.1.0
	 * @param array $report Normalized report from Environment_Report::get_report().
	 * @return array[] Each entry: { severity ('warning'|'info'), message (string) }.
	 */
	public function get_insights( array $report ) {
		$insights = array();
		$min      = zigsiteinfoscout_get_php_min_recommended();

		if ( true === $report['environment']['wp_debug'] ) {
			$insights[] = array(
				'severity' => 'warning',
				'message'  => __( 'WP_DEBUG is enabled — this may expose PHP errors and notices to site visitors. Disable on all production sites.', 'site-info-scout' ),
			);
		}

		if ( true === $report['environment']['wp_debug_log'] ) {
			$insights[] = array(
				'severity' => 'warning',
				'message'  => __( 'Debug logging is enabled — ensure the debug.log file is not publicly accessible via a direct URL.', 'site-info-scout' ),
			);
		}

		if ( version_compare( $report['php']['version'], $min, '<' ) ) {
			$insights[] = array(
				'severity' => 'warning',
				'message'  => sprintf(
					/* translators: 1: Current PHP version string. 2: Minimum recommended PHP version string. */
					__( 'PHP %1$s is below the recommended minimum of %2$s. Older PHP versions may lack security patches and performance improvements. Contact your host to upgrade.', 'site-info-scout' ),
					$report['php']['version'],
					$min
				),
			);
		}

		$bytes = zigsiteinfoscout_convert_memory_to_bytes( $report['php']['memory_limit'] );
		if ( $bytes < 67108864 ) {
			$insights[] = array(
				'severity' => 'warning',
				'message'  => sprintf(
					/* translators: %s: Current PHP memory_limit value, e.g. '32M'. */
					__( 'Low PHP memory limit (%s) — this may cause plugin failures or slow admin performance. WordPress recommends at least 64 MB; 256 MB is ideal for complex sites.', 'site-info-scout' ),
					$report['php']['memory_limit']
				),
			);
		}

		$plugin_count = count( $report['plugins'] );
		if ( $plugin_count >= ZIGSITEINFOSCOUT_HIGH_PLUGIN_THRESHOLD ) {
			$insights[] = array(
				'severity' => 'info',
				'message'  => sprintf(
					/* translators: 1: Number of active plugins. 2: The high plugin count threshold number. */
					__( 'High number of active plugins (%1$d) — running %2$d or more plugins may increase page load times and the risk of conflicts. Review and deactivate unused plugins.', 'site-info-scout' ),
					$plugin_count,
					ZIGSITEINFOSCOUT_HIGH_PLUGIN_THRESHOLD
				),
			);
		}

		if ( true === $report['environment']['wp_cron_disabled'] ) {
			$insights[] = array(
				'severity' => 'info',
				'message'  => __( 'WordPress Cron is disabled — scheduled events will not run automatically via page loads. Ensure a real server-side cron job is configured to trigger wp-cron.php.', 'site-info-scout' ),
			);
		}

		return $insights;
	}

	// ── Individual checks ──────────────────────────────────────────────────

	/**
	 * Flags when PHP is below the recommended minimum version.
	 *
	 * @param array $report Report data.
	 * @return array|null Flag struct or null.
	 */
	private function check_php_version( array $report ) {
		$min = zigsiteinfoscout_get_php_min_recommended();

		if ( version_compare( $report['php']['version'], $min, '<' ) ) {
			return array(
				'id'       => 'php_version',
				'label'    => __( 'PHP Version', 'site-info-scout' ),
				'message'  => sprintf(
					/* translators: 1: Current PHP version string. 2: Minimum recommended PHP version string. */
					__( 'PHP %1$s is below the recommended minimum of PHP %2$s. Consider upgrading your PHP version.', 'site-info-scout' ),
					$report['php']['version'],
					$min
				),
				'severity' => 'warning',
			);
		}

		return null;
	}

	/**
	 * Flags when WP_DEBUG is enabled.
	 *
	 * @param array $report Report data.
	 * @return array|null Flag struct or null.
	 */
	private function check_wp_debug( array $report ) {
		if ( true === $report['environment']['wp_debug'] ) {
			return array(
				'id'       => 'wp_debug',
				'label'    => __( 'WP_DEBUG Enabled', 'site-info-scout' ),
				'message'  => __( 'WP_DEBUG is enabled. This should be disabled on production sites to prevent PHP notices from being shown to visitors.', 'site-info-scout' ),
				'severity' => 'warning',
			);
		}

		return null;
	}

	/**
	 * Flags when WP_DEBUG_LOG is enabled.
	 *
	 * @param array $report Report data.
	 * @return array|null Flag struct or null.
	 */
	private function check_wp_debug_log( array $report ) {
		if ( true === $report['environment']['wp_debug_log'] ) {
			return array(
				'id'       => 'wp_debug_log',
				'label'    => __( 'WP_DEBUG_LOG Enabled', 'site-info-scout' ),
				'message'  => __( 'WP_DEBUG_LOG is enabled. Error output is being written to a log file. Ensure the log file is not publicly accessible.', 'site-info-scout' ),
				'severity' => 'warning',
			);
		}

		return null;
	}

	/**
	 * Flags when DISABLE_WP_CRON is set (informational, not a hard warning).
	 *
	 * @param array $report Report data.
	 * @return array|null Flag struct or null.
	 */
	private function check_disable_wp_cron( array $report ) {
		if ( true === $report['environment']['wp_cron_disabled'] ) {
			return array(
				'id'       => 'disable_wp_cron',
				'label'    => __( 'WP-Cron Disabled', 'site-info-scout' ),
				'message'  => __( 'DISABLE_WP_CRON is enabled. Scheduled tasks will not run automatically via page loads. Ensure a real server-side cron job is configured.', 'site-info-scout' ),
				'severity' => 'info',
			);
		}

		return null;
	}

	/**
	 * Flags when the active plugin count reaches the configured threshold.
	 *
	 * The threshold is defined by ZIGSITEINFOSCOUT_HIGH_PLUGIN_THRESHOLD.
	 *
	 * @param array $report Report data.
	 * @return array|null Flag struct or null.
	 */
	private function check_plugin_count( array $report ) {
		$count     = count( $report['plugins'] );
		$threshold = ZIGSITEINFOSCOUT_HIGH_PLUGIN_THRESHOLD;

		if ( $count >= $threshold ) {
			return array(
				'id'      => 'plugin_count',
				'label'   => __( 'High Plugin Count', 'site-info-scout' ),
			'message' => sprintf(
				/* translators: 1: Number of active plugins. 2: The high plugin count threshold number. */
					__( 'You have %1$d active plugins. Running %2$d or more plugins may increase page load times and the risk of conflicts.', 'site-info-scout' ),
					$count,
					$threshold
				),
				'severity' => 'warning',
			);
		}

		return null;
	}

	/**
	 * Flags when the PHP memory limit is below 64 MB.
	 *
	 * Uses zigsiteinfoscout_convert_memory_to_bytes() to handle shorthand
	 * strings (M, G, K) and the unlimited value (-1) correctly.
	 *
	 * @param array $report Report data.
	 * @return array|null Flag struct or null.
	 */
	private function check_memory_limit( array $report ) {
		$limit = $report['php']['memory_limit'];
		$bytes = zigsiteinfoscout_convert_memory_to_bytes( $limit );

		// 64 MB = 67108864 bytes. PHP_INT_MAX (unlimited) always passes.
		if ( $bytes < 67108864 ) {
			return array(
				'id'      => 'memory_limit',
				'label'   => __( 'Low Memory Limit', 'site-info-scout' ),
			'message' => sprintf(
				/* translators: %s: Current PHP memory_limit value, e.g. '32M'. */
					__( 'PHP memory limit is set to %s. WordPress recommends at least 64 MB; 256 MB is ideal for sites with many plugins.', 'site-info-scout' ),
					$limit
				),
				'severity' => 'warning',
			);
		}

		return null;
	}
}
