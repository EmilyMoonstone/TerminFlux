<?php
require_once __DIR__ . '/db.php';

const TF_CHOICES = [
    'yes' => 3,
    'online' => 2,
    'maybe' => 1,
    'no' => 0,
];

const TF_CHOICES_LABELS = [
    3 => 'Ja',
    2 => 'nur online',
    1 => "wenn's sein muss",
    0 => 'nein'
];

function tf_random_token(int $length = 32): string
{
    return bin2hex(random_bytes($length / 2));
}

function tf_h(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function tf_base_url(): string
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $path = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
    return $scheme . '://' . $host . $path;
}

function tf_fetch_poll_by_token(string $token, bool $admin = false): ?array
{
    $pdo = tf_get_db();
    $stmt = $pdo->prepare('SELECT * FROM polls WHERE ' . ($admin ? 'admin_token' : 'token') . ' = :token');
    $stmt->execute([':token' => $token]);
    $poll = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$poll) {
        return null;
    }
    return tf_enrich_poll($poll);
}

function tf_enrich_poll(array $poll): array
{
    $pdo = tf_get_db();
    $slotsStmt = $pdo->prepare('SELECT * FROM slots WHERE poll_id = :poll_id ORDER BY position, id');
    $slotsStmt->execute([':poll_id' => $poll['id']]);
    $slots = $slotsStmt->fetchAll(PDO::FETCH_ASSOC);

    $participantsStmt = $pdo->prepare('SELECT * FROM participants WHERE poll_id = :poll_id ORDER BY created_at, id');
    $participantsStmt->execute([':poll_id' => $poll['id']]);
    $participants = $participantsStmt->fetchAll(PDO::FETCH_ASSOC);

    $votesStmt = $pdo->prepare('SELECT * FROM votes WHERE slot_id IN (SELECT id FROM slots WHERE poll_id = :poll_id)');
    $votesStmt->execute([':poll_id' => $poll['id']]);
    $votes = $votesStmt->fetchAll(PDO::FETCH_ASSOC);

    $voteMatrix = [];
    foreach ($votes as $vote) {
        $voteMatrix[$vote['participant_id']][$vote['slot_id']] = (int)$vote['choice'];
    }

    $poll['slots'] = $slots;
    $poll['participants'] = $participants;
    $poll['votes'] = $voteMatrix;
    return $poll;
}

function tf_store_poll(array $data, array $slots): array
{
    $pdo = tf_get_db();
    $token = tf_random_token(24);
    $adminToken = tf_random_token(28);

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('INSERT INTO polls (title, description, organizer_name, organizer_email, location, timezone, token, admin_token, created_at) VALUES (:title, :description, :organizer_name, :organizer_email, :location, :timezone, :token, :admin_token, :created_at)');
        $stmt->execute([
            ':title' => $data['title'],
            ':description' => $data['description'] ?? null,
            ':organizer_name' => $data['organizer_name'],
            ':organizer_email' => $data['organizer_email'] ?? null,
            ':location' => $data['location'] ?? null,
            ':timezone' => $data['timezone'] ?? TF_DEFAULT_TIMEZONE,
            ':token' => $token,
            ':admin_token' => $adminToken,
            ':created_at' => date('c'),
        ]);
        $pollId = (int)$pdo->lastInsertId();

        $slotStmt = $pdo->prepare('INSERT INTO slots (poll_id, slot_label, slot_start, slot_end, position) VALUES (:poll_id, :slot_label, :slot_start, :slot_end, :position)');
        $pos = 0;
        foreach ($slots as $slot) {
            $slotStmt->execute([
                ':poll_id' => $pollId,
                ':slot_label' => $slot['label'],
                ':slot_start' => $slot['start'] ?? null,
                ':slot_end' => $slot['end'] ?? null,
                ':position' => $pos++,
            ]);
        }
        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
    return ['token' => $token, 'admin_token' => $adminToken];
}

