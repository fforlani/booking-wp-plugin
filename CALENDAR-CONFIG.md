# Google Calendar Setup

Questo file contiene le istruzioni per configurare Google Calendar con il plugin Booking System.

## Prerequisiti

- Account Google
- Accesso a [Google Cloud Console](https://console.cloud.google.com/)

## Passaggi di Configurazione

### 1. Crea un nuovo progetto

1. Vai a Google Cloud Console
2. Clicca su "Seleziona un progetto" in alto
3. Clicca "Nuovo progetto"
4. Nomina il progetto (es: "Booking System")
5. Clicca "Crea"

### 2. Abilita Google Calendar API

1. Nel menu di sinistra, seleziona "API e servizi" → "Libreria"
2. Cerca "Google Calendar API"
3. Clicca su di essa
4. Clicca "Abilita"

### 3. Crea le credenziali OAuth 2.0

1. Vai a "API e servizi" → "Credenziali"
2. Clicca "Crea credenziali" → "Service Account"
3. Compila il form:
   - Nome account servizio: `booking-system`
   - Descrizione: `Service account for Booking System`
   - Clicca "Crea e continua"
4. Assegna il ruolo "Editor" (o "Viewer" se preferisci)
5. Clicca "Continua"
6. Clicca "Crea chiave"
7. Seleziona "JSON" come tipo di chiave
8. Clicca "Crea"
9. Il file JSON verrà scaricato automaticamente

### 4. Ottieni il Calendar ID

1. Vai a [Google Calendar](https://calendar.google.com/)
2. Accedi con l'account in cui vuoi archiviare le prenotazioni
3. Clicca su "+ Crea" per creare un nuovo calendario
4. Nomina il calendario (es: "Prenotazioni")
5. Crea il calendario
6. Accedi alle impostazioni del calendario
7. Nella sezione "Integra calendario", troverai l'ID calendario (es: `abc123def456@calendar.google.com`)

### 5. Configura il plugin

1. Accedi al pannello di amministrazione WordPress
2. Vai a "Booking System" → "Impostazioni"
3. Abilita "Google Calendar Abilitato"
4. Inserisci il Calendar ID nel campo "Google Calendar ID/Email"
5. Salva le impostazioni

### 6. Carica le credenziali

**NOTA**: La funzionalità di upload credenziali non è ancora implementata. Per ora, le credenziali devono essere gestite manualmente. Sarà implementato nella FASE 5.

Quando sarà disponibile, potrai caricare il file JSON scaricato in precedenza direttamente dal plugin.

## Permessi Richiesti

Il service account ha bisogno dei seguenti permessi nel calendario:

- `calendar.events.create` - Creare eventi
- `calendar.events.read` - Leggere eventi
- `calendar.events.update` - Aggiornare eventi
- `calendar.calendars.get` - Ottenere info calendario

## Sicurezza

⚠️ **IMPORTANTE**: Il file JSON contiene credenziali sensibili. 

- Non condividere il file con nessuno
- Non versionare il file in un repository pubblico
- Custodire il file in una posizione sicura

## Troubleshooting

### Le credenziali non vengono salvate

Verifica che il file sia in formato JSON valido.

### Gli eventi non vengono creati

1. Controlla il Calendar ID
2. Verifica nei log del plugin (menu "Booking System" → "Log")
3. Assicurati che il service account abbia accesso al calendario

### "Permission denied" errors

Verifica che il service account abbia i permessi corretti nel calendario:

1. Vai a Google Calendar
2. Accedi alle impostazioni del calendario
3. Sezione "Condividi con altri utenti"
4. Aggiungi l'email del service account (`booking-system@tuoprogetto.iam.gserviceaccount.com`)
5. Assegnagli il ruolo "Editor"

## Disabilitazione

Se vuoi disabilitare Google Calendar in qualsiasi momento:

1. Vai a "Booking System" → "Impostazioni"
2. Deabilita "Google Calendar Abilitato"
3. Salva le impostazioni

Le prenotazioni continueranno a funzionare normalmente, solo gli eventi non verranno creati su Google Calendar.
