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

### Admin Panel
- ✅ **Interfaccia Tabulata**: 4 sezioni (Generale, Date Bloccate, Google Calendar, Sicurezza)
- ✅ **Gestione Prenotazioni**: Lista di tutte le prenotazioni con paginazione
- ✅ **Visualizzazione Log**: Tracciamento di tutte le azioni con badge colorati

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