# TerminFlux

TerminFlux ist eine schlanke Termin-Umfrage-App ohne Login. Ziel: Termin-Slots erfassen, teilen und ohne Tracking ausfüllen. Basis: PHP 8+ und SQLite.

## Architektur & Dateien
- `config.php` – zentrale Konfiguration (DB-Pfad, Zeitzone).
- `db.php` – SQLite-Verbindung + Schema-Initialisierung.
- `functions.php` – Helfer (Token, Speichern, Laden, Rendering kleiner UI-Bausteine).
- `index.php` – Startseite und Formular zum Erstellen neuer Umfragen, Ausgabe der Links.
- `view.php` – Anzeige einer Umfrage, Teilnahmeformular, Ergebnisübersicht.
- `admin.php` – Administration via Admin-Token (Einstellungen, Slots hinzufügen/löschen, Teilnehmende löschen, Umfrage löschen).
- `assets/css/style.css` – Basislayout.
- `assets/js/slots.js` – kleines Skript zum Hinzufügen/Entfernen von Slot-Feldern.
- `data/terminflux.sqlite` – SQLite-Datenbank (wird automatisch angelegt).

## Datenbankschema (Kurzfassung)
- `polls`: `id`, `title`, `description`, `organizer_name`, `organizer_email`, `location`, `timezone`, `token`, `admin_token`, `created_at`
- `slots`: `id`, `poll_id`, `slot_label`, `slot_start`, `slot_end`, `position`
- `participants`: `id`, `poll_id`, `name`, `comment`, `created_at`
- `votes`: `id`, `participant_id`, `slot_id`, `choice` (0=nein, 1=wenn's sein muss, 2=nur online, 3=Ja)

## Installation (Shared Hosting)
1. PHP 8+ sicherstellen und `sqlite3`-Extension aktiv haben.
2. Alle Dateien in ein Verzeichnis (z.B. `/terminflux`) hochladen.
3. Dem Ordner `data/` Schreibrechte geben (z.B. `chmod 775 data`).
4. Optional: `config.php` prüfen/anpassen (DB-Pfad, Zeitzone).
5. Im Browser `https://deinedomain.tld/terminflux/index.php` aufrufen. Beim ersten Aufruf wird die SQLite-Datei samt Tabellen automatisch erstellt.
6. Über „Neue Termin-Umfrage“ eine Umfrage anlegen und die erzeugten Links speichern.

## Erweiterungsideen
- iCal-Export für ausgewählte Slots
- E-Mail-Benachrichtigungen für Organisator:innen
- Mehrsprachige Oberfläche (DE/EN)
