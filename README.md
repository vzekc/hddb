# Festplattendatenbank (hard disk database)

A 1990s hard disk parameter database — "Festplattendatenbank V1.25,
A. Schneider 11/93, 5/95" — originally a dBASE III application for DOS.
The original files (DBF tables and the dBASE .PRG programs) live
unmodified in `orig/`. `convert.py` converts the tables to `hddb.sqlite`
and per-table CSV files in `csv/`.

## Converting

```
python3 convert.py
```

Regenerates `hddb.sqlite` and `csv/` from `orig/`. No dependencies beyond
the Python 3 standard library.

```
python3 build_html.py
```

Regenerates `hddb.html`, a standalone browser search interface with the
data embedded — open it directly in any browser, no server needed. The
search tolerates missing hyphens and one typo per word ("conner cp 340"
finds the CP-340), and the BIOS type tables are on a second tab.

## Tables

### diskbank — 2103 hard disk models

One row per drive model, with the CHS geometry needed to set up the drive
in a PC BIOS or on an MFM/RLL/SCSI controller.

| column     | meaning                                                        |
|------------|----------------------------------------------------------------|
| produzent  | manufacturer                                                   |
| typ        | model designation                                              |
| cap        | capacity in MB as advertised by the manufacturer               |
| seek       | average seek time in ms                                        |
| inch       | form factor (3.5, 5.25, …)                                     |
| cyl, hds, sec | physical geometry: cylinders, heads, sectors per track      |
| cyl_l, hds_l, sec_l | logical (translated) geometry, where it differs      |
| pre        | write precompensation start cylinder                           |
| lnd        | landing zone cylinder                                          |
| controller | interface: MFM, RLL, AT (IDE), SCSI, ESDI, …                   |
| mtbf       | mean time between failures, in 1000 hours                      |
| lift       | auto-park flag                                                 |
| date       | entry date                                                     |
| notiz      | free-text note (dBASE memo field)                              |
| sektors_al | total sector count (cyl × hds × sec)                           |
| mb         | computed capacity: sektors_al × 512 / 2²⁰                      |
| satz       | original record number                                         |

### herst — 99 manufacturer names

The manufacturer list backing the search menu.

### biostyp / secall / bios — BIOS drive type tables, 225 entries

The fixed drive type tables (type 1–47) of common BIOSes (IBM, AMI, AWARD,
PHOENIX, COMPAQ, HP, IBM PS/2), used to pick the drive type whose geometry
best fits an AT-bus disk. Columns: `bios` (BIOS vendor), `typ` (drive type
number), `tracks`, `heads`, `sektors_tr` (sectors per track), `pre`
(precompensation), `landing` (landing zone), `sektors_al` (total sectors).

`biostyp` and `secall` contain identical data in different file sort
orders (the DOS app used `secall` for its by-size search); `bios` is the
same set minus two rows. Use `biostyp` as the canonical table.

The source files carry the IBM types 1–14 twice, entered on two different
dates with identical geometry; the conversion keeps one row each.

## Conversion notes

- Character encoding of the source files is cp437; the data itself is
  almost entirely ASCII.
- dBASE numeric blanks become SQL `NULL`; dates become ISO `YYYY-MM-DD`
  text.
- Precompensation 65535 is the source's sentinel for "no precompensation"
  and becomes `NULL`.
- The `DISKBANK.DBT` memo file contains a single referenced memo
  (`notiz` = "scsi" on one Fujitsu drive); the rest of the file is empty
  filler.
- Records flagged as deleted in the DBF files are skipped (none were).
