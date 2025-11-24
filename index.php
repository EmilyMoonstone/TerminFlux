<?php
require_once __DIR__ . '/functions.php';

$errors = [];
$created = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $organizerName = trim($_POST['organizer_name'] ?? '');
    $organizerEmail = trim($_POST['organizer_email'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $timezone = trim($_POST['timezone'] ?? TF_DEFAULT_TIMEZONE);
    $slotDates = $_POST['slot_date'] ?? [];
    $slotTimes = $_POST['slot_time'] ?? [];
    $slotNotes = $_POST['slot_note'] ?? [];

    if ($title === '') {
        $errors[] = 'Bitte einen Titel eintragen.';
    }
    if ($organizerName === '') {
        $errors[] = 'Bitte einen Namen für die organisierende Person angeben.';
    }

    $slots = [];
    foreach ($slotDates as $idx => $date) {
        $date = trim($date);
        $time = trim($slotTimes[$idx] ?? '');
        $note = trim($slotNotes[$idx] ?? '');
        if ($date === '') {
            continue;
        }
        $labelParts = [$date];
        if ($time !== '') {
            $labelParts[] = $time;
        }
        if ($note !== '') {
            $labelParts[] = '– ' . $note;
        }
        $label = implode(' ', $labelParts);
        $slots[] = [
            'label' => $label,
            'start' => trim($date . ($time !== '' ? ' ' . $time : '')),
            'end' => null,
        ];
    }
    if (count($slots) === 0) {
        $errors[] = 'Bitte mindestens einen Termin-Slot hinzufügen.';
    }

    if (!$errors) {
        try {
            $created = tf_store_poll([
                'title' => $title,
                'description' => $description,
                'organizer_name' => $organizerName,
                'organizer_email' => $organizerEmail !== '' ? $organizerEmail : null,
                'location' => $location !== '' ? $location : null,
                'timezone' => $timezone !== '' ? $timezone : TF_DEFAULT_TIMEZONE,
            ], $slots);
        } catch (Exception $e) {
            $errors[] = 'Speichern fehlgeschlagen: ' . tf_h($e->getMessage());
        }
    }
}

function tf_render_head(string $title): void
{
    echo '<!DOCTYPE html><html lang="de"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<title>' . tf_h($title) . ' – TerminFlux</title>';
    echo '<link rel="stylesheet" href="assets/css/style.css">';
    echo '<script defer src="assets/js/slots.js"></script>';
    echo '</head><body>';
    echo '<a class="skip-link" href="#main-content">Zum Inhalt springen</a>';
    echo '<header class="topbar" role="banner"><div class="brand"><div class="logo" aria-hidden="true">TF</div><div><strong>TerminFlux</strong><br><small>Schnelle Termin-Umfragen ohne Konto</small></div></div></header>';
    echo '<main class="container" id="main-content" role="main">';
}

function tf_render_footer(): void
{
    echo '<footer class="footer">Made with ❤️ für datensparsame Abstimmungen.</footer></main></body></html>';
}

if ($created) {
    tf_render_head('Umfrage erstellt');
    $base = tf_base_url();
    $viewUrl = $base . '/view.php?t=' . urlencode($created['token']);
    $adminUrl = $base . '/admin.php?a=' . urlencode($created['admin_token']);
    ?>
    <section class="card" role="status" aria-live="polite">
        <h1>Umfrage angelegt</h1>
        <p>Speichere die Links, um sie weiterzugeben oder später zu bearbeiten.</p>
        <div class="links">
            <div><strong>Teilnahme-Link:</strong><br><a href="<?php echo tf_h($viewUrl); ?>"><?php echo tf_h($viewUrl); ?></a></div>
            <div><strong>Admin-Link:</strong><br><a href="<?php echo tf_h($adminUrl); ?>"><?php echo tf_h($adminUrl); ?></a></div>
        </div>
        <a class="button" href="view.php?t=<?php echo urlencode($created['token']); ?>">Umfrage anzeigen</a>
    </section>
    <?php
    tf_render_footer();
    exit;
}

tf_render_head('Neue Termin-Umfrage erstellen');
?>
<section class="card intro">
    <h1>TerminFlux</h1>
    <p>Leichtgewichtige Termin-Umfragen ohne Registrierung und ohne Tracking. Erstelle eine neue Umfrage, teile den Link und sammle Antworten.</p>
    <a class="button" href="#create">Neue Termin-Umfrage erstellen</a>
</section>

<section class="card" id="create" aria-labelledby="create-heading">
    <h2 id="create-heading">Umfrage anlegen</h2>
    <?php if ($errors): ?>
        <div class="alert alert-error" role="alert" aria-live="assertive">
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?php echo tf_h($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
    <form method="post" aria-describedby="slot-help">
        <div class="grid">
            <label>Titel der Umfrage*<br><input type="text" name="title" required value="<?php echo tf_h($_POST['title'] ?? ''); ?>"></label>
            <label>Organisator*in*<br><input type="text" name="organizer_name" required value="<?php echo tf_h($_POST['organizer_name'] ?? ''); ?>"></label>
            <label>Kontakt-E-Mail (optional)<br><input type="email" name="organizer_email" value="<?php echo tf_h($_POST['organizer_email'] ?? ''); ?>"></label>
            <label>Ort / Treffpunkt (optional)<br><input type="text" name="location" value="<?php echo tf_h($_POST['location'] ?? ''); ?>"></label>
            <label>Zeitzone (z.B. Europe/Berlin)<br><input type="text" name="timezone" value="<?php echo tf_h($_POST['timezone'] ?? TF_DEFAULT_TIMEZONE); ?>"></label>
        </div>
        <label>Beschreibung (optional)<br><textarea name="description" rows="3"><?php echo tf_h($_POST['description'] ?? ''); ?></textarea></label>
        <div class="slots" aria-labelledby="slot-heading">
            <div class="slots-head">
                <h3 id="slot-heading">Termine / Zeitslots</h3>
                <button class="button ghost" type="button" id="add-slot">Slot hinzufügen</button>
            </div>
            <p id="slot-help">Datum und Uhrzeit getrennt eingeben. Zeiten lassen sich auf ausgewählte Wochentage kopieren oder per Zeitraum automatisch erzeugen.</p>

            <div class="slot-helper" aria-label="Helfer zum schnellen Hinzufügen von Slots">
                <div class="bulk-group">
                    <h4>Tage im Zeitraum hinzufügen</h4>
                    <div class="bulk-row">
                        <label>Startdatum<br><input type="date" id="range-start"></label>
                        <label>Enddatum<br><input type="date" id="range-end"></label>
                    </div>
                    <div class="weekday-select">
                        <span>Nur diese Wochentage:</span>
                        <?php
                        $weekdays = ['So', 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa'];
                        foreach ($weekdays as $idx => $day): ?>
                            <label><input type="checkbox" name="weekday_filter[]" value="<?php echo $idx; ?>"> <?php echo $day; ?></label>
                        <?php endforeach; ?>
                        <small>(leer lassen für alle)</small>
                    </div>
                    <label>Uhrzeiten (Komma oder Zeilenumbruch, optional für Zeitraum)<br><textarea id="time-templates" rows="2" placeholder="z.B. 09:00, 14:00"></textarea></label>
                    <div class="helper-actions">
                        <button class="button" type="button" id="apply-range">Tage/Zeiten aus Zeitraum hinzufügen</button>
                        <button class="button ghost" type="button" id="apply-times">Uhrzeiten auf vorhandene Tage kopieren</button>
                    </div>
                </div>
            </div>

            <div id="slot-list" class="slot-list">
                <?php
                $existingDates = $_POST['slot_date'] ?? [''];
                $existingTimes = $_POST['slot_time'] ?? [''];
                $existingNotes = $_POST['slot_note'] ?? [''];
                $max = max(count($existingDates), count($existingTimes), count($existingNotes));
                for ($i = 0; $i < $max; $i++):
                    $dateVal = tf_h($existingDates[$i] ?? '');
                    $timeVal = tf_h($existingTimes[$i] ?? '');
                    $noteVal = tf_h($existingNotes[$i] ?? '');
                ?>
                <div class="slot-row">
                    <label class="slot-field">Datum<br><input type="date" name="slot_date[]" required value="<?php echo $dateVal; ?>"></label>
                    <label class="slot-field">Uhrzeit<br><input type="time" name="slot_time[]" value="<?php echo $timeVal; ?>" placeholder="z.B. 18:00"></label>
                    <label class="slot-field">Notiz (optional)<br><input type="text" name="slot_note[]" value="<?php echo $noteVal; ?>" placeholder="z.B. nur online"></label>
                    <button type="button" class="remove-slot" aria-label="Slot entfernen">&times;</button>
                </div>
                <?php endfor; ?>
            </div>
        </div>
        <div class="actions">
            <button type="submit" class="button primary">Umfrage speichern</button>
        </div>
    </form>
</section>
<?php tf_render_footer(); ?>
