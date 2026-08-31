#!/usr/bin/env python3
"""Generate index.html, a standalone browser search interface for hddb.sqlite.

Embeds the diskbank and biostyp tables as JSON in a single HTML file with a
fuzzy search over manufacturer + model. Run convert.py first.
"""

import json
import pathlib
import sqlite3

HERE = pathlib.Path(__file__).parent
OUT = HERE / "index.html"

DISK_COLS = ["produzent", "typ", "cap", "seek", "inch", "cyl", "hds", "sec",
             "cyl_l", "hds_l", "sec_l", "pre", "lnd", "controller", "mtbf",
             "lift", "date", "notiz", "mb"]
BIOS_COLS = ["bios", "typ", "tracks", "heads", "sektors_tr", "pre", "landing",
             "sektors_al"]


def fetch(con, table, cols):
    rows = con.execute(
        f"SELECT {', '.join(cols)} FROM {table} ORDER BY 1, 2").fetchall()
    return [list(r) for r in rows]


def main():
    con = sqlite3.connect(HERE / "hddb.sqlite")
    disks = fetch(con, "diskbank", DISK_COLS)
    bios = fetch(con, "biostyp", BIOS_COLS)
    con.close()

    # <-escape so the JSON can never terminate the script tag
    def js(obj):
        return json.dumps(obj, ensure_ascii=False,
                          separators=(",", ":")).replace("<", "\\u003c")

    html = TEMPLATE.replace("/*DISKS*/", js(disks)) \
                   .replace("/*BIOS*/", js(bios))
    OUT.write_text(html, encoding="utf-8")
    print(f"wrote {OUT} ({OUT.stat().st_size // 1024} KB, "
          f"{len(disks)} disks, {len(bios)} BIOS types)")


