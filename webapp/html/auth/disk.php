<?php
// Single-entry CRUD for members.
//   POST            create; body = editable fields
//   PUT ?id=<id>    update; body = editable fields
//   DELETE ?id=<id> soft-delete
// Every change writes one revision row.

require_once __DIR__ . '/../../lib/db.php';

$user = current_user();
$method = $_SERVER['REQUEST_METHOD'];
$id = (int)($_GET['id'] ?? 0);

try {
switch ($method) {
case 'POST':
    $data = normalize_disk(read_json_body());
    db()->exec('BEGIN IMMEDIATE');
    $id = insert_disk($data, $user);
    log_revision($id, 'create', $user, null, $data);
    db()->exec('COMMIT');
    json_response(fetch_disk($id), 201);

case 'PUT':
    $row = fetch_disk($id);
    if (!$row) {
        json_error('Eintrag nicht gefunden', 404);
    }
    $data = normalize_disk(read_json_body());
    $old = disk_data($row);
    if (!disk_diff($old, $data)) {
        json_response($row);  // nothing changed, nothing logged
    }
    db()->exec('BEGIN IMMEDIATE');
    write_disk($id, $data, $user);
    log_revision($id, 'update', $user, $old, $data);
    db()->exec('COMMIT');
    json_response(fetch_disk($id));

case 'DELETE':
    $row = fetch_disk($id);
    if (!$row) {
        json_error('Eintrag nicht gefunden', 404);
    }
    db()->exec('BEGIN IMMEDIATE');
    $stmt = db()->prepare(
        'UPDATE disk SET deleted = 1, updated_at = :at, updated_by = :by,
                         updated_by_name = :name WHERE id = :id');
    $stmt->bindValue(':at', now_iso());
    $stmt->bindValue(':by', $user['id'], SQLITE3_INTEGER);
    $stmt->bindValue(':name', $user['name']);
    $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
    $stmt->execute();
    log_revision($id, 'delete', $user, disk_data($row), null);
    db()->exec('COMMIT');
    json_response(['deleted' => $id]);

default:
    json_error('Methode nicht erlaubt', 405);
}
} catch (InvalidArgumentException $e) {
    json_error($e->getMessage(), 400);
}
