<?php
/**
 * Database operations for Booking System
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Booking_DB {

	/**
	 * Create database tables on plugin activation
	 */
	public static function create_tables() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		// Table for bookings
		$bookings_table = "
		CREATE TABLE IF NOT EXISTS {$wpdb->prefix}bookings (
			id BIGINT(20) NOT NULL AUTO_INCREMENT,
			booking_date DATE NOT NULL,
			time_slot TIME NOT NULL,
			client_name VARCHAR(100) NOT NULL,
			client_surname VARCHAR(100) NOT NULL,
			client_section INT NOT NULL,
			client_email VARCHAR(100) NOT NULL,
			client_phone VARCHAR(20) NOT NULL,
			client_gender VARCHAR(1) NULL,
			status VARCHAR(20) DEFAULT 'pending',
			google_event_id VARCHAR(255),
			created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY booking_date_idx (booking_date),
			KEY time_slot_idx (time_slot),
			KEY status_idx (status),
			UNIQUE KEY unique_booking (booking_date, time_slot, client_name, client_surname)
		) {$charset_collate};
		";

		// Table for booking logs
		$logs_table = "
		CREATE TABLE IF NOT EXISTS {$wpdb->prefix}booking_logs (
			id BIGINT(20) NOT NULL AUTO_INCREMENT,
			booking_id BIGINT(20),
			action VARCHAR(50) NOT NULL,
			message TEXT,
			client_email VARCHAR(100),
			timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY booking_id_idx (booking_id),
			KEY timestamp_idx (timestamp),
			FOREIGN KEY (booking_id) REFERENCES {$wpdb->prefix}bookings(id) ON DELETE CASCADE
		) {$charset_collate};
		";

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta( $bookings_table );
		dbDelta( $logs_table );
	}

	/**
	 * Create a new booking
	 */
	public static function create_booking( $data ) {
		global $wpdb;

		$wpdb->show_errors();

		$result = $wpdb->insert(
			$wpdb->prefix . 'bookings',
			array(
				'booking_date'    => sanitize_text_field( $data['booking_date'] ),
				'time_slot'       => sanitize_text_field( $data['time_slot'] ),
				'client_name'     => sanitize_text_field( $data['client_name'] ),
				'client_surname'  => sanitize_text_field( $data['client_surname'] ),
				'client_section'  => sanitize_text_field( $data['client_section'] ),
				'client_email'    => sanitize_email( $data['client_email'] ),
				'client_phone'    => sanitize_text_field( $data['client_phone'] ),
				'client_gender'   => ! empty( $data['client_gender'] ) ? sanitize_text_field( $data['client_gender'] ) : null,
				'status'          => 'confirmed',
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( false === $result ) {
			return new WP_Error( 'db_error', $wpdb->last_error );
		}

		return $wpdb->insert_id;
	}

	/**
	 * Update booking status
	 */
	public static function update_booking_status( $booking_id, $status ) {
		global $wpdb;

		$result = $wpdb->update(
			$wpdb->prefix . 'bookings',
			array( 'status' => sanitize_text_field( $status ) ),
			array( 'id' => intval( $booking_id ) ),
			array( '%s' ),
			array( '%d' )
		);

		return $result !== false;
	}

	/**
	 * Update booking with Google Calendar event ID
	 */
	public static function update_booking_google_event( $booking_id, $event_id ) {
		global $wpdb;

		$result = $wpdb->update(
			$wpdb->prefix . 'bookings',
			array( 'google_event_id' => sanitize_text_field( $event_id ) ),
			array( 'id' => intval( $booking_id ) ),
			array( '%s' ),
			array( '%d' )
		);

		return $result !== false;
	}

	/**
	 * Update booking date and time slot.
	 */
	public static function update_booking_schedule( $booking_id, $booking_date, $time_slot ) {
		global $wpdb;

		$result = $wpdb->update(
			$wpdb->prefix . 'bookings',
			array(
				'booking_date' => sanitize_text_field( $booking_date ),
				'time_slot'    => sanitize_text_field( $time_slot ),
			),
			array( 'id' => intval( $booking_id ) ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		return $result !== false;
	}

	/**
	 * Get booking by ID
	 */
	public static function get_booking( $booking_id ) {
		global $wpdb;

		$query = $wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}bookings WHERE id = %d",
			intval( $booking_id )
		);

		return $wpdb->get_row( $query );
	}

	/**
	 * Delete booking by ID
	 */
	public static function delete_booking( $booking_id ) {
		global $wpdb;

		$booking_id = intval( $booking_id );
		if ( $booking_id <= 0 ) {
			return false;
		}

		$result = $wpdb->delete(
			$wpdb->prefix . 'bookings',
			array( 'id' => $booking_id ),
			array( '%d' )
		);

		return $result > 0;
	}

	/**
	 * Count reservations for a specific slot
	 */
	public static function count_reservations_in_slot( $booking_date, $time_slot ) {
		global $wpdb;

		$query = $wpdb->prepare(
			"SELECT COUNT(*) as count FROM {$wpdb->prefix}bookings 
			WHERE booking_date = %s AND time_slot = %s AND status IN ('pending', 'confirmed')",
			sanitize_text_field( $booking_date ),
			sanitize_text_field( $time_slot )
		);

		$result = $wpdb->get_row( $query );
		return $result ? intval( $result->count ) : 0;
	}

	/**
	 * Check if email already has reservation in same slot
	 */
	public static function has_email_booking_in_slot( $booking_date, $time_slot, $email ) {
		global $wpdb;

		$query = $wpdb->prepare(
			"SELECT COUNT(*) as count FROM {$wpdb->prefix}bookings 
			WHERE booking_date = %s AND time_slot = %s AND client_email = %s AND status IN ('pending', 'confirmed')",
			sanitize_text_field( $booking_date ),
			sanitize_text_field( $time_slot ),
			sanitize_email( $email )
		);

		$result = $wpdb->get_row( $query );
		return $result ? intval( $result->count ) > 0 : false;
	}

	/**
	 * Get all bookings for a date
	 */
	public static function get_bookings_by_date( $booking_date ) {
		global $wpdb;

		$query = $wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}bookings WHERE booking_date = %s AND status IN ('pending', 'confirmed') ORDER BY time_slot ASC",
			sanitize_text_field( $booking_date )
		);

		return $wpdb->get_results( $query );
	}

	/**
	 * Log booking action
	 */
	public static function log_action( $action, $message, $booking_id = null, $client_email = null ) {
		global $wpdb;

		$wpdb->insert(
			$wpdb->prefix . 'booking_logs',
			array(
				'booking_id'   => $booking_id ? intval( $booking_id ) : null,
				'action'       => sanitize_text_field( $action ),
				'message'      => $message,
				'client_email' => $client_email ? sanitize_email( $client_email ) : null,
			),
			array( '%d', '%s', '%s', '%s' )
		);
	}

	/**
	 * Get booking logs
	 */
	public static function get_logs( $limit = 100, $offset = 0 ) {
		global $wpdb;

		$query = $wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}booking_logs ORDER BY id DESC LIMIT %d OFFSET %d",
			intval( $limit ),
			intval( $offset )
		);

		return $wpdb->get_results( $query );
	}

	/**
	 * Delete old logs (older than 90 days)
	 */
	public static function cleanup_old_logs( $days = 90 ) {
		global $wpdb;

		$cutoff_date = date( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );

		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->prefix}booking_logs WHERE timestamp < %s",
				$cutoff_date
			)
		);
	}
}
