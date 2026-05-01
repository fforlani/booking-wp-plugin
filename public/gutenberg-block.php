<?php
/**
 * Gutenberg block for Booking System
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Booking_Gutenberg_Block {

	/**
	 * Initialize block
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_block' ) );
	}

	/**
	 * Register Gutenberg block
	 */
	public static function register_block() {
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}

		register_block_type(
			'booking-system/form',
			array(
				'render_callback' => array( __CLASS__, 'render_block' ),
			)
		);
	}

	/**
	 * Render block
	 */
	public static function render_block( $attributes ) {
		return do_shortcode( '[booking_form]' );
	}
}

// Initialize block
Booking_Gutenberg_Block::init();
