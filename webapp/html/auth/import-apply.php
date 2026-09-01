<?php
// POST { token }: apply a staged import preview.  Creates and updates the
// rows exactly as classified by import-preview.php, all in one batch and
// one transaction; conflict and unchanged rows are skipped.  The preview
// is consumed (one apply per preview).

require_once __DIR__ . '/../../lib/db.php';

$user = current_user();
$token = read_json_body()['token'] ?? '';

$stmt = db()->prepare('SELECT * FROM import_preview WHERE token = :token');
$stmt->bindValue(':token', $token);
$preview = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
if (!$preview) {
    json_error('Import-Vorschau nicht gefunden oder abgelaufen', 404);
}
if ($preview['by'] !== $user['id']) {
    json_error('Import-Vorschau gehört zu einem anderen Benutzer', 403);
}
$rows = json_decode($preview['data'], true);

db()->exec('BEGIN IMMEDIATE');

$stmt = db()->prepare(
    'INSERT INTO batch (imported_at, by, by_name, filename, created, updated)
     VALUES (:at, :by, :by_name, :filename, 0, 0)');
$stmt->bindValue(':at', now_iso());
$stmt->bindValue(':by', $user['id'], SQLITE3_INTEGER);
$stmt->bindValue(':by_name', $user['name']);
$stmt->bindValue(':filename', $preview['filename']);
$stmt->execute();
$batchId = db()->lastInsertRowID();

$created = 0;
$updated = 0;
foreach ($rows as $row) {
    if ($row['status'] === 'new') {
        $id = insert_disk($row['data'], $user);
        log_revision($id, 'import', $user, null, $row['data'], $batchId);
        $created++;
    } elseif ($row['status'] === 'changed') {
        $existing = fetch_disk($row['id']);
        if (!$existing) {
            continue;  // deleted since the preview; skip rather than fail
        }
        $old = disk_data($existing);
        if (!disk_diff($old, $row['data'])) {
            continue;  // already matches (edited since the preview)
        }
        write_disk($row['id'], $row['data'], $user);
        log_revision($row['id'], 'import', $user, $old, $row['data'],
                     $batchId);
        $updated++;
    }
}

$stmt = db()->prepare(
    'UPDATE batch SET created = :created, updated = :updated
     WHERE id = :id');
$stmt->bindValue(':created', $created, SQLITE3_INTEGER);
$stmt->bindValue(':updated', $updated, SQLITE3_INTEGER);
$stmt->bindValue(':id', $batchId, SQLITE3_INTEGER);
$stmt->execute();

$stmt = db()->prepare('DELETE FROM import_preview WHERE token = :token');
$stmt->bindValue(':token', $token);
$stmt->execute();

db()->exec('COMMIT');

json_response(['batch' => $batchId, 'created' => $created,
               'updated' => $updated]);
