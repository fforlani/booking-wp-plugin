# Booking System - Plugin WordPress

Un plugin WordPress completo e sicuro per gestire prenotazioni con disponibilità oraria, integrazioni email e Google Calendar.

## Versione

**v1.0.0** - Completamente implementato con tutte le funzionalità di produzione

## Caratteristiche

### Core
- ✅ **Gestione Prenotazioni**: Form per prenotare slot orari
- ✅ **Configurazione Flessibile**: Date, orari, giorni bloccati configurabili dall'admin
- ✅ **Max Prenotazioni per Slot**: Limite configurabile di prenotazioni per fascia oraria
- ✅ **Database Personalizzato**: Tabelle `wp_bookings` e `wp_booking_logs` con indici
- ✅ **Logging Completo**: Tracciamento di tutti i tentativi, successi e errori

### Notifiche
- ✅ **Email di Conferma**: Notifiche email ai clienti e admin
- ✅ **Google Calendar**: Integrazione completa con Google Calendar via OAuth 2.0
- ✅ **Token Refresh**: Refresh automatico del token di accesso Google

### Admin Panel
- ✅ **Interfaccia Tabulata**: 4 sezioni (Generale, Date Bloccate, Google Calendar, Sicurezza)
- ✅ **Gestione Prenotazioni**: Lista di tutte le prenotazioni con paginazione
- ✅ **Visualizzazione Log**: Tracciamento di tutte le azioni con badge colorati
- ✅ **Upload Credenziali Google**: File upload con validazione JSON e controllo dimensione

### Frontend
- ✅ **REST API**: Endpoint per caricamento slot e creazione prenotazioni
- ✅ **Shortcode**: `[booking_form]` per inserire il form di prenotazione
- ✅ **Block Gutenberg**: `booking-system/form` per inserire via editor
- ✅ **AJAX**: Caricamento dinamico degli slot senza ricaricare la pagina
- ✅ **Validazione Client-side**: Feedback immediato sull'input

### Sicurezza
- ✅ **Input Validation & Sanitization**: Validazione e sanitizzazione di tutti gli input
- ✅ **SQL Injection Prevention**: Prepared statements con `$wpdb->prepare()`
- ✅ **CSRF Protection**: NONCE verification su tutte le richieste POST
- ✅ **XSS Protection**: Output escaping con `esc_html()`, `esc_attr()`
- ✅ **Race Condition Prevention**: Transazioni atomiche con double-check availability
- ✅ **Rate Limiting**: Limite di richieste per IP (5 richieste/ora default)
- ✅ **reCAPTCHA v3**: Protezione bot con bot detection score (opzionale)
- ✅ **Disposable Email Detection**: Blocco email da servizi monouso
- ✅ **Secure Credentials Storage**: Validazione file upload + storage sicuro

## Installazione

1. Copia la cartella del plugin nella directory `wp-content/plugins/` del tuo WordPress
2. Attiva il plugin dal pannello di amministrazione WordPress
3. Le tabelle del database verranno create automaticamente

## Configurazione

### 1. Impostazioni di Base (Tab "Generale")

Vai a **Booking System → Impostazioni** per configurare:

- **Data Inizio/Fine Disponibilità**: Range di date (es: 2026-06-03 a 2026-09-18)
- **Primo/Ultimo Slot Orario**: Orari di inizio e fine (es: 09:00 a 17:00)
- **Durata Slot**: Durata di ogni fascia oraria (default: 60 minuti)
- **Max Prenotazioni per Slot**: Numero massimo per fascia (default: 2)
- **Timezone**: Fuso orario (default: Europe/Rome)
- **Invia Email di Conferma**: Abilita notifiche ai clienti
- **Notifica Email Admin**: Ricevi alert per nuove prenotazioni

### 2. Giorni Bloccati (Tab "Date Bloccate")

- **Giorni della Settimana**: Seleziona domenica, sabato, o altri giorni non disponibili
- **Date Specifiche**: Una per riga in formato YYYY-MM-DD (es: 2026-07-15)

### 3. Google Calendar (Tab "Google Calendar")

Per integrare con Google Calendar:

