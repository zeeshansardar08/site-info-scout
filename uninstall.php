<?php
/**
 * Uninstall routine for Site Info Scout.
 *
 * Removes all plugin-owned options and transients.
 * Runs when a user deletes the plugin from the WordPress Plugins screen.
 *
 * @package Zignites\SiteInfoScout
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Delete all plugin-owned data for a single site.
 */
function zigsiteinfoscout_uninstall_site() {
	delete_option( 'zigsiteinfoscout_settings' );
	delete_transient( 'zigsiteinfoscout_report_cache' );
}

if ( is_multisite() ) {
	$zigsiteinfoscout_sites = get_sites( array( 'number' => 0 ) );
	foreach ( $zigsiteinfoscout_sites as $zigsiteinfoscout_site ) {
		switch_to_blog( $zigsiteinfoscout_site->blog_id );
		zigsiteinfoscout_uninstall_site();
		restore_current_blog();
	}
} else {
	zigsiteinfoscout_uninstall_site();
}
