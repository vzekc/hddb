<?php
// GET: all disks and BIOS types as JSON.  The ETag is derived from the
// latest revision id, so unchanged data answers 304.

require_once __DIR__ . '/../../lib/db.php';

$maxRev = db()->querySingle('SELECT COALESCE(MAX(id), 0) FROM revision');
$etag = "\"rev-$maxRev\"";
header('ETag: ' . $etag);
header('Cache-Control: no-cache');
if (($_SERVER['HTTP_IF_NONE_MATCH'] ?? '') === $etag) {
    http_response_code(304);
    exit;
}

$disks = [];
$rows = db()->query(
    'SELECT id, ' . implode(', ', disk_fields()) .
    ', updated_at, updated_by_name FROM disk WHERE deleted = 0
     ORDER BY produzent, typ');
while ($row = $rows->fetchArray(SQLITE3_ASSOC)) {
    $disks[] = $row;
}

$bios = [];
$rows = db()->query('SELECT * FROM biostyp ORDER BY bios, typ');
while ($row = $rows->fetchArray(SQLITE3_ASSOC)) {
    $bios[] = $row;
}

json_response(['disks' => $disks, 'bios' => $bios]);
