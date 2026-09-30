<?php
/**
 * Uninstall functions.
 *
 * Remove the plugin's data when it is deleted. Loaded by uninstall.php.
 *
 * @package TempusFugit
 * @since 1.2.1
 */

defined( 'ABSPATH' ) || exit;

/**
 * Removes the plugin's data from every site.
 *
 * @since 1.2.1
 */
function tempus_fugit_uninstall() {
	if ( is_multisite() ) {
		foreach ( get_sites(
			array(
				'fields' => 'ids',
				'number' => 0,
			)
		) as $site_id ) {
			switch_to_blog( $site_id );
			tempus_fugit_uninstall_site();
			restore_current_blog();
		}
		return;
	}
	tempus_fugit_uninstall_site();
}

/**
 * Removes the plugin's data from the current site.
 *
 * Deletes the widget settings, the widget cache version, and the cached widget results. With a
 * persistent object cache, cached results are not in the database and expire within an hour.
 * The stored rewrite rules are cleared so they are rebuilt without the plugin's rules.
 *
 * @since 1.2.1
 *
 * @global wpdb $wpdb WordPress database abstraction object.
 */
function tempus_fugit_uninstall_site() {
	global $wpdb;
	delete_option( 'widget_tempus_onthisday_widget' );
	delete_option( 'widget_tempus_thisweek_widget' );
	delete_option( 'widget_tempus_random_widget' );
	delete_option( 'tempus_fugit_widget_cache_version' );

	// Cached widget results, with the current names and the names used before 1.2.1.
	foreach ( array( 'tempus_widget_', 'onthisday_widget', 'thisweek_widget' ) as $prefix ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Transients can't be deleted by prefix through the API.
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
				$wpdb->esc_like( '_transient_' . $prefix ) . '%',
				$wpdb->esc_like( '_transient_timeout_' . $prefix ) . '%'
			)
		);
	}

	delete_option( 'rewrite_rules' );
}
