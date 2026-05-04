<?php
/**
 * Availability management for Booking System
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Booking_Availability {

	/**
	 * Generate all available slots for a date
	 */
	public static function get_available_slots_for_date( $date ) {
		// Check if date is within availability range
		$range = Booking_Settings::get_date_range();
		if ( strtotime( $date ) < strtotime( $range['start'] ) || strtotime( $date ) > strtotime( $range['end'] ) ) {
			return array();
		}

		// Check if date is blocked
		if ( Booking_Settings::is_date_blocked( $date ) ) {
			return array();
		}

		$slots = array();
		$time_range = Booking_Settings::get_time_range();
		$first_slot = strtotime( $time_range['first_slot'] );
		$last_slot = strtotime( $time_range['last_slot'] );
		$slot_duration = intval( Booking_Settings::get( 'slot_duration_minutes', 60 ) );
		$max_reservations = Booking_Settings::get_max_reservations_per_slot();

		$current_time = $first_slot;

		while ( $current_time < $last_slot ) {
			$time_slot = date( 'H:i', $current_time );
			$count = Booking_DB::count_reservations_in_slot( $date, $time_slot );

			// Only add slot if below max reservations
			if ( $count < $max_reservations ) {
				$slots[] = array(
					'time'           => $time_slot,
					'available_spots' => $max_reservations - $count,
				);
			}

			$current_time += $slot_duration * 60; // Convert minutes to seconds
		}

		return $slots;
	}

	/**
	 * Get all available dates in range with at least one free slot
	 */
	public static function get_available_dates() {
		$range = Booking_Settings::get_date_range();
		$dates = array();

		$start = strtotime( $range['start'] );
		$end = strtotime( $range['end'] );

		for ( $current = $start; $current <= $end; $current += DAY_IN_SECONDS ) {
			$date = date( 'Y-m-d', $current );

			if ( Booking_Settings::is_date_blocked( $date ) ) {
				continue;
			}

			$slots = self::get_available_slots_for_date( $date );
			if ( ! empty( $slots ) ) {
				$dates[] = $date;
			}
		}

		return $dates;
	}

	/**
	 * Check if a specific slot is available
	 */
	public static function is_slot_available( $date, $time_slot ) {
		// Check if date is within availability range
		$range = Booking_Settings::get_date_range();
		if ( strtotime( $date ) < strtotime( $range['start'] ) || strtotime( $date ) > strtotime( $range['end'] ) ) {
			return false;
		}

		// Check if date is blocked
		if ( Booking_Settings::is_date_blocked( $date ) ) {
			return false;
		}

		// Check if slot is within time range
		$time_range = Booking_Settings::get_time_range();
		$slot_time = strtotime( $time_slot );
		if ( $slot_time < strtotime( $time_range['first_slot'] ) || $slot_time >= strtotime( $time_range['last_slot'] ) ) {
			return false;
		}

		// Check reservation count
		$count = Booking_DB::count_reservations_in_slot( $date, $time_slot );
		$max = Booking_Settings::get_max_reservations_per_slot();

		return $count < $max;
	}

	/**
	 * Get count of reservations in a slot
	 */
	public static function count_reservations_in_slot( $date, $time_slot ) {
		return Booking_DB::count_reservations_in_slot( $date, $time_slot );
	}
}
