<?php
/**
 * Shortcode for Booking System
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Booking_Shortcode {

	/**
	 * Initialize shortcode
	 */
	public static function init() {
		add_shortcode( 'booking_form', array( __CLASS__, 'render_form' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
	}

	/**
	 * Enqueue scripts and styles
	 */
	public static function enqueue_assets() {
		// Only enqueue on pages that have the shortcode
		if ( ! is_singular() && ! has_shortcode( get_the_content(), 'booking_form' ) ) {
			return;
		}

		wp_enqueue_style(
			'booking-form-style',
			BOOKING_PLUGIN_URL . 'public/form-style.css',
			array(),
			BOOKING_PLUGIN_VERSION
		);

		wp_enqueue_script(
			'booking-form-script',
			BOOKING_PLUGIN_URL . 'public/form-script.js',
			array( 'jquery' ),
			BOOKING_PLUGIN_VERSION,
			true
		);

		wp_localize_script(
			'booking-form-script',
			'BookingData',
			array(
				'rest_url'   => rest_url( 'booking/v1/' ),
				'nonce'      => wp_create_nonce( 'booking_nonce' ),
				'site_url'   => site_url(),
			)
		);
	}

	/**
	 * Render booking form
	 */
	public static function render_form() {
		ob_start();
		?>
		<div class="booking-form-container">
			<h2>Prenota un Appuntamento</h2>

			<form id="booking-form" class="booking-form">
				<div class="form-group">
					<label for="booking-date">Data:</label>
					<input type="date" id="booking-date" name="booking_date" list="booking-date-options" required />
					<datalist id="booking-date-options"></datalist>
				</div>

				<div class="form-group">
					<label for="booking-slot">Orario:</label>
					<select id="booking-slot" name="time_slot" required>
						<option value="">Seleziona un orario</option>
					</select>
				</div>

				<div class="form-group">
					<label for="client-name">Nome dell'alunno *:</label>
					<input type="text" id="client-name" name="client_name" required />
				</div>

				<div class="form-group">
					<label for="client-surname">Cognome dell'alunno *:</label>
					<input type="text" id="client-surname" name="client_surname" required />
				</div>

				<div class="form-group">
					<label for="client-section">Futura sezione *:</label>
					<select id="client-section" name="client_section" required >
						<option disabled selected value="">Seleziona</option>
						<option value="1">1ª</option>
						<option value="2">2ª</option>
						<option value="3">3ª</option>
						<option value="4">4ª</option>
						<option value="5">5ª</option>
					</select>
				</div>

				<div class="form-group">
					<label for="client-email">Email *:</label>
					<input type="email" id="client-email" name="client_email" required />
				</div>

				<div class="form-group">
					<label for="client-phone">Cellulare del genitore *:</label>
					<input type="tel" id="client-phone" name="client_phone" required />
				</div>

				<div class="form-group">
					<button type="submit" class="submit-button">Prenota Appuntamento</button>
				</div>

				<div id="form-message" class="form-message" style="display: none;"></div>
			</form>
		</div>
		<?php
		return ob_get_clean();
	}
}
