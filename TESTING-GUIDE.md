# Testing Guide - Booking System

## Unit Tests

### 1. Test Availability Calculation

```php
// Test: Get available slots for a date
$date = '2026-06-10'; // Wednesday
$slots = Booking_Availability::get_available_slots_for_date( $date );

// Expected: Array of slots from 09:00 to 17:00 with available_spots = 2
// Should NOT include: Sunday (06-07), Saturday (06-06)
// Should NOT include: Blocked specific dates

echo count( $slots ) . " slots available";
```

### 2. Test Slot Locking & Race Condition Prevention

```php
// Test: Open 2 browser windows, both attempt to book same slot
// Expected: First request succeeds, second request fails with "slot not available"
// This tests atomic transaction with race condition prevention

$slot = '2026-06-10 10:00';
$result1 = Booking_Reservation::create([
    'booking_date'   => '2026-06-10',
    'time_slot'      => '10:00',
    'client_name'    => 'John',
    'client_surname' => 'Doe',
    'client_email'   => 'john@example.com',
    'client_phone'   => '+39 123 456 7890'
]);

// Result: booking_id on success, WP_Error on failure
```

### 3. Test Rate Limiting

```php
// Make 5 rapid POST requests from same IP
// Expected: First 5 succeed (or fail), 6th request returns HTTP 429 (Too Many Requests)

for ( $i = 0; $i < 7; $i++ ) {
    $response = wp_remote_post( 'https://yoursite.com/wp-json/booking/v1/reserve', [
        'body' => json_encode( $booking_data ),
        'headers' => [ 'X-WP-Nonce' => wp_create_nonce( 'booking_nonce' ) ]
    ]);
    echo wp_remote_retrieve_response_code( $response ) . "\n";
    // Output: 200, 200, 200, 200, 200, 429, 429
}
```

### 4. Test Input Sanitization

```php
// Test: Pass malicious input
$malicious_data = [
    'client_name'    => '<script>alert("xss")</script>John',
    'client_email'   => 'test@example.com; DROP TABLE wp_bookings;--',
    'client_phone'   => '123abc456++'
];

$sanitized = [
    'client_name'    => Booking_Security::sanitize_name( $malicious_data['client_name'] ),
    'client_email'   => Booking_Security::sanitize_email( $malicious_data['client_email'] ),
    'client_phone'   => Booking_Security::sanitize_phone( $malicious_data['client_phone'] )
];

// Expected: HTML tags removed, SQL injection prevented, invalid chars removed
```

### 5. Test Date Validation

```php
// Test: Invalid dates
$tests = [
    '2026-06-03' => true,   // Valid - start date
    '2025-06-03' => false,  // Invalid - in past
    '2026-09-19' => false,  // Invalid - after end date
    '2026-06-07' => false,  // Invalid - Sunday (blocked)
    '2026-06-06' => false,  // Invalid - Saturday (blocked)
    'invalid'    => false,  // Invalid - bad format
];

foreach ( $tests as $date => $expected ) {
    $result = Booking_Security::validate_booking_date( $date );
    assert( $result === $expected, "Date validation failed for $date" );
}
```

### 6. Test Email Sending

```php
// Test: Email confirmation
$booking_id = 1;
$result = Booking_Email::send_confirmation( $booking_id );

// Expected: true if mail queued successfully
// Check wp_mail() is hooked and email is in queue

// Verify email content
$booking = Booking_DB::get_booking( $booking_id );
assert( strpos( $booking->client_name, 'John' ) !== false );
```

### 7. Test Google Calendar Integration

```php
// Test: Add event to Google Calendar
$booking_id = Booking_Reservation::create( $valid_data );
$result = Booking_Google_Calendar::add_event( $booking_id );

// Expected: true if Google Calendar API call succeeds
// Check: google_event_id is stored in booking
$booking = Booking_DB::get_booking( $booking_id );
assert( ! empty( $booking->google_event_id ) );
```

### 8. Test Token Refresh

