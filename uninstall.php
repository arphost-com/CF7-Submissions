<?php
/**
 * Uninstall: remove the submissions table and options.
 *
 * @package arphost-cf7-submission-archive
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

// Removing the plugin's own custom table on uninstall is the expected cleanup.
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $wpdb->prefix . 'cf7dbgs_submissions' ) );

delete_option( 'cf7dbgs_settings' );
delete_option( 'cf7dbgs_db_version' );
