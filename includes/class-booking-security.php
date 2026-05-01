<?php
/**
 * Security hardening for Booking System
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Booking_Security {

	const RATE_LIMIT_TRANSIENT = 'booking_rate_limit_';
	const RATE_LIMIT_ATTEMPTS = 5;
	const RATE_LIMIT_WINDOW = 3600; // 1 hour

	/**
	 * Check rate limit for IP
	 */
	public static function check_rate_limit( $ip = null ) {
		if ( ! $ip ) {
			$ip = self::get_client_ip();
		}

		$transient_key = self::RATE_LIMIT_TRANSIENT . md5( $ip );
		$attempts = get_transient( $transient_key );

		if ( $attempts === false ) {
			$attempts = 0;
		}

		if ( $attempts >= self::RATE_LIMIT_ATTEMPTS ) {
			return new WP_Error( 'rate_limit_exceeded', 'Troppi tentativi. Riprova più tardi.' );
		}

		return true;
	}

	/**
	 * Increment rate limit counter
	 */
	public static function increment_rate_limit( $ip = null ) {
		if ( ! $ip ) {
			$ip = self::get_client_ip();
		}

		$transient_key = self::RATE_LIMIT_TRANSIENT . md5( $ip );
		$attempts = get_transient( $transient_key );

		if ( $attempts === false ) {
			$attempts = 0;
		}

		$attempts++;
		set_transient( $transient_key, $attempts, self::RATE_LIMIT_WINDOW );
	}

	/**
	 * Reset rate limit for IP
	 */
	public static function reset_rate_limit( $ip = null ) {
		if ( ! $ip ) {
			$ip = self::get_client_ip();
		}

		$transient_key = self::RATE_LIMIT_TRANSIENT . md5( $ip );
		delete_transient( $transient_key );
	}

	/**
	 * Get client IP address
	 */
	public static function get_client_ip() {
		// Check for IP from shared internet
		if ( ! empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
			$ip = $_SERVER['HTTP_CLIENT_IP'];
		} elseif ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			// Handle multiple IPs in X-Forwarded-For
			$ips = explode( ',', $_SERVER['HTTP_X_FORWARDED_FOR'] );
			$ip = trim( $ips[0] );
		} else {
			$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
		}

		// Validate IP format
		if ( ! filter_var( $ip, FILTER_VALIDATE_IP ) ) {
			$ip = '0.0.0.0';
		}

		return sanitize_text_field( $ip );
	}

	/**
	 * Validate CAPTCHA response (reCAPTCHA v3)
	 */
	public static function validate_recaptcha( $token ) {
		$secret_key = Booking_Settings::get( 'recaptcha_secret_key' );

		if ( ! $secret_key || ! $token ) {
			// If not configured, skip validation
			return true;
		}

		$response = wp_remote_post(
			'https://www.google.com/recaptcha/api/siteverify',
			array(
				'body' => array(
					'secret'   => $secret_key,
					'response' => $token,
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			Booking_Logger::log_action( null, 'captcha_error', 'reCAPTCHA validation error: ' . $response->get_error_message(), null );
			return false;
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		// Score threshold (0.0 to 1.0, higher = more likely human)
		$threshold = apply_filters( 'booking_recaptcha_threshold', 0.5 );

		if ( isset( $data['success'] ) && $data['success'] && isset( $data['score'] ) && $data['score'] >= $threshold ) {
			return true;
		}

		return false;
	}

	/**
	 * Sanitize and validate email
	 */
	public static function sanitize_email( $email ) {
		$email = sanitize_email( $email );

		// Additional validation
		if ( ! is_email( $email ) ) {
			return false;
		}

		// Check for disposable email domains
		if ( self::is_disposable_email( $email ) ) {
			return false;
		}

		return $email;
	}

	/**
	 * Check if email is from disposable email service
	 */
	private static function is_disposable_email( $email ) {
		$domain = explode( '@', $email )[1];

		// Common disposable email domains
		$disposable_domains = array(
			'tempmail.com',
			'throwaway.email',
			'temp-mail.org',
			'mailinator.com',
			'10minutemail.com',
		);

		return in_array( $domain, $disposable_domains );
	}

	/**
	 * Sanitize phone number
	 */
	public static function sanitize_phone( $phone ) {
		// Remove all non-numeric characters except +
		$phone = preg_replace( '/[^0-9+]/', '', $phone );

		// Validate length (international format)
		if ( strlen( $phone ) < 10 || strlen( $phone ) > 15 ) {
			return false;
		}

		return sanitize_text_field( $phone );
	}

	/**
	 * Sanitize name fields
	 */
	public static function sanitize_name( $name ) {
		// Remove HTML tags
		$name = wp_kses_post( $name );

		// Remove special characters
		$name = preg_replace( '/[^a-zA-Z0-9\s\-\']/', '', $name );

		// Trim whitespace
		$name = trim( $name );

		// Validate length
		if ( strlen( $name ) < 2 || strlen( $name ) > 100 ) {
			return false;
		}

		return $name;
	}

	/**
	 * Get safe error message (don't expose internal details)
	 */
	public static function get_safe_error_message( $error_code ) {
		$safe_messages = array(
			'rate_limit_exceeded'       => 'Troppi tentativi. Riprova più tardi.',
			'invalid_email'             => 'Email non valida. Controlla il formato.',
			'invalid_phone'             => 'Numero di telefono non valido.',
			'invalid_name'              => 'Nome non valido. Evita caratteri speciali.',
			'slot_not_available'        => 'Lo slot selezionato non è più disponibile. Ricarica e prova di nuovo.',
			'duplicate_booking'         => 'Hai già una prenotazione in questo slot.',
			'database_error'            => 'Errore del sistema. Riprova tra qualche minuto.',
			'captcha_failed'            => 'Verifica reCAPTCHA fallita. Riprova.',
		);

		return isset( $safe_messages[ $error_code ] ) ? $safe_messages[ $error_code ] : 'Si è verificato un errore. Riprova.';
	}

	/**
	 * Validate date format and range
	 */
	public static function validate_booking_date( $date ) {
		// Validate format YYYY-MM-DD
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
			return false;
		}

		// Validate date is valid
		$d = DateTime::createFromFormat( 'Y-m-d', $date );
		if ( ! $d || $d->format( 'Y-m-d' ) !== $date ) {
			return false;
		}

		// Validate within availability range
		$availability_start = Booking_Settings::get( 'availability_start_date' );
		$availability_end = Booking_Settings::get( 'availability_end_date' );

		if ( $date < $availability_start || $date > $availability_end ) {
			return false;
		}

		// Validate not in past
		if ( $date < date( 'Y-m-d' ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Validate time slot format
	 */
	public static function validate_time_slot( $time_slot ) {
		// Validate format HH:MM
		if ( ! preg_match( '/^\d{2}:\d{2}$/', $time_slot ) ) {
			return false;
		}

		// Validate hours and minutes
		$parts = explode( ':', $time_slot );
		$hour = intval( $parts[0] );
		$minute = intval( $parts[1] );

		if ( $hour < 0 || $hour > 23 || $minute < 0 || $minute > 59 ) {
			return false;
		}

		// Validate within booking hours
		$first_slot = Booking_Settings::get( 'first_slot_time' );
		$last_slot = Booking_Settings::get( 'last_slot_time' );

		if ( $time_slot < $first_slot || $time_slot > $last_slot ) {
			return false;
		}

		return true;
	}
}
