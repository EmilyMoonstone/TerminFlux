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
    $slotLabels = $_POST['slot_label'] ?? [];
    $slotStarts = $_POST['slot_start'] ?? [];

    if ($title === '') {
        $errors[] = 'Bitte einen Titel eintragen.';
    }
    if ($organizerName === '') {
        $errors[] = 'Bitte einen Namen für die organisierende Person angeben.';
    }

    $slots = [];
    foreach ($slotLabels as $idx => $label) {
        $label = trim($label);
        $start = trim($slotStarts[$idx] ?? '');
        if ($label === '' && $start === '') {
            continue;
        }
        $slots[] = [
            'label' => $label !== '' ? $label : $start,
            'start' => $start !== '' ? $start : null,
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
    echo '</head><body><header class="topbar"><div class="brand"><div class="logo">TF</div><div><strong>TerminFlux</strong><br><small>Schnelle Termin-Umfragen ohne Konto</small></div></div></header><main class="container">';
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
    <section class="card">
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

<section class="card" id="create">
    <h2>Umfrage anlegen</h2>
    <?php if ($errors): ?>
        <div class="alert alert-error">
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?php echo tf_h($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
    <form method="post">
        <div class="grid">
            <label>Titel der Umfrage*<br><input type="text" name="title" required value="<?php echo tf_h($_POST['title'] ?? ''); ?>"></label>
            <label>Organisator*in*<br><input type="text" name="organizer_name" required value="<?php echo tf_h($_POST['organizer_name'] ?? ''); ?>"></label>
            <label>Kontakt-E-Mail (optional)<br><input type="email" name="organizer_email" value="<?php echo tf_h($_POST['organizer_email'] ?? ''); ?>"></label>
            <label>Ort / Treffpunkt (optional)<br><input type="text" name="location" value="<?php echo tf_h($_POST['location'] ?? ''); ?>"></label>
            <label>Zeitzone (z.B. Europe/Berlin)<br><input type="text" name="timezone" value="<?php echo tf_h($_POST['timezone'] ?? TF_DEFAULT_TIMEZONE); ?>"></label>
        </div>
        <label>Beschreibung (optional)<br><textarea name="description" rows="3"><?php echo tf_h($_POST['description'] ?? ''); ?></textarea></label>
        <div class="slots">
            <div class="slots-head">
                <h3>Termine / Zeitslots</h3>
                <button class="button ghost" type="button" id="add-slot">Slot hinzufügen</button>
            </div>
            <p>Trage Datum/Uhrzeit oder eine kurze Slot-Beschreibung ein.</p>
            <div id="slot-list" class="slot-list">
                <?php
                $existingSlots = $_POST['slot_label'] ?? [''];
                foreach ($existingSlots as $idx => $label):
                    $value = tf_h($label);
                ?>
                <div class="slot-row">
                    <input type="text" name="slot_label[]" placeholder="z.B. 12.06. 18:00" value="<?php echo $value; ?>" required>
                    <button type="button" class="remove-slot" aria-label="Slot entfernen">&times;</button>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="actions">
            <button type="submit" class="button primary">Umfrage speichern</button>
        </div>
    </form>
</section>
<?php tf_render_footer(); ?>
