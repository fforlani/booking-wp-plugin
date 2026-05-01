<?php
/**
 * Plugin Name: Booking System
 * Description: Gestisci prenotazioni con slot orari, Google Calendar e email di conferma
 * Version: 1.0.0
 * Author: Dev Team
 * License: GPL-2.0-or-later
 * Text Domain: booking-system
 * Domain Path: /languages
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants
define( 'BOOKING_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'BOOKING_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'BOOKING_PLUGIN_VERSION', '1.0.0' );

// Load configuration and classes
require_once BOOKING_PLUGIN_DIR . 'config/defaults.php';
require_once BOOKING_PLUGIN_DIR . 'includes/class-booking-db.php';
require_once BOOKING_PLUGIN_DIR . 'includes/class-booking-settings.php';
require_once BOOKING_PLUGIN_DIR . 'includes/class-booking-logger.php';
require_once BOOKING_PLUGIN_DIR . 'includes/class-booking-security.php';
require_once BOOKING_PLUGIN_DIR . 'includes/class-booking-availability.php';
require_once BOOKING_PLUGIN_DIR . 'includes/class-booking-reservation.php';
require_once BOOKING_PLUGIN_DIR . 'includes/class-booking-email.php';
require_once BOOKING_PLUGIN_DIR . 'includes/class-booking-google-calendar.php';
require_once BOOKING_PLUGIN_DIR . 'includes/rest-api.php';
require_once BOOKING_PLUGIN_DIR . 'admin/admin-menu.php';
require_once BOOKING_PLUGIN_DIR . 'public/shortcode.php';
require_once BOOKING_PLUGIN_DIR . 'public/gutenberg-block.php';

/**
 * Plugin activation hook
 */
register_activation_hook( __FILE__, 'booking_plugin_activate' );
function booking_plugin_activate() {
	// Create database tables
	Booking_DB::create_tables();

	// Flush rewrite rules (for REST API)
	flush_rewrite_rules();
}

/**
 * Plugin deactivation hook
 */
register_deactivation_hook( __FILE__, 'booking_plugin_deactivate' );
function booking_plugin_deactivate() {
	// Clean up if needed
	flush_rewrite_rules();
}

/**
 * Plugin initialization
 */
add_action( 'plugins_loaded', 'booking_plugin_init' );
function booking_plugin_init() {
	// Load plugin text domain for translations
	load_plugin_textdomain( 'booking-system', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

	// Initialize admin only features
	if ( is_admin() ) {
		Booking_Admin::init();
	}

	// Initialize shortcode
	Booking_Shortcode::init();

	// Initialize REST API
	Booking_REST_API::init();
}

// Ensure Booking_DB is instantiated when needed
add_action( 'init', function() {
	// Make sure our database class is available
	if ( ! class_exists( 'Booking_DB' ) ) {
		require_once BOOKING_PLUGIN_DIR . 'includes/class-booking-db.php';
	}
} );
