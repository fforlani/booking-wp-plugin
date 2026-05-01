# Security Guide - Booking System

## Overview

The Booking System includes comprehensive security measures to protect against common web vulnerabilities and ensure data integrity.

## Security Features Implemented

### 1. Input Validation & Sanitization

All user input is validated and sanitized before processing:

```php
// Email validation
$email = Booking_Security::sanitize_email( $user_input );
// Checks: valid email format, not from disposable service

// Phone validation
$phone = Booking_Security::sanitize_phone( $user_input );
// Checks: valid phone format, 10-15 digits with optional +

// Name validation
$name = Booking_Security::sanitize_name( $user_input );
// Checks: no HTML tags, no SQL chars, 2-100 chars

// Date validation
Booking_Security::validate_booking_date( $date );
// Checks: YYYY-MM-DD format, within availability range, not past

// Time validation
Booking_Security::validate_time_slot( $time );
// Checks: HH:MM format, within booking hours
```

### 2. SQL Injection Prevention

All database queries use prepared statements with `$wpdb->prepare()`:

```php
// ✅ SAFE - Uses prepared statement
$wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}bookings WHERE booking_date = %s AND client_email = %s",
    $date,
    $email
);

// ❌ UNSAFE - Direct concatenation (NOT USED IN THIS PLUGIN)
$wpdb->query( "SELECT * FROM wp_bookings WHERE email = '$email'" );
```

### 3. CSRF Protection (Cross-Site Request Forgery)

All POST requests require a NONCE token:

```php
// In frontend:
<input type="hidden" name="booking_nonce" value="<?php echo wp_create_nonce( 'booking_nonce' ); ?>" />

// In REST API:
$nonce = $request->get_header( 'X-WP-Nonce' );
if ( ! wp_verify_nonce( $nonce, 'booking_nonce' ) ) {
    return new WP_Error( 'invalid_nonce', 'Invalid nonce', array( 'status' => 403 ) );
}
```

### 4. XSS Protection (Cross-Site Scripting)

All output is escaped using appropriate WordPress functions:

```php
// HTML escaping
echo esc_html( $user_data );
echo esc_attr( $user_data ); // For HTML attributes

// In database
$wpdb->prepare( "SELECT * FROM wp_bookings WHERE id = %d", $id );

// In JSON responses
wp_json_encode( $data ); // Auto-escapes JSON
```

### 5. Rate Limiting

Prevent brute force and DoS attacks:

```php
// Configuration in config/defaults.php:
'rate_limit_enabled'    => true,
'rate_limit_attempts'   => 5,      // Max attempts per hour
'rate_limit_window'     => 3600,   // 1 hour

// Usage:
$rate_check = Booking_Security::check_rate_limit( $ip );
if ( is_wp_error( $rate_check ) ) {
    // Reject request - HTTP 429
    return new WP_Error( 'rate_limit_exceeded', '...', array( 'status' => 429 ) );
}

// On successful booking, reset rate limit:
Booking_Security::reset_rate_limit( $ip );
```

**How it works:**
- Tracks requests by client IP using WordPress transients
- Stores request count with 1-hour TTL
- After 5 requests, additional requests blocked for 1 hour
- Resets on successful booking (reward good behavior)

### 6. reCAPTCHA v3 Integration

Optional protection against bot attacks:

```php
// Configuration:
'enable_recaptcha'    => true,
'recaptcha_site_key'  => 'your_site_key',
'recaptcha_secret_key'=> 'your_secret_key',

// In frontend form:
<script src="https://www.google.com/recaptcha/api.js?render=YOUR_SITE_KEY"></script>
<script>
    grecaptcha.ready(function() {
        grecaptcha.execute('YOUR_SITE_KEY', {action: 'booking'}).then(function(token) {
            document.querySelector('input[name="recaptcha_token"]').value = token;
        });
    });
</script>

// In backend:
if ( ! Booking_Security::validate_recaptcha( $recaptcha_token ) ) {
    return new WP_Error( 'captcha_failed', 'reCAPTCHA validation failed' );
}
```

