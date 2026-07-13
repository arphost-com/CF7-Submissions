<?php
/**
 * Direct Google Sheets API delivery via a service account.
 * No Apps Script, no deployments: paste the service-account JSON key in
 * Settings and share the spreadsheet with the service account's email.
 *
 * @package cf7-db-gsheets
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class CF7DBGS_Sheets_API
 */
class CF7DBGS_Sheets_API {

	const TOKEN_TRANSIENT = 'cf7dbgs_sa_token';
	const META_KEYS       = array( 'formTitle', 'formId' );

	/**
	 * Deliver a payload to Google Sheets.
	 *
	 * @param array     $payload Mapped payload (includes formTitle/formId).
	 * @param int|false $row_id  DB row ID for status tracking.
	 * @return bool Success.
	 */
	public static function deliver( $payload, $row_id = false ) {
		$settings = cf7dbgs_get_settings();

		$sa = json_decode( (string) $settings['sa_json'], true );
		if ( empty( $sa['client_email'] ) || empty( $sa['private_key'] ) ) {
			self::record( $row_id, 'failed', 'Service account JSON missing or invalid — paste it in Settings.' );
			return false;
		}

		$token = self::access_token( $sa );
		if ( is_wp_error( $token ) ) {
			self::record( $row_id, 'failed', 'Auth: ' . $token->get_error_message() );
			return false;
		}

		list( $sheet_id, $tab ) = self::route( $payload, $settings );
		if ( ! $sheet_id ) {
			self::record( $row_id, 'failed', 'No Spreadsheet ID configured in Settings.' );
			return false;
		}

		$result = self::append( $token, $sheet_id, $tab, $payload );
		if ( is_wp_error( $result ) ) {
			self::record( $row_id, 'failed', $result->get_error_message() );
			return false;
		}

		self::record( $row_id, 'sent', 'Appended to "' . $tab . '"' );
		return true;
	}

	/**
	 * Resolve destination spreadsheet + tab for a payload.
	 *
	 * Routes format (Settings), one per line:
	 *   Form Title=Tab Name
	 *   Form Title=SPREADSHEET_ID!Tab Name   (different spreadsheet)
	 * Unrouted forms: default spreadsheet, tab named after the form.
	 *
	 * @param array $payload  Payload with formTitle.
	 * @param array $settings Plugin settings.
	 * @return array [ sheet_id, tab ]
	 */
	public static function route( $payload, $settings ) {
		$title    = isset( $payload['formTitle'] ) ? (string) $payload['formTitle'] : '';
		$sheet_id = trim( (string) $settings['sheet_id'] );
		$tab      = '' !== $title ? $title : 'Submissions';

		foreach ( preg_split( '/[\r\n]+/', (string) $settings['sheet_routes'] ) as $line ) {
			$line = trim( $line );
			if ( '' === $line || 0 === strpos( $line, '#' ) || false === strpos( $line, '=' ) ) {
				continue;
			}
			list( $from, $to ) = array_map( 'trim', explode( '=', $line, 2 ) );
			if ( 0 !== strcasecmp( $from, $title ) || '' === $to ) {
				continue;
			}
			if ( false !== strpos( $to, '!' ) ) {
				list( $sid, $tname ) = array_map( 'trim', explode( '!', $to, 2 ) );
				if ( '' !== $sid ) {
					$sheet_id = $sid;
				}
				if ( '' !== $tname ) {
					$tab = $tname;
				}
			} else {
				$tab = $to;
			}
			break;
		}

		return array( $sheet_id, $tab );
	}

