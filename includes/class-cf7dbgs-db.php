<?php
/**
 * Database layer: table creation and CRUD for submissions.
 *
 * @package arphost-cf7-submission-archive
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class CF7DBGS_DB
 */
class CF7DBGS_DB {

	const DB_VERSION        = '1.0';
	const DB_VERSION_OPTION = 'cf7dbgs_db_version';

	/**
	 * Full table name.
	 *
	 * @return string
	 */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'cf7dbgs_submissions';
	}

	/**
	 * Create / upgrade the table (activation hook).
	 */
	public static function install() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table   = self::table();
		$charset = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			form_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			form_title VARCHAR(200) NOT NULL DEFAULT '',
			submitted_at DATETIME NOT NULL,
			fields LONGTEXT NOT NULL,
			remote_ip VARCHAR(45) NOT NULL DEFAULT '',
			user_agent VARCHAR(255) NOT NULL DEFAULT '',
			sheets_status VARCHAR(20) NOT NULL DEFAULT 'disabled',
			sheets_response VARCHAR(500) NOT NULL DEFAULT '',
			PRIMARY KEY  (id),
			KEY form_id (form_id),
			KEY submitted_at (submitted_at)
		) {$charset};";

		dbDelta( $sql );

		update_option( self::DB_VERSION_OPTION, self::DB_VERSION );
	}

	/**
	 * Run install again if the stored schema version is stale.
	 */
	public static function maybe_upgrade() {
		if ( get_option( self::DB_VERSION_OPTION ) !== self::DB_VERSION ) {
			self::install();
		}
	}

	/**
	 * Insert a submission row.
	 *
	 * @param array $row Keys: form_id, form_title, fields (array), remote_ip, user_agent, sheets_status.
	 * @return int|false Insert ID or false.
	 */
	public static function insert( $row ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- write to the plugin's own custom table.
		$ok = $wpdb->insert(
			self::table(),
			array(
				'form_id'         => absint( $row['form_id'] ),
				'form_title'      => sanitize_text_field( $row['form_title'] ),
				'submitted_at'    => current_time( 'mysql' ),
				'fields'          => wp_json_encode( $row['fields'] ),
				'remote_ip'       => sanitize_text_field( $row['remote_ip'] ),
				'user_agent'      => sanitize_text_field( $row['user_agent'] ),
				'sheets_status'   => sanitize_key( $row['sheets_status'] ),
				'sheets_response' => '',
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		return $ok ? (int) $wpdb->insert_id : false;
	}

	/**
	 * Update webhook status for a row.
	 *
	 * @param int    $id       Row ID.
	 * @param string $status   sent|failed|disabled|pending.
	 * @param string $response Short response note.
	 */
	public static function set_sheets_status( $id, $status, $response = '' ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- write to the plugin's own custom table.
		$wpdb->update(
			self::table(),
			array(
				'sheets_status'   => sanitize_key( $status ),
				'sheets_response' => mb_substr( sanitize_text_field( $response ), 0, 500 ),
			),
			array( 'id' => absint( $id ) ),
			array( '%s', '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Fetch one submission.
	 *
	 * @param int $id Row ID.
	 * @return object|null
	 */
	public static function get( $id ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom table; row is fetched fresh on demand.
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', self::table(), absint( $id ) ) );
	}

	/**
	 * Query submissions.
	 *
	 * @param array $args form_id, search, per_page, paged, orderby, order.
	 * @return array{items: array, total: int}
	 */
	public static function query( $args = array() ) {
		global $wpdb;

		$defaults = array(
			'form_id'  => 0,
			'search'   => '',
			'per_page' => 20,
			'paged'    => 1,
			'orderby'  => 'submitted_at',
			'order'    => 'DESC',
		);
		$args     = wp_parse_args( $args, $defaults );

		$table   = self::table();
		$form_id = absint( $args['form_id'] );
		$like    = ( '' !== $args['search'] ) ? '%' . $wpdb->esc_like( $args['search'] ) . '%' : '';

		$orderby = in_array( $args['orderby'], array( 'id', 'form_id', 'submitted_at', 'sheets_status' ), true ) ? $args['orderby'] : 'submitted_at';
		$order   = ( 'ASC' === strtoupper( $args['order'] ) ) ? 'ASC' : 'DESC';

		$limit  = max( 1, absint( $args['per_page'] ) );
		$offset = ( max( 1, absint( $args['paged'] ) ) - 1 ) * $limit;

		// Every query below is a literal string with explicit placeholders.
		// $orderby is restricted by the in_array() whitelist above to one of four
		// hardcoded column names and $order to ASC|DESC — they can never carry
		// user input, so interpolating them is safe by construction.
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
		if ( $form_id && '' !== $like ) {
			$total = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE form_id = %d AND fields LIKE %s', $table, $form_id, $like ) );
			$items = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM %i WHERE form_id = %d AND fields LIKE %s ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d", $table, $form_id, $like, $limit, $offset ) );
		} elseif ( $form_id ) {
			$total = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE form_id = %d', $table, $form_id ) );
			$items = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM %i WHERE form_id = %d ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d", $table, $form_id, $limit, $offset ) );
		} elseif ( '' !== $like ) {
			$total = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE fields LIKE %s', $table, $like ) );
			$items = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM %i WHERE fields LIKE %s ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d", $table, $like, $limit, $offset ) );
		} else {
			$total = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $table ) );
			$items = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM %i ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d", $table, $limit, $offset ) );
		}
		// phpcs:enable

		return array(
			'items' => $items ? $items : array(),
			'total' => $total,
		);
	}

	/**
	 * Delete submissions.
	 *
	 * @param int[] $ids Row IDs.
	 * @return int Rows deleted.
	 */
	public static function delete( $ids ) {
		global $wpdb;

		$ids = array_filter( array_map( 'absint', (array) $ids ) );
		if ( ! $ids ) {
			return 0;
		}

		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		// $placeholders is only a run of literal %d markers sized to $ids — safe by construction.
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (int) $wpdb->query( $wpdb->prepare( "DELETE FROM %i WHERE id IN ({$placeholders})", array_merge( array( self::table() ), $ids ) ) );
	}

	/**
	 * Distinct forms that have submissions.
	 *
	 * @return array of objects {form_id, form_title}
	 */
	public static function forms() {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom table; small aggregate for admin filter dropdown.
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT form_id, MAX(form_title) AS form_title FROM %i GROUP BY form_id ORDER BY form_title', self::table() ) );
		return $rows ? $rows : array();
	}
}