### 7. Atomic Transactions (Race Condition Prevention)

Bookings use database transactions to prevent overbooking:

```php
// In class-booking-reservation.php:
wpdb->query( "START TRANSACTION" );

// Double-check availability INSIDE transaction
$current_count = $wpdb->get_var(
    $wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}bookings WHERE booking_date = %s AND time_slot = %s",
        $booking_date,
        $time_slot
    )
);

if ( $current_count >= $max_reservations ) {
    $wpdb->query( "ROLLBACK" );
    return new WP_Error( 'slot_not_available', '...' );
}

// Insert booking
$wpdb->insert( $wpdb->prefix . 'bookings', $booking_data );

// Commit transaction
$wpdb->query( "COMMIT" );
```

### 8. Secure Google Credentials Storage

Google service account credentials stored safely:

```php
// Validate file upload:
if ( $_FILES['booking_credentials_file']['size'] > 1048576 ) { // 1MB max
    return new WP_Error( 'file_too_large', '...' );
}

$file_type = mime_content_type( $_FILES['booking_credentials_file']['tmp_name'] );
if ( $file_type !== 'application/json' && $file_type !== 'text/plain' ) {
    return new WP_Error( 'invalid_file_type', '...' );
}

// Validate JSON structure
$credentials = json_decode( file_get_contents( $file_path ), true );
if ( ! isset( $credentials['type'] ) || $credentials['type'] !== 'service_account' ) {
    return new WP_Error( 'invalid_credentials', '...' );
}

// Store securely
update_option( 'booking_google_credentials', wp_json_encode( $credentials ) );
```

**Important:**
- Credentials are never logged or output
- Stored in WordPress options (encrypted if using security plugins)
- Never commit credentials to version control
- Use `.gitignore`: `credentials.json`

### 9. Secure Error Handling

Never expose sensitive system details to users:

```php
// ❌ BAD - Exposes database details
if ( is_wp_error( $result ) ) {
    wp_die( "MySQL Error: " . $result->get_error_message() );
}

// ✅ GOOD - Safe error message
if ( is_wp_error( $result ) ) {
    $safe_msg = Booking_Security::get_safe_error_message( 'database_error' );
    return new WP_Error( 'database_error', $safe_msg );
}

// Safe messages map:
'database_error'    => 'Errore del sistema. Riprova tra qualche minuto.'
'slot_not_available'=> 'Lo slot non è più disponibile. Ricarica e prova di nuovo.'
'invalid_email'     => 'Email non valida. Controlla il formato.'
```

### 10. Logging & Audit Trail

All actions logged for security audit:

```php
// Every booking action is logged
Booking_Logger::log_action( $booking_id, 'attempt', 'User attempted to book', $email );
Booking_Logger::log_action( $booking_id, 'success', 'Booking confirmed', $email );
Booking_Logger::log_error( $booking_id, 'slot_full', 'Slot already fully booked', $email );
Booking_Logger::log_google_event_created( $booking_id, $google_event_id );

// Security events also logged
Booking_Logger::log_action( null, 'rate_limit_exceeded', 'Rate limit hit on GET /slots', $ip );
Booking_Logger::log_action( null, 'captcha_failed', 'reCAPTCHA validation failed', $email );
```

View logs in WordPress admin: Booking System → Log

---

## Security Checklist

### Pre-Deployment

- [ ] Verify all database queries use `$wpdb->prepare()`
- [ ] Test NONCE verification on all POST requests
- [ ] Enable rate limiting in production
- [ ] Configure reCAPTCHA (optional but recommended)
- [ ] Update Google credentials file upload validators
- [ ] Review error messages for info leakage
- [ ] Test input validation with malicious payloads
- [ ] Verify email sanitization prevents header injection

### Production Configuration

```php
// In wp-config.php or admin settings:

// Enable security features
define( 'BOOKING_RATE_LIMIT', true );
define( 'BOOKING_RECAPTCHA_ENABLED', true );

// Security headers (in .htaccess for Apache)
Header set X-Content-Type-Options "nosniff"
Header set X-Frame-Options "DENY"
Header set X-XSS-Protection "1; mode=block"
Header set Referrer-Policy "strict-origin-when-cross-origin"
```

