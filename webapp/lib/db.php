<?php
// Shared helpers for the hddb PHP endpoints: database access, field
// definitions, JSON responses, authentication info, revision logging.

const DISK_TEXT_FIELDS = ['produzent', 'modell', 'typ', 'inch', 'controller',
                          'lift', 'date', 'notiz'];
const DISK_INT_FIELDS = ['cap', 'seek', 'cyl', 'hds', 'sec', 'cyl_l',
                         'hds_l', 'sec_l', 'pre', 'lnd', 'mtbf'];
const DISK_FLOAT_FIELDS = ['hoehe'];

function disk_fields(): array
{
    return array_merge(DISK_TEXT_FIELDS, DISK_INT_FIELDS,
                       DISK_FLOAT_FIELDS);
}

function data_dir(): string
{
    return getenv('HDDB_DATA_DIR') ?: dirname(__DIR__) . '/data';
}

function db(): SQLite3
{
    static $db = null;
    if ($db === null) {
        $db = new SQLite3(data_dir() . '/hddb.sqlite');
        $db->busyTimeout(5000);
        $db->exec('PRAGMA journal_mode = WAL');
        $db->exec('PRAGMA foreign_keys = ON');
        $db->enableExceptions(true);
    }
    return $db;
}

function json_response($data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function json_error(string $message, int $status): never
{
    json_response(['error' => $message], $status);
}

function read_json_body(): array
{
    $body = json_decode(file_get_contents('php://input'), true);
    if (!is_array($body)) {
        json_error('Ungültiger JSON-Request', 400);
    }
    return $body;
}

// Identity as established by mod_auth_openidc.  The forum delivers the
// numeric user id as `sub`; the nickname claim is used when the forum
// sends it, otherwise an optional local resolver (username.php, deployed
// only on the server) may map the id to a name.
function current_user(): array
{
    $sub = $_SERVER['OIDC_CLAIM_sub'] ?? getenv('OIDC_CLAIM_sub');
    if (!$sub) {
        json_error('Nicht angemeldet', 401);
    }
    $name = $_SERVER['OIDC_CLAIM_nickname'] ?? getenv('OIDC_CLAIM_nickname');
    if (!$name || $name === 'null') {
        $resolver = __DIR__ . '/username.php';
        if (is_file($resolver)) {
            require_once $resolver;
            $name = resolve_username((int)$sub);
        }
    }
    return ['id' => (int)$sub, 'name' => $name ?: "Mitglied #$sub"];
}

function now_iso(): string
{
    return gmdate('Y-m-d\TH:i:s\Z');
}

// Editable field values of a disk row, normalized for storage/comparison:
// text trimmed with '' → null, integers cast, unknown keys dropped.
// Throws InvalidArgumentException on invalid values.
function normalize_disk(array $input): array
{
    $out = [];
    foreach (DISK_TEXT_FIELDS as $f) {
        $v = isset($input[$f]) ? trim((string)$input[$f]) : '';
        $out[$f] = $v === '' ? null : $v;
    }
    foreach (DISK_INT_FIELDS as $f) {
        $v = $input[$f] ?? null;
        if (is_string($v)) {
            $v = trim($v);
        }
        if ($v === null || $v === '') {
            $out[$f] = null;
        } elseif (is_numeric($v)) {
            $out[$f] = (int)$v;
        } else {
            throw new InvalidArgumentException("Feld $f muss eine Zahl sein");
        }
    }
    foreach (DISK_FLOAT_FIELDS as $f) {
        $v = $input[$f] ?? null;
        if (is_string($v)) {
            $v = str_replace(',', '.', trim($v));  // 12,7 → 12.7
        }
        if ($v === null || $v === '') {
            $out[$f] = null;
        } elseif (is_numeric($v)) {
            $out[$f] = (float)$v;
        } else {
            throw new InvalidArgumentException("Feld $f muss eine Zahl sein");
        }
    }
    if ($out['produzent'] === null || $out['typ'] === null) {
        throw new InvalidArgumentException(
            'Hersteller und Typ sind Pflichtfelder');
    }
    return $out;
}

function fetch_disk(int $id): ?array
{
    $stmt = db()->prepare('SELECT * FROM disk WHERE id = :id AND deleted = 0');
    $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
    $row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
    return $row ?: null;
}

function disk_data(array $row): array
{
    return array_intersect_key($row, array_flip(disk_fields()));
}

// Insert a revision row; caller manages the surrounding transaction.
function log_revision(int $diskId, string $action, array $user,
                      ?array $old, ?array $new, ?int $batchId = null): void
{
    $stmt = db()->prepare(
        'INSERT INTO revision (disk_id, action, changed_at, changed_by,
                               changed_by_name, old_data, new_data, batch_id)
         VALUES (:disk_id, :action, :at, :by, :by_name, :old, :new, :batch)');
    $stmt->bindValue(':disk_id', $diskId, SQLITE3_INTEGER);
    $stmt->bindValue(':action', $action);
    $stmt->bindValue(':at', now_iso());
    $stmt->bindValue(':by', $user['id'], SQLITE3_INTEGER);
    $stmt->bindValue(':by_name', $user['name']);
    $stmt->bindValue(':old', $old === null ? null :
        json_encode($old, JSON_UNESCAPED_UNICODE));
    $stmt->bindValue(':new', $new === null ? null :
        json_encode($new, JSON_UNESCAPED_UNICODE));
    $stmt->bindValue(':batch', $batchId, SQLITE3_INTEGER);
    $stmt->execute();
}

// Write the editable fields plus edit metadata to a disk row.
function write_disk(int $id, array $data, array $user): void
{
    $sets = [];
    foreach (disk_fields() as $f) {
        $sets[] = "$f = :$f";
    }
    $stmt = db()->prepare(
        'UPDATE disk SET ' . implode(', ', $sets) .
        ', updated_at = :updated_at, updated_by = :updated_by,
           updated_by_name = :updated_by_name WHERE id = :id');
    foreach (DISK_TEXT_FIELDS as $f) {
        $stmt->bindValue(":$f", $data[$f]);
    }
    foreach (DISK_INT_FIELDS as $f) {
        $stmt->bindValue(":$f", $data[$f], SQLITE3_INTEGER);
    }
    foreach (DISK_FLOAT_FIELDS as $f) {
        $stmt->bindValue(":$f", $data[$f], SQLITE3_FLOAT);
    }
    $stmt->bindValue(':updated_at', now_iso());
    $stmt->bindValue(':updated_by', $user['id'], SQLITE3_INTEGER);
    $stmt->bindValue(':updated_by_name', $user['name']);
    $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
    $stmt->execute();
}

function insert_disk(array $data, array $user): int
{
    $fields = disk_fields();
    $cols = implode(', ', $fields);
    $params = implode(', ', array_map(fn($f) => ":$f", $fields));
    $stmt = db()->prepare(
        "INSERT INTO disk ($cols, updated_at, updated_by, updated_by_name)
         VALUES ($params, :updated_at, :updated_by, :updated_by_name)");
    foreach (DISK_TEXT_FIELDS as $f) {
        $stmt->bindValue(":$f", $data[$f]);
    }
    foreach (DISK_INT_FIELDS as $f) {
        $stmt->bindValue(":$f", $data[$f], SQLITE3_INTEGER);
    }
    foreach (DISK_FLOAT_FIELDS as $f) {
        $stmt->bindValue(":$f", $data[$f], SQLITE3_FLOAT);
    }
    $stmt->bindValue(':updated_at', now_iso());
    $stmt->bindValue(':updated_by', $user['id'], SQLITE3_INTEGER);
    $stmt->bindValue(':updated_by_name', $user['name']);
    $stmt->execute();
    return db()->lastInsertRowID();
}

// The dBASE inch field combined the form-factor diameter with height
// codes (F/FH = full height, H/HH = half height, S = slimline) in three
// characters.  Splits such a value into a clean diameter and the height
// in millimeters; returns [inch, hoehe] with hoehe null when the source
// does not state a height.
function decode_inch(?string $inch): array
{
    return match ($inch) {
        '5F', 'FH5' => ['5.25', 82.6],
        '5H', 'HH5', '5HH' => ['5.25', 41.3],
        '3H' => ['3.5', 41.3],
        '3S' => ['3.5', 25.4],
        '5', '5.2' => ['5.25', null],
        '3' => ['3.5', null],
        default => [$inch, null],
    };
}

// Field-by-field difference between two normalized data arrays.
function disk_diff(?array $old, ?array $new): array
{
    $diff = [];
    foreach (disk_fields() as $f) {
        $o = $old[$f] ?? null;
        $n = $new[$f] ?? null;
        if ($o !== $n) {
            $diff[] = ['field' => $f, 'old' => $o, 'new' => $n];
        }
    }
    return $diff;
}
