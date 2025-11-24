<?php
// Basis-Konfiguration für TerminFlux

// Pfad zur SQLite-Datenbank-Datei
const TF_DB_PATH = __DIR__ . '/data/terminflux.sqlite';

// Standard-Zeitzone für Datumsanzeigen
const TF_DEFAULT_TIMEZONE = 'Europe/Berlin';

date_default_timezone_set(TF_DEFAULT_TIMEZONE);