```php
// Test: Google Calendar token refresh
$credentials = Booking_Google_Calendar::get_credentials_with_validation();
$access_token = Booking_Google_Calendar::get_access_token();

// Expected: Access token is refreshed if expired
// Check: Token stored with expiry timestamp
$expiry = get_option( 'booking_google_access_token_expiry' );
assert( $expiry > time() );
```

---

## Integration Tests

### 1. Complete Booking Flow

```php
// Full user journey from slot selection to Google Calendar sync

// Step 1: Check availability
$date = '2026-06-10';
$slots = Booking_Availability::get_available_slots_for_date( $date );
assert( ! empty( $slots ) );

// Step 2: Create booking
$booking_data = [
    'booking_date'   => '2026-06-10',
    'time_slot'      => $slots[0]['time'],
    'client_name'    => 'Jane',
    'client_surname' => 'Smith',
    'client_email'   => 'jane@example.com',
    'client_phone'   => '+39 200 300 400'
];
$booking_id = Booking_Reservation::create( $booking_data );
assert( ! is_wp_error( $booking_id ) );

// Step 3: Verify email sent
Booking_Email::send_confirmation( $booking_id );
// Check transient or mail log

// Step 4: Verify Google Calendar event
$booking = Booking_DB::get_booking( $booking_id );
assert( ! empty( $booking->google_event_id ) );

// Step 5: Verify logging
$logs = Booking_DB::get_logs( 10 );
assert( count( $logs ) > 0 );
```

### 2. Duplicate Booking Prevention

```php
// Verify unique constraint (booking_date, time_slot, client_email)

$booking_data = [
    'booking_date'   => '2026-06-10',
    'time_slot'      => '10:00',
    'client_name'    => 'John',
    'client_surname' => 'Doe',
    'client_email'   => 'john@example.com',
    'client_phone'   => '+39 123 456 7890'
];

// First booking
$id1 = Booking_Reservation::create( $booking_data );
assert( ! is_wp_error( $id1 ) );

// Same slot, same email
$id2 = Booking_Reservation::create( $booking_data );
assert( is_wp_error( $id2 ) ); // Should fail
```

### 3. Admin Settings Persistence

```php
// Test: Admin saves settings, they persist

$settings = [
    'availability_start_date'   => '2026-07-01',
    'availability_end_date'     => '2026-08-31',
    'max_reservations_per_slot' => 3,
    'blocked_weekdays'          => [ 0, 5, 6 ], // Sun, Fri, Sat
];

// Save settings
foreach ( $settings as $key => $value ) {
    Booking_Settings::set( $key, $value );
}

// Verify retrieval
$retrieved = Booking_Settings::get_all();
assert( $retrieved['availability_start_date'] === '2026-07-01' );
assert( in_array( 5, $retrieved['blocked_weekdays'] ) );
```

---

## End-to-End Testing (Manual)

### 1. Frontend Form Testing

**Steps:**
1. Navigate to page with `[booking_form]` shortcode
2. Select a date from date picker
3. Observe slots loading dynamically via AJAX
4. Select a time slot
5. Enter valid name, email, phone
6. Click "Prenota"
7. Observe success message
8. Check confirmation email received
9. Check Google Calendar event created

**Expected Results:**
- Form displays correctly
- Date picker shows dates from start_date to end_date only
- Time slots load without page refresh
- Blocked days not selectable
- Success message appears after 3 seconds
- Email arrives within 1 minute
- Google Calendar event created with correct details

### 2. Admin Panel Testing

**Steps:**
1. Login as admin
2. Navigate to "Booking System" menu
3. Click "Impostazioni" tab
4. Modify:
   - Date range: 2026-07-15 to 2026-08-31
   - Hours: 08:00 to 18:00
   - Max reservations: 3
5. Click "Salva Impostazioni"
6. Refresh page, verify settings saved
7. Go to "Date Bloccate" tab
8. Enable Friday (5)
9. Add specific date 2026-07-25
10. Go to "Google Calendar" tab
11. Upload credentials.json
12. Enable Google Calendar

