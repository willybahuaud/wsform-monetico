<?php
/**
 * Removes the plugin settings when the plugin is deleted.
 *
 * Payment data stored on WS Form submissions stays: it belongs to the
 * merchant's records.
 *
 * @package WSForm_Monetico
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

delete_option( 'wsform_monetico_accounts' );
delete_transient( 'wsform_monetico_logs_cleaned' );

// Same key as WSForm_Monetico\Updater\Github_Release::get_cache_key().
delete_site_transient( 'wsform_monetico_release_' . md5( 'willybahuaud/wsform-monetico' ) );

// Leftover IPN locks.
$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like( 'wsform_monetico_lock_' ) . '%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

$wsform_monetico_uploads = wp_upload_dir( null, false );
$wsform_monetico_logs    = trailingslashit( $wsform_monetico_uploads['basedir'] ) . 'wsform-monetico';
foreach ( (array) glob( $wsform_monetico_logs . '/logs/{,.}*', GLOB_BRACE ) as $wsform_monetico_file ) {
	if ( is_file( $wsform_monetico_file ) ) {
		wp_delete_file( $wsform_monetico_file );
	}
}
// phpcs:disable WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- Empty folders created by the plugin.
@rmdir( $wsform_monetico_logs . '/logs' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
@rmdir( $wsform_monetico_logs ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
// phpcs:enable
