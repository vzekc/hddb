<?php
// Create and seed the live database.
//
//   php init-db.php <seed.sqlite>
//
// Creates <data dir>/hddb.sqlite (data dir from HDDB_DATA_DIR, default
// ../../data) with the schema from lib/schema.sql and copies the diskbank
// and biostyp tables from the given seed database (the repository's
// hddb.sqlite as produced by convert.py).  Refuses to run if the target
// already exists.

require_once __DIR__ . '/../lib/db.php';

if ($argc !== 2) {
    fwrite(STDERR, "usage: php init-db.php <seed.sqlite>\n");
    exit(1);
}
$seedPath = $argv[1];
$target = data_dir() . '/hddb.sqlite';
if (file_exists($target)) {
    fwrite(STDERR, "$target already exists, not touching it\n");
    exit(1);
}
if (!is_dir(data_dir())) {
    mkdir(data_dir(), 0775, true);
}

$db = new SQLite3($target);
$db->enableExceptions(true);
$db->exec(file_get_contents(__DIR__ . '/../lib/schema.sql'));

$seed = new SQLite3($seedPath, SQLITE3_OPEN_READONLY);
$seed->enableExceptions(true);

$db->exec('BEGIN');

$fields = disk_fields();
$params = implode(', ', array_map(fn($f) => ":$f", $fields));
$insert = $db->prepare(
    'INSERT INTO disk (' . implode(', ', $fields) . ") VALUES ($params)");
$rows = $seed->query('SELECT * FROM diskbank');
$n = 0;
while ($row = $rows->fetchArray(SQLITE3_ASSOC)) {
    [$row['inch'], $row['hoehe']] = decode_inch($row['inch']);
    foreach ($fields as $f) {
        // the seed has no modell column; other missing fields stay null,
        // except the required name fields, which become empty strings
        $v = $row[$f] ?? null;
        if ($v === null && ($f === 'produzent' || $f === 'typ')) {
            $v = '';
        }
        $insert->bindValue(":$f", $v);
    }
    $insert->execute();
    $insert->reset();
    $n++;
}

$insertBios = $db->prepare(
    'INSERT INTO biostyp (bios, typ, tracks, heads, sektors_tr, pre,
                          landing, sektors_al)
     VALUES (:bios, :typ, :tracks, :heads, :sektors_tr, :pre, :landing,
             :sektors_al)');
$rows = $seed->query('SELECT * FROM biostyp');
$b = 0;
while ($row = $rows->fetchArray(SQLITE3_ASSOC)) {
    foreach (['bios', 'typ', 'tracks', 'heads', 'sektors_tr', 'pre',
              'landing', 'sektors_al'] as $f) {
        $insertBios->bindValue(":$f", $row[$f]);
    }
    $insertBios->execute();
    $insertBios->reset();
    $b++;
}

$db->exec('COMMIT');
echo "created $target: $n disks, $b BIOS types\n";
