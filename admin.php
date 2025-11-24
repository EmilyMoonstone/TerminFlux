<?php
require_once __DIR__ . '/functions.php';

$token = $_GET['a'] ?? '';
$poll = $token ? tf_fetch_poll_by_token($token, true) : null;
if (!$poll) {
    http_response_code(404);
    echo 'Admin-Link ungültig oder Umfrage nicht gefunden.';
    exit;
}

$notice = '';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete_poll'])) {
        tf_delete_poll((int)$poll['id']);
        echo 'Umfrage gelöscht.';
        exit;
    }

    if (isset($_POST['delete_participant'])) {
        tf_delete_participant((int)$_POST['delete_participant'], (int)$poll['id']);
        $notice = 'Teilnehmer-Eintrag gelöscht.';
        $poll = tf_fetch_poll_by_token($token, true);
    } elseif (isset($_POST['delete_slot'])) {
        $deleted = tf_delete_slot_if_empty((int)$_POST['delete_slot'], (int)$poll['id']);
        $notice = $deleted ? 'Slot entfernt.' : 'Slot kann nicht gelöscht werden, da bereits Stimmen vorliegen.';
        $poll = tf_fetch_poll_by_token($token, true);
    } elseif (isset($_POST['update_poll'])) {
        $title = trim($_POST['title'] ?? '');
        $organizerName = trim($_POST['organizer_name'] ?? '');
        if ($title === '' || $organizerName === '') {
            $errors[] = 'Titel und Organisator*in dürfen nicht leer sein.';
        }
        if (!$errors) {
            tf_update_poll($poll, [
                'title' => $title,
                'description' => trim($_POST['description'] ?? ''),
                'organizer_name' => $organizerName,
                'organizer_email' => trim($_POST['organizer_email'] ?? ''),
                'location' => trim($_POST['location'] ?? ''),
                'timezone' => trim($_POST['timezone'] ?? TF_DEFAULT_TIMEZONE),
            ]);
            $notice = 'Änderungen gespeichert.';
            $poll = tf_fetch_poll_by_token($token, true);
        }
    } elseif (isset($_POST['add_slot'])) {
        $label = trim($_POST['new_slot'] ?? '');
        if ($label === '') {
            $errors[] = 'Bitte einen Slot-Text angeben.';
        } else {
            tf_add_slot((int)$poll['id'], ['label' => $label, 'start' => null, 'end' => null]);
            $notice = 'Slot hinzugefügt.';
            $poll = tf_fetch_poll_by_token($token, true);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin – <?php echo tf_h($poll['title']); ?> – TerminFlux</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<a class="skip-link" href="#main-content">Zum Inhalt springen</a>
<header class="topbar" role="banner">
    <div class="brand"><div class="logo" aria-hidden="true">TF</div><div><strong>TerminFlux</strong><br><small>Termin-Umfragen ohne Konto</small></div></div>
    <a class="button ghost" href="view.php?t=<?php echo urlencode($poll['token']); ?>">Zur Umfrage</a>
</header>
<main class="container" id="main-content">
    <header class="poll-head">
        <div>
            <p class="eyebrow">Administration</p>
            <h1><?php echo tf_h($poll['title']); ?></h1>
            <p class="meta">Admin-Link: <?php echo tf_h(tf_base_url() . '/admin.php?a=' . $token); ?></p>
        </div>
        <div class="badge">Admin</div>
    </header>

    <?php if ($notice): ?><div class="alert alert-success" role="status" aria-live="polite"><?php echo tf_h($notice); ?></div><?php endif; ?>
    <?php if ($errors): ?><div class="alert alert-error" role="alert" aria-live="assertive"><ul><?php foreach ($errors as $error): ?><li><?php echo tf_h($error); ?></li><?php endforeach; ?></ul></div><?php endif; ?>

    <section class="card">
        <h2>Umfrage-Einstellungen</h2>
        <form method="post">
            <input type="hidden" name="update_poll" value="1">
            <div class="grid">
                <label>Titel*<br><input type="text" name="title" required value="<?php echo tf_h($poll['title']); ?>"></label>
                <label>Organisator*in*<br><input type="text" name="organizer_name" required value="<?php echo tf_h($poll['organizer_name']); ?>"></label>
                <label>Kontakt-E-Mail<br><input type="email" name="organizer_email" value="<?php echo tf_h($poll['organizer_email']); ?>"></label>
                <label>Ort<br><input type="text" name="location" value="<?php echo tf_h($poll['location']); ?>"></label>
                <label>Zeitzone<br><input type="text" name="timezone" value="<?php echo tf_h($poll['timezone'] ?: TF_DEFAULT_TIMEZONE); ?>"></label>
            </div>
            <label>Beschreibung<br><textarea name="description" rows="3"><?php echo tf_h($poll['description']); ?></textarea></label>
            <div class="actions"><button class="button primary" type="submit">Speichern</button></div>
        </form>
    </section>

    <section class="card">
        <h2>Termine verwalten</h2>
        <ul class="slot-admin">
            <?php foreach ($poll['slots'] as $slot): ?>
                <li>
                    <span><?php echo tf_h($slot['slot_label']); ?></span>
                    <form method="post" onsubmit="return confirm('Slot wirklich löschen? Nur möglich, wenn noch keine Stimmen vorhanden sind.');">
                        <input type="hidden" name="delete_slot" value="<?php echo (int)$slot['id']; ?>">
                        <button class="button ghost" type="submit">Löschen</button>
                    </form>
                </li>
            <?php endforeach; ?>
        </ul>
        <form method="post" class="inline-form">
            <input type="hidden" name="add_slot" value="1">
            <input type="text" name="new_slot" placeholder="Neuer Slot (z.B. 20.06. 19:00)" required>
            <button class="button" type="submit">Slot hinzufügen</button>
        </form>
    </section>

    <section class="card">
        <h2>Teilnehmende</h2>
        <?php if (empty($poll['participants'])): ?>
            <p>Noch keine Einträge.</p>
        <?php else: ?>
            <div class="table-wrapper">
                <table class="responses">
                    <thead><tr><th>Name</th><th>Kommentar</th><th>Aktion</th></tr></thead>
                    <tbody>
                        <?php foreach ($poll['participants'] as $participant): ?>
                        <tr>
                            <td><?php echo tf_h($participant['name']); ?></td>
                            <td><?php echo tf_h($participant['comment']); ?></td>
                            <td>
                                <form method="post" onsubmit="return confirm('Diesen Eintrag wirklich löschen?');">
                                    <input type="hidden" name="delete_participant" value="<?php echo (int)$participant['id']; ?>">
                                    <button class="button ghost" type="submit">Entfernen</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <section class="card danger">
        <h2>Umfrage löschen</h2>
        <form method="post" onsubmit="return confirm('Umfrage wirklich löschen? Dies kann nicht rückgängig gemacht werden.');">
            <input type="hidden" name="delete_poll" value="1">
            <button class="button danger" type="submit">Umfrage endgültig löschen</button>
        </form>
    </section>
</main>
<footer class="footer">Made with ❤️ für datensparsame Abstimmungen.</footer>
</body>
</html>
