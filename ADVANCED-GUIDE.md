# Advanced Usage Guide - Booking System

## Customization

### Changing Business Hours

Edit settings in WordPress admin or programmatically:

```php
// Via admin: Booking System → Impostazioni → Orari Disponibilità

// Programmatically:
Booking_Settings::set( 'first_slot_time', '08:00' );  // 8 AM
Booking_Settings::set( 'last_slot_time', '20:00' );   // 8 PM
```

### Slot Duration

Change from 1-hour to 30-minute slots:

```php
Booking_Settings::set( 'slot_duration_minutes', 30 );
```

### Max Bookings Per Slot

Increase from 2 to 5 bookings per slot:

```php
Booking_Settings::set( 'max_reservations_per_slot', 5 );
```

---

## Custom Hooks & Filters

Extend functionality with WordPress hooks:

```php
// Before creating booking
add_filter( 'booking_before_create', function( $data ) {
    // Add custom validation
    if ( strpos( $data['client_name'], 'test' ) !== false ) {
        return new WP_Error( 'invalid_name', 'Test names not allowed' );
    }
    return $data;
} );

// After booking created
add_action( 'booking_created', function( $booking_id, $booking_data ) {
    // Send SMS notification
    send_sms( $booking_data['client_phone'], 'Booking confirmed: ' . $booking_id );
}, 10, 2 );

// Modify reCAPTCHA score threshold
add_filter( 'booking_recaptcha_threshold', function( $threshold ) {
    return 0.7; // More strict (default 0.5)
} );

// Modify available slots
add_filter( 'booking_available_slots', function( $slots, $date ) {
    // Remove lunch hours (12:00-13:00)
    return array_filter( $slots, function( $slot ) {
        return ! in_array( $slot['time'], [ '12:00', '13:00' ] );
    });
}, 10, 2 );
```

---

## REST API Endpoints

### Get Available Slots

```bash
GET /wp-json/booking/v1/slots?date=2026-06-10

Response:
{
  "date": "2026-06-10",
  "slots": [
    { "time": "09:00", "available_spots": 2 },
    { "time": "10:00", "available_spots": 1 },
    { "time": "11:00", "available_spots": 2 }
  ]
}
```

### Create Reservation

```bash
POST /wp-json/booking/v1/reserve

Headers:
  X-WP-Nonce: {nonce}
  Content-Type: application/json

Body:
{
  "booking_date": "2026-06-10",
  "time_slot": "10:00",
  "client_name": "John",
  "client_surname": "Doe",
  "client_email": "john@example.com",
  "client_phone": "+39 123 456 7890",
  "recaptcha_token": "{token}"
}

Response (Success):
{
  "success": true,
  "booking_id": 42,
  "booking": {
    "id": 42,
    "booking_date": "2026-06-10",
    "time_slot": "10:00",
    "client_name": "John",
    "client_surname": "Doe",
    "client_email": "john@example.com",
    "client_phone": "+39 123 456 7890",
    "status": "confirmed",
    "google_event_id": "abc123xyz",
    "created_at": "2026-04-29 14:30:00",
    "updated_at": "2026-04-29 14:30:00"
  },
  "message": "Prenotazione confermata!"
}

Response (Error):
{
  "code": "slot_not_available",
  "message": "Lo slot non è più disponibile. Ricarica e prova di nuovo.",
  "data": { "status": 400 }
}
```

---

## Database Schema

### Bookings Table

