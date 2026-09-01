<?php
// GET: CSV export of all disks.  Includes the id column so an edited file
// can be re-imported with exact row matching.  UTF-8 with BOM so Excel
// detects the encoding.

require_once __DIR__ . '/../../lib/db.php';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="hddb-export.csv"');

$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF");
$columns = array_merge(['id'], disk_fields());
fputcsv($out, $columns, ',', '"', '');

$rows = db()->query(
    'SELECT ' . implode(', ', $columns) .
    ' FROM disk WHERE deleted = 0 ORDER BY produzent, typ');
while ($row = $rows->fetchArray(SQLITE3_ASSOC)) {
    fputcsv($out, array_map(fn($c) => $row[$c], $columns), ',', '"', '');
}
fclose($out);
