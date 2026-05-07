<?php
/**
 * Default configuration for Booking System
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Get all default settings
 */
function booking_get_defaults() {
	return array(
		// Date and time settings
		'availability_start_date'    => '2026-06-03', // June 3, 2026
		'availability_end_date'      => '2026-09-18', // September 18, 2026
		'first_slot_time'            => '09:00',      // 9:00 AM
		'last_slot_time'             => '17:00',      // 5:00 PM
		'slot_duration_minutes'      => 60,           // 1 hour slots
		'max_reservations_per_slot'  => 2,            // Max 2 bookings per slot

		// Blocked days (0 = Sunday, 1 = Monday, ... 6 = Saturday)
		'blocked_weekdays'           => array( 0, 6 ), // Sunday and Saturday

		// Specific blocked dates (array of dates in YYYY-MM-DD format)
		'blocked_specific_dates'     => array(),

		// Email settings
		'admin_email_on_booking'     => true,
		'send_confirmation_email'    => true,

		// SMTP settings
		'smtp_enabled'               => false,
		'smtp_host'                  => '',
		'smtp_port'                  => 587,
		'smtp_username'              => '',
		'smtp_password'              => '',
		'smtp_secure'                => 'tls',
		'smtp_from_email'            => '',
		'smtp_from_name'             => 'Booking System',

		// Google Calendar settings
		'google_calendar_enabled'    => false,
		'google_calendar_id'         => '', // Google Calendar email or ID

		// Security settings
		'enable_recaptcha'           => false,
		'recaptcha_site_key'         => '', // reCAPTCHA v3 site key
		'recaptcha_secret_key'       => '', // reCAPTCHA v3 secret key
		'recaptcha_threshold'        => 0.5, // reCAPTCHA score threshold (0.0-1.0)

		// Rate limiting
		'rate_limit_enabled'         => true,
		'rate_limit_attempts'        => 5,      // Max attempts per hour
		'rate_limit_window'          => 3600,   // Time window in seconds

		// Timezone
		'booking_timezone'           => 'Europe/Rome', // Default timezone
	);
}

/**
 * Get a specific default setting
 */
function booking_get_default( $key ) {
	$defaults = booking_get_defaults();
	return isset( $defaults[ $key ] ) ? $defaults[ $key ] : null;
}