```sql
CREATE TABLE wp_bookings (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  booking_date DATE NOT NULL,
  time_slot TIME NOT NULL,
  client_name VARCHAR(100) NOT NULL,
  client_surname VARCHAR(100) NOT NULL,
  client_email VARCHAR(100) NOT NULL,
  client_phone VARCHAR(20) NOT NULL,
  status ENUM('pending', 'confirmed', 'cancelled') DEFAULT 'pending',
  google_event_id VARCHAR(255),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY unique_booking (booking_date, time_slot, client_email),
  KEY idx_date (booking_date),
  KEY idx_email (client_email),
  KEY idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Logs Table

```sql
CREATE TABLE wp_booking_logs (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  booking_id BIGINT,
  action VARCHAR(50) NOT NULL,
  message TEXT,
  client_email VARCHAR(100),
  timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (booking_id) REFERENCES wp_bookings(id) ON DELETE CASCADE,
  KEY idx_action (action),
  KEY idx_timestamp (timestamp)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## Developers Guide

### Class Hierarchy

```
Booking_DB                          // Database operations
  ├── create_booking()
  ├── update_booking_status()
  └── get_logs()

Booking_Settings                    // Configuration
  ├── get()
  ├── set()
  └── validate()

Booking_Availability                // Slot availability
  ├── get_available_slots_for_date()
  └── is_slot_available()

Booking_Reservation                 // Booking creation
  ├── validate()
  └── create()

Booking_Security                    // Input validation & protection
  ├── sanitize_email()
  ├── sanitize_phone()
  ├── check_rate_limit()
  └── validate_recaptcha()

Booking_Email                       // Email notifications
  ├── send_confirmation()
  └── send_admin_notification()

Booking_Google_Calendar             // Google Calendar sync
  ├── add_event()
  ├── update_event()
  ├── delete_event()
  └── get_access_token()

Booking_Logger                      // Audit logging
  ├── log_attempt()
  ├── log_success()
  ├── log_error()
  └── log_google_event_created()

Booking_REST_API                    // REST endpoints
  ├── get_slots()
  └── create_reservation()
```

### Extending the Plugin

#### Add Custom Email Template

```php
// In your theme's functions.php
add_filter( 'booking_email_body', function( $body, $booking_id ) {
    $booking = Booking_DB::get_booking( $booking_id );
    
    return "Caro {$booking->client_name},\n\n" .
           "Grazie per la prenotazione del {$booking->booking_date} alle {$booking->time_slot}.\n\n" .
           "Dettagli:\n" .
           "- Data: " . date_i18n( 'd/m/Y', strtotime( $booking->booking_date ) ) . "\n" .
           "- Orario: {$booking->time_slot}\n" .
           "- Email: {$booking->client_email}\n" .
           "- Telefono: {$booking->client_phone}\n\n" .
           "Un saluto,\nIl Team";
}, 10, 2 );
```

#### Add Custom Validation

```php
// Block certain email domains
add_filter( 'booking_validate_email', function( $email ) {
    $blocked_domains = [ 'competitor.com', 'spam.net' ];
    $domain = explode( '@', $email )[1];
    
    if ( in_array( $domain, $blocked_domains ) ) {
        return new WP_Error( 'email_blocked', 'Questo dominio non è consentito' );
    }
    
    return true;
} );
```

#### Add SMS Notification

```php
// Send SMS after booking confirmation
add_action( 'booking_created', function( $booking_id, $booking_data ) {
    $twilio = new Twilio\Rest\Client( TWILIO_ACCOUNT_SID, TWILIO_AUTH_TOKEN );
    
    $message = "Booking confermato! Data: " . $booking_data['booking_date'] . 
               " Orario: " . $booking_data['time_slot'];
    
    $twilio->messages->create(
        $booking_data['client_phone'],
        array( 'from' => TWILIO_NUMBER, 'body' => $message )
    );
}, 10, 2 );
```

#### Add Slack Notification

```php
// Send Slack alert for new bookings
add_action( 'booking_created', function( $booking_id, $booking_data ) {
    $slack_webhook = 'https://hooks.slack.com/services/YOUR/WEBHOOK/URL';
    
    $message = array(
        'text' => 'Nuova prenotazione!',
        'attachments' => array(
            array(
                'color' => 'good',
                'fields' => array(
                    array(
                        'title' => 'Cliente',
                        'value' => $booking_data['client_name'] . ' ' . $booking_data['client_surname'],
                        'short' => false
                    ),
                    array(
                        'title' => 'Data',
                        'value' => $booking_data['booking_date'],
                        'short' => true
                    ),
                    array(
                        'title' => 'Orario',
                        'value' => $booking_data['time_slot'],
                        'short' => true
                    ),
                    array(
                        'title' => 'Email',
                        'value' => $booking_data['client_email'],
                        'short' => false
                    )
                )
            )
        )
    );
    
    wp_remote_post( $slack_webhook, array(
        'body' => wp_json_encode( $message )
    ));
}, 10, 2 );
```

---

## Troubleshooting

### Debug Mode

Enable debug logging:

```php
// Add to wp-config.php
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
define( 'WP_DEBUG_DISPLAY', false );

// Then check: wp-content/debug.log
```

### Check System Info

```php
// Add to admin page
echo '<pre>';
echo 'PHP Version: ' . phpversion() . "\n";
echo 'MySQL Version: ' . mysql_get_server_info() . "\n";
echo 'WordPress Version: ' . get_bloginfo( 'version' ) . "\n";
echo 'Plugin Version: ' . BOOKING_PLUGIN_VERSION . "\n";
echo 'Bookings Table Exists: ' . ( $wpdb->get_var( "SHOW TABLES LIKE 'wp_bookings'" ) ? 'Yes' : 'No' ) . "\n";
echo 'Google Credentials: ' . ( Booking_Google_Calendar::get_credentials() ? 'Configured' : 'Not configured' ) . "\n";
echo '</pre>';
```

### Monitor Performance

```php
// Track booking creation time
$start = microtime( true );
$booking_id = Booking_Reservation::create( $data );
$time_ms = ( microtime( true ) - $start ) * 1000;
echo "Booking created in {$time_ms}ms";
```

---

## Performance Optimization

### Database Indexes

The plugin creates indexes on:
- `booking_date` - For date range queries
- `client_email` - For duplicate prevention
- `status` - For filtering

Add custom index if querying specific fields frequently:

```php
// In wp-config.php or custom initialization
$wpdb->query( "ALTER TABLE {$wpdb->prefix}bookings ADD INDEX idx_phone (client_phone)" );
```

### Caching

Cache available slots (they don't change frequently):

```php
$cache_key = 'booking_slots_' . $date;
$slots = wp_cache_get( $cache_key );

if ( $slots === false ) {
    $slots = Booking_Availability::get_available_slots_for_date( $date );
    wp_cache_set( $cache_key, $slots, '', 3600 ); // 1 hour
}
```

### Query Optimization

Use `LIMIT` when fetching logs:

```php
// ✅ GOOD - Limited results
$logs = Booking_DB::get_logs( 100 );

// ❌ AVOID - No limit
$logs = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}booking_logs" );
```