function tf_store_participation(int $pollId, string $name, ?string $comment, array $votes): void
{
    $pdo = tf_get_db();
    $pdo->beginTransaction();
    try {
        $pStmt = $pdo->prepare('INSERT INTO participants (poll_id, name, comment, created_at) VALUES (:poll_id, :name, :comment, :created_at)');
        $pStmt->execute([
            ':poll_id' => $pollId,
            ':name' => $name,
            ':comment' => $comment,
            ':created_at' => date('c'),
        ]);
        $participantId = (int)$pdo->lastInsertId();

        $vStmt = $pdo->prepare('INSERT INTO votes (participant_id, slot_id, choice) VALUES (:participant_id, :slot_id, :choice)');
        foreach ($votes as $slotId => $choice) {
            $vStmt->execute([
                ':participant_id' => $participantId,
                ':slot_id' => $slotId,
                ':choice' => $choice,
            ]);
        }
        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function tf_delete_participant(int $participantId, int $pollId): void
{
    $pdo = tf_get_db();
    $stmt = $pdo->prepare('DELETE FROM participants WHERE id = :id AND poll_id = :poll_id');
    $stmt->execute([':id' => $participantId, ':poll_id' => $pollId]);
}

function tf_delete_poll(int $pollId): void
{
    $pdo = tf_get_db();
    $stmt = $pdo->prepare('DELETE FROM polls WHERE id = :id');
    $stmt->execute([':id' => $pollId]);
}

function tf_update_poll(array $poll, array $fields): void
{
    $pdo = tf_get_db();
    $stmt = $pdo->prepare('UPDATE polls SET title = :title, description = :description, organizer_name = :organizer_name, organizer_email = :organizer_email, location = :location, timezone = :timezone WHERE id = :id');
    $stmt->execute([
        ':title' => $fields['title'],
        ':description' => $fields['description'] ?? null,
        ':organizer_name' => $fields['organizer_name'],
        ':organizer_email' => $fields['organizer_email'] ?? null,
        ':location' => $fields['location'] ?? null,
        ':timezone' => $fields['timezone'] ?? TF_DEFAULT_TIMEZONE,
        ':id' => $poll['id'],
    ]);
}

function tf_add_slot(int $pollId, array $slot): void
{
    $pdo = tf_get_db();
    $pos = (int)$pdo->query('SELECT COALESCE(MAX(position), 0) FROM slots WHERE poll_id = ' . (int)$pollId)->fetchColumn();
    $stmt = $pdo->prepare('INSERT INTO slots (poll_id, slot_label, slot_start, slot_end, position) VALUES (:poll_id, :slot_label, :slot_start, :slot_end, :position)');
    $stmt->execute([
        ':poll_id' => $pollId,
        ':slot_label' => $slot['label'],
        ':slot_start' => $slot['start'] ?? null,
        ':slot_end' => $slot['end'] ?? null,
        ':position' => $pos + 1,
    ]);
}

function tf_delete_slot_if_empty(int $slotId, int $pollId): bool
{
    $pdo = tf_get_db();
    $countStmt = $pdo->prepare('SELECT COUNT(*) FROM votes WHERE slot_id = :slot_id');
    $countStmt->execute([':slot_id' => $slotId]);
    if ((int)$countStmt->fetchColumn() > 0) {
        return false;
    }
    $stmt = $pdo->prepare('DELETE FROM slots WHERE id = :id AND poll_id = :poll_id');
    $stmt->execute([':id' => $slotId, ':poll_id' => $pollId]);
    return true;
}

function tf_render_choice_cell(?int $choice): string
{
    if ($choice === null) {
        return '<span class="chip chip-empty">–</span>';
    }
    $label = TF_CHOICES_LABELS[$choice] ?? '';
    $class = 'chip chip-' . $choice;
    return '<span class="' . $class . '">' . tf_h($label) . '</span>';
}
