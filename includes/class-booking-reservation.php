<?php
/**
 * Reservation creation and management
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Booking_Reservation {

	/**
	 * Validate reservation data
	 */
	public static function validate( $data ) {
		$errors = array();

		// Required fields
		$required_fields = array( 'booking_date', 'time_slot', 'client_name', 'client_surname', 'client_email', 'client_phone', 'client_section' );
		foreach ( $required_fields as $field ) {
			if ( empty( $data[ $field ] ) ) {
				$errors[] = "Campo obbligatorio: {$field}";
			}
		}

		// Email validation
		if ( ! empty( $data['client_email'] ) && ! is_email( $data['client_email'] ) ) {
			$errors[] = 'Email non valida';
		}

		// Phone validation (basic)
		if ( ! empty( $data['client_phone'] ) && ! preg_match( '/^\+?[0-9\s\-\(\)]{7,}$/', $data['client_phone'] ) ) {
			$errors[] = 'Numero di telefono non valido';
		}

		// Date validation
		if ( ! empty( $data['booking_date'] ) && ! strtotime( $data['booking_date'] ) ) {
			$errors[] = 'Data non valida';
		}

		// Time slot validation
		if ( ! empty( $data['time_slot'] ) && ! preg_match( '/^\d{2}:\d{2}$/', $data['time_slot'] ) ) {
			$errors[] = 'Slot orario non valido';
		}

		return $errors;
	}

	/**
	 * Create a new reservation with atomic transaction
	 */
	public static function create( $data ) {
		global $wpdb;

		// Validate input
		$validation_errors = self::validate( $data );
		if ( ! empty( $validation_errors ) ) {
			return new WP_Error( 'validation_error', implode( ', ', $validation_errors ) );
		}

		$email = sanitize_email( $data['client_email'] );
		$date = sanitize_text_field( $data['booking_date'] );
		$time_slot = sanitize_text_field( $data['time_slot'] );

		// Log attempt
		Booking_Logger::log_attempt( $email, $date, $time_slot );

		// Start transaction
		$wpdb->query( 'START TRANSACTION' );

		try {
			// Final availability check (prevent race condition)
			if ( ! Booking_Availability::is_slot_available( $date, $time_slot ) ) {
				$wpdb->query( 'ROLLBACK' );
				Booking_Logger::log_slot_unavailable( $email, $date, $time_slot );
				return new WP_Error( 'slot_unavailable', 'Slot non disponibile' );
			}

			// Create booking
			$booking_id = Booking_DB::create_booking( $data );
			if ( is_wp_error( $booking_id ) || ! $booking_id ) {
				$wpdb->query( 'ROLLBACK' );
				Booking_Logger::log_error( $email, 'Errore durante la creazione della prenotazione', null );
				return new WP_Error( 'db_error', 'Errore durante la prenotazione' );
			}

			// Commit transaction
			$wpdb->query( 'COMMIT' );

			return $booking_id;

		} catch ( Exception $e ) {
			$wpdb->query( 'ROLLBACK' );
			Booking_Logger::log_error( $email, 'Eccezione: ' . $e->getMessage(), null );
			return new WP_Error( 'exception', $e->getMessage() );
		}
	}

	/**
	 * Get reservation by ID
	 */
	public static function get( $booking_id ) {
		return Booking_DB::get_booking( $booking_id );
	}

	/**
	 * Update reservation status
	 */
	public static function update_status( $booking_id, $status ) {
		return Booking_DB::update_booking_status( $booking_id, $status );
	}
}
