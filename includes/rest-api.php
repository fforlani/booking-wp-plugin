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
	}

	/**
	 * Get available dates with at least one available slot
	 */
	public static function get_available_dates( $request ) {
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
	 * Create a new reservation
	 */
	public static function create_reservation( $request ) {
		// Check rate limit
		$rate_limit_check = Booking_Security::check_rate_limit();
		if ( is_wp_error( $rate_limit_check ) ) {
			Booking_Logger::log_action( null, 'rate_limit_exceeded', 'Rate limit hit on POST /reserve', Booking_Security::get_client_ip() );
			return new WP_Error( 'rate_limit_exceeded', 'Troppi tentativi. Riprova più tardi.', array( 'status' => 429 ) );
		}

		// Verify nonce
		/* $nonce = $request->get_header( 'X-WP-Nonce' );
		if ( ! wp_verify_nonce( $nonce, 'booking_nonce' ) ) {
			Booking_Security::increment_rate_limit();
			return new WP_Error( 'invalid_nonce', 'Invalid nonce', array( 'status' => 403 ) );
		} */

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
			$validation_errors[] = 'Sezione non valida';
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

		// Send confirmation email
		if(Booking_Settings::is_send_confirm_email()) {
			Booking_Email::send_confirmation( $booking_id );
		}

		// Send admin notification
		if(Booking_Settings::is_send_admin_notification_email()) {
			Booking_Email::send_admin_notification( $booking_id );
		}

		// Add to Google Calendar
		if (Booking_Settings::get( 'google_calendar_enabled' ) ) {
			Booking_Google_Calendar::add_event( $booking_id );
		}

		// Reset rate limit on successful booking
		// Booking_Security::reset_rate_limit();

		$booking = Booking_DB::get_booking( $booking_id );

		return array(
			'success'    => true,
			'booking_id' => $booking_id,
			'booking'    => $booking,
			'message'    => 'Prenotazione confermata!',
		);
	}
}
