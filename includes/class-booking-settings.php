<?php
/**
 * Settings management for Booking System
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Booking_Settings {

	const OPTION_PREFIX = 'booking_system_';

	/**
	 * Get a setting value
	 */
	public static function get( $key, $default = null ) {
		$value = get_option( self::OPTION_PREFIX . $key );

		if ( false === $value ) {
			$value = booking_get_default( $key );
		}

		return $value !== null ? $value : $default;
	}

	/**
	 * Save a setting value
	 */
	public static function set( $key, $value ) {
		update_option( self::OPTION_PREFIX . $key, $value );
		return true;
	}

	/**
	 * Get all settings
	 */
	public static function get_all() {
		$defaults = booking_get_defaults();
		$settings = array();

		foreach ( array_keys( $defaults ) as $key ) {
			$settings[ $key ] = self::get( $key );
		}

		return $settings;
	}

	/**
	 * Save multiple settings
	 */
	public static function set_multiple( $settings ) {
		foreach ( $settings as $key => $value ) {
			self::set( $key, $value );
		}
		return true;
	}

	/**
	 * Reset settings to defaults
	 */
	public static function reset() {
		$defaults = booking_get_defaults();

		foreach ( array_keys( $defaults ) as $key ) {
			delete_option( self::OPTION_PREFIX . $key );
		}

		return true;
	}

	/**
	 * Get timezone
	 */
	public static function get_timezone() {
		return self::get( 'booking_timezone', 'Europe/Rome' );
	}
	
	public static function is_send_confirm_email() {
		return (bool) self::get( 'send_confirmation_email', false );
	}
	
	public static function is_send_admin_notification_email() {
		return (bool) self::get( 'admin_email_on_booking', false );
	}


	/**
	 * Get availability date range
	 */
	public static function get_date_range() {
		return array(
			'start' => self::get( 'availability_start_date' ),
			'end'   => self::get( 'availability_end_date' ),
		);
	}

	/**
	 * Get time range
	 */
	public static function get_time_range() {
		return array(
			'first_slot' => self::get( 'first_slot_time' ),
			'last_slot'  => self::get( 'last_slot_time' ),
		);
	}

	/**
	 * Get blocked days of week (0 = Sunday, 6 = Saturday)
	 */
	public static function get_blocked_weekdays() {
		$blocked = self::get( 'blocked_weekdays' );
		return is_array( $blocked ) ? $blocked : array();
	}

	/**
	 * Get blocked specific dates
	 */
	public static function get_blocked_dates() {
		$blocked = self::get( 'blocked_specific_dates' );
		return is_array( $blocked ) ? $blocked : array();
	}

	/**
	 * Check if a date is blocked
	 */
	public static function is_date_blocked( $date ) {
		// Check if date is in blocked specific dates
		if ( in_array( $date, self::get_blocked_dates() ) ) {
			return true;
		}

		// Check if day of week is blocked
		$day_of_week = intval( date( 'w', strtotime( $date ) ) );
		if ( in_array( $day_of_week, self::get_blocked_weekdays() ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Get max reservations per slot
	 */
	public static function get_max_reservations_per_slot() {
		return intval( self::get( 'max_reservations_per_slot', 2 ) );
	}

	/**
	 * Validate settings
	 */
	public static function validate( $settings ) {
		$errors = array();

		// Validate dates
		if ( ! empty( $settings['availability_start_date'] ) ) {
			if ( ! strtotime( $settings['availability_start_date'] ) ) {
				$errors[] = 'Invalid start date';
			}
		}

		if ( ! empty( $settings['availability_end_date'] ) ) {
			if ( ! strtotime( $settings['availability_end_date'] ) ) {
				$errors[] = 'Invalid end date';
			}
		}

		// Validate that start date is before end date
		if ( ! empty( $settings['availability_start_date'] ) && ! empty( $settings['availability_end_date'] ) ) {
			if ( strtotime( $settings['availability_start_date'] ) > strtotime( $settings['availability_end_date'] ) ) {
				$errors[] = 'Start date must be before end date';
			}
		}

		// Validate times
		if ( ! empty( $settings['first_slot_time'] ) ) {
			if ( ! preg_match( '/^\d{2}:\d{2}$/', $settings['first_slot_time'] ) ) {
				$errors[] = 'Invalid first slot time format';
			}
		}

		if ( ! empty( $settings['last_slot_time'] ) ) {
			if ( ! preg_match( '/^\d{2}:\d{2}$/', $settings['last_slot_time'] ) ) {
				$errors[] = 'Invalid last slot time format';
			}
		}

		// Validate max reservations
		if ( isset( $settings['max_reservations_per_slot'] ) ) {
			if ( intval( $settings['max_reservations_per_slot'] ) < 1 ) {
				$errors[] = 'Max reservations per slot must be at least 1';
			}
		}

		return $errors;
	}
}