TEMPLATE = r"""<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Festplattendatenbank</title>
<style>
:root {
  color-scheme: light dark;
  --bg: #f6f5f2; --panel: #ffffff; --ink: #1d1d1f; --muted: #6e6e73;
  --line: #e3e2de; --accent: #0a6e4f; --accent-ink: #ffffff;
  --hit: #fff3c4; --head: #f0efec;
}
@media (prefers-color-scheme: dark) {
  :root {
    --bg: #1a1b1e; --panel: #232428; --ink: #e8e8ea; --muted: #9a9aa1;
    --line: #35363b; --accent: #34c48e; --accent-ink: #10241c;
    --hit: #4a4020; --head: #2a2b30;
  }
}
* { box-sizing: border-box; }
body {
  margin: 0; background: var(--bg); color: var(--ink);
  font: 14px/1.45 ui-sans-serif, system-ui, "Segoe UI", sans-serif;
}
header { max-width: 1100px; margin: 0 auto; padding: 28px 20px 0; }
h1 { font-size: 22px; margin: 0 0 2px; letter-spacing: -0.01em; }
h1 small { font-weight: 400; color: var(--muted); font-size: 13px; margin-left: 8px; }
main { max-width: 1100px; margin: 0 auto; padding: 14px 20px 40px; }
.controls { display: flex; gap: 10px; flex-wrap: wrap; margin: 14px 0 6px; }
#q {
  flex: 1 1 260px; padding: 9px 12px; font-size: 15px; color: var(--ink);
  background: var(--panel); border: 1px solid var(--line); border-radius: 8px;
}
#q:focus { outline: 2px solid var(--accent); outline-offset: -1px; }
select {
  padding: 8px 10px; font-size: 14px; color: var(--ink);
  background: var(--panel); border: 1px solid var(--line); border-radius: 8px;
}
.tabs { display: flex; gap: 6px; margin-top: 18px; }
.tabs button {
  padding: 7px 14px; font-size: 14px; border: 1px solid var(--line);
  border-radius: 8px; background: var(--panel); color: var(--ink); cursor: pointer;
}
.tabs button[aria-selected="true"] {
  background: var(--accent); color: var(--accent-ink); border-color: var(--accent);
}
#count { color: var(--muted); font-size: 13px; margin: 6px 2px 10px; }
.tablewrap {
  overflow-x: auto; background: var(--panel);
  border: 1px solid var(--line); border-radius: 10px;
}
table { border-collapse: collapse; width: 100%; white-space: nowrap; }
th, td { padding: 6px 12px; text-align: left; border-bottom: 1px solid var(--line); }
td.num, th.num { text-align: right; font-variant-numeric: tabular-nums; }
th {
  position: sticky; top: 0; background: var(--head); font-weight: 600;
  font-size: 12px; text-transform: uppercase; letter-spacing: 0.04em;
  color: var(--muted);
}
tr:last-child td { border-bottom: none; }
tbody tr:hover td { background: color-mix(in srgb, var(--accent) 7%, transparent); }
mark { background: var(--hit); color: inherit; border-radius: 2px; padding: 0 1px; }
.more {
  margin: 10px 2px; color: var(--muted); font-size: 13px;
}
footer { max-width: 1100px; margin: 0 auto; padding: 0 20px 30px; color: var(--muted); font-size: 12px; }
[hidden] { display: none !important; }
</style>
</head>
<body>
<header>
  <h1>Festplattendatenbank <small>V1.25 · A. Schneider 1993–1995 · 2103 Laufwerke</small></h1>
</header>
<main>
  <div class="tabs" role="tablist">
    <button id="tab-disks" role="tab" aria-selected="true">Festplatten</button>
    <button id="tab-bios" role="tab" aria-selected="false">BIOS-Typen</button>
  </div>

  <section id="pane-disks">
    <div class="controls">
      <input id="q" type="search" placeholder="Suche, z.B. »conner cp 340« oder »maxtor 7120«…" autofocus>
    </div>
    <div id="count"></div>
    <div class="tablewrap"><table>
      <thead><tr>
        <th>Hersteller</th><th>Modell</th>
        <th class="num">MB</th><th>Zoll</th>
        <th class="num">Zyl</th><th class="num">Köpfe</th><th class="num">Sek</th>
        <th>CHS logisch</th>
        <th>Interface</th><th class="num">Seek&nbsp;ms</th>
        <th class="num">Precomp</th><th class="num">Landezone</th>
        <th class="num">MTBF&nbsp;kh</th><th>Datum</th><th>Notiz</th>
      </tr></thead>
      <tbody id="rows"></tbody>
    </table></div>
    <div class="more" id="more" hidden></div>
  </section>

  <section id="pane-bios" hidden>
    <div class="controls">
      <select id="vendor"></select>
    </div>
    <div class="tablewrap"><table>
      <thead><tr>
        <th>BIOS</th><th class="num">Typ</th>
        <th class="num">Zylinder</th><th class="num">Köpfe</th><th class="num">Sek/Spur</th>
        <th class="num">Precomp</th><th class="num">Landezone</th>
        <th class="num">MB</th>
      </tr></thead>
      <tbody id="biosrows"></tbody>
    </table></div>
  </section>
</main>
<footer>
  Konvertiert aus der dBASE-III-Originaldatenbank. MB = Zylinder × Köpfe × Sektoren × 512 / 2²⁰.
  Suche toleriert fehlende Bindestriche und einen Tippfehler pro Wort.
</footer>

<script>
const DISKS = /*DISKS*/;
const BIOS = /*BIOS*/;

// diskbank column indices
const P=0, T=1, CAP=2, SEEK=3, INCH=4, CYL=5, HDS=6, SEC=7,
      CYL_L=8, HDS_L=9, SEC_L=10, PRE=11, LND=12, CTRL=13, MTBF=14,
      LIFT=15, DATE=16, NOTIZ=17, MBC=18;

const squash = s => s.toLowerCase().replace(/[^a-z0-9]+/g, "");

// Precompute the searchable key for every drive: manufacturer + model, squashed.
const KEYS = DISKS.map(r => squash((r[P] || "") + " " + (r[T] || "")));

// Approximate substring search: position where `pat` matches inside `text`
// with at most one edit (insert/delete/substitute), or -1. Sellers algorithm.
function fuzzyIndexOf(text, pat) {
  const m = pat.length;
  if (!m || m > text.length + 1) return -1;
  let prev = Array.from({length: m + 1}, (_, i) => i);
  for (let j = 1; j <= text.length; j++) {
    const cur = [0];
    const tc = text[j - 1];
    for (let i = 1; i <= m; i++) {
      cur[i] = Math.min(
        prev[i - 1] + (pat[i - 1] === tc ? 0 : 1),
        prev[i] + 1,
        cur[i - 1] + 1);
    }
    if (cur[m] <= 1) return Math.max(0, j - m);
    prev = cur;
  }
  return -1;
}

// Score a record key against the query; -1 = no match, higher = better.
function scoreKey(key, qSquash, qTokens) {
  const whole = key.indexOf(qSquash);
  if (whole >= 0)
    return 2000 - whole * 2 - (key.length - qSquash.length);
  let total = 0, lastPos = -1, ordered = true;
  for (const t of qTokens) {
    let i = key.indexOf(t), exact = true;
    if (i < 0 && t.length >= 3) { i = fuzzyIndexOf(key, t); exact = false; }
    if (i < 0) return -1;
    total += (exact ? 100 : 40) - i;
    if (i < lastPos) ordered = false;
    lastPos = i;
  }
  return total + (ordered ? 200 : 0) - key.length;
}

function search(query) {
  const qSquash = squash(query);
  const qTokens = query.toLowerCase().split(/\s+/).map(squash).filter(Boolean);
  const out = [];
  for (let i = 0; i < DISKS.length; i++) {
    if (!qSquash) { out.push([0, i]); continue; }
    const s = scoreKey(KEYS[i], qSquash, qTokens);
    if (s >= 0) out.push([s, i]);
  }
  if (qSquash) out.sort((a, b) => b[0] - a[0]);
  return out.map(x => x[1]);
}

const esc = s => String(s).replace(/[&<>"]/g,
  c => ({"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;"}[c]));

// Wrap the parts of `text` whose squashed characters are covered by the
// query tokens in <mark>. Works per character via the squash mapping.
function highlight(text, qTokens) {
  if (!qTokens.length || !text) return esc(text ?? "");
  const chars = [...text];
  const sq = [], map = [];
  chars.forEach((c, i) => {
    const s = c.toLowerCase().replace(/[^a-z0-9]/g, "");
    if (s) { sq.push(s); map.push(i); }
  });
  const sqs = sq.join("");
  const hit = new Array(chars.length).fill(false);
  for (const t of qTokens) {
    let from = 0, i;
    while ((i = sqs.indexOf(t, from)) >= 0) {
      for (let k = i; k < i + t.length; k++) hit[map[k]] = true;
      from = i + 1;
    }
  }
  return chars.map((c, i) => hit[i] ? "<mark>" + esc(c) + "</mark>" : esc(c))
              .join("").replace(/<\/mark><mark>/g, "");
}

const $ = id => document.getElementById(id);
const LIMIT = 300;

function fmt(v) { return v == null ? "" : v; }
function chsLog(r) {
  return (r[CYL_L] || r[HDS_L] || r[SEC_L])
    ? `${r[CYL_L]}/${r[HDS_L]}/${r[SEC_L]}` : "";
}

function render() {
  const query = $("q").value.trim();
  const qTokens = query.toLowerCase().split(/\s+/).map(squash).filter(Boolean);
  const idxs = search(query);
  const shown = idxs.slice(0, LIMIT);
  $("count").textContent = idxs.length === DISKS.length
    ? `${DISKS.length} Laufwerke`
    : `${idxs.length} Treffer`;
  $("rows").innerHTML = shown.map(i => {
    const r = DISKS[i];
    return `<tr>
      <td>${highlight(r[P], qTokens)}</td>
      <td>${highlight(r[T], qTokens)}</td>
      <td class="num">${fmt(r[MBC] || r[CAP])}</td>
      <td>${fmt(r[INCH])}</td>
      <td class="num">${fmt(r[CYL])}</td>
      <td class="num">${fmt(r[HDS])}</td>
      <td class="num">${fmt(r[SEC])}</td>
      <td>${chsLog(r)}</td>
      <td>${esc(fmt(r[CTRL]))}</td>
      <td class="num">${fmt(r[SEEK])}</td>
      <td class="num">${fmt(r[PRE])}</td>
      <td class="num">${fmt(r[LND])}</td>
      <td class="num">${fmt(r[MTBF])}</td>
      <td>${fmt(r[DATE])}</td>
      <td>${esc(fmt(r[NOTIZ]))}</td>
    </tr>`;
  }).join("");
  const more = $("more");
  more.hidden = idxs.length <= LIMIT;
  if (!more.hidden)
    more.textContent = `Nur die ersten ${LIMIT} von ${idxs.length} Treffern angezeigt – Suche eingrenzen.`;
}

function renderBios() {
  const vendor = $("vendor").value;
  $("biosrows").innerHTML = BIOS
    .filter(r => r[0] === vendor)
    .map(r => `<tr>
      <td>${esc(r[0])}</td><td class="num">${r[1]}</td>
      <td class="num">${fmt(r[2])}</td><td class="num">${fmt(r[3])}</td>
      <td class="num">${fmt(r[4])}</td><td class="num">${fmt(r[5])}</td>
      <td class="num">${fmt(r[6])}</td>
      <td class="num">${r[7] ? Math.round(r[7] * 512 / 1048576) : ""}</td>
    </tr>`).join("");
}

function fillSelect(id, values) {
  $(id).insertAdjacentHTML("beforeend",
    values.map(v => `<option>${esc(v)}</option>`).join(""));
}
fillSelect("vendor", [...new Set(BIOS.map(r => r[0]))].sort());

function showTab(which) {
  $("pane-disks").hidden = which !== "disks";
  $("pane-bios").hidden = which !== "bios";
  $("tab-disks").setAttribute("aria-selected", which === "disks");
  $("tab-bios").setAttribute("aria-selected", which === "bios");
}
$("tab-disks").onclick = () => showTab("disks");
$("tab-bios").onclick = () => showTab("bios");

$("q").addEventListener("input", render);
$("vendor").addEventListener("change", renderBios);

render();
renderBios();
</script>
</body>
</html>
"""

if __name__ == "__main__":
    main()
