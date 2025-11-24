<?php
require_once __DIR__ . '/config.php';

function tf_get_db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO('sqlite:' . TF_DB_PATH);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('PRAGMA foreign_keys = ON');
        tf_ensure_schema($pdo);
    }
    return $pdo;
}

function tf_ensure_schema(PDO $pdo): void
{
    $pdo->exec('CREATE TABLE IF NOT EXISTS polls (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        title TEXT NOT NULL,
        description TEXT,
        organizer_name TEXT NOT NULL,
        organizer_email TEXT,
        location TEXT,
        timezone TEXT,
        token TEXT NOT NULL UNIQUE,
        admin_token TEXT NOT NULL UNIQUE,
        created_at TEXT NOT NULL
    )');

    $pdo->exec('CREATE TABLE IF NOT EXISTS slots (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        poll_id INTEGER NOT NULL REFERENCES polls(id) ON DELETE CASCADE,
        slot_label TEXT NOT NULL,
        slot_start TEXT,
        slot_end TEXT,
        position INTEGER NOT NULL DEFAULT 0
    )');

    $pdo->exec('CREATE TABLE IF NOT EXISTS participants (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        poll_id INTEGER NOT NULL REFERENCES polls(id) ON DELETE CASCADE,
        name TEXT NOT NULL,
        comment TEXT,
        created_at TEXT NOT NULL
    )');

    $pdo->exec('CREATE TABLE IF NOT EXISTS votes (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        participant_id INTEGER NOT NULL REFERENCES participants(id) ON DELETE CASCADE,
        slot_id INTEGER NOT NULL REFERENCES slots(id) ON DELETE CASCADE,
        choice INTEGER NOT NULL
    )');
}
