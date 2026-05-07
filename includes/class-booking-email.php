<?php
/**
 * Email notifications for Booking System
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Booking_Email {
	static $smtp_initialized = false;

	public static function init_smtp() {
		$smtp_enabled = Booking_Settings::get( 'smtp_enabled', false );

		// Only configure if custom SMTP is enabled
		if ( ! $smtp_enabled ) {
			remove_action('phpmailer_init', function($phpmailer) {});
			return;
		}

		add_action('phpmailer_init', function($phpmailer) {

			$host = Booking_Settings::get( 'smtp_host' );
			$port = Booking_Settings::get( 'smtp_port' );
			$username = Booking_Settings::get( 'smtp_username' );
			$password = Booking_Settings::get( 'smtp_password' );
			$secure = Booking_Settings::get( 'smtp_secure' );

			// Validate required settings
			if ( ! $host || ! $port || ! $username || ! $password ) {
				return;
			}

			$phpmailer->isSMTP();
			$phpmailer->Host = $host;
			$phpmailer->SMTPAuth = true;
			$phpmailer->Port = intval( $port );
			$phpmailer->Username = $username;
			$phpmailer->Password = $password;
			
			// Set secure connection type
			if ( 'ssl' === $secure ) {
				$phpmailer->SMTPSecure = 'ssl';
			} elseif ( 'tls' === $secure ) {
				$phpmailer->SMTPSecure = 'tls';
			} else {
				$phpmailer->SMTPSecure = '';
			}
		});

		// Set sender name and email if configured
		$from_email = Booking_Settings::get( 'smtp_from_email' );
		if ( $from_email ) {
			add_filter('wp_mail_from', function() use ($from_email) {
				return $from_email;
			});
		}

		$from_name = Booking_Settings::get( 'smtp_from_name' );
		if ( $from_name ) {
			add_filter('wp_mail_from_name', function() use ($from_name) {
				return $from_name;
			});
		}
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
		$headers = array(
			"Content-Type: text/plain; charset=UTF-8",
			"Bcc: " . get_option( 'admin_email' )
		);

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
		
		if ( ! empty( $booking->client_gender ) ) {
			$gender_display = 'M' === $booking->client_gender ? 'Maschio' : 'Femmina';
			$message .= "Genere: {$gender_display}\n";
		}
		
		$message .= "Email: {$booking->client_email}\n";
		$message .= "Telefono: {$booking->client_phone}\n\n";
		$message .= "Grazie per la tua prenotazione!\n\n";
		$message .= "Cordiali saluti,\n";
		$message .= get_bloginfo( 'name' );

		return $message;
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
		
		if ( ! empty( $booking->client_gender ) ) {
			$gender_display = 'M' === $booking->client_gender ? 'Maschio' : 'Femmina';
			$message .= "Genere: {$gender_display}\n";
		}
		
		$message .= "Email: {$booking->client_email}\n";
		$message .= "Telefono: {$booking->client_phone}\n";
		$message .= "Data: " . date_i18n( 'd/m/Y', strtotime( $booking->booking_date ) ) . "\n";
		$message .= "Orario: {$booking->time_slot}\n";

		return wp_mail( $admin_email, $subject, $message );
	}
}
