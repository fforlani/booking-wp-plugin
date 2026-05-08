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
			'booking-bootstrap-style',
			'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css',
			array(),
			'5.3.3'
		);

		wp_enqueue_style(
			'booking-flatpickr-style',
			'https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css',
			array(),
			'4.6.13'
		);

		wp_enqueue_style(
			'booking-google-fonts',
			'https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&display=swap',
			array(),
			null
		);

		wp_enqueue_style(
			'booking-form-style',
			BOOKING_PLUGIN_URL . 'public/form-style.css',
			array( 'booking-bootstrap-style', 'booking-flatpickr-style', 'booking-google-fonts' ),
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
			'booking-bootstrap',
			'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js',
			array(),
			'5.3.3',
			true
		);

		wp_enqueue_script(
			'booking-form-script',
			BOOKING_PLUGIN_URL . 'public/form-script.js',
			array( 'jquery', 'booking-flatpickr', 'booking-bootstrap' ),
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
		$privacy_url = get_privacy_policy_url();
		if ( empty( $privacy_url ) ) {
			$privacy_url = home_url( '/privacy-policy/' );
		}

		ob_start();
		?>
		<div class="booking-form-container">
			<div class="booking-form-header">
				<p class="booking-form-kicker">Prenotazione prova taglie</p>
				<h2 class="text-uppercase">Prenota il tuo appuntamento</h2>
				<p>Scegli una data disponibile, seleziona l'orario e completa i dati richiesti.</p>
			</div>

			<form id="booking-form" class="booking-form">
				<div class="booking-step booking-step-date is-active">
					<div class="booking-step-heading">
						<span class="booking-step-number">1</span>
						<div>
							<h3>Scegli la data</h3>
							<p>Mostriamo solo i giorni con disponibilità.</p>
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
							<p>Ti invieremo la conferma alla email fornita.</p>
						</div>
					</div>

					<div class="booking-selection-summary">
						Appuntamento: <strong id="booking-selected-summary">-</strong>
					</div>

					<div class="booking-details-sections">
						<div class="booking-fieldset">
							<div class="booking-fieldset-heading">
								<h4>Informazioni sull'alunno</h4>
								<p>Inserisci i dati dello studente per cui stai prenotando l'appuntamento.</p>
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
									<label for="client-gender">Genere *</label>
									<select id="client-gender" name="client_gender" required>
										<option disabled selected value="">Seleziona</option>
										<option value="M">Maschio</option>
										<option value="F">Femmina</option>
									</select>
								</div>

								<div class="form-group">
									<label for="client-section">Futura sezione *</label>
									<select id="client-section" name="client_section" required>
										<option disabled selected value="">Seleziona</option>
										<option value="1">1&ordf;</option>
										<option value="2">2&ordf;</option>
										<option value="3">3&ordf;</option>
									</select>
								</div>
							</div>
						</div>

						<div class="booking-fieldset">
							<div class="booking-fieldset-heading">
								<h4>Recapiti dell'accompagnatore</h4>
								<p>Indica email e cellulare della persona che accompagnerà l'alunno: genitore, parente, amico o altro accompagnatore, purchè maggiorenne.</p>
							</div>

							<div class="booking-fields-grid">
								<div class="form-group">
									<label for="client-email">Email accompagnatore *</label>
									<input type="email" id="client-email" name="client_email" autocomplete="email" required />
								</div>

								<div class="form-group">
									<label for="client-phone">Cellulare accompagnatore *</label>
									<input type="tel" id="client-phone" name="client_phone" autocomplete="tel" required />
								</div>
							</div>
						</div>

						<div class="booking-privacy-box">
							<label for="privacy-consent" class="booking-privacy-label">
								<input type="checkbox" id="privacy-consent" name="privacy_consent" value="1" required />
								<span>
									Dichiaro di aver letto e accettato l'informativa sulla privacy e autorizzo il trattamento dei dati per la gestione della prenotazione.
									<a href="<?php echo esc_url( $privacy_url ); ?>" target="_blank" rel="noopener noreferrer">Leggi l'informativa privacy</a>
								</span>
							</label>
						</div>
					</div>

					<div class="form-group">
						<button type="submit" class="submit-button">Conferma prenotazione</button>
					</div>
				</div>

				<div id="form-message" class="form-message" style="display: none;"></div>
			</form>

			<div id="booking-loading-state" class="booking-state booking-loading-state text-center" hidden>
				<div class="booking-loading-spinner" aria-hidden="true"></div>
				<p class="booking-form-kicker">Ci siamo quasi</p>
				<h2>Conferma della prenotazione in corso</h2>
				<p>Non chiudere la pagina: stiamo verificando lo slot scelto e completando la registrazione.</p>
			</div>

			<div id="booking-success-state" class="booking-state booking-success-state text-center" hidden>
				<div class="booking-success-icon" aria-hidden="true">
					<span></span>
				</div>
				<p class="booking-form-kicker">Grazie!</p>
				<h2 class="text-uppercase">Prenotazione confermata</h2>
				<p id="booking-success-message">La tua prenotazione è stata registrata correttamente.</p>
				<div class="booking-success-summary">
					<div>
						<span>Quando</span>
						<strong id="booking-success-when">-</strong>
					</div>
					<div>
						<span>Alunno</span>
						<strong id="booking-success-student">-</strong>
					</div>
					<div>
						<span>Accompagnatore</span>
						<strong id="booking-success-contact">-</strong>
					</div>
				</div>
				<button type="button" id="booking-new-reservation" class="submit-button booking-secondary-button">Nuova prenotazione</button>
			</div>

			<div id="booking-error-modal" class="booking-error-modal" role="dialog" aria-modal="true" aria-labelledby="booking-error-title" hidden>
				<div class="booking-error-backdrop" data-booking-error-close></div>
				<div class="booking-error-card">
					<button type="button" class="booking-error-close" aria-label="Chiudi avviso" data-booking-error-close>&times;</button>
					<div class="booking-error-icon" aria-hidden="true">!</div>
					<p class="booking-form-kicker">Prenotazione non completata</p>
					<h2 id="booking-error-title">Si è verificato un errore</h2>
					<p id="booking-error-text">Non siamo riusciti a completare la prenotazione. Riprova tra qualche istante.</p>
					<button type="button" class="submit-button booking-error-button" data-booking-error-close>Ho capito</button>
				</div>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}
}
