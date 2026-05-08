<?php
/**
 * Email notifications for Booking System
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Booking_Email {

	public static function set_smtp() {
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
		if(!Booking_Settings::is_send_confirm_email()) {
			return true;
		}

		$booking = Booking_DB::get_booking( $booking_id );

		if ( ! $booking ) {
			Booking_Logger::log_email_error( $booking_id, '', 'Prenotazione non trovata' );
			return false;
		}

		$to = $booking->client_email;
		$subject = 'Conferma Prenotazione';
		$message = Booking_Settings::get( 'confirmation_email_template' );
		if(empty($message)) {
			return;
		}
		$message = self::replace_tokens( $message, $booking );

		$headers = array(
			"Content-Type: text/html; charset=UTF-8",
			"Bcc: " . get_option( 'admin_email' )
		);

		self::set_smtp();
		$sent = wp_mail( $to, $subject, $message, $headers );

		if ( $sent ) {
			Booking_Logger::log_email_sent( $booking_id, $to );
		} else {
			Booking_Logger::log_email_error( $booking_id, $to, 'Invio email fallito' );
		}

		return $sent;
	}

	/**
	 * Send cancellation email to client.
	 */
	public static function send_cancellation( $booking ) {
		if(!Booking_Settings::is_send_confirm_email()) {
			return true;
		}
		
		if ( is_numeric( $booking ) ) {
			$booking = Booking_DB::get_booking( $booking );
		}

		if ( ! $booking ) {
			Booking_Logger::log_email_error( 0, '', 'Prenotazione non trovata per email di cancellazione' );
			return false;
		}

		$message = Booking_Settings::get( 'cancellation_email_template' );
		if ( empty( $message ) ) {
			return true;
		}

		$to = $booking->client_email;
		$subject = 'Cancellazione Prenotazione';
		$message = self::replace_tokens( $message, $booking );

		$headers = array(
			"Content-Type: text/html; charset=UTF-8",
			"Bcc: " . get_option( 'admin_email' )
		);

		self::set_smtp();
		$sent = wp_mail( $to, $subject, $message, $headers );

		if ( $sent ) {
			Booking_Logger::log_email_sent( $booking->id, $to );
		} else {
			Booking_Logger::log_email_error( $booking->id, $to, 'Invio email cancellazione fallito' );
		}

		return $sent;
	}

	/**
	 * Get available template tokens.
	 */
	public static function get_available_tokens() {
		return array(
			'{booking_id}'       => 'ID della prenotazione',
			'{booking_date}'     => 'Data prenotazione formattata (es: 03/06/2026)',
			'{booking_date_raw}' => 'Data prenotazione nel formato salvato (YYYY-MM-DD)',
			'{time_slot}'        => 'Orario della prenotazione',
			'{client_name}'      => 'Nome accompagnatore',
			'{client_surname}'   => 'Cognome accompagnatore',
			'{client_full_name}' => 'Nome e cognome accompagnatore',
			'{client_email}'     => 'Email accompagnatore',
			'{client_phone}'     => 'Telefono accompagnatore',
			'{client_section}'   => 'Sezione alunno',
			'{client_gender}'    => 'Genere alunno',
			'{status}'           => 'Stato della prenotazione',
			'{created_at}'       => 'Data e ora di creazione della prenotazione',
			'{manage_booking_token}' => 'Token per modificare o cancellare la prenotazione'
		);
	}

	/**
	 * Replace template tokens with booking data.
	 */
	private static function replace_tokens( $template, $booking ) {
		$gender_display = '';
		if ( ! empty( $booking->client_gender ) ) {
			if ( 'M' === $booking->client_gender ) {
				$gender_display = 'Maschio';
			} elseif ( 'F' === $booking->client_gender ) {
				$gender_display = 'Femmina';
			} else {
				$gender_display = $booking->client_gender;
			}
		}

		$created_at = '';
		if ( ! empty( $booking->created_at ) ) {
			$created_at = date_i18n( 'd/m/Y H:i', strtotime( $booking->created_at ) );
		}

		$replacements = array(
			'{booking_id}'       => isset( $booking->id ) ? $booking->id : '',
			'{booking_date}'     => ! empty( $booking->booking_date ) ? date_i18n( 'd/m/Y', strtotime( $booking->booking_date ) ) : '',
			'{booking_date_raw}' => isset( $booking->booking_date ) ? $booking->booking_date : '',
			'{time_slot}'        => isset( $booking->time_slot ) ? $booking->time_slot : '',
			'{client_name}'      => isset( $booking->client_name ) ? $booking->client_name : '',
			'{client_surname}'   => isset( $booking->client_surname ) ? $booking->client_surname : '',
			'{client_full_name}' => trim( ( isset( $booking->client_name ) ? $booking->client_name : '' ) . ' ' . ( isset( $booking->client_surname ) ? $booking->client_surname : '' ) ),
			'{client_email}'     => isset( $booking->client_email ) ? $booking->client_email : '',
			'{client_phone}'     => isset( $booking->client_phone ) ? $booking->client_phone : '',
			'{client_section}'   => isset( $booking->client_section ) ? $booking->client_section : '',
			'{client_gender}'    => $gender_display,
			'{status}'           => isset( $booking->status ) ? $booking->status : '',
			'{created_at}'       => $created_at,
			'{manage_booking_token}' => Booking_Security::create_booking_management_token( $booking->id, $booking->client_email )
		);

		return strtr( $template, $replacements );
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

		self::set_smtp();
		return wp_mail( $admin_email, $subject, $message );
	}
}
