<?php
/**
 * Plugin Name:       CF7 Database & Google Sheets
 * Plugin URI:        https://github.com/arphost/cf7-db-gsheets
 * Description:       Saves Contact Form 7 submissions to the WordPress database and optionally forwards them to a Google Sheets webhook (Google Apps Script). Includes an admin submissions browser and CSV export.
 * Version:           1.1.4
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            ARPHost, LLC
 * Author URI:        https://arphost.com
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       cf7-db-gsheets
 *
 * CF7 Database & Google Sheets is free software: you can redistribute it
 * and/or modify it under the terms of the GNU General Public License as
 * published by the Free Software Foundation, either version 2 of the
 * License, or any later version.
 */

defined( 'ABSPATH' ) || exit;

define( 'CF7DBGS_VERSION', '1.1.4' );
define( 'CF7DBGS_PLUGIN_FILE', __FILE__ );
define( 'CF7DBGS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'CF7DBGS_OPTION', 'cf7dbgs_settings' );

require_once CF7DBGS_PLUGIN_DIR . 'includes/class-cf7dbgs-db.php';
require_once CF7DBGS_PLUGIN_DIR . 'includes/class-cf7dbgs-capture.php';
require_once CF7DBGS_PLUGIN_DIR . 'includes/class-cf7dbgs-webhook.php';
require_once CF7DBGS_PLUGIN_DIR . 'includes/class-cf7dbgs-sheets-api.php';

register_activation_hook( __FILE__, array( 'CF7DBGS_DB', 'install' ) );

/**
 * Default settings.
 *
 * @return array
 */
function cf7dbgs_default_settings() {
	return array(
		'store_db'     => 1,
		'send_webhook' => 0,
		'sheets_mode'  => 'webhook', // webhook | api
		'webhook_url'  => '',
		'sa_json'      => '',
		'sheet_id'     => '',
		'sheet_routes' => '',
		'field_map'    => '',
		'store_ip'     => 0,
		'store_ua'     => 0,
	);
}

/**
 * Get merged settings.
 *
 * @return array
 */
function cf7dbgs_get_settings() {
	$saved = get_option( CF7DBGS_OPTION, array() );
	return wp_parse_args( is_array( $saved ) ? $saved : array(), cf7dbgs_default_settings() );
}

add_action( 'plugins_loaded', 'cf7dbgs_bootstrap' );

/**
 * Bootstrap the plugin once all plugins are loaded.
 */
function cf7dbgs_bootstrap() {
	load_plugin_textdomain( 'cf7-db-gsheets', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

	if ( ! defined( 'WPCF7_VERSION' ) ) {
		add_action( 'admin_notices', 'cf7dbgs_missing_cf7_notice' );
		return;
	}

	CF7DBGS_DB::maybe_upgrade();
	CF7DBGS_Capture::init();

	if ( is_admin() ) {
		require_once CF7DBGS_PLUGIN_DIR . 'admin/class-cf7dbgs-admin.php';
		CF7DBGS_Admin::init();
	}
}

/**
 * Admin notice when Contact Form 7 is not active.
 */
function cf7dbgs_missing_cf7_notice() {
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}
	printf(
		'<div class="notice notice-warning"><p>%s</p></div>',
		esc_html__( 'CF7 Database & Google Sheets requires the Contact Form 7 plugin to be installed and active.', 'cf7-db-gsheets' )
	);
}
