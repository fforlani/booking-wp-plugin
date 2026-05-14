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

		$recaptcha_enabled  = Booking_Security::is_recaptcha_enabled();
		$recaptcha_site_key = Booking_Settings::get( 'recaptcha_site_key', '' );
		$form_script_deps   = array( 'jquery', 'booking-flatpickr', 'booking-bootstrap' );

		if ( $recaptcha_enabled ) {
			wp_enqueue_script(
				'booking-google-recaptcha',
				'https://www.google.com/recaptcha/api.js?render=' . rawurlencode( $recaptcha_site_key ),
				array(),
				null,
				true
			);

			$form_script_deps[] = 'booking-google-recaptcha';
		}

		wp_enqueue_script(
			'booking-form-script',
			BOOKING_PLUGIN_URL . 'public/form-script.js',
			$form_script_deps,
			BOOKING_PLUGIN_VERSION,
			true
		);

		wp_localize_script(
			'booking-form-script',
			'BookingData',
			array(
				'rest_url'           => rest_url( 'booking/v1/' ),
				'nonce'              => wp_create_nonce( 'wp_rest' ),
				'site_url'           => site_url(),
				'manage_token'       => self::get_management_token_from_request(),
				'recaptcha_enabled'  => $recaptcha_enabled,
				'recaptcha_site_key' => $recaptcha_enabled ? $recaptcha_site_key : '',
				'recaptcha_action'   => 'booking_reserve',
			)
		);
	}

	/**
	 * Render booking form
	 */
	public static function render_form() {
		if ( self::is_management_request() ) {
			return self::render_management_form();
		}

		$privacy_url = get_privacy_policy_url();
		if ( empty( $privacy_url ) ) {
			$privacy_url = home_url( '/privacy-policy/' );
		}

		ob_start();
		?>
		<div class="booking-form-container">
			<div class="booking-form-header">
				<p class="booking-form-kicker pb-0">Prenota il tuo appuntamento</p>
				<h2 class="text-uppercase">Prenotazione prova taglie</h2>
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
								<p>* Campi obbligatori</p>
							</div>

							<div class="booking-fields-grid">
								<div class="form-group">
									<label for="client-name">Nome dell'alunno *</label>
									<input type="text" id="client-name" name="client_name" autocomplete="given-name" placeholder="Mario" required />
								</div>

								<div class="form-group">
									<label for="client-surname">Cognome dell'alunno *</label>
									<input type="text" id="client-surname" name="client_surname" autocomplete="family-name" placeholder="Rossi" required />
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
									<label for="client-section">Futura classe *</label>
									<select id="client-section" name="client_section" required>
										<option disabled selected value="">Seleziona</option>
										<option value="1">1&ordf;</option>
										<option value="2">2&ordf;</option>
										<option value="3">3&ordf;</option>
										<option value="4">4&ordf;</option>
										<option value="5">5&ordf;</option>
									</select>
								</div>
							</div>
						</div>

						<div class="booking-fieldset">
							<div class="booking-fieldset-heading">
								<h4>Recapiti del genitore</h4>
								<p>Indica email e cellulare della persona che accompagnerà l'alunno: genitore, parente, amico o altro genitore, purchè maggiorenne.</p>
								<p>* Campi obbligatori</p>
							</div>

							<div class="booking-fields-grid">
								<div class="form-group">
									<label for="client-email">Email genitore *</label>
									<input type="email" id="client-email" name="client_email" autocomplete="email" placeholder="lucabianchi@gmail.com" required />
								</div>

								<div class="form-group">
									<label for="client-phone">Cellulare genitore *</label>
									<input type="tel" id="client-phone" name="client_phone" autocomplete="tel" placeholder="3334455666" required />
								</div>
							</div>
						</div>

						<div class="booking-privacy-box mb-2">
							<label for="privacy-consent" class="booking-privacy-label">
								<input type="checkbox" id="privacy-consent" name="privacy_consent" value="1" required />
								<span class="text-dark">
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
						<span>Genitore</span>
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

	private static function render_management_form() {
		$token = self::get_management_token_from_request();
		$booking = self::get_booking_from_management_token( $token );

		ob_start();
		?>
		<div class="booking-form-container booking-management-container">
			<?php if ( is_wp_error( $booking ) ) : ?>
				<div class="booking-state text-center">
					<p class="booking-form-kicker">Link non valido</p>
					<h2>Non possiamo gestire questa prenotazione</h2>
					<p><?php echo esc_html( $booking->get_error_message() ); ?></p>
				</div>
			<?php else : ?>
				<div class="booking-form-header">
					<p class="booking-form-kicker">Gestisci prenotazione</p>
					<h2 class="text-uppercase">Modifica o cancella il tuo appuntamento</h2>
					<p>Puoi cancellare la prenotazione oppure scegliere una nuova data e un nuovo orario tra quelli disponibili.</p>
				</div>

				<div class="booking-current-summary">
					<div>
						<span>Appuntamento attuale</span>
						<strong><?php echo esc_html( date_i18n( 'd/m/Y', strtotime( $booking->booking_date ) ) . ' alle ' . substr( $booking->time_slot, 0, 5 ) ); ?></strong>
					</div>
					<div>
						<span>Alunno</span>
						<strong><?php echo esc_html( trim( $booking->client_name . ' ' . $booking->client_surname ) ); ?></strong>
					</div>
					<div>
						<span>Contatti</span>
						<strong><?php echo esc_html( $booking->client_email ); ?></strong>
						<strong><?php echo esc_html( $booking->client_phone ); ?></strong>
					</div>
				</div>

				<form id="booking-manage-form" class="booking-form" data-booking-token="<?php echo esc_attr( $token ); ?>">
					<input type="hidden" id="booking-manage-token" value="<?php echo esc_attr( $token ); ?>" />

					<div class="booking-step booking-step-date is-active">
						<div class="booking-step-heading">
							<span class="booking-step-number">1</span>
							<div>
								<h3>Scegli la nuova data</h3>
								<p>Lascia la data attuale oppure seleziona un altro giorno disponibile.</p>
							</div>
						</div>

						<div class="form-group">
							<label for="booking-date">Data</label>
							<input type="text" id="booking-date" name="booking_date" list="booking-date-options" value="<?php echo esc_attr( $booking->booking_date ); ?>" readonly required />
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
							<input type="hidden" id="booking-slot" name="time_slot" value="<?php echo esc_attr( substr( $booking->time_slot, 0, 5 ) ); ?>" />
							<div id="booking-slot-options" class="booking-slot-options" aria-live="polite">
								<div class="booking-slot-empty">Seleziona una data per vedere gli orari disponibili</div>
							</div>
						</div>
					</div>

					<div class="booking-selection-summary">
						Nuovo appuntamento: <strong id="booking-selected-summary">-</strong>
					</div>

					<div class="booking-management-actions">
						<button type="submit" class="submit-button">Riprogramma prenotazione</button>
						<button type="button" id="booking-cancel-reservation" class="submit-button booking-danger-button">Cancella prenotazione</button>
					</div>

					<div id="form-message" class="form-message" style="display: none;"></div>
				</form>

				<div id="booking-loading-state" class="booking-state booking-loading-state text-center" hidden>
					<div class="booking-loading-spinner" aria-hidden="true"></div>
					<p class="booking-form-kicker">Aggiornamento in corso</p>
					<h2>Stiamo aggiornando la prenotazione</h2>
					<p>Non chiudere la pagina: stiamo verificando disponibilità e calendario.</p>
				</div>

				<div id="booking-success-state" class="booking-state booking-success-state text-center" hidden>
					<div class="booking-success-icon" aria-hidden="true">
						<span></span>
					</div>
					<p class="booking-form-kicker">Operazione completata</p>
					<h2 class="text-uppercase">Prenotazione aggiornata</h2>
					<p id="booking-success-message">La tua richiesta e stata completata correttamente.</p>
				</div>
			<?php endif; ?>

			<div id="booking-error-modal" class="booking-error-modal" role="dialog" aria-modal="true" aria-labelledby="booking-error-title" hidden>
				<div class="booking-error-backdrop" data-booking-error-close></div>
				<div class="booking-error-card">
					<button type="button" class="booking-error-close" aria-label="Chiudi avviso" data-booking-error-close>&times;</button>
					<div class="booking-error-icon" aria-hidden="true">!</div>
					<p class="booking-form-kicker">Operazione non completata</p>
					<h2 id="booking-error-title">Si e verificato un errore</h2>
					<p id="booking-error-text">Non siamo riusciti a completare la richiesta. Riprova tra qualche istante.</p>
					<button type="button" class="submit-button booking-error-button" data-booking-error-close>Ho capito</button>
				</div>
			</div>

			<div id="booking-confirm-cancel-modal" class="booking-error-modal booking-confirm-modal" role="dialog" aria-modal="true" aria-labelledby="booking-confirm-cancel-title" hidden>
				<div class="booking-error-backdrop" data-booking-confirm-close></div>
				<div class="booking-error-card">
					<button type="button" class="booking-error-close" aria-label="Chiudi conferma" data-booking-confirm-close>&times;</button>
					<div class="booking-confirm-icon" aria-hidden="true">?</div>
					<p class="booking-form-kicker">Conferma cancellazione</p>
					<h2 id="booking-confirm-cancel-title">Vuoi cancellare la prenotazione?</h2>
					<p>Questa operazione libererà lo slot scelto e non potra essere annullata da questa pagina.</p>
					<div class="booking-confirm-actions">
						<button type="button" class="submit-button booking-secondary-button" data-booking-confirm-close>Annulla</button>
						<button type="button" id="booking-confirm-cancel-action" class="submit-button booking-danger-button">Cancella prenotazione</button>
					</div>
				</div>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	private static function is_management_request() {
		return isset( $_GET['booking_action'], $_GET['token'] ) && 'manage' === sanitize_key( wp_unslash( $_GET['booking_action'] ) );
	}

	private static function get_management_token_from_request() {
		return isset( $_GET['token'] ) ? sanitize_text_field( wp_unslash( $_GET['token'] ) ) : '';
	}

	private static function get_booking_from_management_token( $token ) {
		$token_data = Booking_Security::validate_booking_management_token( $token );

		if ( is_wp_error( $token_data ) ) {
			return $token_data;
		}

		$booking = Booking_DB::get_booking( $token_data['booking_id'] );
		if ( ! $booking ) {
			return new WP_Error( 'booking_not_found', 'La prenotazione non esiste piu o e gia stata cancellata.' );
		}

		if ( 'cancelled' === strtolower( (string) $booking->status ) ) {
			return new WP_Error( 'booking_cancelled', 'La prenotazione e gia stata cancellata.' );
		}

		if ( strtolower( $booking->client_email ) !== strtolower( $token_data['email'] ) ) {
			return new WP_Error( 'invalid_token', 'Il link non corrisponde alla prenotazione richiesta.' );
		}

		return $booking;
	}
}
