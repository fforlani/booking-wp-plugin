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
			'booking-flatpickr-style',
			'https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css',
			array(),
			'4.6.13'
		);

		wp_enqueue_style(
			'booking-form-style',
			BOOKING_PLUGIN_URL . 'public/form-style.css',
			array( 'booking-flatpickr-style' ),
			BOOKING_PLUGIN_VERSION
		);

		wp_enqueue_script(
			'booking-flatpickr',
			'https://cdn.jsdelivr.net/npm/flatpickr',
			array(),
			'4.6.13',
			true
		);

		wp_enqueue_script(
			'booking-form-script',
			BOOKING_PLUGIN_URL . 'public/form-script.js',
			array( 'jquery', 'booking-flatpickr' ),
			BOOKING_PLUGIN_VERSION,
			true
		);

		wp_localize_script(
			'booking-form-script',
			'BookingData',
			array(
				'rest_url' => rest_url( 'booking/v1/' ),
				'nonce'    => wp_create_nonce( 'booking_nonce' ),
				'site_url' => site_url(),
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
			<div class="booking-form-header">
				<p class="booking-form-kicker">Prenotazione appuntamento</p>
				<h2>Prenota il tuo incontro</h2>
				<p>Scegli una data disponibile, seleziona l'orario e completa i dati richiesti.</p>
			</div>

			<form id="booking-form" class="booking-form">
				<div class="booking-step booking-step-date is-active">
					<div class="booking-step-heading">
						<span class="booking-step-number">1</span>
						<div>
							<h3>Scegli la data</h3>
							<p>Mostriamo solo i giorni con disponibilita.</p>
						</div>
					</div>

					<div class="form-group">
						<label for="booking-date">Data</label>
						<input type="text" id="booking-date" name="booking_date" list="booking-date-options" placeholder="Seleziona una data disponibile" readonly required />
						<datalist id="booking-date-options"></datalist>
					</div>
				</div>

				<div id="booking-slot-step" class="booking-step booking-step-slots" hidden>
					<div class="booking-step-heading">
						<span class="booking-step-number">2</span>
						<div>
							<h3>Scegli l'orario</h3>
							<p>Gli slot cambiano in base alla data selezionata.</p>
						</div>
					</div>

					<div class="booking-selection-summary">
						Data selezionata: <strong id="booking-selected-date">-</strong>
					</div>

					<div class="form-group">
						<label for="booking-slot">Orario</label>
						<input type="hidden" id="booking-slot" name="time_slot" />
						<div id="booking-slot-options" class="booking-slot-options" aria-live="polite">
							<div class="booking-slot-empty">Seleziona una data per vedere gli orari disponibili</div>
						</div>
					</div>
				</div>

				<div id="booking-details-step" class="booking-step booking-step-details" hidden>
					<div class="booking-step-heading">
						<span class="booking-step-number">3</span>
						<div>
							<h3>Completa i dati</h3>
							<p>Ti invieremo la conferma ai recapiti indicati.</p>
						</div>
					</div>

					<div class="booking-selection-summary">
						Appuntamento: <strong id="booking-selected-summary">-</strong>
					</div>

					<div class="booking-fields-grid">
						<div class="form-group">
							<label for="client-name">Nome dell'alunno *</label>
							<input type="text" id="client-name" name="client_name" autocomplete="given-name" required />
						</div>

						<div class="form-group">
							<label for="client-surname">Cognome dell'alunno *</label>
							<input type="text" id="client-surname" name="client_surname" autocomplete="family-name" required />
						</div>

						<div class="form-group">
							<label for="client-section">Futura sezione *</label>
							<select id="client-section" name="client_section" required>
								<option disabled selected value="">Seleziona</option>
								<option value="1">1&ordf;</option>
								<option value="2">2&ordf;</option>
								<option value="3">3&ordf;</option>
								<option value="4">4&ordf;</option>
								<option value="5">5&ordf;</option>
							</select>
						</div>

						<div class="form-group">
							<label for="client-gender">Genere</label>
							<select id="client-gender" name="client_gender">
								<option value="">Non specificato</option>
								<option value="M">Maschio</option>
								<option value="F">Femmina</option>
							</select>
						</div>

						<div class="form-group">
							<label for="client-email">Email *</label>
							<input type="email" id="client-email" name="client_email" autocomplete="email" required />
						</div>

						<div class="form-group">
							<label for="client-phone">Cellulare del genitore *</label>
							<input type="tel" id="client-phone" name="client_phone" autocomplete="tel" required />
						</div>
					</div>

					<div class="form-group">
						<button type="submit" class="submit-button">Conferma prenotazione</button>
					</div>
				</div>

				<div id="form-message" class="form-message" style="display: none;"></div>
			</form>
		</div>
		<?php
		return ob_get_clean();
	}
}
