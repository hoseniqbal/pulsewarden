<?php
/**
 * Uninstall: remove data only when the owner asked for it.
 *
 * @package PulseWarden
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$pulsewarden_settings = get_option( 'pulsewarden_settings', array() );
if ( ! is_array( $pulsewarden_settings ) || empty( $pulsewarden_settings['delete_data'] ) || 'yes' !== $pulsewarden_settings['delete_data'] ) {
	return;
}

global $wpdb;
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- uninstall.
$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $wpdb->prefix . 'pulsewarden_log' ) );
foreach ( array( 'pulsewarden_settings', 'pulsewarden_db_version', 'pulsewarden_state', 'pulsewarden_since', 'pulsewarden_last_check', 'pulsewarden_backfill' ) as $pulsewarden_option ) {
	delete_option( $pulsewarden_option );
}
delete_transient( 'pulsewarden_baseline' );
wp_clear_scheduled_hook( 'pulsewarden_check' );
wp_clear_scheduled_hook( 'pulsewarden_daily' );
if ( function_exists( 'as_unschedule_all_actions' ) ) {
	as_unschedule_all_actions( 'pulsewarden_backfill' );
}
