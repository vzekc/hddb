<?php
// POST: stage a bulk import and report what applying it would change.
// Body: { filename: string, columns: [names], rows: [ { id?, ... }, ... ] }
// (the browser parses CSV/XLSX and sends plain rows plus the column list
// found in the file).  Only the file's columns are authoritative: fields
// absent from the file keep their existing values on matched rows.
//
// Classification per row:
//   changed   - matched an existing entry (by id, or by produzent+typ for
//               rows without id) and differs; carries the field diff
//   new       - no match; will be created
//   unchanged - matched and identical; skipped on apply
//   conflict  - id given but unknown, or ambiguous produzent+typ match;
//               skipped on apply
// The staged rows are stored under a token; import-apply.php applies
// exactly what was previewed.  Imports never delete entries.

require_once __DIR__ . '/../../lib/db.php';

$user = current_user();
$body = read_json_body();
$rows = $body['rows'] ?? null;
if (!is_array($rows) || !$rows) {
    json_error('Keine Datenzeilen im Import', 400);
}
if (count($rows) > 20000) {
    json_error('Import zu groß', 400);
}
$columns = array_values(array_intersect(
    (array)($body['columns'] ?? disk_fields()), disk_fields()));
if (!$columns) {
    json_error('Keine bekannten Spalten im Import', 400);
}
$pick = fn(array $raw) => array_intersect_key($raw, array_flip($columns));

// index existing entries for matching without id
$byKey = [];
$res = db()->query(
    'SELECT id, produzent, typ FROM disk WHERE deleted = 0');
while ($r = $res->fetchArray(SQLITE3_ASSOC)) {
    $key = mb_strtolower(trim($r['produzent']) . '|' . trim($r['typ']));
    $byKey[$key][] = $r['id'];
}

$classified = [];
$counts = ['new' => 0, 'changed' => 0, 'unchanged' => 0, 'conflict' => 0];
foreach ($rows as $i => $raw) {
    if (!is_array($raw)) {
        json_error('Ungültige Zeile im Import', 400);
    }
    $line = $i + 2;  // 1-based plus header row, for user-facing messages
    $p = trim((string)($raw['produzent'] ?? ''));
    $t = trim((string)($raw['typ'] ?? ''));
    $conflict = function (string $message) use (&$classified, &$counts,
                                                $line, $p, $t) {
        $classified[] = ['status' => 'conflict', 'line' => $line,
            'message' => $message, 'produzent' => $p, 'typ' => $t];
        $counts['conflict']++;
    };

    $id = isset($raw['id']) && $raw['id'] !== '' ? (int)$raw['id'] : null;
    if ($id === null && $p !== '' && $t !== '') {
        $matches = $byKey[mb_strtolower("$p|$t")] ?? [];
        if (count($matches) > 1) {
            $conflict('Hersteller/Typ nicht eindeutig');
            continue;
        }
        $id = $matches[0] ?? null;
    }

    try {
        if ($id === null) {
            $data = normalize_disk($pick($raw));
            $classified[] = ['status' => 'new', 'line' => $line,
                'data' => $data,
                'produzent' => $data['produzent'], 'typ' => $data['typ']];
            $counts['new']++;
            continue;
        }

        $existing = fetch_disk($id);
        if (!$existing) {
            $conflict("Unbekannte Id $id");
            continue;
        }
        $old = disk_data($existing);
        $data = normalize_disk(array_merge($old, $pick($raw)));
    } catch (InvalidArgumentException $e) {
        $conflict($e->getMessage());
        continue;
    }
    $diff = disk_diff($old, $data);
    if (!$diff) {
        $classified[] = ['status' => 'unchanged', 'line' => $line,
            'id' => $id];
        $counts['unchanged']++;
    } else {
        $classified[] = ['status' => 'changed', 'line' => $line, 'id' => $id,
            'data' => $data, 'diff' => $diff,
            'produzent' => $data['produzent'], 'typ' => $data['typ']];
        $counts['changed']++;
    }
}

$token = bin2hex(random_bytes(16));
db()->exec('BEGIN IMMEDIATE');
db()->exec("DELETE FROM import_preview
            WHERE created_at < datetime('now', '-1 day')");
$stmt = db()->prepare(
    'INSERT INTO import_preview (token, created_at, by, by_name, filename,
                                 data)
     VALUES (:token, :at, :by, :by_name, :filename, :data)');
$stmt->bindValue(':token', $token);
$stmt->bindValue(':at', now_iso());
$stmt->bindValue(':by', $user['id'], SQLITE3_INTEGER);
$stmt->bindValue(':by_name', $user['name']);
$stmt->bindValue(':filename', (string)($body['filename'] ?? ''));
$stmt->bindValue(':data', json_encode($classified, JSON_UNESCAPED_UNICODE));
$stmt->execute();
db()->exec('COMMIT');

// the full row data stays server-side; the client gets what it displays
$display = array_map(function ($row) {
    unset($row['data']);
    return $row;
}, array_values(array_filter($classified,
    fn($r) => $r['status'] !== 'unchanged')));

json_response([
    'token' => $token,
    'counts' => $counts,
    'rows' => $display,
]);