1. Vai a [Google Cloud Console](https://console.cloud.google.com/)
2. Crea un nuovo progetto e abilita Google Calendar API
3. Crea un Service Account e scarica il JSON
4. Carica il file nella tab "Google Calendar" dell'admin
5. Inseri l'email/ID del calendario Google

**Cosa fa**:
- Crea evento per ogni prenotazione confermata
- Aggiorna/elimina evento se prenotazione cambia
- Sincronizza automaticamente con il calendario

### 4. Sicurezza (Tab "Sicurezza")

- **Rate Limiting**: Abilita protezione anti-brute-force (default: abilitato)
- **Max Tentativi/Ora**: Limite di richieste per IP (default: 5)
- **reCAPTCHA v3**: Protezione bot (opzionale)
  - Ottieni Site Key e Secret Key da [Google reCAPTCHA](https://www.google.com/recaptcha/admin)
  - Inserisci le chiavi nella tab Sicurezza

## Utilizzo

### Per i Clienti

1. Naviga alla pagina con il form di prenotazione: `[booking_form]`
2. Seleziona una data dal date picker
3. Scegli un orario dalla lista (caricata dinamicamente)
4. Inserisci nome, cognome, email e telefono
5. Clicca "Prenota"
6. Ricevi email di conferma con dettagli

### Per gli Admin

1. **Tab Impostazioni**: Configura date, orari, email
2. **Tab Prenotazioni**: Visualizza tutte le prenotazioni con filtri
3. **Tab Log**: Tracciamento di tutti gli eventi (success, error, email sent, etc.)
4. **Tab Sicurezza**: Configura protezioni e reCAPTCHA

## REST API

### Endpoint 1: Carica Slot Disponibili

```bash
GET /wp-json/booking/v1/slots?date=2026-06-10

Response:
{
  "date": "2026-06-10",
  "slots": [
    { "time": "09:00", "available_spots": 2 },
    { "time": "10:00", "available_spots": 1 }
  ]
}
```

### Endpoint 2: Crea Prenotazione

```bash
POST /wp-json/booking/v1/reserve
Headers: X-WP-Nonce: {nonce}
Body: {
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
  "message": "Prenotazione confermata!"
}
```

## Database Schema

### Tabella `wp_bookings`

```sql
id, booking_date, time_slot, client_name, client_surname, client_email, 
client_phone, status, google_event_id, created_at, updated_at
```

**Constraints**:
- UNIQUE(booking_date, time_slot, client_email) - Previene duplicate prenotazioni
- Indexed: booking_date, client_email, status

### Tabella `wp_booking_logs`

```sql
id, booking_id, action, message, client_email, timestamp
```

**Actions tracked**:
- `attempt` - Tentativo di prenotazione
- `success` - Prenotazione confermata
- `error` - Errore durante prenotazione
- `email_sent` - Email spedita
- `google_event_created` - Evento Google creato
- `google_event_deleted` - Evento Google eliminato
- `rate_limit_exceeded` - Limite di velocità superato
- `captcha_failed` - reCAPTCHA fallito

## Security Best Practices

1. **Backup del Database**: Fai backup regolarmente prima di aggiornamenti
2. **HTTPS**: Usa sempre SSL/TLS in produzione
3. **Credenziali Sicure**: Non condividere mai il file JSON di Google
4. **Aggiorna WordPress**: Mantieni WordPress e plugin aggiornati
5. **Monitora Log**: Controlla settimanalmente la sezione Log per anomalie
6. **Rate Limiting**: Mantieni abilitato il rate limiting
7. **reCAPTCHA**: Consigliato attivare per protezione extra

## Guida Avanzata

Vedi [ADVANCED-GUIDE.md](ADVANCED-GUIDE.md) per:
- Hooks e filter personalizzati
- Estensioni (SMS, Slack, etc.)
- Ottimizzazione database
- Problematiche comuni e debugging

## Guida Sicurezza

Vedi [SECURITY-GUIDE.md](SECURITY-GUIDE.md) per:
- Dettagli su ogni misura di sicurezza
- Checklist pre-deployment
- Gestione incident
- Compliance GDPR/PCI

## Guida Testing

Vedi [TESTING-GUIDE.md](TESTING-GUIDE.md) per:
- Unit test examples
- Integration test scenarios
- E2E testing checklist
- Performance testing
- Debugging tips

## Troubleshooting

### Email non viene spedita

1. Verifica wp_mail funzionante: `wp_mail( get_option( 'admin_email' ), 'Test', 'Test' )`
2. Controlla configurazione SMTP
3. Verifica cartella spam

### Google Calendar non sincronizza

1. Controlla credenziali: Admin → Sicurezza → Google Calendar
2. Verifica Calendar ID corretto
3. Controlla log per errori API
4. Verifica quota Google Cloud

### Rate Limiting troppo restrittivo

1. Aumenta limite in Admin → Sicurezza → Max Tentativi
2. Verifica IP reale (proxy/load balancer)
3. Controlla transient: `get_transient( 'booking_rate_limit_...' )`

## Support & Documentazione

- **Assets**: Vedi cartella `assets/` per setup Google Calendar
- **Codice**: Classe principali in `includes/`
- **Admin**: Interfaccia in `admin/admin-menu.php`
- **Frontend**: Form in `public/shortcode.php`

## Roadmap Futuro

- [ ] Pagamenti integrati (Stripe)
- [ ] SMS notifications
- [ ] Calendario lato frontend
- [ ] Export PDF
- [ ] Multi-language support
- [ ] API v2 con GraphQL
- [ ] Mobile app

## Licenza

GPL-2.0-or-later

## Autore

Dev Team
4. Crea le credenziali OAuth 2.0 (service account)
5. Scarica il file JSON delle credenziali
6. Abilita Google Calendar nelle impostazioni del plugin
7. Inserisci il tuo Calendar ID (email del calendario)

**Nota**: Le credenziali vanno gestite via admin panel (da implementare completamente nella FASE 5)

## Utilizzo del Form

### Shortcode

Inserisci il shortcode in una pagina o post:

```
[booking_form]
```

### Block Gutenberg

Nel builder di Gutenberg, cerca il block "Booking Form" e aggiungilo alla pagina.

## Struttura del Database

### Tabella `wp_bookings`
```sql
- id: ID della prenotazione
- booking_date: Data della prenotazione (YYYY-MM-DD)
- time_slot: Orario dello slot (HH:MM)
- client_name: Nome cliente
- client_surname: Cognome cliente
- client_email: Email cliente
- client_phone: Telefono cliente
- status: Stato (pending/confirmed/cancelled)
- google_event_id: ID evento Google Calendar
- created_at: Data creazione
- updated_at: Data ultimo aggiornamento
```

### Tabella `wp_booking_logs`
```sql
- id: ID del log
- booking_id: Riferimento a prenotazione
- action: Tipo di azione (attempt/success/error/email_sent/etc)
- message: Messaggio di log
- client_email: Email del cliente
- timestamp: Data/ora del log
```

## REST API Endpoints

### GET /wp-json/booking/v1/slots

Ottiene gli slot disponibili per una data.

**Parametri:**
- `date` (required): Data in formato YYYY-MM-DD

**Risposta:**
```json
{
  "date": "2026-06-10",
  "slots": [
    {
      "time": "09:00",
      "available_spots": 2
    },
    {
      "time": "10:00",
      "available_spots": 1
    }
  ]
}
```

### POST /wp-json/booking/v1/reserve

Crea una nuova prenotazione.

**Parametri (JSON body):**
```json
{
  "booking_date": "2026-06-10",
  "time_slot": "09:00",
  "client_name": "Mario",
  "client_surname": "Rossi",
  "client_email": "mario@example.com",
  "client_phone": "+39 123 456 7890"
}
```

**Risposta (success):**
```json
{
  "success": true,
  "booking_id": 1,
  "message": "Prenotazione confermata!",
  "booking": {
    "id": "1",
    "booking_date": "2026-06-10",
    "time_slot": "09:00",
    ...
  }
}
```

## Flusso Prenotazione

1. **Validazione Input**: Controllo dei dati inseriti
2. **Verifica Slot Disponibile**: Check atomico della disponibilità
3. **Creazione Prenotazione**: Inserimento nel DB con transazione
4. **Email di Conferma**: Invio email al cliente
5. **Notifica Admin**: Notifica all'amministratore
6. **Google Calendar**: Creazione evento sul calendario

Ogni step viene loggato nella tabella `wp_booking_logs`.

## Sicurezza

- ✅ NONCE verificato su endpoint POST
- ✅ Input sanitizzato e escaped
- ✅ Prepared statements per query SQL
- ✅ Transazione DB atomica (previene race condition)
- ✅ Controllo permessi admin

## Troubleshooting

### Le tabelle non vengono create

Verifica che WordPress abbia permessi di scrivere nel database. Prova a disattivare e riattivare il plugin.

### Email non vengono inviate

Controlla che il tuo server supporti `wp_mail()`. Assicurati che l'email admin sia configurata correttamente in WordPress.

### Google Calendar non funziona

1. Verifica che le credenziali siano corrette
2. Assicurati che l'API Google Calendar sia abilitata
3. Controlla i log del plugin (menu Booking System → Log)

## Prossimi Step

- [ ] Interfaccia upload credenziali Google OAuth
- [ ] Validazione avanzata dei parametri admin
- [ ] Rate limiting per evitare spam
- [ ] Email template HTML personalizzabili
- [ ] Cancellazione prenotazioni
- [ ] Sistema SMS (opzionale)
- [ ] Export prenotazioni (CSV/PDF)
- [ ] Multi-language support (i18n)

## Supporto

Per problemi o domande, contatta il team di sviluppo.

## Licenza

GPL-2.0-or-later
