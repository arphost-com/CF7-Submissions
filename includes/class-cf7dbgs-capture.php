<?php
/**
 * Captures Contact Form 7 submissions.
 *
 * @package cf7-db-gsheets
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class CF7DBGS_Capture
 */
class CF7DBGS_Capture {

	/**
	 * Hook into CF7.
	 */
	public static function init() {
		// before_send_mail so submissions are stored even if SMTP fails.
		add_action( 'wpcf7_before_send_mail', array( __CLASS__, 'handle_submission' ), 10, 1 );
	}

	/**
	 * Handle a CF7 submission: store in DB, forward to webhook.
	 *
	 * @param WPCF7_ContactForm $contact_form The submitted form.
	 */
	public static function handle_submission( $contact_form ) {
		$submission = class_exists( 'WPCF7_Submission' ) ? WPCF7_Submission::get_instance() : null;
		if ( ! $submission ) {
			return;
		}

		$settings = cf7dbgs_get_settings();
		$fields   = self::clean_fields( $submission->get_posted_data() );

		/**
		 * Filter whether this submission should be captured at all.
		 *
		 * @param bool              $capture      Default true.
		 * @param WPCF7_ContactForm $contact_form Form object.
		 * @param array             $fields       Cleaned posted fields.
		 */
		if ( ! apply_filters( 'cf7dbgs_capture_submission', true, $contact_form, $fields ) ) {
			return;
		}

		$webhook_enabled = ! empty( $settings['send_webhook'] ) && ! empty( $settings['webhook_url'] );

		$row_id = false;
		if ( ! empty( $settings['store_db'] ) ) {
			/**
			 * Filter the fields stored in the database.
			 *
			 * @param array             $fields       Cleaned posted fields.
			 * @param WPCF7_ContactForm $contact_form Form object.
			 */
			$store_fields = apply_filters( 'cf7dbgs_store_fields', $fields, $contact_form );

			$row_id = CF7DBGS_DB::insert(
				array(
					'form_id'       => $contact_form->id(),
					'form_title'    => $contact_form->title(),
					'fields'        => $store_fields,
					'remote_ip'     => ! empty( $settings['store_ip'] ) ? (string) $submission->get_meta( 'remote_ip' ) : '',
					'user_agent'    => ! empty( $settings['store_ua'] ) ? (string) $submission->get_meta( 'user_agent' ) : '',
					'sheets_status' => $webhook_enabled ? 'pending' : 'disabled',
				)
			);

			/**
			 * Fires after a submission row is stored.
			 *
			 * @param int|false         $row_id       Insert ID or false on failure.
			 * @param array             $store_fields Stored fields.
			 * @param WPCF7_ContactForm $contact_form Form object.
			 */
			do_action( 'cf7dbgs_after_store', $row_id, $store_fields, $contact_form );
		}

		if ( $webhook_enabled ) {
			CF7DBGS_Webhook::send( $fields, $contact_form, $row_id );
		}
	}

	/**
	 * Strip CF7 internals and normalize posted data.
	 *
	 * @param array $posted Raw posted data from WPCF7_Submission.
	 * @return array
	 */
	public static function clean_fields( $posted ) {
		$fields = array();

		$skip = array( 'g-recaptcha-response', 'cf-turnstile-response', 'h-captcha-response', 'frc-captcha-response' );

		foreach ( (array) $posted as $key => $value ) {
			// Skip CF7 internals and captcha tokens (recaptcha, Turnstile, hCaptcha…).
			if ( 0 === strpos( $key, '_' ) || in_array( $key, $skip, true ) || false !== strpos( $key, 'captcha' ) ) {
				continue;
			}

			if ( is_array( $value ) ) {
				$fields[ $key ] = array_map( 'sanitize_textarea_field', $value );
			} else {
				$fields[ $key ] = sanitize_textarea_field( (string) $value );
			}
		}

		return $fields;
	}
}
