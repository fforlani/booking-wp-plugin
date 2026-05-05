<?php
/**
 * Admin menu and settings for Booking System
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Booking_Admin {

	/**
	 * Initialize admin features
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_admin_menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin_assets' ) );
	}

	/**
	 * Enqueue admin assets
	 */
	public static function enqueue_admin_assets( $hook ) {
		if ( strpos( $hook, 'booking-system' ) === false ) {
			return;
		}

		wp_enqueue_style( 'booking-admin-style', BOOKING_PLUGIN_URL . 'admin/admin-style.css', array(), BOOKING_PLUGIN_VERSION );
		wp_enqueue_script( 'booking-admin-script', BOOKING_PLUGIN_URL . 'admin/admin-script.js', array( 'jquery' ), BOOKING_PLUGIN_VERSION, true );
	}

	/**
	 * Add admin menu
	 */
	public static function add_admin_menu() {
		add_menu_page(
			'Booking System',
			'Booking System',
			'manage_options',
			'booking-system',
			array( __CLASS__, 'render_settings_page' ),
			'dashicons-calendar-alt',
			30
		);

		add_submenu_page(
			'booking-system',
			'Impostazioni',
			'Impostazioni',
			'manage_options',
			'booking-system',
			array( __CLASS__, 'render_settings_page' )
		);

		add_submenu_page(
			'booking-system',
			'Prenotazioni',
			'Prenotazioni',
			'manage_options',
			'booking-bookings',
			array( __CLASS__, 'render_bookings_page' )
		);

		add_submenu_page(
			'booking-system',
			'Log',
			'Log',
			'manage_options',
			'booking-logs',
			array( __CLASS__, 'render_logs_page' )
		);
	}

	/**
	 * Sanitize blocked specific dates from textarea (convert string to array)
	 */
	public static function sanitize_blocked_specific_dates( $value ) {
		if ( is_array( $value ) ) {
			return array_filter( array_map( 'sanitize_text_field', $value ) );
		}

		// Convert newline-separated string to array
		$dates = array_map( 'trim', explode( "\n", $value ) );
		$dates = array_filter( $dates ); // Remove empty lines

		return array_map( 'sanitize_text_field', $dates );
	}

	/**
	 * Register settings
	 */
	public static function register_settings() {
		register_setting( 'booking_system_group', 'booking_system_availability_start_date' );
		register_setting( 'booking_system_group', 'booking_system_availability_end_date' );
		register_setting( 'booking_system_group', 'booking_system_first_slot_time' );
		register_setting( 'booking_system_group', 'booking_system_last_slot_time' );
		register_setting( 'booking_system_group', 'booking_system_slot_duration_minutes' );
		register_setting( 'booking_system_group', 'booking_system_max_reservations_per_slot' );
		register_setting( 'booking_system_group', 'booking_system_blocked_weekdays' );
		register_setting( 
			'booking_system_group', 
			'booking_system_blocked_specific_dates',
			array(
				'sanitize_callback' => array( __CLASS__, 'sanitize_blocked_specific_dates' ),
				'type'              => 'array'
			)
		);
		register_setting( 'booking_system_group', 'booking_system_booking_timezone' );
		register_setting( 'booking_system_group', 'booking_system_admin_email_on_booking' );
		register_setting( 'booking_system_group', 'booking_system_send_confirmation_email' );
		register_setting( 'booking_system_group', 'booking_system_google_calendar_enabled' );
		register_setting( 'booking_system_group', 'booking_system_google_calendar_id' );
		register_setting('booking_system_group', 'booking_system_rate_limit_enabled');
		register_setting('booking_system_group', 'booking_system_rate_limit_attempts');
/* 		register_setting( 'booking_system_group', 'booking_system_enable_recaptcha' );
		register_setting( 'booking_system_group', 'booking_system_recaptcha_site_key' );
		register_setting( 'booking_system_group', 'booking_system_recaptcha_secret_key' ); */

		// Handle Google credentials upload
		if ( isset( $_POST['booking_upload_credentials_nonce'] )) {
			self::handle_google_credentials_upload();
		}
	}

	/**
	 * Handle Google Calendar credentials upload
	 */
	private static function handle_google_credentials_upload() {
		// Verify nonce
		if ( ! isset( $_POST['booking_upload_credentials_nonce'] ) || 
		     ! wp_verify_nonce( $_POST['booking_upload_credentials_nonce'], 'booking_upload_credentials' ) ) {
			wp_die( 'Nonce verification failed' );
		}

		// Check user permissions
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Accesso negato' );
		}

		// Check if file was uploaded
		if ( ! isset( $_FILES['booking_credentials_file'] ) || empty( $_FILES['booking_credentials_file']['name'] ) ) {
			set_transient( 'booking_upload_error', 'Nessun file selezionato', 10 );
			wp_redirect( admin_url( 'admin.php?page=booking-system' ) );
			return;
		}

		$file = $_FILES['booking_credentials_file'];

		// Validate file
		if ( $file['error'] !== UPLOAD_ERR_OK ) {
			set_transient( 'booking_upload_error', 'Errore nel caricamento del file: ' . $file['error'], 10 );
			wp_redirect( admin_url( 'admin.php?page=booking-system' ) );
			return;
		}

		// Check file type
		$allowed_types = array( 'application/json' );
		$file_type = mime_content_type( $file['tmp_name'] );
		if ( ! in_array( $file_type, $allowed_types ) ) {
			set_transient( 'booking_upload_error', 'Il file deve essere in formato JSON', 10 );
			wp_redirect( admin_url( 'admin.php?page=booking-system' ) );
			return;
		}

		// Check file size (max 1MB)
		if ( $file['size'] > 1048576 ) {
			set_transient( 'booking_upload_error', 'Il file è troppo grande (max 1MB)', 10 );
			wp_redirect( admin_url( 'admin.php?page=booking-system' ) );
			return;
		}

		// Read and validate JSON
		$content = file_get_contents( $file['tmp_name'] );
		$credentials = json_decode( $content, true );

		if ( ! $credentials || ! isset( $credentials['type'] ) || $credentials['type'] !== 'service_account' ) {
			set_transient( 'booking_upload_error', 'File credenziali non valido. Deve essere un file service account JSON di Google', 10 );
			wp_redirect( admin_url( 'admin.php?page=booking-system' ) );
			return;
		}

		// Save credentials
		$result = Booking_Google_Calendar::save_credentials( $content );
		if ( is_wp_error( $result ) ) {
			set_transient( 'booking_upload_error', 'Errore: ' . $result->get_error_message(), 10 );
		} else {
			set_transient( 'booking_upload_success', 'Credenziali Google salvate con successo!', 10 );
		}

		wp_redirect( admin_url( 'admin.php?page=booking-system' ) );
		return;
	}

	/**
	 * Check if credentials are configured
	 */
	private static function has_google_credentials() {
		return Booking_Google_Calendar::get_credentials() !== null;
	}

	/**
	 * Render settings page
	 */
	public static function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Accesso negato' );
		}

		$settings = Booking_Settings::get_all();

		// Check for transient messages
		$upload_error = get_transient( 'booking_upload_error' );
		$upload_success = get_transient( 'booking_upload_success' );

		?>
		<div class="wrap booking-admin-wrap">
			<h1>Booking System - Impostazioni</h1>

			<?php if ( $upload_error ) { ?>
				<div class="notice notice-error is-dismissible">
					<p><?php echo esc_html( $upload_error ); ?></p>
				</div>
				<?php delete_transient( 'booking_upload_error' ); ?>
			<?php } ?>

			<?php if ( $upload_success ) { ?>
				<div class="notice notice-success is-dismissible">
					<p><?php echo esc_html( $upload_success ); ?></p>
				</div>
				<?php delete_transient( 'booking_upload_success' ); ?>
			<?php } ?>

			<h2 class="nav-tab-wrapper">
				<a href="#tab-general" class="nav-tab nav-tab-active">Generale</a>
				<a href="#tab-blocked-dates" class="nav-tab">Date Bloccate</a>
				<a href="#tab-google" class="nav-tab">Google Calendar</a>
				<a href="#tab-security" class="nav-tab">Sicurezza</a>
			</h2>

			<form method="post" action="options.php">
				<?php settings_fields( 'booking_system_group' ); ?>

				<!-- General Settings Tab -->
				<div id="tab-general" class="tab-content active">
					<h3>Configurazione Base</h3>
					<table class="form-table">
						<tr>
							<th scope="row">
								<label for="availability_start_date">Data Inizio Disponibilità:</label>
							</th>
							<td>
								<input type="date" id="availability_start_date" name="booking_system_availability_start_date" value="<?php echo esc_attr( $settings['availability_start_date'] ); ?>" />
								<p class="description">Data da cui iniziano le prenotazioni (es: 3 giugno 2026)</p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="availability_end_date">Data Fine Disponibilità:</label>
							</th>
							<td>
								<input type="date" id="availability_end_date" name="booking_system_availability_end_date" value="<?php echo esc_attr( $settings['availability_end_date'] ); ?>" />
								<p class="description">Data fino a cui è possibile prenotare (es: 18 settembre 2026)</p>
							</td>
						</tr>
					</table>

					<h3>Orari Disponibilità</h3>
					<table class="form-table">
						<tr>
							<th scope="row">
								<label for="first_slot_time">Primo Slot Orario:</label>
							</th>
							<td>
								<input type="time" id="first_slot_time" name="booking_system_first_slot_time" value="<?php echo esc_attr( $settings['first_slot_time'] ); ?>" />
								<p class="description">Orario di inizio della giornata (es: 09:00)</p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="last_slot_time">Ultimo Slot Orario:</label>
							</th>
							<td>
								<input type="time" id="last_slot_time" name="booking_system_last_slot_time" value="<?php echo esc_attr( $settings['last_slot_time'] ); ?>" />
								<p class="description">Orario di fine della giornata (es: 17:00)</p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="slot_duration_minutes">Durata Slot (minuti):</label>
							</th>
							<td>
								<input type="number" id="slot_duration_minutes" name="booking_system_slot_duration_minutes" value="<?php echo esc_attr( $settings['slot_duration_minutes'] ); ?>" min="15" step="15" />
								<p class="description">Durata di ogni fascia oraria (es: 60 minuti = 1 ora)</p>
							</td>
						</tr>
					</table>

					<h3>Prenotazioni</h3>
					<table class="form-table">
						<tr>
							<th scope="row">
								<label for="max_reservations_per_slot">Max Prenotazioni per Slot:</label>
							</th>
							<td>
								<input type="number" id="max_reservations_per_slot" name="booking_system_max_reservations_per_slot" value="<?php echo esc_attr( $settings['max_reservations_per_slot'] ); ?>" min="1" />
								<p class="description">Numero massimo di prenotazioni per fascia oraria (es: 2)</p>
							</td>
						</tr>
					</table>

					<h3>Impostazioni Generali</h3>
					<table class="form-table">
						<tr>
							<th scope="row">
								<label for="booking_timezone">Timezone:</label>
							</th>
							<td>
								<select id="booking_timezone" name="booking_system_booking_timezone">
									<?php
									$timezones = timezone_identifiers_list();
									foreach ( $timezones as $tz ) {
										$selected = ( $tz === $settings['booking_timezone'] ) ? 'selected' : '';
										echo '<option value="' . esc_attr( $tz ) . '" ' . $selected . '>' . esc_html( $tz ) . '</option>';
									}
									?>
								</select>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="send_confirmation_email">Invia Email di Conferma:</label>
							</th>
							<td>
								<input type="checkbox" id="send_confirmation_email" name="booking_system_send_confirmation_email" value="1" <?php checked( $settings['send_confirmation_email'] ); ?> />
								<p class="description">Invia email di conferma ai clienti dopo la prenotazione</p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="admin_email_on_booking">Notifica Email Admin:</label>
							</th>
							<td>
								<input type="checkbox" id="admin_email_on_booking" name="booking_system_admin_email_on_booking" value="1" <?php checked( $settings['admin_email_on_booking'] ); ?> />
								<p class="description">Ricevi notifiche quando viene fatta una prenotazione</p>
							</td>
						</tr>
					</table>
				</div>

				<!-- Blocked Dates Tab -->
				<div id="tab-blocked-dates" class="tab-content">
					<h3>Giorni della Settimana Bloccati</h3>
					<table class="form-table">
						<tr>
							<th scope="row">Seleziona giorni:</th>
							<td>
								<label><input type="checkbox" name="booking_system_blocked_weekdays[]" value="0" <?php checked( in_array( 0, $settings['blocked_weekdays'] ) ); ?> /> Domenica</label><br />
								<label><input type="checkbox" name="booking_system_blocked_weekdays[]" value="1" <?php checked( in_array( 1, $settings['blocked_weekdays'] ) ); ?> /> Lunedì</label><br />
								<label><input type="checkbox" name="booking_system_blocked_weekdays[]" value="2" <?php checked( in_array( 2, $settings['blocked_weekdays'] ) ); ?> /> Martedì</label><br />
								<label><input type="checkbox" name="booking_system_blocked_weekdays[]" value="3" <?php checked( in_array( 3, $settings['blocked_weekdays'] ) ); ?> /> Mercoledì</label><br />
								<label><input type="checkbox" name="booking_system_blocked_weekdays[]" value="4" <?php checked( in_array( 4, $settings['blocked_weekdays'] ) ); ?> /> Giovedì</label><br />
								<label><input type="checkbox" name="booking_system_blocked_weekdays[]" value="5" <?php checked( in_array( 5, $settings['blocked_weekdays'] ) ); ?> /> Venerdì</label><br />
								<label><input type="checkbox" name="booking_system_blocked_weekdays[]" value="6" <?php checked( in_array( 6, $settings['blocked_weekdays'] ) ); ?> /> Sabato</label><br />
								<p class="description">I giorni selezionati non avranno disponibilità di prenotazione</p>
							</td>
						</tr>
					</table>

					<h3>Date Specifiche Bloccate</h3>
					<table class="form-table">
						<tr>
							<th scope="row">
								<label for="blocked_specific_dates">Date (una per riga):</label>
							</th>
							<td>
								<textarea id="blocked_specific_dates" name="booking_system_blocked_specific_dates" rows="8" style="width: 100%; font-family: monospace;"><?php echo esc_textarea( implode( "\n", $settings['blocked_specific_dates'] ) ); ?></textarea>
								<p class="description">Inserisci le date nel formato YYYY-MM-DD (es: 2026-06-15)</p>
							</td>
						</tr>
					</table>
				</div>

				<!-- Google Calendar Tab -->
				<div id="tab-google" class="tab-content">
					<h3>Configurazione Google Calendar</h3>
					<table class="form-table">
						<tr>
							<th scope="row">
								<label for="google_calendar_enabled">Abilita Google Calendar:</label>
							</th>
							<td>
								<input type="checkbox" id="google_calendar_enabled" name="booking_system_google_calendar_enabled" value="1" <?php checked( $settings['google_calendar_enabled'] ); ?> />
								<p class="description">Abilita la sincronizzazione degli eventi con Google Calendar</p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="google_calendar_id">Calendar ID/Email:</label>
							</th>
							<td>
								<input type="email" id="google_calendar_id" name="booking_system_google_calendar_id" value="<?php echo esc_attr( $settings['google_calendar_id'] ); ?>" placeholder="your-calendar@gmail.com" />
								<p class="description">L'email o ID del calendario Google (es: tuocalendario@gmail.com)</p>
							</td>
						</tr>
					</table>

					<h3>Credenziali Google (Service Account)</h3>
					<?php if ( self::has_google_credentials() ) { ?>
						<div class="notice notice-success">
							<p>✓ Le credenziali Google sono state salvate correttamente</p>
						</div>
					<?php } else { ?>
						<div class="notice notice-info">
							<p>Per abilitare la sincronizzazione con Google Calendar, devi caricare le credenziali OAuth 2.0.</p>
						</div>
					<?php } ?>

					<p>Per configurare Google Calendar:</p>
					<ol>
						<li>Vai a <a href="https://console.cloud.google.com/" target="_blank">Google Cloud Console</a></li>
						<li>Crea un nuovo progetto e abilita l'API Google Calendar</li>
						<li>Crea un Service Account e scarica il file JSON delle credenziali</li>
						<li>Carica il file JSON qui sotto</li>
					</ol>

					<table class="form-table">
						<tr>
							<th scope="row">
								<label for="booking_credentials_file">Carica Credenziali JSON:</label>
							</th>
							<td>
								<?php wp_nonce_field( 'booking_upload_credentials', 'booking_upload_credentials_nonce' ); ?>
								<input type="file" id="booking_credentials_file" name="booking_credentials_file" accept=".json" />
								<p class="description">Carica il file JSON delle credenziali Google Service Account</p>
								<button type="button" id="btn-upload-credentials" class="button button-primary" style="margin-top: 10px;">Carica Credenziali</button>
								<span id="upload-status" style="margin-left: 10px; display: none;"></span>
							</td>
						</tr>
					</table>

					<p><strong>Documentazione:</strong> Vedi il file README.md nella cartella assets per istruzioni dettagliate</p>
				</div>

				<!-- Security Tab -->
				<div id="tab-security" class="tab-content">
					<h3>Protezione Rate Limiting</h3>
					<table class="form-table">
						<tr>
							<th scope="row">
								<label for="rate_limit_enabled">Abilita Rate Limiting:</label>
							</th>
							<td>
								<input type="checkbox" id="rate_limit_enabled" name="booking_system_rate_limit_enabled" value="1" <?php checked( get_option( 'booking_system_rate_limit_enabled' ) ); ?> />
								<p class="description">Limita il numero di richieste per prevenire brute force e DoS (raccomandato: sempre abilitato)</p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="rate_limit_attempts">Max Tentativi per Ora:</label>
							</th>
							<td>
								<input type="number" id="rate_limit_attempts" name="booking_system_rate_limit_attempts" value="<?php echo esc_attr( $settings['rate_limit_attempts'] ); ?>" min="1" max="100" />
								<p class="description">Numero massimo di richieste permesse per ora da un IP</p>
							</td>
						</tr>
					</table>

					<!-- To be implemented in frontend if needed-->
					<!-- <h3>Protezione reCAPTCHA v3</h3>
					<div class="notice notice-info">
						<p><strong>reCAPTCHA v3</strong> protegge dal bot senza richiedere interazione dell'utente. Per configurarlo:</p>
						<ol>
							<li>Vai a <a href="https://www.google.com/recaptcha/admin" target="_blank">Google reCAPTCHA Admin Console</a></li>
							<li>Crea un nuovo sito reCAPTCHA v3</li>
							<li>Copia Site Key e Secret Key qui sotto</li>
						</ol>
					</div>

					<table class="form-table">
						<tr>
							<th scope="row">
								<label for="enable_recaptcha">Abilita reCAPTCHA:</label>
							</th>
							<td>
								<input type="checkbox" id="enable_recaptcha" name="booking_system_enable_recaptcha" value="1" <?php checked( get_option( 'booking_system_enable_recaptcha' ) ); ?> />
								<p class="description">Abilita la protezione reCAPTCHA v3 sul form di prenotazione</p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="recaptcha_site_key">Site Key reCAPTCHA:</label>
							</th>
							<td>
								<input type="text" id="recaptcha_site_key" name="booking_system_recaptcha_site_key" value="<?php echo esc_attr( get_option( 'booking_system_recaptcha_site_key' ) ); ?>" placeholder="6Lc_..." style="width: 100%; font-family: monospace;" />
								<p class="description">La chiave pubblica di reCAPTCHA (visibile lato client)</p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="recaptcha_secret_key">Secret Key reCAPTCHA:</label>
							</th>
							<td>
								<input type="password" id="recaptcha_secret_key" name="booking_system_recaptcha_secret_key" value="<?php echo esc_attr( get_option( 'booking_system_recaptcha_secret_key' ) ); ?>" placeholder="6Lc_..." style="width: 100%; font-family: monospace;" />
								<p class="description" style="color: #d63638;">⚠️ Non condividere questa chiave. Mantienila segreta!</p>
							</td>
						</tr>
					</table> -->

					<h3>Info Sicurezza</h3>
					<div style="margin-top: 20px;">
						<p><strong>Misure di sicurezza attive:</strong></p>
						<ul style="margin-left: 20px;">
							<li>✓ Validazione input lato server</li>
							<li>✓ Protezione SQL Injection (prepared statements)</li>
							<li>✓ Protezione CSRF (NONCE tokens)</li>
							<li>✓ Protezione XSS (output escaping)</li>
							<li>✓ Transazioni atomiche (prevenzione race conditions)</li>
							<li>✓ Rate limiting per IP</li>
							<li>✓ Sanitizzazione email, telefono, nomi</li>
							<li>✓ Controllo credenziali Google (file upload validation)</li>
							<li>✓ Logging di tutte le azioni</li>
						</ul>
					</div>
				</div>

				<?php submit_button(); ?>
			</form>
		</div>

		<script>
			jQuery(document).ready(function($) {
				$('.nav-tab').on('click', function(e) {
					e.preventDefault();
					const tab = $(this).attr('href');
					$('.nav-tab').removeClass('nav-tab-active');
					$('.tab-content').removeClass('active');
					$(this).addClass('nav-tab-active');
					$(tab).addClass('active');
				});

				// Handle Google Credentials Upload via AJAX
				$('#btn-upload-credentials').on('click', function(e) {
					e.preventDefault();
					
					const fileInput = document.getElementById('booking_credentials_file');
					const file = fileInput.files[0];
					const statusEl = $('#upload-status');
					
					if (!file) {
						statusEl.html('<span style="color: #d63638;">Seleziona un file JSON</span>').show();
						return;
					}
					
					if (file.type !== 'application/json') {
						statusEl.html('<span style="color: #d63638;">Il file deve essere JSON</span>').show();
						return;
					}
					
					if (file.size > 1048576) {
						statusEl.html('<span style="color: #d63638;">File troppo grande (max 1MB)</span>').show();
						return;
					}
					
					// Create FormData manually without form element
					const formData = new FormData();
					formData.append('booking_credentials_file', file);
					formData.append('booking_upload_credentials_nonce', $('[name="booking_upload_credentials_nonce"]').val());
					
					statusEl.html('<span style="color: #b91447;">Caricamento in corso...</span>').show();
					
					$.ajax({
						type: 'POST',
						url: window.location.href,
						data: formData,
						processData: false,
						contentType: false,
						success: function(response) {
							statusEl.html('<span style="color: #28a745;">✓ Credenziali caricate con successo!</span>').show();
							fileInput.value = '';
							setTimeout(function() {
								location.reload();
							}, 1500);
						},
						error: function(xhr, status, error) {
							statusEl.html('<span style="color: #d63638;">Errore nel caricamento</span>').show();
						}
					});
				});
			});
		</script>

		<style>
			.booking-admin-wrap {
				max-width: 900px;
			}
			.nav-tab-wrapper {
				border-bottom: 1px solid #ccc;
				margin-bottom: 20px;
			}
			.tab-content {
				display: none;
			}
			.tab-content.active {
				display: block;
			}
			.form-table td {
				max-width: 600px;
			}
			.description {
				display: block;
				font-size: 12px;
				color: #666;
				margin-top: 5px;
			}
		</style>
		<?php
	}

	/**
	 * Render bookings page
	 */
	public static function render_bookings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Accesso negato' );
		}

		global $wpdb;
		$bookings = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}bookings ORDER BY booking_date DESC, time_slot DESC LIMIT 100" );

		?>
		<div class="wrap">
			<h1>Booking System - Prenotazioni</h1>

			<?php if ( ! empty( $bookings ) ) { ?>
				<p>Totale prenotazioni: <strong><?php echo esc_html( count( $bookings ) ); ?></strong></p>
			<?php } ?>

			<table class="widefat striped">
				<thead>
					<tr>
						<th>ID</th>
						<th>Nome</th>
						<th>Sezione</th>
						<th>Email</th>
						<th>Data</th>
						<th>Orario</th>
						<th>Telefono</th>
						<th>Status</th>
						<th>Data Creazione</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $bookings as $booking ) { ?>
						<tr>
							<td><?php echo esc_html( $booking->id ); ?></td>
							<td><?php echo esc_html( $booking->client_name . ' ' . $booking->client_surname ); ?></td>
							<td><?php echo esc_html( $booking->client_section ); ?></td>
							<td><?php echo esc_html( $booking->client_email ); ?></td>
							<td><?php echo esc_html( date_i18n( 'd/m/Y', strtotime( $booking->booking_date ) ) ); ?></td>
							<td><?php echo esc_html( $booking->time_slot ); ?></td>
							<td><?php echo esc_html( $booking->client_phone ); ?></td>
							<td>
								<span class="status-<?php echo esc_attr( $booking->status ); ?>">
									<?php echo esc_html( ucfirst( $booking->status ) ); ?>
								</span>
							</td>
							<td><?php echo esc_html( date_i18n( 'd/m/Y H:i', strtotime( $booking->created_at ) ) ); ?></td>
						</tr>
					<?php } ?>
				</tbody>
			</table>

			<style>
				.status-pending { color: #ffc107; font-weight: bold; }
				.status-confirmed { color: #28a745; font-weight: bold; }
				.status-cancelled { color: #dc3545; font-weight: bold; }
			</style>
		</div>
		<?php
	}

	/**
	 * Render logs page
	 */
	public static function render_logs_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Accesso negato' );
		}

		$logs = Booking_DB::get_logs( 150 );

		?>
		<div class="wrap">
			<h1>Booking System - Log</h1>

			<table class="widefat striped">
				<thead>
					<tr>
						<th>ID</th>
						<th>Azione</th>
						<th>Prenotazione ID</th>
						<th>Email</th>
						<th>Messaggio</th>
						<th>Timestamp</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $logs as $log ) { ?>
						<tr>
							<td><?php echo esc_html( $log->id ); ?></td>
							<td>
								<span class="badge action-<?php echo esc_attr( $log->action ); ?>">
									<?php echo esc_html( $log->action ); ?>
								</span>
							</td>
							<td><?php echo esc_html( $log->booking_id ? $log->booking_id : '-' ); ?></td>
							<td><?php echo esc_html( $log->client_email ? $log->client_email : '-' ); ?></td>
							<td><?php echo esc_html( $log->message ); ?></td>
							<td><?php echo esc_html( date_i18n( 'd/m/Y H:i:s', strtotime( $log->timestamp ) ) ); ?></td>
						</tr>
					<?php } ?>
				</tbody>
			</table>

			<style>
				.badge {
					display: inline-block;
					padding: 3px 8px;
					border-radius: 3px;
					font-size: 11px;
					font-weight: bold;
					color: white;
				}
				.action-success { background-color: #28a745; }
				.action-error { background-color: #dc3545; }
				.action-attempt { background-color: #ffc107; color: #333; }
				.action-email_sent { background-color: #17a2b8; }
				.action-google_event_created { background-color: #007bff; }
				.action-google_error { background-color: #e83e8c; }
			</style>
		</div>
		<?php
	}
}