	/**
	 * Get an OAuth2 access token for the service account (cached ~55 min).
	 *
	 * @param array $sa Decoded service-account JSON.
	 * @return string|WP_Error
	 */
	protected static function access_token( $sa ) {
		$cached = get_transient( self::TOKEN_TRANSIENT );
		if ( is_array( $cached ) && ! empty( $cached['token'] ) && $cached['email'] === $sa['client_email'] ) {
			return $cached['token'];
		}

		if ( ! function_exists( 'openssl_sign' ) ) {
			return new WP_Error( 'cf7dbgs_no_openssl', 'PHP OpenSSL extension is required for Google API mode.' );
		}

		$now    = time();
		$header = self::b64url( wp_json_encode( array( 'alg' => 'RS256', 'typ' => 'JWT' ) ) );
		$claims = self::b64url(
			wp_json_encode(
				array(
					'iss'   => $sa['client_email'],
					'scope' => 'https://www.googleapis.com/auth/spreadsheets',
					'aud'   => 'https://oauth2.googleapis.com/token',
					'exp'   => $now + 3600,
					'iat'   => $now,
				)
			)
		);

		$signature = '';
		if ( ! openssl_sign( $header . '.' . $claims, $signature, $sa['private_key'], 'sha256WithRSAEncryption' ) ) {
			return new WP_Error( 'cf7dbgs_sign_failed', 'Could not sign JWT — check the private key in the service account JSON.' );
		}

		$response = wp_remote_post(
			'https://oauth2.googleapis.com/token',
			array(
				'timeout' => 15,
				'body'    => array(
					'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
					'assertion'  => $header . '.' . $claims . '.' . self::b64url( $signature ),
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( empty( $body['access_token'] ) ) {
			$err = isset( $body['error_description'] ) ? $body['error_description'] : wp_remote_retrieve_body( $response );
			return new WP_Error( 'cf7dbgs_token_failed', 'Google rejected the service account: ' . $err );
		}

		$ttl = isset( $body['expires_in'] ) ? max( 60, (int) $body['expires_in'] - 60 ) : 3000;
		set_transient(
			self::TOKEN_TRANSIENT,
			array(
				'token' => $body['access_token'],
				'email' => $sa['client_email'],
			),
			$ttl
		);

		return $body['access_token'];
	}

	/**
	 * Append one payload row; creates the tab and manages header columns.
	 *
	 * @param string $token    Access token.
	 * @param string $sheet_id Spreadsheet ID.
	 * @param string $tab      Tab title.
	 * @param array  $payload  Payload.
	 * @return true|WP_Error
	 */
	protected static function append( $token, $sheet_id, $tab, $payload ) {
		$data = $payload;
		foreach ( self::META_KEYS as $k ) {
			unset( $data[ $k ] );
		}

		$base = 'https://sheets.googleapis.com/v4/spreadsheets/' . rawurlencode( $sheet_id );

		// Ensure the tab exists.
		$meta = self::request( $token, 'GET', $base . '?fields=sheets.properties.title' );
		if ( is_wp_error( $meta ) ) {
			return $meta;
		}
		$titles = array();
		if ( ! empty( $meta['sheets'] ) ) {
			foreach ( $meta['sheets'] as $s ) {
				$titles[] = $s['properties']['title'];
			}
		}
		if ( ! in_array( $tab, $titles, true ) ) {
			$created = self::request(
				$token,
				'POST',
				$base . ':batchUpdate',
				array( 'requests' => array( array( 'addSheet' => array( 'properties' => array( 'title' => $tab ) ) ) ) )
			);
			if ( is_wp_error( $created ) ) {
				return $created;
			}
		}

		// Read header row.
		$range   = rawurlencode( $tab . '!1:1' );
		$got     = self::request( $token, 'GET', $base . '/values/' . $range );
		if ( is_wp_error( $got ) ) {
			return $got;
		}
		$headers = isset( $got['values'][0] ) ? array_map( 'strval', $got['values'][0] ) : array();

		if ( ! $headers ) {
			$headers = array_merge( array( 'Timestamp' ), array_keys( $data ) );
			$set     = self::request(
				$token,
				'PUT',
				$base . '/values/' . $range . '?valueInputOption=RAW',
				array( 'values' => array( $headers ) )
			);
			if ( is_wp_error( $set ) ) {
				return $set;
			}
		} else {
			$new_keys = array_diff( array_keys( $data ), $headers );
			if ( $new_keys ) {
				$headers = array_merge( $headers, array_values( $new_keys ) );
				$set     = self::request(
					$token,
					'PUT',
					$base . '/values/' . $range . '?valueInputOption=RAW',
					array( 'values' => array( $headers ) )
				);
				if ( is_wp_error( $set ) ) {
					return $set;
				}
			}
		}

		// Build the row aligned to headers.
		$row = array();
		foreach ( $headers as $h ) {
			if ( 'Timestamp' === $h ) {
				// WP timezone (Settings > General) with zone label, e.g. "2026-07-13 08:43:21 MDT".
				$row[] = function_exists( 'wp_date' ) ? wp_date( 'Y-m-d H:i:s T' ) : current_time( 'mysql' );
				continue;
			}
			$v     = isset( $data[ $h ] ) ? $data[ $h ] : '';
			$row[] = is_array( $v ) ? implode( ', ', $v ) : (string) $v;
		}

		$appended = self::request(
			$token,
			'POST',
			$base . '/values/' . $range . ':append?valueInputOption=RAW&insertDataOption=INSERT_ROWS',
			array( 'values' => array( $row ) )
		);

		return is_wp_error( $appended ) ? $appended : true;
	}

	/**
	 * Signed JSON request to the Sheets API.
	 *
	 * @param string     $token  Access token.
	 * @param string     $method HTTP method.
	 * @param string     $url    URL.
	 * @param array|null $body   JSON body.
	 * @return array|WP_Error Decoded response.
	 */
	protected static function request( $token, $method, $url, $body = null ) {
		$args = array(
			'method'  => $method,
			'timeout' => 15,
			'headers' => array(
				'Authorization' => 'Bearer ' . $token,
				'Content-Type'  => 'application/json',
			),
		);
		if ( null !== $body ) {
			$args['body'] = wp_json_encode( $body );
		}

		$response = wp_remote_request( $url, $args );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code    = (int) wp_remote_retrieve_response_code( $response );
		$decoded = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code < 200 || $code >= 300 ) {
			$msg = isset( $decoded['error']['message'] ) ? $decoded['error']['message'] : ( 'HTTP ' . $code );
			if ( 403 === $code ) {
				$msg .= ' — did you share the spreadsheet with the service account email?';
			}
			return new WP_Error( 'cf7dbgs_api_error', 'Sheets API: ' . $msg );
		}

		return is_array( $decoded ) ? $decoded : array();
	}

	/**
	 * Base64url encode.
	 *
	 * @param string $input Raw bytes.
	 * @return string
	 */
	protected static function b64url( $input ) {
		return rtrim( strtr( base64_encode( $input ), '+/', '-_' ), '=' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	}

	/**
	 * Record status on the DB row.
	 *
	 * @param int|false $row_id   Row ID.
	 * @param string    $status   Status.
	 * @param string    $response Note.
	 */
	protected static function record( $row_id, $status, $response ) {
		if ( $row_id ) {
			CF7DBGS_DB::set_sheets_status( $row_id, $status, $response );
		}
	}
}
