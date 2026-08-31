#!/usr/bin/env python3
"""Convert the dBASE III Festplattendatenbank in orig/ to SQLite and CSV.

Reads the .DBF tables (and the .DBT memo file for DISKBANK) and writes
hddb.sqlite plus one CSV per table into csv/.
"""

import csv
import datetime
import pathlib
import sqlite3
import struct

ORIG = pathlib.Path(__file__).parent / "orig"
OUT_DB = pathlib.Path(__file__).parent / "hddb.sqlite"
OUT_CSV = pathlib.Path(__file__).parent / "csv"
ENCODING = "cp437"  # DOS-era code page used by dBASE III


def read_memo_file(path):
    """Return {block_number: text} from a dBASE III .DBT file."""
    data = path.read_bytes()
    memos = {}
    block = 1
    while block * 512 < len(data):
        chunk = data[block * 512:]
        end = chunk.find(b"\x1a\x1a")
        if end == -1:
            end = chunk.find(b"\x1a")
        if end == -1:
            end = len(chunk)
        text = chunk[:end].decode(ENCODING, errors="replace")
        memos[block] = text.replace("\r\n", "\n").strip()
        # next memo starts at the next 512-byte boundary after this one
        block += (end + 2 + 511) // 512 or 1
    return memos


def read_dbf(path, memos=None):
    """Parse a .DBF file; returns (fields, rows).

    fields: list of (name, type, length, decimals)
    rows: list of dicts with typed Python values
    """
    data = path.read_bytes()
    nrec, hlen, rlen = struct.unpack("<IHH", data[4:12])

    fields = []
    off = 32
    while data[off] != 0x0D:
        name = data[off:off + 11].split(b"\0")[0].decode("ascii")
        ftype = chr(data[off + 11])
        flen = data[off + 16]
        fdec = data[off + 17]
        fields.append((name, ftype, flen, fdec))
        off += 32

    rows = []
    for i in range(nrec):
        rec = data[hlen + i * rlen: hlen + (i + 1) * rlen]
        if len(rec) < rlen or rec[0:1] == b"*":  # deleted record
            continue
        row = {}
        pos = 1
        for name, ftype, flen, fdec in fields:
            raw = rec[pos:pos + flen]
            pos += flen
            text = raw.decode(ENCODING, errors="replace").strip()
            if ftype == "N":
                if not text:
                    value = None
                elif fdec:
                    value = float(text)
                else:
                    try:
                        value = int(text)
                    except ValueError:
                        value = None
            elif ftype == "D":
                try:
                    value = datetime.date(int(text[0:4]), int(text[4:6]),
                                          int(text[6:8])).isoformat()
                except ValueError:
                    value = None
            elif ftype == "M":
                value = memos.get(int(text)) if text and memos else None
            else:
                value = text or None
            row[name] = value
        rows.append(row)
    return fields, rows


def sql_type(ftype, fdec):
    if ftype == "N":
        return "REAL" if fdec else "INTEGER"
    return "TEXT"


def dedupe_ignoring_date(rows):
    """Drop rows that duplicate an earlier row in every column except DATE.

    The BIOS type tables carry the IBM types 1-14 twice, entered on two
    different dates with identical geometry; keep the first occurrence.
    A blank numeric field counts as 0 (blank and 0 precompensation both
    mean none).
    """
    seen = set()
    out = []
    for r in rows:
        key = tuple(0 if v is None else v
                    for k, v in r.items() if k != "DATE")
        if key not in seen:
            seen.add(key)
            out.append(r)
    return out


def main():
    memos = read_memo_file(ORIG / "DISKBANK.DBT")

    tables = {
        "diskbank": ("DISKBANK.DBF", memos),
        "herst": ("HERST.DBF", None),
        "bios": ("BIOS.DBF", None),
        "biostyp": ("BIOSTYP.DBF", None),
        "secall": ("SECALL.DBF", None),
    }

    OUT_DB.unlink(missing_ok=True)
    OUT_CSV.mkdir(exist_ok=True)
    con = sqlite3.connect(OUT_DB)

    for table, (fn, memo_map) in tables.items():
        fields, rows = read_dbf(ORIG / fn, memo_map)
        for r in rows:  # 65535 is the sentinel for "no precompensation"
            if r.get("PRE") == 65535:
                r["PRE"] = None
        if table in ("bios", "biostyp", "secall"):
            rows = dedupe_ignoring_date(rows)
        colnames = [name.lower() for name, *_ in fields]
        coldefs = ", ".join(
            f"{name.lower()} {sql_type(ftype, fdec)}"
            for name, ftype, flen, fdec in fields)
        con.execute(f"CREATE TABLE {table} ({coldefs})")
        con.executemany(
            f"INSERT INTO {table} VALUES ({','.join('?' * len(fields))})",
            [tuple(r[name] for name, *_ in fields) for r in rows])

        with open(OUT_CSV / f"{table}.csv", "w", newline="") as f:
            w = csv.writer(f)
            w.writerow(colnames)
            for r in rows:
                w.writerow(tuple(r[name] for name, *_ in fields))

        print(f"{table}: {len(rows)} rows")

    con.commit()
    con.close()
    print(f"wrote {OUT_DB} and {OUT_CSV}/")


if __name__ == "__main__":
    main()
