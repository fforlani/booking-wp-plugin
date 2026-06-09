<?php
/**
 * REST API endpoints for Booking System
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Booking_REST_API {

	/**
	 * Initialize REST API endpoints
	 */
	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	/**
	 * Register REST API routes
	 */
	public static function register_routes() {
		// Get available dates endpoint
		register_rest_route(
			'booking/v1',
			'/dates',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'get_available_dates' ),
				'permission_callback' => '__return_true',
			)
		);

		// Get available slots endpoint
		register_rest_route(
			'booking/v1',
			'/slots',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'get_slots' ),
				'permission_callback' => '__return_true',
			)
		);

		// Create reservation endpoint
		register_rest_route(
			'booking/v1',
			'/reserve',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'create_reservation' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			'booking/v1',
			'/cancel',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'cancel_reservation_request' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			'booking/v1',
			'/reschedule',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'reschedule_reservation_request' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Get available dates with at least one available slot
	 */
	public static function get_available_dates() {
		$rate_limit_check = Booking_Security::check_rate_limit();
		if ( is_wp_error( $rate_limit_check ) ) {
			Booking_Logger::log_action( null, 'rate_limit_exceeded', 'Rate limit hit on GET /dates', Booking_Security::get_client_ip() );
			return new WP_Error( 'rate_limit_exceeded', 'Troppi tentativi. Riprova più tardi.', array( 'status' => 429 ) );
		}

		$dates = Booking_Availability::get_available_dates();

		return array(
			'dates' => $dates,
		);
	}

	/**
	 * Get available slots for a date
	 */
	public static function get_slots( $request ) {
		// Check rate limit
		$rate_limit_check = Booking_Security::check_rate_limit();
		if ( is_wp_error( $rate_limit_check ) ) {
			Booking_Logger::log_action( null, 'rate_limit_exceeded', 'Rate limit hit on GET /slots', Booking_Security::get_client_ip() );
			return new WP_Error( 'rate_limit_exceeded', 'Troppi tentativi. Riprova più tardi.', array( 'status' => 429 ) );
		}

		$date = $request->get_param( 'date' );

		if ( ! $date || ! Booking_Security::validate_booking_date( $date ) ) {
			Booking_Security::increment_rate_limit();
			return new WP_Error( 'invalid_date', 'Data non valida', array( 'status' => 400 ) );
		}

		$slots = Booking_Availability::get_available_slots_for_date( $date );

		return array(
			'date'  => $date,
			'slots' => $slots,
		);
	}

	/**
	 * Cancel an existing reservation from a signed public token.
	 */
	public static function cancel_reservation_request( $request ) {
		$nonce_check = self::verify_request_nonce( $request );
		if ( is_wp_error( $nonce_check ) ) {
			return $nonce_check;
		}

		$data = $request->get_json_params();
		if ( ! is_array( $data ) ) {
			return new WP_Error( 'invalid_data', 'Dati non validi.', array( 'status' => 400 ) );
		}

		$token = isset( $data['token'] ) ? sanitize_text_field( $data['token'] ) : '';
		$booking = self::get_booking_from_token( $token );

		if ( is_wp_error( $booking ) ) {
			return $booking;
		}

		return self::cancel_reservation( $booking );
	}

	/**
	 * Reschedule an existing reservation from a signed public token.
	 */
	public static function reschedule_reservation_request( $request ) {
		$nonce_check = self::verify_request_nonce( $request );
		if ( is_wp_error( $nonce_check ) ) {
			return $nonce_check;
		}

		$data = $request->get_json_params();
		if ( ! is_array( $data ) ) {
			return new WP_Error( 'invalid_data', 'Dati non validi.', array( 'status' => 400 ) );
		}

		$token = isset( $data['token'] ) ? sanitize_text_field( $data['token'] ) : '';
		$booking = self::get_booking_from_token( $token );

		if ( is_wp_error( $booking ) ) {
			return $booking;
		}

		$new_date = isset( $data['booking_date'] ) ? sanitize_text_field( $data['booking_date'] ) : '';
		$new_time_slot = isset( $data['time_slot'] ) ? sanitize_text_field( $data['time_slot'] ) : '';

		return self::reschedule_reservation( $booking, $new_date, $new_time_slot );
	}

	/**
	 * Create a new reservation
	 */
	public static function create_reservation( $request ) {
		// Check rate limit
		$rate_limit_check = Booking_Security::check_rate_limit();
		if ( is_wp_error( $rate_limit_check ) ) {
			Booking_Logger::log_action( null, 'rate_limit_exceeded', 'Rate limit hit on POST /reserve', Booking_Security::get_client_ip() );
			return new WP_Error( 'rate_limit_exceeded', 'Troppi tentativi. Riprova più tardi.', array( 'status' => 429 ) );
		}

		$nonce_check = self::verify_request_nonce( $request );
		if ( is_wp_error( $nonce_check ) ) {
			Booking_Security::increment_rate_limit();
			return $nonce_check;
		}

		// Verify reCAPTCHA if configured
		$recaptcha_token = $request->get_param( 'recaptcha_token' );
		if ( ! Booking_Security::validate_recaptcha( $recaptcha_token ) ) {
			Booking_Security::increment_rate_limit();
			Booking_Logger::log_action( null, 'captcha_failed', 'reCAPTCHA validation failed', Booking_Security::get_client_ip() );
			return new WP_Error( 'captcha_failed', 'Verifica reCAPTCHA fallita. Riprova.', array( 'status' => 400 ) );
		}

		$data = $request->get_json_params();

		if ( ! $data ) {
			Booking_Security::increment_rate_limit();
			return new WP_Error( 'invalid_data', 'No data provided', array( 'status' => 400 ) );
		}

		// Sanitize and validate input
		$sanitized_data = array(
			'booking_date'   => isset( $data['booking_date'] ) ? sanitize_text_field( $data['booking_date'] ) : '',
			'time_slot'      => isset( $data['time_slot'] ) ? sanitize_text_field( $data['time_slot'] ) : '',
			'client_name'    => isset( $data['client_name'] ) ? Booking_Security::sanitize_name( $data['client_name'] ) : '',
			'client_gender'    => isset( $data['client_gender'] ) ? $data['client_gender'] : '',
			'client_surname' => isset( $data['client_surname'] ) ? Booking_Security::sanitize_name( $data['client_surname'] ) : '',
			'client_section' => isset( $data['client_section'] ) ? (int)( $data['client_section'] ) : '',
			'client_email'   => isset( $data['client_email'] ) ? Booking_Security::sanitize_email( $data['client_email'] ) : '',
			'client_phone'   => isset( $data['client_phone'] ) ? Booking_Security::sanitize_phone( $data['client_phone'] ) : '',
		);

		// Validate all fields
		$validation_errors = array();

		if ( ! Booking_Security::validate_booking_date( $sanitized_data['booking_date'] ) ) {
			$validation_errors[] = 'Data non valida';
		}

		if ( ! Booking_Security::validate_time_slot( $sanitized_data['time_slot'] ) ) {
			$validation_errors[] = 'Orario non valido';
		}

		if ( ! $sanitized_data['client_name'] ) {
			$validation_errors[] = 'Nome non valido';
		}

		if ( ! $sanitized_data['client_surname'] ) {
			$validation_errors[] = 'Cognome non valido';
		}

		if ( ! $sanitized_data['client_section'] ) {
			$validation_errors[] = 'Classe non valida';
		}

		if ( ! $sanitized_data['client_email'] ) {
			$validation_errors[] = 'Email non valida';
		}

		if ( ! $sanitized_data['client_phone'] ) {
			$validation_errors[] = 'Telefono non valido';
		}

		if ( ! empty( $validation_errors ) ) {
			Booking_Security::increment_rate_limit();
			Booking_Logger::log_action( null, 'validation_failed', implode( ', ', $validation_errors ), $sanitized_data['client_email'] ?? null );
			return new WP_Error( 'validation_failed', implode( '; ', $validation_errors ), array( 'status' => 400 ) );
		}

		// Create reservation
		$booking_id = Booking_Reservation::create( $sanitized_data );

		if ( is_wp_error( $booking_id ) ) {
			Booking_Security::increment_rate_limit();
			Booking_Logger::log_action( null, 'reservation_failed', $booking_id->get_error_message(), $sanitized_data['client_email'] );
			return new WP_Error( 'reservation_failed', Booking_Security::get_safe_error_message( 'database_error' ), array( 'status' => 400 ) );
		}

		$booking = Booking_DB::get_booking( $booking_id );

		// Add to Google Calendar
		if (Booking_Settings::get( 'google_calendar_enabled' ) ) {
			$added = Booking_Google_Calendar::add_event( $booking_id );
			if( !$added ) {
				// retry, if not possible rollback
				$added = Booking_Google_Calendar::add_event( $booking_id );
			}
			if( !$added ) {
				Booking_DB::cancel_booking( $booking_id );
				return new WP_Error( 'email_sending_failed', "Errore nella creazione dell'evento", array( 'status' => 400 ) );
			}
		}

		// Send confirmation email
		$sent = Booking_Email::send_confirmation( $booking_id );
		if(! $sent) {
			Booking_DB::cancel_booking( $booking_id );
			Booking_Google_Calendar::delete_event( $booking_id );
			return new WP_Error( 'email_sending_failed', "Errore nell'invio della mail", array( 'status' => 400 ) );
		}

		// Send admin notification
		Booking_Email::send_admin_notification( $booking_id );
		

		// Log success
		Booking_Logger::log_success( $booking_id, $booking->client_email );

		// Reset rate limit on successful booking
		// Booking_Security::reset_rate_limit();

		return array(
			'success'    => true,
			'booking_id' => $booking_id,
			'booking'    => $booking,
			'message'    => 'Ti abbiamo inviato un’email con la conferma e tutte le informazioni utili',
		);
	}

	private static function get_booking_from_token( $token ) {
		$token_data = Booking_Security::validate_booking_management_token( $token );

		if ( is_wp_error( $token_data ) ) {
			return new WP_Error( 'invalid_token', $token_data->get_error_message(), array( 'status' => 403 ) );
		}

		$booking = Booking_DB::get_booking( $token_data['booking_id'] );

		if ( ! $booking ) {
			return new WP_Error( 'booking_not_found', 'Prenotazione non trovata.', array( 'status' => 404 ) );
		}

		if ( 'cancelled' === strtolower( (string) $booking->status ) ) {
			return new WP_Error( 'booking_cancelled', 'La prenotazione e gia stata cancellata.', array( 'status' => 404 ) );
		}

		if ( strtolower( $booking->client_email ) !== strtolower( $token_data['email'] ) ) {
			return new WP_Error( 'invalid_token', 'Link di gestione non valido.', array( 'status' => 403 ) );
		}

		return $booking;
	}

	private static function cancel_reservation( $booking ) {
		if ( Booking_Settings::get( 'google_calendar_enabled' ) && ! empty( $booking->google_event_id ) ) {
			$deleted = Booking_Google_Calendar::delete_event( $booking->id );
			if ( ! $deleted ) {
				return new WP_Error( 'calendar_error', 'Non siamo riusciti ad aggiornare il calendario. Riprova tra qualche minuto.', array( 'status' => 400 ) );
			}
		}

		if ( ! Booking_DB::cancel_booking( $booking->id ) ) {
			return new WP_Error( 'delete_failed', 'Non siamo riusciti a cancellare la prenotazione.', array( 'status' => 400 ) );
		}

		$booking->status = 'cancelled';
		Booking_Logger::log_action( $booking->id, 'booking_cancelled_by_customer', 'Prenotazione cancellata dal link pubblico.', $booking->client_email );
		Booking_Email::send_cancellation( $booking );

		return array(
			'success' => true,
			'message' => 'La prenotazione e stata cancellata correttamente.',
		);
	}

	private static function reschedule_reservation( $booking, $new_date, $new_time_slot ) {
		if ( ! Booking_Security::validate_booking_date( $new_date ) ) {
			return new WP_Error( 'invalid_date', 'Data non valida.', array( 'status' => 400 ) );
		}

		if ( ! Booking_Security::validate_time_slot( $new_time_slot ) ) {
			return new WP_Error( 'invalid_time_slot', 'Orario non valido.', array( 'status' => 400 ) );
		}

		if ( ! Booking_Availability::is_slot_available( $new_date, $new_time_slot, $booking->id ) ) {
			return new WP_Error( 'slot_not_available', 'Lo slot selezionato non e piu disponibile.', array( 'status' => 400 ) );
		}

		$old_date = $booking->booking_date;
		$old_time_slot = $booking->time_slot;

		if ( ! Booking_DB::update_booking_schedule( $booking->id, $new_date, $new_time_slot ) ) {
			return new WP_Error( 'update_failed', 'Non siamo riusciti a riprogrammare la prenotazione.', array( 'status' => 400 ) );
		}

		if ( Booking_Settings::get( 'google_calendar_enabled' ) && ! empty( $booking->google_event_id ) ) {
			$updated = Booking_Google_Calendar::update_event( $booking->id );
			if ( ! $updated ) {
				Booking_DB::update_booking_schedule( $booking->id, $old_date, $old_time_slot );
				return new WP_Error( 'calendar_error', 'Non siamo riusciti ad aggiornare il calendario. Riprova tra qualche minuto.', array( 'status' => 400 ) );
			}
		}

		Booking_Logger::log_action(
			$booking->id,
			'booking_rescheduled_by_customer',
			"Prenotazione riprogrammata da {$old_date} {$old_time_slot} a {$new_date} {$new_time_slot}.",
			$booking->client_email
		);

		if ( ! Booking_Email::send_confirmation( $booking->id ) ) {
			Booking_DB::update_booking_schedule( $booking->id, $old_date, $old_time_slot );
			if ( Booking_Settings::get( 'google_calendar_enabled' ) && ! empty( $booking->google_event_id ) ) {
				Booking_Google_Calendar::update_event( $booking->id );
			}

			return new WP_Error( 'email_sending_failed', "Errore nell'invio della mail di conferma", array( 'status' => 400 ) );
		}

		return array(
			'success' => true,
			'message' => 'La prenotazione e stata riprogrammata correttamente.',
			'booking' => Booking_DB::get_booking( $booking->id ),
		);
	}

	private static function verify_request_nonce( $request ) {
		$nonce = $request->get_header( 'X-WP-Nonce' );

		/*if ( ! $nonce || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			Booking_Logger::log_action( null, 'invalid_nonce', 'Invalid booking REST nonce', Booking_Security::get_client_ip() );
			return new WP_Error( 'invalid_nonce', 'Sessione scaduta. Ricarica la pagina e riprova.', array( 'status' => 403 ) );
		}*/

		return true;
	}
}
