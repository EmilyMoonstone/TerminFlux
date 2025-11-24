<?php
require_once __DIR__ . '/functions.php';

$token = $_GET['t'] ?? '';
$poll = $token ? tf_fetch_poll_by_token($token) : null;
if (!$poll) {
    http_response_code(404);
    echo 'Umfrage nicht gefunden.';
    exit;
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $comment = trim($_POST['comment'] ?? '');
    if ($name === '') {
        $errors[] = 'Bitte einen Namen eintragen.';
    }

    $votes = [];
    foreach ($poll['slots'] as $slot) {
        $key = 'slot_' . $slot['id'];
        $choiceKey = $_POST[$key] ?? null;
        if ($choiceKey === null || !array_key_exists($choiceKey, TF_CHOICES)) {
            $errors[] = 'Bitte alle Slots bewerten.';
            break;
        }
        $votes[$slot['id']] = TF_CHOICES[$choiceKey];
    }

    if (!$errors) {
        try {
            tf_store_participation((int)$poll['id'], $name, $comment !== '' ? $comment : null, $votes);
            $poll = tf_fetch_poll_by_token($token); // reload with new data
        } catch (Exception $e) {
            $errors[] = 'Speichern fehlgeschlagen: ' . tf_h($e->getMessage());
        }
    }
}

function tf_choice_select(string $name): string
{
    $options = [
        'yes' => 'Ja',
        'online' => 'nur online',
        'maybe' => "wenn\'s sein muss",
        'no' => 'nein'
    ];
    $html = '<select name="' . tf_h($name) . '" required>';
    foreach ($options as $key => $label) {
        $html .= '<option value="' . tf_h($key) . '">' . tf_h($label) . '</option>';
    }
    $html .= '</select>';
    return $html;
}

function tf_render_poll_header(array $poll): void
{
    ?>
    <header class="poll-head">
        <div>
            <p class="eyebrow">Termin-Umfrage</p>
            <h1><?php echo tf_h($poll['title']); ?></h1>
            <?php if ($poll['description']): ?><p><?php echo nl2br(tf_h($poll['description'])); ?></p><?php endif; ?>
            <p class="meta">Organisiert von <?php echo tf_h($poll['organizer_name']); ?><?php if ($poll['location']): ?> · Ort: <?php echo tf_h($poll['location']); ?><?php endif; ?> · Zeitzone: <?php echo tf_h($poll['timezone'] ?: TF_DEFAULT_TIMEZONE); ?></p>
        </div>
        <div class="badge">TerminFlux</div>
    </header>
    <?php
}

function tf_render_table(array $poll): void
{
    ?>
    <div class="table-wrapper">
        <table class="responses">
            <thead>
                <tr>
                    <th>Teilnehmende</th>
                    <?php foreach ($poll['slots'] as $slot): ?>
                        <th><?php echo tf_h($slot['slot_label']); ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($poll['participants'])): ?>
                    <tr><td colspan="<?php echo count($poll['slots']) + 1; ?>">Noch keine Antworten.</td></tr>
                <?php else: ?>
                    <?php foreach ($poll['participants'] as $participant): ?>
                        <tr>
                            <td><strong><?php echo tf_h($participant['name']); ?></strong><br><small><?php echo tf_h($participant['comment']); ?></small></td>
                            <?php foreach ($poll['slots'] as $slot):
                                $choice = $poll['votes'][$participant['id']][$slot['id']] ?? null; ?>
                                <td><?php echo tf_render_choice_cell($choice); ?></td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php
}

function tf_render_summary(array $poll): void
{
    $summary = [];
    foreach ($poll['slots'] as $slot) {
        $summary[$slot['id']] = [0 => 0, 1 => 0, 2 => 0, 3 => 0];
    }
    foreach ($poll['votes'] as $participantVotes) {
        foreach ($participantVotes as $slotId => $choice) {
            $summary[$slotId][$choice]++;
        }
    }
    ?>
    <div class="summary">
        <h3>Auswertung</h3>
        <div class="summary-grid">
            <?php foreach ($poll['slots'] as $slot): $counts = $summary[$slot['id']]; ?>
                <div class="summary-card">
                    <h4><?php echo tf_h($slot['slot_label']); ?></h4>
                    <ul>
                        <li><span class="chip chip-3">Ja</span> <?php echo $counts[3]; ?></li>
                        <li><span class="chip chip-2">nur online</span> <?php echo $counts[2]; ?></li>
                        <li><span class="chip chip-1">wenn's sein muss</span> <?php echo $counts[1]; ?></li>
                        <li><span class="chip chip-0">nein</span> <?php echo $counts[0]; ?></li>
                    </ul>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo tf_h($poll['title']); ?> – TerminFlux</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<header class="topbar">
    <div class="brand"><div class="logo">TF</div><div><strong>TerminFlux</strong><br><small>Termin-Umfragen ohne Konto</small></div></div>
    <a class="button ghost" href="index.php">Neue Umfrage</a>
</header>
<main class="container">
    <?php tf_render_poll_header($poll); ?>

    <?php if ($errors): ?>
        <div class="alert alert-error">
            <ul><?php foreach ($errors as $error): ?><li><?php echo tf_h($error); ?></li><?php endforeach; ?></ul>
        </div>
    <?php endif; ?>

    <section class="card">
        <h2>Teilnehmen</h2>
        <form method="post">
            <div class="grid">
                <label>Name*<br><input type="text" name="name" required></label>
                <label>Kommentar (optional)<br><input type="text" name="comment"></label>
            </div>
            <div class="table-wrapper">
                <table class="vote-table">
                    <thead>
                        <tr>
                            <th>Termin</th>
                            <th>Antwort</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($poll['slots'] as $slot): ?>
                        <tr>
                            <td><?php echo tf_h($slot['slot_label']); ?></td>
                            <td><?php echo tf_choice_select('slot_' . $slot['id']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="actions">
                <button class="button primary" type="submit">Antwort absenden</button>
            </div>
        </form>
    </section>

    <section class="card">
        <h2>Ergebnisse</h2>
        <?php tf_render_table($poll); ?>
        <?php tf_render_summary($poll); ?>
    </section>
</main>
<footer class="footer">Made with ❤️ für datensparsame Abstimmungen.</footer>
</body>
</html>
