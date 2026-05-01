<?php
/**
 * Logging for Booking System
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Booking_Logger {

	/**
	 * Log a booking attempt
	 */
	public static function log_attempt( $email, $date, $time_slot ) {
		Booking_DB::log_action(
			'attempt',
			"Tentativo di prenotazione per {$date} alle {$time_slot}",
			null,
			$email
		);
	}

	/**
	 * Log successful booking
	 */
	public static function log_success( $booking_id, $email ) {
		Booking_DB::log_action(
			'success',
			"Prenotazione confermata",
			$booking_id,
			$email
		);
	}

	/**
	 * Log booking error
	 */
	public static function log_error( $email, $message, $booking_id = null ) {
		Booking_DB::log_action(
			'error',
			$message,
			$booking_id,
			$email
		);
	}

	/**
	 * Log email sent
	 */
	public static function log_email_sent( $booking_id, $email ) {
		Booking_DB::log_action(
			'email_sent',
			"Email di conferma inviata a {$email}",
			$booking_id,
			$email
		);
	}

	/**
	 * Log email error
	 */
	public static function log_email_error( $booking_id, $email, $error ) {
		Booking_DB::log_action(
			'email_error',
			"Errore invio email: {$error}",
			$booking_id,
			$email
		);
	}

	/**
	 * Log Google Calendar event created
	 */
	public static function log_google_event_created( $booking_id, $event_id ) {
		Booking_DB::log_action(
			'google_event_created',
			"Evento Google Calendar creato: {$event_id}",
			$booking_id
		);
	}

	/**
	 * Log Google Calendar error
	 */
	public static function log_google_error( $booking_id, $error ) {
		Booking_DB::log_action(
			'google_error',
			"Errore Google Calendar: {$error}",
			$booking_id
		);
	}

	/**
	 * Log slot unavailable error
	 */
	public static function log_slot_unavailable( $email, $date, $time_slot ) {
		Booking_DB::log_action(
			'error',
			"Slot non disponibile: {$date} alle {$time_slot}",
			null,
			$email
		);
	}
}
