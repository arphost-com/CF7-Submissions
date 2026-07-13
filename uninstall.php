<?php
/**
 * Uninstall: remove the submissions table and options.
 *
 * @package cf7-db-gsheets
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}cf7dbgs_submissions" );

delete_option( 'cf7dbgs_settings' );
delete_option( 'cf7dbgs_db_version' );
