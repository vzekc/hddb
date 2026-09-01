-- Schema for the editable Festplattendatenbank.
-- Applied by bin/init-db.php on an empty database.

CREATE TABLE disk (
    id INTEGER PRIMARY KEY,
    produzent TEXT NOT NULL,
    modell TEXT,              -- Modellbezeichnung (e.g. "Elite", "Bigfoot")
    typ TEXT NOT NULL,
    cap INTEGER,              -- advertised capacity in MB
    seek INTEGER,             -- average seek time in ms
    inch TEXT,                -- form factor diameter (5.25, 3.5, ...)
    hoehe REAL,               -- drive height in mm (82.6 = full height,
                              -- 41.3 = half height)
    cyl INTEGER, hds INTEGER, sec INTEGER,        -- physical geometry
    cyl_l INTEGER, hds_l INTEGER, sec_l INTEGER,  -- logical geometry
    pre INTEGER,              -- write precompensation start cylinder
    lnd INTEGER,              -- landing zone cylinder
    controller TEXT,          -- interface
    mtbf INTEGER,             -- mean time between failures, in 1000 h
    lift TEXT,                -- auto-park flag
    date TEXT,                -- original entry date (ISO)
    notiz TEXT,
    deleted INTEGER NOT NULL DEFAULT 0,
    updated_at TEXT,          -- ISO timestamp of last edit
    updated_by INTEGER,       -- forum user id
    updated_by_name TEXT
);

CREATE INDEX disk_produzent ON disk (produzent, typ);

-- One row per change to one disk entry.  old_data/new_data hold the
-- editable fields as JSON; creates have old_data NULL, deletes new_data
-- NULL.  Imports link their rows via batch_id.
CREATE TABLE revision (
    id INTEGER PRIMARY KEY,
    disk_id INTEGER NOT NULL,
    action TEXT NOT NULL,     -- create | update | delete | import
    changed_at TEXT NOT NULL,
    changed_by INTEGER NOT NULL,
    changed_by_name TEXT NOT NULL,
    old_data TEXT,
    new_data TEXT,
    batch_id INTEGER REFERENCES batch (id)
);

CREATE INDEX revision_disk ON revision (disk_id, id);

CREATE TABLE batch (
    id INTEGER PRIMARY KEY,
    imported_at TEXT NOT NULL,
    by INTEGER NOT NULL,
    by_name TEXT NOT NULL,
    filename TEXT,
    created INTEGER NOT NULL,
    updated INTEGER NOT NULL
);

-- Staged import previews; applied or expired previews are cleaned up.
CREATE TABLE import_preview (
    token TEXT PRIMARY KEY,
    created_at TEXT NOT NULL,
    by INTEGER NOT NULL,
    by_name TEXT NOT NULL,
    filename TEXT,
    data TEXT NOT NULL        -- JSON: classified rows as shown to the user
);

-- Read-only reference: BIOS drive type tables.
CREATE TABLE biostyp (
    bios TEXT NOT NULL,
    typ INTEGER NOT NULL,
    tracks INTEGER, heads INTEGER, sektors_tr INTEGER,
    pre INTEGER, landing INTEGER, sektors_al INTEGER
);
