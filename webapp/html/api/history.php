<?php
// GET: change history, newest first, keyset-paginated.
//   ?before=<revision id>  continue an earlier listing
//   ?disk=<disk id>        history of one entry only
//   ?limit=<n>             page size (default 50, max 200)

require_once __DIR__ . '/../../lib/db.php';

$limit = min(max((int)($_GET['limit'] ?? 50), 1), 200);
$before = (int)($_GET['before'] ?? 0);
$diskId = (int)($_GET['disk'] ?? 0);

$where = [];
if ($before > 0) {
    $where[] = 'r.id < :before';
}
if ($diskId > 0) {
    $where[] = 'r.disk_id = :disk';
}
$sql = 'SELECT r.*, b.filename AS batch_filename FROM revision r
        LEFT JOIN batch b ON b.id = r.batch_id' .
       ($where ? ' WHERE ' . implode(' AND ', $where) : '') .
       ' ORDER BY r.id DESC LIMIT :limit';
$stmt = db()->prepare($sql);
if ($before > 0) {
    $stmt->bindValue(':before', $before, SQLITE3_INTEGER);
}
if ($diskId > 0) {
    $stmt->bindValue(':disk', $diskId, SQLITE3_INTEGER);
}
$stmt->bindValue(':limit', $limit + 1, SQLITE3_INTEGER);

$revisions = [];
$rows = $stmt->execute();
while ($row = $rows->fetchArray(SQLITE3_ASSOC)) {
    $old = $row['old_data'] === null ? null :
        json_decode($row['old_data'], true);
    $new = $row['new_data'] === null ? null :
        json_decode($row['new_data'], true);
    $label = $new ?? $old ?? [];
    $revisions[] = [
        'id' => $row['id'],
        'disk_id' => $row['disk_id'],
        'action' => $row['action'],
        'changed_at' => $row['changed_at'],
        'changed_by_name' => $row['changed_by_name'],
        'batch_filename' => $row['batch_filename'],
        'produzent' => $label['produzent'] ?? null,
        'typ' => $label['typ'] ?? null,
        'diff' => disk_diff($old, $new),
    ];
}

$next = null;
if (count($revisions) > $limit) {
    array_pop($revisions);
    $next = end($revisions)['id'];
}

json_response(['revisions' => $revisions, 'next' => $next]);
