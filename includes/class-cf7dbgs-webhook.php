<?php
/**
 * Sends submissions to a Google Sheets webhook (Google Apps Script Web App).
 *
 * @package cf7-db-gsheets
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class CF7DBGS_Webhook
 */
class CF7DBGS_Webhook {

	/**
	 * Send a submission to the configured webhook.
	 *
	 * @param array                  $fields       Cleaned posted fields.
	 * @param WPCF7_ContactForm|null $contact_form Form object (null on resend).
	 * @param int|false              $row_id       DB row ID for status tracking, or false.
	 * @return bool Success.
	 */
	public static function send( $fields, $contact_form = null, $row_id = false ) {
		$settings = cf7dbgs_get_settings();
		$url      = esc_url_raw( $settings['webhook_url'] );

		if ( ! $url ) {
			return false;
		}

		$payload = self::map_fields( $fields, $settings['field_map'] );

		/**
		 * Filter the JSON payload sent to the webhook.
		 *
		 * @param array                  $payload      Mapped payload.
		 * @param array                  $fields       Original cleaned fields.
		 * @param WPCF7_ContactForm|null $contact_form Form object or null.
		 */
		$payload = apply_filters( 'cf7dbgs_webhook_payload', $payload, $fields, $contact_form );

		$args = array(
			'timeout'     => 15, // Apps Script cold starts can exceed 8s.
			// Do NOT auto-follow: Apps Script 302-redirects to
			// script.googleusercontent.com, which only accepts GET. WP's HTTP
			// library re-POSTs there and Google answers 400. We follow the
			// redirect manually with GET below.
			'redirection' => 0,
			'headers'     => array( 'Content-Type' => 'application/json; charset=utf-8' ),
			'body'        => wp_json_encode( $payload ),
		);

		/**
		 * Filter the wp_remote_post args for the webhook request.
		 *
		 * @param array  $args Request args.
		 * @param string $url  Webhook URL.
		 */
		$args = apply_filters( 'cf7dbgs_webhook_args', $args, $url );

		$response = wp_remote_post( $url, $args );

		if ( is_wp_error( $response ) ) {
			self::record( $row_id, 'failed', $response->get_error_message() );
			return false;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );

		// Follow the Apps Script redirect manually with GET.
		if ( in_array( $code, array( 301, 302, 303, 307, 308 ), true ) ) {
			$location = wp_remote_retrieve_header( $response, 'location' );
			if ( $location ) {
				$response = wp_remote_get( $location, array( 'timeout' => 15 ) );
				if ( is_wp_error( $response ) ) {
					self::record( $row_id, 'failed', $response->get_error_message() );
					return false;
				}
				$code = (int) wp_remote_retrieve_response_code( $response );
				$body = wp_remote_retrieve_body( $response );
			}
		}

		if ( $code < 200 || $code >= 300 ) {
			self::record( $row_id, 'failed', 'HTTP ' . $code . ': ' . $body );
			return false;
		}

		// Apps Script returns HTTP 200 even on errors — the real status is
		// in the JSON body: {"success":false,"error":"..."}.
		$json = json_decode( $body, true );
		if ( is_array( $json ) && isset( $json['success'] ) && ! $json['success'] ) {
			$error = isset( $json['error'] ) ? $json['error'] : 'Apps Script reported failure';
			self::record( $row_id, 'failed', 'Script error: ' . $error );
			return false;
		}

		self::record( $row_id, 'sent', $body );
		return true;
	}

	/**
	 * Resend a stored submission by row ID.
	 *
	 * @param int $row_id DB row ID.
	 * @return bool Success.
	 */
	public static function resend( $row_id ) {
		$row = CF7DBGS_DB::get( $row_id );
		if ( ! $row ) {
			return false;
		}

		$fields = json_decode( $row->fields, true );
		if ( ! is_array( $fields ) ) {
			return false;
		}

		return self::send( $fields, null, $row_id );
	}

	/**
	 * Apply the user-configured field map (one "cf7-field=payloadKey" per line).
	 * Unmapped fields are passed through under their original names.
	 *
	 * @param array  $fields   Cleaned posted fields.
	 * @param string $map_text Raw mapping text from settings.
	 * @return array
	 */
	public static function map_fields( $fields, $map_text ) {
		$map = array();

		foreach ( preg_split( '/[\r\n]+/', (string) $map_text ) as $line ) {
			$line = trim( $line );
			if ( '' === $line || false === strpos( $line, '=' ) || 0 === strpos( $line, '#' ) ) {
				continue;
			}
			list( $from, $to ) = array_map( 'trim', explode( '=', $line, 2 ) );
			if ( '' !== $from && '' !== $to ) {
				// Store under a normalized key so friendly names work:
				// "First Name" matches the CF7 field "first-name".
				$map[ self::normalize_key( $from ) ] = $to;
			}
		}

		$payload = array();
		foreach ( $fields as $key => $value ) {
			$norm    = self::normalize_key( $key );
			$out_key = isset( $map[ $norm ] ) ? $map[ $norm ] : $key;

			// CF7 selects post single values as one-element arrays, which
			// break Apps Script sheet.appendRow(). Flatten them; real
			// multi-value arrays (checkboxes) are kept as arrays.
			if ( is_array( $value ) && count( $value ) <= 1 ) {
				$value = $value ? reset( $value ) : '';
			}

			$payload[ $out_key ] = $value;
		}

		return $payload;
	}

	/**
	 * Normalize a field-map key for forgiving matching:
	 * lowercase, spaces/underscores treated as hyphens.
	 * "First Name", "first_name", and "first-name" all match.
	 *
	 * @param string $key Raw key.
	 * @return string
	 */
	public static function normalize_key( $key ) {
		return preg_replace( '/[\s_]+/', '-', strtolower( trim( (string) $key ) ) );
	}

	/**
	 * Record webhook status on the DB row, if one exists.
	 *
	 * @param int|false $row_id   Row ID.
	 * @param string    $status   Status.
	 * @param string    $response Response note.
	 */
	protected static function record( $row_id, $status, $response ) {
		if ( $row_id ) {
			CF7DBGS_DB::set_sheets_status( $row_id, $status, $response );
		}
	}
}