**Expected Results:**
- All settings persist after save
- Tab switching works without page reload
- File upload validates JSON structure
- Settings reflected in frontend availability

### 3. Stress Testing

**Steps:**
1. Use browser console or tool to make 10 rapid booking requests
2. Monitor rate limiting
3. Check database for duplicate bookings
4. Verify all attempts logged

**Expected Results:**
- First 5 requests succeed
- Requests 6-10 blocked with HTTP 429
- Database shows no race condition duplicates
- Log shows 10 attempts

### 4. Mobile Responsive Testing

**Steps:**
1. Open booking form on mobile (375px viewport)
2. Test form input on small screen
3. Test date picker functionality
4. Test slot selection dropdown
5. Submit booking

**Expected Results:**
- Form fully visible without horizontal scroll
- Font size prevents auto-zoom (16px minimum)
- Inputs large enough to tap easily
- Success message readable

---

## Performance Testing

### 1. Database Query Performance

```php
// Test: Get available slots for date with many bookings

// Setup: Create 1000 bookings across various dates
$start_time = microtime( true );
$slots = Booking_Availability::get_available_slots_for_date( '2026-06-15' );
$elapsed = microtime( true ) - $start_time;

// Expected: < 100ms for availability query
assert( $elapsed < 0.1, "Query took " . $elapsed . " seconds" );
```

### 2. Google Calendar API Performance

```php
// Test: Add event to Google Calendar with retry logic

$start_time = microtime( true );
$result = Booking_Google_Calendar::add_event( $booking_id );
$elapsed = microtime( true ) - $start_time;

// Expected: < 2 seconds (with network latency)
assert( $elapsed < 2 );
```

### 3. Load Testing

```php
// Concurrent booking requests
// Use: Apache Bench, LoadRunner, or similar tool

// Command: ab -n 100 -c 10 -p data.json https://yoursite.com/wp-json/booking/v1/reserve
// Expected: No 500 errors, response time < 500ms average
```

---

## Debugging Checklist

### Email Not Sending
- [ ] Check wp_mail() is working: `wp_mail( get_option( 'admin_email' ), 'Test', 'Test' )`
- [ ] Check SMTP settings in `wp-config.php`
- [ ] Check email transient: `get_transient( 'booking_email_sent_' . $booking_id )`
- [ ] Check logs: `Booking_DB::get_logs( 50 )`

### Google Calendar Event Not Created
- [ ] Check credentials stored: `Booking_Google_Calendar::get_credentials_with_validation()`
- [ ] Check access token: `get_option( 'booking_google_access_token' )`
- [ ] Check logs for API errors: `Booking_DB::get_logs( 50, 'google_error' )`
- [ ] Verify calendar ID correct: `Booking_Settings::get( 'google_calendar_id' )`

### Rate Limiting Too Strict
- [ ] Adjust in defaults.php: `'rate_limit_attempts' => 10`
- [ ] Check client IP detection: `Booking_Security::get_client_ip()`
- [ ] Check transient: `get_transient( 'booking_rate_limit_' . md5( $ip ) )`

### Database Errors
- [ ] Check tables exist: `SHOW TABLES LIKE 'wp_bookings'`
- [ ] Check UNIQUE constraint: `SHOW INDEX FROM wp_bookings`
- [ ] Verify column types match schema
- [ ] Check WordPress DB error logs

---

## Test Checklists

### Before Production Deployment
- [ ] Run all unit tests
- [ ] Run integration tests
- [ ] Test on staging with real email/Google account
- [ ] Load test with 100+ concurrent users
- [ ] Test all 3 tabs in admin panel
- [ ] Verify rate limiting works
- [ ] Test mobile responsiveness
- [ ] Test with different browsers (Chrome, Firefox, Safari, Edge)
- [ ] Backup database before deploying
- [ ] Document any custom settings

### Weekly Monitoring
- [ ] Check error logs for exceptions
- [ ] Review booking logs for unusual patterns
- [ ] Monitor Google Calendar sync failures
- [ ] Check email delivery rate
- [ ] Verify database size not growing unexpectedly
