<?php
/**
 * Shared utility functions for Site Info Scout.
 *
 * All functions are prefixed zigsiteinfoscout_ to avoid global collisions.
 *
 * @package Zignites\SiteInfoScout
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Converts a PHP memory limit string to an integer byte count.
 *
 * Handles the following formats:
 *   - Human-readable shorthand: '128M', '1G', '512K'
 *   - Raw integer strings:      '134217728'
 *   - Unlimited:                '-1'  → returns PHP_INT_MAX
 *
 * @param string $value Memory limit string from ini_get() or a WP constant.
 * @return int Memory in bytes, or PHP_INT_MAX for unlimited (-1).
 */
function zigsiteinfoscout_convert_memory_to_bytes( $value ) {
	if ( '-1' === (string) $value ) {
		return PHP_INT_MAX;
	}

	return wp_convert_hr_to_bytes( (string) $value );
}

/**
 * Returns the minimum recommended PHP version string used by health checks.
 *
 * Centralised here so the threshold can be updated in one place.
 *
 * @return string PHP version string, e.g. '8.0'.
 */
function zigsiteinfoscout_get_php_min_recommended() {
	return '8.0';
}
