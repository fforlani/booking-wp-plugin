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
		add_action( 'admin_init', array( __CLASS__, 'handle_booking_admin_actions' ) );
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
		register_setting( 'booking_system_group', 'booking_system_bcc_email', array(
			'sanitize_callback' => array( __CLASS__, 'sanitize_optional_email' ),
		) );
		register_setting( 'booking_system_group', 'booking_system_confirmation_email_template');
		register_setting( 'booking_system_group', 'booking_system_cancellation_email_template');
		register_setting( 'booking_system_group', 'booking_system_google_calendar_enabled' );
		register_setting( 'booking_system_group', 'booking_system_google_calendar_id' );
		register_setting('booking_system_group', 'booking_system_rate_limit_enabled');
		register_setting('booking_system_group', 'booking_system_rate_limit_attempts');
		
		// Email/SMTP settings
		register_setting( 'booking_system_group', 'booking_system_smtp_enabled' );
		register_setting( 'booking_system_group', 'booking_system_smtp_host' );
		register_setting( 'booking_system_group', 'booking_system_smtp_port' );
		register_setting( 'booking_system_group', 'booking_system_smtp_username' );
		register_setting( 'booking_system_group', 'booking_system_smtp_password', array(
			'sanitize_callback' => array( __CLASS__, 'sanitize_smtp_password' )
		) );
		register_setting( 'booking_system_group', 'booking_system_smtp_secure' );
		register_setting( 'booking_system_group', 'booking_system_smtp_from_email' );
		register_setting( 'booking_system_group', 'booking_system_smtp_from_name' );
		register_setting( 'booking_system_group', 'booking_system_enable_recaptcha', array(
			'sanitize_callback' => array( __CLASS__, 'sanitize_checkbox' ),
		) );
		register_setting( 'booking_system_group', 'booking_system_recaptcha_site_key', array(
			'sanitize_callback' => 'sanitize_text_field',
		) );
		register_setting( 'booking_system_group', 'booking_system_recaptcha_secret_key', array(
			'sanitize_callback' => 'sanitize_text_field',
		) );
		register_setting( 'booking_system_group', 'booking_system_recaptcha_threshold', array(
			'sanitize_callback' => array( __CLASS__, 'sanitize_recaptcha_threshold' ),
		) );

		// Handle Google credentials upload
		if ( isset( $_POST['booking_upload_credentials_nonce'] )) {
			self::handle_google_credentials_upload();
		}
	}

	/**
	 * Sanitize SMTP password
	 */
	public static function sanitize_smtp_password( $value ) {
		if ( empty( $value ) ) {
			return '';
		}
		return sanitize_text_field( $value );
	}

	public static function sanitize_checkbox( $value ) {
		return empty( $value ) ? 0 : 1;
	}

	public static function sanitize_optional_email( $value ) {
		if ( empty( $value ) ) {
			return '';
		}

		$email = sanitize_email( $value );
		return is_email( $email ) ? $email : '';
	}

	public static function sanitize_recaptcha_threshold( $value ) {
		$value = (float) $value;

		if ( $value < 0 ) {
			return 0;
		}

		if ( $value > 1 ) {
			return 1;
		}

		return $value;
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
	 * Handle actions from the bookings admin table.
	 */
	public static function handle_booking_admin_actions() {
		if ( ! is_admin() || ! isset( $_GET['page'], $_GET['booking_action'], $_GET['booking_id'] ) ) {
			return;
		}

		if ( 'booking-bookings' !== sanitize_key( wp_unslash( $_GET['page'] ) ) || 'cancel' !== sanitize_key( wp_unslash( $_GET['booking_action'] ) ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Accesso negato' );
		}

		$return_args = array( 'page' => 'booking-bookings' );
		if ( isset( $_GET['paged'] ) ) {
			$return_args['paged'] = max( 1, absint( wp_unslash( $_GET['paged'] ) ) );
		}
		if ( isset( $_GET['per_page'] ) ) {
			$return_args['per_page'] = min( 500, max( 1, absint( wp_unslash( $_GET['per_page'] ) ) ) );
		}
		$return_url = add_query_arg( $return_args, admin_url( 'admin.php' ) );

		$booking_id = intval( $_GET['booking_id'] );
		check_admin_referer( 'booking_cancel_booking_' . $booking_id );

		$booking = Booking_DB::get_booking( $booking_id );
		if ( ! $booking ) {
			set_transient( 'booking_admin_error', 'Prenotazione non trovata.', 10 );
			wp_safe_redirect( $return_url );
			exit;
		}

		if ( 'cancelled' === strtolower( (string) $booking->status ) ) {
			set_transient( 'booking_admin_error', 'La prenotazione e gia cancellata.', 10 );
			wp_safe_redirect( $return_url );
			exit;
		}

		if ( Booking_Settings::get( 'google_calendar_enabled' ) && ! empty( $booking->google_event_id ) ) {
			$deleted = Booking_Google_Calendar::delete_event( $booking->id );
		}

		if ( ! Booking_DB::cancel_booking( $booking->id ) ) {
			set_transient( 'booking_admin_error', 'Non siamo riusciti a cancellare la prenotazione.', 10 );
			wp_safe_redirect( $return_url );
			exit;
		}

		$booking->status = 'cancelled';
		Booking_Logger::log_action( $booking->id, 'booking_cancelled_by_admin', 'Prenotazione cancellata dalla sezione admin.', $booking->client_email );
		Booking_Email::send_cancellation( $booking );

		set_transient( 'booking_admin_success', 'Prenotazione cancellata correttamente.', 10 );
		wp_safe_redirect( $return_url );
		exit;
	}

	/**
	 * Get pagination settings from admin query params.
	 */
	private static function get_pagination_args( $default_per_page = 20 ) {
		$per_page = isset( $_GET['per_page'] ) ? absint( wp_unslash( $_GET['per_page'] ) ) : $default_per_page;
		$per_page = min( 500, max( 1, $per_page ) );

		$current_page = isset( $_GET['paged'] ) ? absint( wp_unslash( $_GET['paged'] ) ) : 1;
		$current_page = max( 1, $current_page );

		return array(
			'per_page'     => $per_page,
			'current_page' => $current_page,
		);
	}

	/**
	 * Render pagination and per-page controls for admin tables.
	 */
	private static function render_pagination_controls( $page_slug, $current_page, $per_page, $total_items, $label ) {
		$total_pages = max( 1, (int) ceil( $total_items / $per_page ) );
		$first_item  = $total_items > 0 ? ( ( $current_page - 1 ) * $per_page ) + 1 : 0;
		$last_item   = min( $total_items, $current_page * $per_page );
		$per_page_options = array( 10, 20, 50, 100, 200, 500 );
		$page_placeholder = 999999999;

		$pagination_links = paginate_links(
			array(
				'base'      => str_replace(
					$page_placeholder,
					'%#%',
					esc_url(
						add_query_arg(
							array(
								'page'     => $page_slug,
								'per_page' => $per_page,
								'paged'    => $page_placeholder,
							),
							admin_url( 'admin.php' )
						)
					)
				),
				'format'    => '',
				'current'   => $current_page,
				'total'     => $total_pages,
				'prev_text' => '&laquo;',
				'next_text' => '&raquo;',
				'type'      => 'array',
			)
		);
		?>
		<div class="booking-pagination-controls">
			<div class="booking-pagination-summary">
				<?php
				printf(
					esc_html__( '%1$s: %2$d-%3$d di %4$d', 'booking-system' ),
					esc_html( $label ),
					intval( $first_item ),
					intval( $last_item ),
					intval( $total_items )
				);
				?>
			</div>

			<form method="get" class="booking-pagination-form">
				<input type="hidden" name="page" value="<?php echo esc_attr( $page_slug ); ?>" />
				<label>
					<span>Record per pagina</span>
					<select name="per_page">
						<?php foreach ( $per_page_options as $option ) { ?>
							<option value="<?php echo esc_attr( $option ); ?>" <?php selected( $per_page, $option ); ?>>
								<?php echo esc_html( $option ); ?>
							</option>
						<?php } ?>
					</select>
				</label>
				<label>
					<span>Pagina</span>
					<input type="number" name="paged" value="<?php echo esc_attr( $current_page ); ?>" min="1" max="<?php echo esc_attr( $total_pages ); ?>" />
				</label>
				<button type="submit" class="button">Applica</button>
			</form>
		</div>
		<?php
	}

	/**
	 * Render settings page
	 */
	public static function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Accesso negato' );
		}

		$settings = Booking_Settings::get_all();
		$email_tokens = Booking_Email::get_available_tokens();

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
				<a href="#tab-email" class="nav-tab">Email</a>
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

				<!-- Email Configuration Tab -->
				<div id="tab-email" class="tab-content">
					<h3>Configurazione Email</h3>
					<table class="form-table">
						<tr>
							<th scope="row">
								<label for="smtp_enabled">Usa SMTP Personalizzato:</label>
							</th>
							<td>
								<input type="checkbox" id="smtp_enabled" name="booking_system_smtp_enabled" value="1" <?php checked( $settings['smtp_enabled'] ); ?> />
								<p class="description">Abilita per usare un server SMTP personalizzato invece di quello di WordPress</p>
							</td>
						</tr>
					</table>

					<h3 id="smtp_fields_title" style="<?php echo $settings['smtp_enabled'] ? '' : 'display: none;'; ?>">Parametri SMTP</h3>
					<div id="smtp_fields" class="smtp-config" style="<?php echo $settings['smtp_enabled'] ? '' : 'display: none;'; ?>">
						<table class="form-table">
							<tr>
								<th scope="row">
									<label for="smtp_host">Host SMTP *</label>
								</th>
								<td>
									<input type="text" id="smtp_host" name="booking_system_smtp_host" value="<?php echo esc_attr( $settings['smtp_host'] ); ?>" placeholder="smtp.gmail.com" />
									<p class="description">Es: smtp.gmail.com, smtp.office365.com</p>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="smtp_port">Porta *</label>
								</th>
								<td>
									<input type="number" id="smtp_port" name="booking_system_smtp_port" value="<?php echo esc_attr( $settings['smtp_port'] ); ?>" placeholder="587" min="1" max="65535" />
									<p class="description">Normalmente: 587 (TLS), 465 (SSL), 25 (non sicuro)</p>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="smtp_secure">Tipo di Sicurezza *</label>
								</th>
								<td>
									<select id="smtp_secure" name="booking_system_smtp_secure">
										<option value="">Seleziona</option>
										<option value="tls" <?php selected( $settings['smtp_secure'], 'tls' ); ?>>TLS</option>
										<option value="ssl" <?php selected( $settings['smtp_secure'], 'ssl' ); ?>>SSL</option>
										<option value="none" <?php selected( $settings['smtp_secure'], 'none' ); ?>>Nessuno</option>
									</select>
									<p class="description">TLS è consigliato (porta 587), SSL per porta 465</p>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="smtp_username">Username *</label>
								</th>
								<td>
									<input type="text" id="smtp_username" name="booking_system_smtp_username" value="<?php echo esc_attr( $settings['smtp_username'] ); ?>" placeholder="tua.email@gmail.com" />
									<p class="description">Solitamente è l'indirizzo email del tuo account</p>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="smtp_password">Password *</label>
								</th>
								<td>
									<input type="password" id="smtp_password" name="booking_system_smtp_password" value="<?php echo esc_attr( $settings['smtp_password'] ); ?>" placeholder="Password app o account" />
									<p class="description" style="color: #d63638;">⚠️ Per Gmail, usa una password app (non la password principale)</p>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="smtp_from_email">Email Mittente *</label>
								</th>
								<td>
									<input type="email" id="smtp_from_email" name="booking_system_smtp_from_email" value="<?php echo esc_attr( $settings['smtp_from_email'] ); ?>" placeholder="noreply@tuodominio.com" />
									<p class="description">Indirizzo email che apparirà come mittente dei messaggi</p>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="smtp_from_name">Nome Mittente</label>
								</th>
								<td>
									<input type="text" id="smtp_from_name" name="booking_system_smtp_from_name" value="<?php echo esc_attr( $settings['smtp_from_name'] ); ?>" placeholder="Booking System" />
									<p class="description">Nome che apparirà nei messaggi inviati</p>
								</td>
							</tr>
						</table>
					</div>

					<h3>Email Inviate</h3>
					<table class="form-table">
						<tr>
							<th scope="row">
								<label for="send_confirmation_email">Invia Email di Conferma:</label>
							</th>
							<td>
								<input type="checkbox" id="send_confirmation_email" name="booking_system_send_confirmation_email" value="1" <?php checked( $settings['send_confirmation_email'] ); ?> />
								<p class="description">Invia email di conferma ai clienti. Se configurato, usa anche l'indirizzo CCN sotto.</p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="bcc_email">Email CCN:</label>
							</th>
							<td>
								<input type="email" id="bcc_email" name="booking_system_bcc_email" value="<?php echo esc_attr( $settings['bcc_email'] ); ?>" placeholder="segreteria@tuodominio.com" />
								<p class="description">Indirizzo in copia conoscenza nascosta per email di conferma e cancellazione. Lascia vuoto per non inviare CCN.</p>
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
						<tr>
							<th scope="row">
								<label for="confirmation_email_template">Messaggio Email Conferma:</label>
							</th>
							<td>
								<textarea id="confirmation_email_template" name="booking_system_confirmation_email_template" rows="16" style="width: 100%; font-family: monospace;"><?php echo esc_textarea( $settings['confirmation_email_template'] ); ?></textarea>
								<p class="description">Testo inviato al cliente. Puoi usare i token sotto per inserire automaticamente i dati della prenotazione.</p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="cancellation_email_template">Messaggio Email Cancellazione:</label>
							</th>
							<td>
								<textarea id="cancellation_email_template" name="booking_system_cancellation_email_template" rows="10" style="width: 100%; font-family: monospace;"><?php echo esc_textarea( $settings['cancellation_email_template'] ); ?></textarea>
								<p class="description">Testo inviato al cliente quando cancella la prenotazione dal link di gestione. Puoi usare gli stessi token disponibili sotto.</p>
							</td>
						</tr>
					</table>

					<h3>Token disponibili</h3>
					<p class="description">Inserisci questi token nel messaggio: saranno sostituiti al momento dell'invio.</p>
					<table class="widefat striped booking-token-table">
						<thead>
							<tr>
								<th>Token</th>
								<th>Valore sostituito</th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $email_tokens as $token => $description ) { ?>
								<tr>
									<td><code><?php echo esc_html( $token ); ?></code></td>
									<td><?php echo esc_html( $description ); ?></td>
								</tr>
							<?php } ?>
						</tbody>
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

					<h3>Protezione reCAPTCHA v3</h3>
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
								<input type="hidden" name="booking_system_enable_recaptcha" value="0" />
								<input type="checkbox" id="enable_recaptcha" name="booking_system_enable_recaptcha" value="1" <?php checked( $settings['enable_recaptcha'] ); ?> />
								<p class="description">Abilita la protezione reCAPTCHA v3 sul form di prenotazione</p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="recaptcha_site_key">Site Key reCAPTCHA:</label>
							</th>
							<td>
								<input type="text" id="recaptcha_site_key" name="booking_system_recaptcha_site_key" value="<?php echo esc_attr( $settings['recaptcha_site_key'] ); ?>" placeholder="6Lc_..." style="width: 100%; font-family: monospace;" />
								<p class="description">La chiave pubblica di reCAPTCHA (visibile lato client)</p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="recaptcha_secret_key">Secret Key reCAPTCHA:</label>
							</th>
							<td>
								<input type="password" id="recaptcha_secret_key" name="booking_system_recaptcha_secret_key" value="<?php echo esc_attr( $settings['recaptcha_secret_key'] ); ?>" placeholder="6Lc_..." style="width: 100%; font-family: monospace;" />
								<p class="description" style="color: #d63638;">⚠️ Non condividere questa chiave. Mantienila segreta!</p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="recaptcha_threshold">Soglia reCAPTCHA:</label>
							</th>
							<td>
								<input type="number" id="recaptcha_threshold" name="booking_system_recaptcha_threshold" value="<?php echo esc_attr( $settings['recaptcha_threshold'] ); ?>" min="0" max="1" step="0.1" />
								<p class="description">Soglia minima dello score v3. Valori piu alti sono piu restrittivi (default: 0.5).</p>
							</td>
						</tr>
					</table>

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
				// Tab switching
				$('.nav-tab').on('click', function(e) {
					e.preventDefault();
					const tab = $(this).attr('href');
					$('.nav-tab').removeClass('nav-tab-active');
					$('.tab-content').removeClass('active');
					$(this).addClass('nav-tab-active');
					$(tab).addClass('active');
				});

				// Toggle SMTP fields visibility
				$('#smtp_enabled').on('change', function() {
					if ($(this).is(':checked')) {
						$('#smtp_fields').slideDown();
						$('#smtp_fields_title').show();
					} else {
						$('#smtp_fields').slideUp();
						$('#smtp_fields_title').hide();
					}
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
			.booking-token-table {
				max-width: 720px;
				margin-top: 10px;
			}
			.booking-token-table code {
				user-select: all;
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

		$pagination = self::get_pagination_args( 20 );
		$total_bookings = Booking_DB::count_bookings();
		$total_pages = max( 1, (int) ceil( $total_bookings / $pagination['per_page'] ) );
		$current_page = min( $pagination['current_page'], $total_pages );
		$offset = ( $current_page - 1 ) * $pagination['per_page'];
		$bookings = Booking_DB::get_bookings( $pagination['per_page'], $offset );
		$admin_error = get_transient( 'booking_admin_error' );
		$admin_success = get_transient( 'booking_admin_success' );

		?>
		<div class="wrap">
			<h1>Booking System - Prenotazioni</h1>

			<?php if ( $admin_error ) { ?>
				<div class="notice notice-error is-dismissible">
					<p><?php echo esc_html( $admin_error ); ?></p>
				</div>
				<?php delete_transient( 'booking_admin_error' ); ?>
			<?php } ?>

			<?php if ( $admin_success ) { ?>
				<div class="notice notice-success is-dismissible">
					<p><?php echo esc_html( $admin_success ); ?></p>
				</div>
				<?php delete_transient( 'booking_admin_success' ); ?>
			<?php } ?>

			<?php self::render_pagination_controls( 'booking-bookings', $current_page, $pagination['per_page'], $total_bookings, 'Prenotazioni' ); ?>

			<table class="widefat striped">
				<thead>
					<tr>
						<th>ID</th>
						<th>Nome</th>
						<th>Genere</th>
						<th>Classe</th>
						<th>Email</th>
						<th>Telefono</th>
						<th>Data</th>
						<th>Orario</th>
						<th>Status</th>
						<th>Data Creazione</th>
						<th>Azioni</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $bookings as $booking ) { ?>
						<tr>
							<td><?php echo esc_html( $booking->id ); ?></td>
							<td><?php echo esc_html( $booking->client_name . ' ' . $booking->client_surname ); ?></td>
							<td><?php echo esc_html( $booking->client_gender ); ?></td>
							<td><?php echo esc_html( $booking->client_section ); ?></td>
							<td><?php echo esc_html( $booking->client_email ); ?></td>
							<td><?php echo esc_html( $booking->client_phone ); ?></td>
							<td><?php echo esc_html( date_i18n( 'd/m/Y', strtotime( $booking->booking_date ) ) ); ?></td>
							<td><?php echo esc_html( $booking->time_slot ); ?></td>
							<td>
								<span class="status-<?php echo esc_attr( $booking->status ); ?>">
									<?php echo esc_html( ucfirst( $booking->status ) ); ?>
								</span>
							</td>
							<td><?php echo esc_html( date_i18n( 'd/m/Y H:i', strtotime( $booking->created_at ) ) ); ?></td>
							<td>
								<?php if ( 'cancelled' !== strtolower( (string) $booking->status ) ) { ?>
									<?php
									$cancel_url = wp_nonce_url(
										add_query_arg(
											array(
												'page'           => 'booking-bookings',
												'paged'          => $current_page,
												'per_page'       => $pagination['per_page'],
												'booking_action' => 'cancel',
												'booking_id'     => intval( $booking->id ),
											),
											admin_url( 'admin.php' )
										),
										'booking_cancel_booking_' . intval( $booking->id )
									);
									?>
									<a
										class="button button-small"
										href="<?php echo esc_url( $cancel_url ); ?>"
										onclick="return confirm('Vuoi cancellare questa prenotazione?');"
									>
										Cancella
									</a>
								<?php } else { ?>
									-
								<?php } ?>
							</td>
						</tr>
					<?php } ?>
				</tbody>
			</table>

			<?php self::render_pagination_controls( 'booking-bookings', $current_page, $pagination['per_page'], $total_bookings, 'Prenotazioni' ); ?>

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

		$pagination = self::get_pagination_args( 20 );
		$total_logs = Booking_DB::count_logs();
		$total_pages = max( 1, (int) ceil( $total_logs / $pagination['per_page'] ) );
		$current_page = min( $pagination['current_page'], $total_pages );
		$offset = ( $current_page - 1 ) * $pagination['per_page'];
		$logs = Booking_DB::get_logs( $pagination['per_page'], $offset );

		?>
		<div class="wrap">
			<h1>Booking System - Log</h1>

			<?php self::render_pagination_controls( 'booking-logs', $current_page, $pagination['per_page'], $total_logs, 'Log' ); ?>

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

			<?php self::render_pagination_controls( 'booking-logs', $current_page, $pagination['per_page'], $total_logs, 'Log' ); ?>

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