### Ongoing Monitoring

- [ ] Monitor login attempts in WordPress
- [ ] Review booking logs for suspicious patterns
- [ ] Check rate limiting effectiveness (transients)
- [ ] Verify email confirmations being sent properly
- [ ] Monitor Google Calendar API errors
- [ ] Review reCAPTCHA analytics for bot attempts
- [ ] Backup database regularly
- [ ] Keep WordPress core and plugins updated

---

## Common Vulnerabilities & Mitigation

### SQL Injection
- **Mitigation**: All queries use prepared statements with `$wpdb->prepare()`
- **Test**: Try email like `test' OR '1'='1`
- **Result**: Query fails safely, no data exposed

### XSS (Cross-Site Scripting)
- **Mitigation**: All output escaped with `esc_html()`, `esc_attr()`, `esc_url()`
- **Test**: Try name like `<script>alert('xss')</script>`
- **Result**: Script tags removed, displayed as plain text

### CSRF (Cross-Site Request Forgery)
- **Mitigation**: NONCE verification on all state-changing requests
- **Test**: Submit form from external site
- **Result**: Request rejected with "Invalid nonce" error

### Brute Force / DoS
- **Mitigation**: Rate limiting (5 attempts per hour per IP)
- **Test**: Make 10 rapid booking requests from same IP
- **Result**: First 5 process, requests 6-10 return HTTP 429

### Timing Attacks (on password reset, etc.)
- **Not applicable**: No user authentication in this plugin

### Insecure Direct Object References (IDOR)
- **Mitigation**: No user-specific booking links exposed
- **Users**: Can only see own booking confirmation email

---

## Incident Response

### If Rate Limiting Falsely Triggered

1. Check IP address: `Booking_Security::get_client_ip()`
2. Clear transient: `delete_transient( 'booking_rate_limit_' . md5( $ip ) )`
3. Temporarily increase limit in settings if needed
4. Check for bot activity in logs

### If Google Calendar Sync Failing

1. Check credentials: Admin → Booking System → Google Calendar
2. Verify credentials file is valid JSON
3. Check API quota at Google Cloud Console
4. Verify calendar ID is correct
5. Check logs for specific error messages

### If Email Not Sending

1. Test WordPress email: `wp_mail( get_option( 'admin_email' ), 'Test', 'Test' )`
2. Check mail server configuration
3. Verify sender email in WordPress settings
4. Check logs for email errors
5. Check spam folder
6. Review mail server logs (SMTP, sendmail)

---

## Best Practices for Administrators

1. **Backup regularly**: Database backups before major changes
2. **Update WordPress**: Keep WordPress and all plugins current
3. **Monitor logs**: Check booking logs weekly for errors
4. **Rotate credentials**: Update Google service account credentials annually
5. **Use HTTPS**: Always enable SSL/TLS on production site
6. **Strong passwords**: Use strong admin passwords
7. **Limit admin access**: Restrict booking system access to trusted users
8. **Test updates**: Test plugin updates on staging before production

---

## Compliance

This plugin is designed to help comply with:
- **GDPR**: Stores minimal personal data, provides audit logs
- **PCI DSS**: No payment processing (reduces PCI scope)
- **WCAG 2.1**: Form accessible to screen readers, keyboard navigation

For GDPR compliance:
- [ ] Obtain user consent before storing email/phone
- [ ] Provide data export functionality
- [ ] Provide data deletion functionality
- [ ] Update privacy policy

---

## Resources

- [OWASP Top 10 Web Application Risks](https://owasp.org/www-project-top-ten/)
- [WordPress Security](https://developer.wordpress.org/plugins/security/)
- [Google Calendar API Security](https://developers.google.com/calendar/api/guides/auth)
- [reCAPTCHA Documentation](https://developers.google.com/recaptcha/docs/v3)
