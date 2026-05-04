<?php
/**
 * Email notifications for Booking System
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Booking_Email {
	static $smtp_initialized = false;

	private static function init_smtp() {
		add_action('phpmailer_init', function($phpmailer) {
			$phpmailer->isSMTP();
			$phpmailer->Host = 'smtp.gmail.com';
			$phpmailer->SMTPAuth = true;
			$phpmailer->Port = 587;
			$phpmailer->Username = 'fediforla@gmail.com';
			$phpmailer->Password = 'qvjx grdp ebwi ivuk';
			$phpmailer->SMTPSecure = 'tls';
		});

		add_filter('wp_mail_from', function() {
			return 'fediforla@gmail.com';
		});
	}

	/**
	 * Send confirmation email to client
	 */
	public static function send_confirmation( $booking_id ) {
		$booking = Booking_DB::get_booking( $booking_id );

		if ( ! $booking ) {
			Booking_Logger::log_email_error( $booking_id, '', 'Prenotazione non trovata' );
			return false;
		}

		if( !Booking_Email::$smtp_initialized ) {
			Booking_Email::init_smtp();
			Booking_Email::$smtp_initialized = true;
		}

		$to = $booking->client_email;
		$subject = 'Conferma Prenotazione';
		$message = self::get_message( $booking );
		$headers = self::get_headers();

		$sent = wp_mail( $to, $subject, $message, $headers );

		if ( $sent ) {
			Booking_Logger::log_email_sent( $booking_id, $to );
		} else {
			Booking_Logger::log_email_error( $booking_id, $to, 'Invio email fallito' );
		}

		return $sent;
	}

	/**
	 * Get email message
	 */
	private static function get_message( $booking ) {
		$message = "Caro/a {$booking->client_name},\n\n";
		$message .= "La tua prenotazione è stata registrata con successo!\n\n";
		$message .= "DETTAGLI PRENOTAZIONE:\n";
		$message .= "Data: " . date_i18n( 'd/m/Y', strtotime( $booking->booking_date ) ) . "\n";
		$message .= "Orario: {$booking->time_slot}\n";
		$message .= "Nome: {$booking->client_name} {$booking->client_surname}\n";
		$message .= "Sezione: {$booking->client_section}\n";
		$message .= "Email: {$booking->client_email}\n";
		$message .= "Telefono: {$booking->client_phone}\n\n";
		$message .= "Grazie per la tua prenotazione!\n\n";
		$message .= "Cordiali saluti,\n";
		$message .= get_bloginfo( 'name' );

		return $message;
	}

	/**
	 * Get email headers
	 */
	private static function get_headers() {
		$headers = "Content-Type: text/plain; charset=UTF-8\r\n";
		$headers .= "From: " . get_option( 'admin_email' ) . "\r\n";
		return $headers;
	}

	/**
	 * Send admin notification
	 */
	public static function send_admin_notification( $booking_id ) {
		if ( ! Booking_Settings::get( 'admin_email_on_booking' ) ) {
			return true;
		}

		$booking = Booking_DB::get_booking( $booking_id );

		if ( ! $booking ) {
			return false;
		}

		if( !Booking_Email::$smtp_initialized ) {
			Booking_Email::init_smtp();
			Booking_Email::$smtp_initialized = true;
		}

		$admin_email = get_option( 'admin_email' );
		$subject = 'Nuova prenotazione ricevuta';
		$message = "Una nuova prenotazione è stata ricevuta:\n\n";
		$message .= "Nome: {$booking->client_name} {$booking->client_surname}\n";
		$message .= "Sezione: {$booking->client_section}\n";
		$message .= "Email: {$booking->client_email}\n";
		$message .= "Telefono: {$booking->client_phone}\n";
		$message .= "Data: " . date_i18n( 'd/m/Y', strtotime( $booking->booking_date ) ) . "\n";
		$message .= "Orario: {$booking->time_slot}\n";

		return wp_mail( $admin_email, $subject, $message );
	}
}
