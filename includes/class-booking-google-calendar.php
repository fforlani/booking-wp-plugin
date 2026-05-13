<?php
/**
 * Google Calendar integration for Booking System
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Booking_Google_Calendar {

	const CREDENTIALS_OPTION = 'booking_google_credentials';
	const ACCESS_TOKEN_OPTION = 'booking_google_access_token';
	const REFRESH_TOKEN_OPTION = 'booking_google_refresh_token';

	/**
	 * Add event to Google Calendar
	 */
	public static function add_event( $booking_id ) {

		$booking = Booking_DB::get_booking( $booking_id );
		if ( ! $booking ) {
			return false;
		}

		try {
			// Get access token
			$access_token = self::get_access_token();
			if ( ! $access_token ) {
				Booking_Logger::log_google_error( $booking_id, 'Token di accesso non disponibile' );
				return false;
			}

			// Create event
			$event = self::create_event_data( $booking );
			$calendar_id = Booking_Settings::get( 'google_calendar_id' );

			if ( ! $calendar_id ) {
				Booking_Logger::log_google_error( $booking_id, 'Calendar ID non configurato' );
				return false;
			}

			// Make API request
			$response = wp_remote_post(
				"https://www.googleapis.com/calendar/v3/calendars/{$calendar_id}/events",
				array(
					'headers' => array(
						'Authorization' => 'Bearer ' . $access_token,
						'Content-Type'  => 'application/json',
					),
					'body'    => wp_json_encode( $event ),
				)
			);

			if ( is_wp_error( $response ) ) {
				Booking_Logger::log_google_error( $booking_id, $response->get_error_message() );
				return false;
			}

			$body = wp_remote_retrieve_body( $response );
			$data = json_decode( $body );

			if ( isset( $data->id ) ) {
				Booking_DB::update_booking_google_event( $booking_id, $data->id );
				Booking_Logger::log_google_event_created( $booking_id, $data->id );
				return true;
			} else {
				Booking_Logger::log_google_error( $booking_id, 'Risposta API invalida: ' . $body );
				return false;
			}

		} catch ( Exception $e ) {
			Booking_Logger::log_google_error( $booking_id, $e->getMessage() );
			return false;
		}
	}

	/**
	 * Create event data for Google Calendar API
	 */
	private static function create_event_data( $booking ) {
		$date = $booking->booking_date;
		$time = $booking->time_slot;
		$slot_duration = intval( Booking_Settings::get( 'slot_duration_minutes', 60 ) );
		$timezone = new DateTimeZone(Booking_Settings::get_timezone());
		
		// Create start and end times
		$start_datetime = (new DateTime("{$date} {$time}", $timezone))->format("Y-m-d\\TH:i:sP");
		$end_time = date( 'H:i', strtotime( $time . " + {$slot_duration} minutes" ) );
		$end_datetime = (new DateTime("{$date} {$end_time}", $timezone))->format("Y-m-d\\TH:i:sP");

		$description = "Prenotazione di {$booking->client_name} {$booking->client_surname}\nEmail: {$booking->client_email}\nTelefono: {$booking->client_phone}\nClasse: {$booking->client_section}";
		
		if ( ! empty( $booking->client_gender ) ) {
			$gender_display = 'M' === $booking->client_gender ? 'Maschio' : 'Femmina';
			$description .= "\nGenere: {$gender_display}";
		}

		$event = array(
			'summary'     => "Prenotazione - {$booking->client_name} {$booking->client_surname}",
			'description' => $description,
			'start'       => array(
				'dateTime' => $start_datetime,
				'timeZone' => $timezone,
			),
			'end'         => array(
				'dateTime' => $end_datetime,
				'timeZone' => $timezone,
			),
		);

		return $event;
	}

	/**
	 * Get Google Calendar access token
	 */
	private static function get_access_token() {
		$token = get_option( self::ACCESS_TOKEN_OPTION );
		$expiry = get_option(self::ACCESS_TOKEN_OPTION . '_expiry');

		if ( $token && $expiry > time()) {
			return $token;
		}

		// Try to refresh token
		return self::refresh_access_token();
	}

	/**
	 * Refresh Google Calendar access token (for Service Account)
	 */
	private static function refresh_access_token() {
		$credentials = self::get_credentials();
		if ( ! $credentials ) {
			return false;
		}

		try {
			// Create JWT token for service account
			$now = time();
			$expiry = $now + 3600;

			$header = array(
				'alg' => 'RS256',
				'typ' => 'JWT',
			);

			$payload = array(
				'iss'   => $credentials['client_email'],
				'scope' => 'https://www.googleapis.com/auth/calendar',
				'aud'   => 'https://oauth2.googleapis.com/token',
				'exp'   => $expiry,
				'iat'   => $now,
			);

			// Encode JWT
			$jwt = self::create_jwt( $header, $payload, $credentials['private_key'] );

			// Request access token
			$response = wp_remote_post(
				'https://oauth2.googleapis.com/token',
				array(
					'body' => array(
						'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
						'assertion'  => $jwt,
					),
				)
			);

			if ( is_wp_error( $response ) ) {
				return false;
			}

			$body = wp_remote_retrieve_body( $response );
			$data = json_decode( $body, true );

			if ( isset( $data['access_token'] ) ) {
				// Store access token with expiry
				update_option( self::ACCESS_TOKEN_OPTION, $data['access_token'] );
				update_option( self::ACCESS_TOKEN_OPTION . '_expiry', $now + ( $data['expires_in'] - 300 ) );
				return $data['access_token'];
			}

			return false;

		} catch ( Exception $e ) {
			return false;
		}
	}

	/**
	 * Create JWT token for Google authentication
	 */
	private static function create_jwt( $header, $payload, $private_key ) {
		$header_encoded = rtrim( strtr( base64_encode( wp_json_encode( $header ) ), '+/', '-_' ), '=' );
		$payload_encoded = rtrim( strtr( base64_encode( wp_json_encode( $payload ) ), '+/', '-_' ), '=' );
		$signature_input = $header_encoded . '.' . $payload_encoded;

		$signature = '';
		openssl_sign( $signature_input, $signature, $private_key, 'sha256' );
		$signature_encoded = rtrim( strtr( base64_encode( $signature ), '+/', '-_' ), '=' );

		return $signature_input . '.' . $signature_encoded;
	}

	/**
	 * Delete event from Google Calendar
	 */
	public static function delete_event( $booking_id ) {
		if ( ! Booking_Settings::get( 'google_calendar_enabled' ) ) {
			return true;
		}

		$booking = Booking_DB::get_booking( $booking_id );
		if ( ! $booking || ! $booking->google_event_id ) {
			return false;
		}

		try {
			$access_token = self::get_access_token();
			if ( ! $access_token ) {
				Booking_Logger::log_google_error( $booking_id, 'Token di accesso non disponibile per delete' );
				return false;
			}

			$calendar_id = Booking_Settings::get( 'google_calendar_id' );
			$response = wp_remote_request(
				"https://www.googleapis.com/calendar/v3/calendars/{$calendar_id}/events/{$booking->google_event_id}",
				array(
					'method'  => 'DELETE',
					'headers' => array(
						'Authorization' => 'Bearer ' . $access_token,
					),
				)
			);

			if ( is_wp_error( $response ) ) {
				Booking_Logger::log_google_error( $booking_id, 'Delete fallito: ' . $response->get_error_message() );
				return false;
			}

			Booking_Logger::log_action( $booking_id, 'google_event_deleted', 'Evento cancellato da Google Calendar', null );
			return true;

		} catch ( Exception $e ) {
			Booking_Logger::log_google_error( $booking_id, 'Delete exception: ' . $e->getMessage() );
			return false;
		}
	}

	/**
	 * Update event on Google Calendar
	 */
	public static function update_event( $booking_id ) {
		if ( ! Booking_Settings::get( 'google_calendar_enabled' ) ) {
			return true;
		}

		$booking = Booking_DB::get_booking( $booking_id );
		if ( ! $booking || ! $booking->google_event_id ) {
			return false;
		}

		try {
			$access_token = self::get_access_token();
			if ( ! $access_token ) {
				Booking_Logger::log_google_error( $booking_id, 'Token di accesso non disponibile per update' );
				return false;
			}

			$event = self::create_event_data( $booking );
			$calendar_id = Booking_Settings::get( 'google_calendar_id' );

			$response = wp_remote_request(
				"https://www.googleapis.com/calendar/v3/calendars/{$calendar_id}/events/{$booking->google_event_id}",
				array(
					'method'  => 'PATCH',
					'headers' => array(
						'Authorization' => 'Bearer ' . $access_token,
						'Content-Type'  => 'application/json',
					),
					'body'    => wp_json_encode( $event ),
				)
			);

			if ( is_wp_error( $response ) ) {
				Booking_Logger::log_google_error( $booking_id, 'Update fallito: ' . $response->get_error_message() );
				return false;
			}

			Booking_Logger::log_action( $booking_id, 'google_event_updated', 'Evento aggiornato su Google Calendar', null );
			return true;

		} catch ( Exception $e ) {
			Booking_Logger::log_google_error( $booking_id, 'Update exception: ' . $e->getMessage() );
			return false;
		}
	}

	/**
	 * Get credentials with expiry check
	 */
	public static function get_credentials_with_validation() {
		$credentials = self::get_credentials();
		if ( ! $credentials ) {
			return null;
		}

		// Validate required fields for service account
		$required_fields = array( 'type', 'project_id', 'private_key_id', 'private_key', 'client_email', 'client_id' );
		foreach ( $required_fields as $field ) {
			if ( ! isset( $credentials[ $field ] ) ) {
				return null;
			}
		}

		return $credentials;
	}

	/**
	 * Save credentials from JSON file
	 */
	public static function save_credentials( $credentials_json ) {
		try {
			$credentials = json_decode( $credentials_json, true );

			if ( ! isset( $credentials['type'] ) || $credentials['type'] !== 'service_account' ) {
				return new WP_Error( 'invalid_credentials', 'File credenziali non valido' );
			}

			update_option( self::CREDENTIALS_OPTION, wp_json_encode( $credentials ) );
			return true;

		} catch ( Exception $e ) {
			return new WP_Error( 'credentials_error', $e->getMessage() );
		}
	}

	/**
	 * Get stored credentials
	 */
	public static function get_credentials() {
		$credentials_json = get_option( self::CREDENTIALS_OPTION );
		return $credentials_json ? json_decode( $credentials_json, true ) : null;
	}
}
