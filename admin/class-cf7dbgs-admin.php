<?php
/**
 * Admin UI: submissions list, detail view, settings, CSV export.
 *
 * @package cf7-db-gsheets
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class CF7DBGS_Admin
 */
class CF7DBGS_Admin {

	const CAPABILITY = 'manage_options';
	const PAGE_SLUG  = 'cf7dbgs-submissions';

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_post_cf7dbgs_export', array( __CLASS__, 'export_csv' ) );
		add_action( 'admin_init', array( __CLASS__, 'handle_row_actions' ) );
	}

	/**
	 * Add admin menu pages.
	 */
	public static function menu() {
		add_menu_page(
			__( 'CF7 Submissions', 'cf7-db-gsheets' ),
			__( 'CF7 Submissions', 'cf7-db-gsheets' ),
			self::CAPABILITY,
			self::PAGE_SLUG,
			array( __CLASS__, 'render_page' ),
			'dashicons-feedback',
			30
		);

		add_submenu_page(
			self::PAGE_SLUG,
			__( 'Settings', 'cf7-db-gsheets' ),
			__( 'Settings', 'cf7-db-gsheets' ),
			self::CAPABILITY,
			'cf7dbgs-settings',
			array( __CLASS__, 'render_settings' )
		);
	}

	/**
	 * Register the settings option.
	 */
	public static function register_settings() {
		register_setting(
			'cf7dbgs_settings_group',
			CF7DBGS_OPTION,
			array( 'sanitize_callback' => array( __CLASS__, 'sanitize_settings' ) )
		);
	}

	/**
	 * Sanitize settings input.
	 *
	 * @param array $input Raw input.
	 * @return array
	 */
	public static function sanitize_settings( $input ) {
		$input = is_array( $input ) ? $input : array();
		return array(
			'store_db'     => empty( $input['store_db'] ) ? 0 : 1,
			'send_webhook' => empty( $input['send_webhook'] ) ? 0 : 1,
			'webhook_url'  => isset( $input['webhook_url'] ) ? esc_url_raw( trim( $input['webhook_url'] ) ) : '',
			'field_map'    => isset( $input['field_map'] ) ? sanitize_textarea_field( $input['field_map'] ) : '',
			'store_ip'     => empty( $input['store_ip'] ) ? 0 : 1,
			'store_ua'     => empty( $input['store_ua'] ) ? 0 : 1,
		);
	}

	/**
	 * Handle resend / delete row actions (GET with nonce).
	 */
	public static function handle_row_actions() {
		if ( ! isset( $_GET['page'] ) || self::PAGE_SLUG !== $_GET['page'] || ! isset( $_GET['cf7dbgs_action'], $_GET['id'] ) ) {
			return;
		}
		if ( ! current_user_can( self::CAPABILITY ) ) {
			return;
		}

		$action = sanitize_key( wp_unslash( $_GET['cf7dbgs_action'] ) );
		$id     = absint( $_GET['id'] );

		check_admin_referer( 'cf7dbgs_' . $action . '_' . $id );

		$notice = '';
		if ( 'resend' === $action ) {
			$notice = CF7DBGS_Webhook::resend( $id ) ? 'resent' : 'resend_failed';
		} elseif ( 'delete' === $action ) {
			CF7DBGS_DB::delete( array( $id ) );
			$notice = 'deleted';
		}

		wp_safe_redirect( add_query_arg( array( 'page' => self::PAGE_SLUG, 'cf7dbgs_notice' => $notice ), admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Render submissions page (list or detail).
	 */
	public static function render_page() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			return;
		}

		if ( isset( $_GET['view'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			self::render_detail( absint( $_GET['view'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		self::render_list();
	}

	/**
	 * Render the submissions list.
	 */
	public static function render_list() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$form_id = isset( $_GET['form_id'] ) ? absint( $_GET['form_id'] ) : 0;
		$search  = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		$paged   = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
		$notice  = isset( $_GET['cf7dbgs_notice'] ) ? sanitize_key( $_GET['cf7dbgs_notice'] ) : '';
		// phpcs:enable

		$per_page = 20;
		$result   = CF7DBGS_DB::query(
			array(
				'form_id'  => $form_id,
				'search'   => $search,
				'per_page' => $per_page,
				'paged'    => $paged,
			)
		);

		$total_pages = (int) ceil( $result['total'] / $per_page );

		$notices = array(
			'resent'        => array( 'success', __( 'Submission resent to Google Sheets.', 'cf7-db-gsheets' ) ),
			'resend_failed' => array( 'error', __( 'Resend failed — check the webhook URL in Settings.', 'cf7-db-gsheets' ) ),
			'deleted'       => array( 'success', __( 'Submission deleted.', 'cf7-db-gsheets' ) ),
		);

		echo '<div class="wrap"><h1 class="wp-heading-inline">' . esc_html__( 'CF7 Submissions', 'cf7-db-gsheets' ) . '</h1>';

		$export_url = wp_nonce_url(
			add_query_arg(
				array(
					'action'  => 'cf7dbgs_export',
					'form_id' => $form_id,
				),
				admin_url( 'admin-post.php' )
			),
			'cf7dbgs_export'
		);
		echo ' <a href="' . esc_url( $export_url ) . '" class="page-title-action">' . esc_html__( 'Export CSV', 'cf7-db-gsheets' ) . '</a><hr class="wp-header-end">';

		if ( $notice && isset( $notices[ $notice ] ) ) {
			printf(
				'<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
				esc_attr( $notices[ $notice ][0] ),
				esc_html( $notices[ $notice ][1] )
			);
		}

		// Filter bar.
		echo '<form method="get" style="margin:12px 0;">';
		echo '<input type="hidden" name="page" value="' . esc_attr( self::PAGE_SLUG ) . '">';
		echo '<select name="form_id"><option value="0">' . esc_html__( 'All forms', 'cf7-db-gsheets' ) . '</option>';
		foreach ( CF7DBGS_DB::forms() as $form ) {
			printf(
				'<option value="%1$d" %2$s>%3$s</option>',
				(int) $form->form_id,
				selected( $form_id, (int) $form->form_id, false ),
				esc_html( $form->form_title ? $form->form_title : ( '#' . $form->form_id ) )
			);
		}
		echo '</select> ';
		echo '<input type="search" name="s" value="' . esc_attr( $search ) . '" placeholder="' . esc_attr__( 'Search fields…', 'cf7-db-gsheets' ) . '"> ';
		submit_button( __( 'Filter', 'cf7-db-gsheets' ), 'secondary', '', false );
		echo '</form>';

		// Table.
		echo '<table class="widefat striped"><thead><tr>';
		echo '<th>' . esc_html__( 'ID', 'cf7-db-gsheets' ) . '</th>';
		echo '<th>' . esc_html__( 'Date', 'cf7-db-gsheets' ) . '</th>';
		echo '<th>' . esc_html__( 'Form', 'cf7-db-gsheets' ) . '</th>';
		echo '<th>' . esc_html__( 'Summary', 'cf7-db-gsheets' ) . '</th>';
		echo '<th>' . esc_html__( 'Sheets', 'cf7-db-gsheets' ) . '</th>';
		echo '<th>' . esc_html__( 'Actions', 'cf7-db-gsheets' ) . '</th>';
		echo '</tr></thead><tbody>';

		if ( ! $result['items'] ) {
			echo '<tr><td colspan="6">' . esc_html__( 'No submissions yet.', 'cf7-db-gsheets' ) . '</td></tr>';
		}

		foreach ( $result['items'] as $row ) {
			$fields  = json_decode( $row->fields, true );
			$summary = array();
			if ( is_array( $fields ) ) {
				foreach ( array_slice( $fields, 0, 3, true ) as $k => $v ) {
					$v         = is_array( $v ) ? implode( ', ', $v ) : $v;
					$summary[] = $k . ': ' . wp_html_excerpt( $v, 40, '…' );
				}
			}

			$view_url   = add_query_arg( array( 'page' => self::PAGE_SLUG, 'view' => $row->id ), admin_url( 'admin.php' ) );
			$resend_url = wp_nonce_url( add_query_arg( array( 'page' => self::PAGE_SLUG, 'cf7dbgs_action' => 'resend', 'id' => $row->id ), admin_url( 'admin.php' ) ), 'cf7dbgs_resend_' . $row->id );
			$delete_url = wp_nonce_url( add_query_arg( array( 'page' => self::PAGE_SLUG, 'cf7dbgs_action' => 'delete', 'id' => $row->id ), admin_url( 'admin.php' ) ), 'cf7dbgs_delete_' . $row->id );

			echo '<tr>';
			echo '<td>' . (int) $row->id . '</td>';
			echo '<td>' . esc_html( $row->submitted_at ) . '</td>';
			echo '<td>' . esc_html( $row->form_title ? $row->form_title : ( '#' . $row->form_id ) ) . '</td>';
			echo '<td>' . esc_html( implode( ' | ', $summary ) ) . '</td>';
			echo '<td>' . esc_html( $row->sheets_status ) . '</td>';
			echo '<td><a href="' . esc_url( $view_url ) . '">' . esc_html__( 'View', 'cf7-db-gsheets' ) . '</a> | ';
			echo '<a href="' . esc_url( $resend_url ) . '">' . esc_html__( 'Resend', 'cf7-db-gsheets' ) . '</a> | ';
			echo '<a href="' . esc_url( $delete_url ) . '" onclick="return confirm(\'' . esc_js( __( 'Delete this submission?', 'cf7-db-gsheets' ) ) . '\');" style="color:#b32d2e;">' . esc_html__( 'Delete', 'cf7-db-gsheets' ) . '</a></td>';
			echo '</tr>';
		}

		echo '</tbody></table>';

		// Pagination.
		if ( $total_pages > 1 ) {
			echo '<div class="tablenav"><div class="tablenav-pages">';
			echo wp_kses_post(
				paginate_links(
					array(
						'base'    => add_query_arg( 'paged', '%#%' ),
						'format'  => '',
						'current' => $paged,
						'total'   => $total_pages,
					)
				)
			);
			echo '</div></div>';
		}

		echo '</div>';
	}

	/**
	 * Render one submission.
	 *
	 * @param int $id Row ID.
	 */
	public static function render_detail( $id ) {
		$row = CF7DBGS_DB::get( $id );

		echo '<div class="wrap"><h1>' . esc_html__( 'Submission', 'cf7-db-gsheets' ) . ' #' . (int) $id . '</h1>';
		echo '<p><a href="' . esc_url( add_query_arg( 'page', self::PAGE_SLUG, admin_url( 'admin.php' ) ) ) . '">&larr; ' . esc_html__( 'Back to list', 'cf7-db-gsheets' ) . '</a></p>';

		if ( ! $row ) {
			echo '<p>' . esc_html__( 'Not found.', 'cf7-db-gsheets' ) . '</p></div>';
			return;
		}

		$fields = json_decode( $row->fields, true );

		echo '<table class="widefat striped" style="max-width:800px;"><tbody>';
		echo '<tr><th style="width:200px;">' . esc_html__( 'Date', 'cf7-db-gsheets' ) . '</th><td>' . esc_html( $row->submitted_at ) . '</td></tr>';
		echo '<tr><th>' . esc_html__( 'Form', 'cf7-db-gsheets' ) . '</th><td>' . esc_html( $row->form_title ) . ' (#' . (int) $row->form_id . ')</td></tr>';

		if ( is_array( $fields ) ) {
			foreach ( $fields as $k => $v ) {
				$v = is_array( $v ) ? implode( ', ', $v ) : $v;
				echo '<tr><th>' . esc_html( $k ) . '</th><td>' . nl2br( esc_html( $v ) ) . '</td></tr>';
			}
		}

		if ( $row->remote_ip ) {
			echo '<tr><th>' . esc_html__( 'IP address', 'cf7-db-gsheets' ) . '</th><td>' . esc_html( $row->remote_ip ) . '</td></tr>';
		}
		if ( $row->user_agent ) {
			echo '<tr><th>' . esc_html__( 'User agent', 'cf7-db-gsheets' ) . '</th><td>' . esc_html( $row->user_agent ) . '</td></tr>';
		}

		echo '<tr><th>' . esc_html__( 'Sheets status', 'cf7-db-gsheets' ) . '</th><td>' . esc_html( $row->sheets_status ) . '</td></tr>';
		if ( $row->sheets_response ) {
			echo '<tr><th>' . esc_html__( 'Sheets response', 'cf7-db-gsheets' ) . '</th><td><code>' . esc_html( $row->sheets_response ) . '</code></td></tr>';
		}

		echo '</tbody></table></div>';
	}

	/**
	 * Render the settings page.
	 */
	public static function render_settings() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			return;
		}

		$s = cf7dbgs_get_settings();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'CF7 Database & Google Sheets — Settings', 'cf7-db-gsheets' ); ?></h1>
			<form method="post" action="options.php">
				<?php settings_fields( 'cf7dbgs_settings_group' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Store in database', 'cf7-db-gsheets' ); ?></th>
						<td><label><input type="checkbox" name="<?php echo esc_attr( CF7DBGS_OPTION ); ?>[store_db]" value="1" <?php checked( $s['store_db'] ); ?>>
							<?php esc_html_e( 'Save every CF7 submission to the WordPress database', 'cf7-db-gsheets' ); ?></label></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Send to Google Sheets', 'cf7-db-gsheets' ); ?></th>
						<td><label><input type="checkbox" name="<?php echo esc_attr( CF7DBGS_OPTION ); ?>[send_webhook]" value="1" <?php checked( $s['send_webhook'] ); ?>>
							<?php esc_html_e( 'Forward submissions to the webhook URL below', 'cf7-db-gsheets' ); ?></label></td>
					</tr>
					<tr>
						<th scope="row"><label for="cf7dbgs_webhook_url"><?php esc_html_e( 'Webhook URL', 'cf7-db-gsheets' ); ?></label></th>
						<td>
							<input type="url" class="large-text" id="cf7dbgs_webhook_url" name="<?php echo esc_attr( CF7DBGS_OPTION ); ?>[webhook_url]" value="<?php echo esc_attr( $s['webhook_url'] ); ?>" placeholder="https://script.google.com/macros/s/…/exec">
							<p class="description"><?php esc_html_e( 'Google Apps Script Web App deployment URL (see readme for the companion script).', 'cf7-db-gsheets' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="cf7dbgs_field_map"><?php esc_html_e( 'Field mapping', 'cf7-db-gsheets' ); ?></label></th>
						<td>
							<textarea id="cf7dbgs_field_map" class="large-text code" rows="8" name="<?php echo esc_attr( CF7DBGS_OPTION ); ?>[field_map]" placeholder="First Name=firstName&#10;Last Name=lastName&#10;your-email=email"><?php echo esc_textarea( $s['field_map'] ); ?></textarea>
							<p class="description"><?php esc_html_e( 'One mapping per line: cf7-field-name=payloadKey. Matching is forgiving — case-insensitive, spaces and underscores count as hyphens (so "First Name" matches "first-name"). Unmapped fields are sent with their original names. Lines starting with # are ignored.', 'cf7-db-gsheets' ); ?></p>
							<?php self::render_detected_fields(); ?>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Privacy', 'cf7-db-gsheets' ); ?></th>
						<td>
							<label><input type="checkbox" name="<?php echo esc_attr( CF7DBGS_OPTION ); ?>[store_ip]" value="1" <?php checked( $s['store_ip'] ); ?>> <?php esc_html_e( 'Store submitter IP address', 'cf7-db-gsheets' ); ?></label><br>
							<label><input type="checkbox" name="<?php echo esc_attr( CF7DBGS_OPTION ); ?>[store_ua]" value="1" <?php checked( $s['store_ua'] ); ?>> <?php esc_html_e( 'Store submitter user agent', 'cf7-db-gsheets' ); ?></label>
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * List every CF7 form's field names so users can build the mapping
	 * without guessing. Fields are read live from Contact Form 7.
	 */
	public static function render_detected_fields() {
		if ( ! class_exists( 'WPCF7_ContactForm' ) ) {
			return;
		}

		$forms = WPCF7_ContactForm::find( array( 'posts_per_page' => 50 ) );
		if ( ! $forms ) {
			return;
		}

		echo '<div style="margin-top:10px;padding:10px 14px;background:#f6f7f7;border:1px solid #dcdcde;border-radius:4px;max-width:640px;">';
		echo '<strong>' . esc_html__( 'Detected Contact Form 7 fields', 'cf7-db-gsheets' ) . '</strong>';
		echo '<p class="description" style="margin:4px 0 8px;">' . esc_html__( 'Click "Auto-map" to fill the mapping for a form automatically, or click a field name to add it by hand.', 'cf7-db-gsheets' ) . '</p>';

		foreach ( $forms as $form ) {
			$fields = array();
			foreach ( $form->scan_form_tags() as $tag ) {
				if ( empty( $tag->name ) || 'submit' === $tag->basetype ) {
					continue;
				}
				$fields[] = array(
					'name' => $tag->name,
					'type' => $tag->basetype,
				);
			}
			if ( ! $fields ) {
				continue;
			}

			echo '<p style="margin:6px 0;"><em>' . esc_html( $form->title() ) . ':</em> ';
			printf(
				'<button type="button" class="button button-small cf7dbgs-automap" data-form="%1$s" data-fields="%2$s">%3$s</button><br>',
				esc_attr( $form->title() ),
				esc_attr( wp_json_encode( $fields ) ),
				esc_html__( 'Auto-map', 'cf7-db-gsheets' )
			);
			foreach ( $fields as $f ) {
				printf(
					'<code class="cf7dbgs-field" data-field="%1$s" style="cursor:pointer;margin:2px 6px 2px 0;display:inline-block;" title="%2$s">%1$s</code>',
					esc_attr( $f['name'] ),
					esc_attr( $f['type'] )
				);
			}
			echo '</p>';
		}

		echo '</div>';
		?>
		<script>
		(function () {
			var ta = function () { return document.getElementById('cf7dbgs_field_map'); };

			// Suggest a payload key from a CF7 field name + type.
			function suggest(name, type) {
				if (type === 'email') { return 'email'; }
				if (type === 'tel') { return 'phone'; }
				var n = name.toLowerCase()
					.replace(/^your[-_]/, '')   // your-message -> message
					.replace(/[-_]\d+$/, '');   // checkbox-123 -> checkbox
				return n.split(/[-_\s]+/).map(function (p, i) {
					return i ? p.charAt(0).toUpperCase() + p.slice(1) : p;
				}).join('');
			}

			function normalize(k) { return k.toLowerCase().trim().replace(/[\s_]+/g, '-'); }

			function existingKeys() {
				var keys = {};
				ta().value.split(/\n/).forEach(function (line) {
					line = line.trim();
					if (!line || line.indexOf('#') === 0 || line.indexOf('=') === -1) { return; }
					keys[normalize(line.split('=')[0])] = true;
				});
				return keys;
			}

			document.addEventListener('click', function (e) {
				var t = e.target;
				if (t.classList && t.classList.contains('cf7dbgs-automap')) {
					var fields = JSON.parse(t.getAttribute('data-fields'));
					var have = existingKeys();
					var lines = [];
					fields.forEach(function (f) {
						if (have[normalize(f.name)]) { return; }       // already mapped
						var key = suggest(f.name, f.type);
						if (key === f.name) { return; }                 // passthrough, no line needed
						lines.push(f.name + '=' + key);
					});
					if (lines.length) {
						var header = '# ' + t.getAttribute('data-form');
						ta().value = (ta().value.replace(/\s+$/, '') + '\n' + header + '\n' + lines.join('\n')).replace(/^\n/, '');
					}
					ta().focus();
					return;
				}
				if (t.classList && t.classList.contains('cf7dbgs-field')) {
					ta().value = (ta().value.replace(/\s+$/, '') + '\n' + t.getAttribute('data-field') + '=').replace(/^\n/, '');
					ta().focus();
					ta().setSelectionRange(ta().value.length, ta().value.length);
				}
			});
		})();
		</script>
		<?php
	}

	/**
	 * Stream all (filtered) submissions as CSV.
	 */
	public static function export_csv() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'cf7-db-gsheets' ) );
		}
		check_admin_referer( 'cf7dbgs_export' );

		$form_id = isset( $_GET['form_id'] ) ? absint( $_GET['form_id'] ) : 0;

		$result = CF7DBGS_DB::query(
			array(
				'form_id'  => $form_id,
				'per_page' => 100000,
				'paged'    => 1,
				'orderby'  => 'id',
				'order'    => 'ASC',
			)
		);

		// Union of field keys across rows -> CSV columns.
		$field_keys = array();
		$decoded    = array();
		foreach ( $result['items'] as $row ) {
			$fields               = json_decode( $row->fields, true );
			$fields               = is_array( $fields ) ? $fields : array();
			$decoded[ $row->id ]  = $fields;
			$field_keys           = array_unique( array_merge( $field_keys, array_keys( $fields ) ) );
		}

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=cf7-submissions-' . gmdate( 'Ymd-His' ) . '.csv' );

		$out = fopen( 'php://output', 'w' );
		fputcsv( $out, array_merge( array( 'id', 'date', 'form' ), $field_keys, array( 'ip', 'user_agent', 'sheets_status' ) ) );

		foreach ( $result['items'] as $row ) {
			$line = array( $row->id, $row->submitted_at, $row->form_title );
			foreach ( $field_keys as $key ) {
				$v      = isset( $decoded[ $row->id ][ $key ] ) ? $decoded[ $row->id ][ $key ] : '';
				$line[] = is_array( $v ) ? implode( ', ', $v ) : $v;
			}
			$line[] = $row->remote_ip;
			$line[] = $row->user_agent;
			$line[] = $row->sheets_status;
			fputcsv( $out, $line );
		}

		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		exit;
	}
}
