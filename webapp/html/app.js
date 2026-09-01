'use strict';

// ---------------------------------------------------------------------------
// Field definitions (mirrors lib/db.php)

const FIELDS = [
  { name: 'produzent', label: 'Hersteller', type: 'text', required: true },
  { name: 'modell', label: 'Modellbezeichnung', type: 'text' },
  { name: 'typ', label: 'Typ', type: 'text', required: true },
  { name: 'cap', label: 'Kapazität (MB)', type: 'int' },
  { name: 'seek', label: 'Seek (ms)', type: 'int' },
  { name: 'inch', label: 'Zoll', type: 'text' },
  { name: 'cyl', label: 'Zylinder', type: 'int' },
  { name: 'hds', label: 'Köpfe', type: 'int' },
  { name: 'sec', label: 'Sektoren/Spur', type: 'int' },
  { name: 'cyl_l', label: 'Zylinder (log.)', type: 'int' },
  { name: 'hds_l', label: 'Köpfe (log.)', type: 'int' },
  { name: 'sec_l', label: 'Sektoren (log.)', type: 'int' },
  { name: 'pre', label: 'Precomp', type: 'int' },
  { name: 'lnd', label: 'Landezone', type: 'int' },
  { name: 'controller', label: 'Interface', type: 'text' },
  { name: 'mtbf', label: 'MTBF (1000 h)', type: 'int' },
  { name: 'lift', label: 'Autopark', type: 'text' },
  { name: 'date', label: 'Datum', type: 'text', noform: true },
  { name: 'notiz', label: 'Notiz', type: 'text', wide: true },
];
const FIELD_LABEL = Object.fromEntries(FIELDS.map(f => [f.name, f.label]));
const ACTION_LABEL = {
  create: 'angelegt', update: 'geändert', delete: 'gelöscht',
  import: 'importiert',
};

const $ = id => document.getElementById(id);
const esc = s => String(s ?? '').replace(/[&<>"]/g,
  c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
const fmtTime = iso => iso ? new Date(iso).toLocaleString('de-DE',
  { dateStyle: 'medium', timeStyle: 'short' }) : '';

let DISKS = [];
let BIOS = [];
let KEYS = [];
let USER = null;
let PRODUCERS = [];

// ---------------------------------------------------------------------------
// Fuzzy search (squashed substring match, one edit tolerated per word)

const squash = s => s.toLowerCase().replace(/[^a-z0-9]+/g, '');

function buildKeys() {
  KEYS = DISKS.map(d =>
    squash([d.produzent, d.modell, d.typ].filter(Boolean).join(' ')));
}

function fuzzyIndexOf(text, pat) {
  const m = pat.length;
  if (!m || m > text.length + 1) return -1;
  let prev = Array.from({ length: m + 1 }, (_, i) => i);
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

function search(query, producer) {
  const qSquash = squash(query);
  const qTokens = query.toLowerCase().split(/\s+/).map(squash).filter(Boolean);
  const out = [];
  for (let i = 0; i < DISKS.length; i++) {
    if (producer && DISKS[i].produzent !== producer) continue;
    if (!qSquash) { out.push([0, i]); continue; }
    const s = scoreKey(KEYS[i], qSquash, qTokens);
    if (s >= 0) out.push([s, i]);
  }
  if (qSquash) out.sort((a, b) => b[0] - a[0]);
  return out.map(x => x[1]);
}

function highlight(text, qTokens) {
  if (!qTokens.length || !text) return esc(text ?? '');
  const chars = [...String(text)];
  const sq = [], map = [];
  chars.forEach((c, i) => {
    const s = c.toLowerCase().replace(/[^a-z0-9]/g, '');
    if (s) { sq.push(s); map.push(i); }
  });
  const sqs = sq.join('');
  const hit = new Array(chars.length).fill(false);
  for (const t of qTokens) {
    let from = 0, i;
    while ((i = sqs.indexOf(t, from)) >= 0) {
      for (let k = i; k < i + t.length; k++) hit[map[k]] = true;
      from = i + 1;
    }
  }
  return chars.map((c, i) => hit[i] ? '<mark>' + esc(c) + '</mark>' : esc(c))
    .join('').replace(/<\/mark><mark>/g, '');
}

// ---------------------------------------------------------------------------
// Column sorting: a click cycles a column ascending → descending → off;
// clicking further columns appends them as additional sort keys.

let SORT = [];  // [{ col, dir }] in priority order

const SORT_VALUE = {
  mb: d => mbOf(d) === '' ? null : mbOf(d),
};
const NUMERIC_COLS = new Set(['mb', 'cyl', 'hds', 'sec', 'seek', 'pre',
                              'lnd', 'mtbf']);

function compareDisks(a, b) {
  for (const { col, dir } of SORT) {
    const get = SORT_VALUE[col] ?? (d => d[col]);
    let va = get(a), vb = get(b);
    if (va == null && vb == null) continue;
    if (va == null) return 1;   // empty values always sort last
    if (vb == null) return -1;
    let c;
    if (NUMERIC_COLS.has(col)) {
      c = va - vb;
    } else {
      c = String(va).localeCompare(String(vb), 'de', { numeric: true });
    }
    if (c) return dir === 'desc' ? -c : c;
  }
  return 0;
}

function updateSortMarks() {
  document.querySelectorAll('#pane-disks th[data-col]').forEach(th => {
    const pos = SORT.findIndex(s => s.col === th.dataset.col);
    th.querySelector('.sortmark').textContent = pos < 0 ? ''
      : (SORT[pos].dir === 'asc' ? '▲' : '▼') +
        (SORT.length > 1 ? pos + 1 : '');
  });
}

document.querySelector('#pane-disks thead').addEventListener('click', ev => {
  const th = ev.target.closest('th[data-col]');
  if (!th) return;
  const col = th.dataset.col;
  const cur = SORT.find(s => s.col === col);
  if (!cur) SORT.push({ col, dir: 'asc' });
  else if (cur.dir === 'asc') cur.dir = 'desc';
  else SORT = SORT.filter(s => s.col !== col);
  renderDisks();
});

// ---------------------------------------------------------------------------
// Disk table

const LIMIT = 300;

function mbOf(d) {
  if (d.cyl && d.hds && d.sec)
    return Math.round(d.cyl * d.hds * d.sec * 512 / 1048576);
  return d.cap ?? '';
}
const chsLog = d => (d.cyl_l || d.hds_l || d.sec_l)
  ? `${d.cyl_l ?? ''}/${d.hds_l ?? ''}/${d.sec_l ?? ''}` : '';

function renderDisks() {
  const query = $('q').value.trim();
  const qTokens = query.toLowerCase().split(/\s+/).map(squash).filter(Boolean);
  const idxs = search(query, $('fherst').value);
  if (SORT.length) idxs.sort((a, b) => compareDisks(DISKS[a], DISKS[b]));
  updateSortMarks();
  const shown = idxs.slice(0, LIMIT);
  $('count').innerHTML = (idxs.length === DISKS.length
    ? `${DISKS.length} Laufwerke` : `${idxs.length} Treffer`) +
    (SORT.length ? '<a id="clearsort">Sortierung aufheben</a>' : '');
  if (SORT.length)
    $('clearsort').onclick = () => { SORT = []; renderDisks(); };
  $('rows').innerHTML = shown.map(i => {
    const d = DISKS[i];
    return `<tr data-id="${d.id}">
      <td>${highlight(d.produzent, qTokens)}</td>
      <td>${highlight(d.modell, qTokens)}</td>
      <td>${highlight(d.typ, qTokens)}</td>
      <td class="num">${mbOf(d)}</td>
      <td>${esc(d.inch)}</td>
      <td class="num">${d.cyl ?? ''}</td>
      <td class="num">${d.hds ?? ''}</td>
      <td class="num">${d.sec ?? ''}</td>
      <td>${chsLog(d)}</td>
      <td>${esc(d.controller)}</td>
      <td class="num">${d.seek ?? ''}</td>
      <td class="num">${d.pre ?? ''}</td>
      <td class="num">${d.lnd ?? ''}</td>
      <td class="num">${d.mtbf ?? ''}</td>
      <td>${esc(d.notiz)}</td>
      <td>${esc(d.updated_by_name)}</td>
    </tr>`;
  }).join('');
  const more = $('more');
  more.hidden = idxs.length <= LIMIT;
  if (!more.hidden)
    more.textContent =
      `Nur die ersten ${LIMIT} von ${idxs.length} Treffern angezeigt – Suche eingrenzen.`;
}

$('rows').addEventListener('click', ev => {
  const tr = ev.target.closest('tr[data-id]');
  if (!tr) return;
  const disk = DISKS.find(d => d.id === Number(tr.dataset.id));
  if (disk) openDiskDialog(disk);
});

// ---------------------------------------------------------------------------
// BIOS tab

function renderBios() {
  const vendor = $('vendor').value;
  $('biosrows').innerHTML = BIOS
    .filter(r => r.bios === vendor)
    .map(r => `<tr>
      <td>${esc(r.bios)}</td><td class="num">${r.typ}</td>
      <td class="num">${r.tracks ?? ''}</td><td class="num">${r.heads ?? ''}</td>
      <td class="num">${r.sektors_tr ?? ''}</td><td class="num">${r.pre ?? ''}</td>
      <td class="num">${r.landing ?? ''}</td>
      <td class="num">${r.sektors_al ? Math.round(r.sektors_al * 512 / 1048576) : ''}</td>
    </tr>`).join('');
}

// ---------------------------------------------------------------------------
// Detail / edit dialog

let dialogDisk = null;   // null = new entry

const NEW_PRODUCER = '__neu__';

function fieldInput(f, value) {
  const attrs = USER ? '' : 'readonly';
  const val = esc(value ?? '');
  if (f.name === 'produzent' && USER)
    return `<label>${f.label}
      <select name="produzent" required>
        <option value="" disabled ${value ? '' : 'selected'} hidden>– wählen –</option>
        ${PRODUCERS.map(p => `<option ${p === value ? 'selected' : ''}>${esc(p)}</option>`).join('')}
        <option value="${NEW_PRODUCER}">Neuer Hersteller…</option>
      </select>
      <input type="text" name="produzent_neu" placeholder="Name des Herstellers" hidden></label>`;
  if (f.name === 'notiz')
    return `<label class="wide">${f.label}
      <input type="text" name="${f.name}" value="${val}" ${attrs}></label>`;
  const type = f.type === 'int' ? 'number' : 'text';
  const req = f.required && USER ? 'required' : '';
  return `<label>${f.label}
    <input type="${type}" name="${f.name}" value="${val}" ${attrs} ${req}></label>`;
}

$('dlg-fields').addEventListener('change', ev => {
  if (ev.target.name !== 'produzent') return;
  const neu = $('disk-form').elements.produzent_neu;
  neu.hidden = ev.target.value !== NEW_PRODUCER;
  neu.required = !neu.hidden;
  if (!neu.hidden) neu.focus();
});

function openDiskDialog(disk) {
  dialogDisk = disk;
  $('dlg-title').textContent = disk
    ? `${disk.produzent} ${disk.typ}` : 'Neuer Eintrag';
  $('dlg-fields').innerHTML = FIELDS.filter(f => !f.noform)
    .map(f => fieldInput(f, disk?.[f.name])).join('');
  $('btn-save').hidden = !USER;
  $('btn-delete').hidden = !USER || !disk;
  const hist = $('dlg-history');
  hist.innerHTML = '';
  if (disk) {
    const touched = disk.updated_by_name
      ? ` – zuletzt geändert von ${esc(disk.updated_by_name)} am ${fmtTime(disk.updated_at)}` : '';
    hist.innerHTML = `<h3>Historie${touched}</h3><div>Lade…</div>`;
    loadDiskHistory(disk.id);
  }
  $('dlg-disk').showModal();
}

async function loadDiskHistory(diskId) {
  const res = await fetch(`api/history.php?disk=${diskId}&limit=50`);
  const { revisions } = await res.json();
  if (dialogDisk?.id !== diskId) return;
  const container = $('dlg-history');
  const header = container.querySelector('h3').outerHTML;
  container.innerHTML = header + (revisions.length
    ? revisions.map(revHtml).join('')
    : '<div class="rev">Keine aufgezeichneten Änderungen.</div>');
}

function diffHtml(diff) {
  return diff.map(d =>
    `<b>${esc(FIELD_LABEL[d.field] ?? d.field)}</b>: ${esc(d.old ?? '–')} → ${esc(d.new ?? '–')}`
  ).join(', ');
}

function revHtml(r) {
  const what = r.action === 'update' || r.action === 'import'
    ? `<span class="diff">${diffHtml(r.diff)}</span>` : '';
  const file = r.batch_filename ? ` (${esc(r.batch_filename)})` : '';
  return `<div class="rev">
    <span class="who">${fmtTime(r.changed_at)} – </span>
    <span class="name">${esc(r.changed_by_name)}</span>
    <span class="action-${r.action}"> ${ACTION_LABEL[r.action] ?? r.action}${file}</span>
    ${what}</div>`;
}

$('disk-form').addEventListener('submit', async ev => {
  ev.preventDefault();
  if (!USER) { $('dlg-disk').close(); return; }
  const data = {};
  for (const f of FIELDS.filter(f => !f.noform))
    data[f.name] = $('disk-form').elements[f.name].value;
  if (data.produzent === NEW_PRODUCER)
    data.produzent = $('disk-form').elements.produzent_neu.value;
  const url = dialogDisk ? `auth/disk.php?id=${dialogDisk.id}` : 'auth/disk.php';
  const res = await fetch(url, {
    method: dialogDisk ? 'PUT' : 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(data),
  });
  if (!res.ok) { alert(await errorText(res)); return; }
  $('dlg-disk').close();
  await reloadDisks();
});

$('btn-delete').addEventListener('click', async () => {
  if (!dialogDisk) return;
  if (!confirm(`Eintrag ${dialogDisk.produzent} ${dialogDisk.typ} wirklich löschen?`))
    return;
  const res = await fetch(`auth/disk.php?id=${dialogDisk.id}`,
    { method: 'DELETE' });
  if (!res.ok) { alert(await errorText(res)); return; }
  $('dlg-disk').close();
  await reloadDisks();
});

$('btn-cancel').addEventListener('click', () => $('dlg-disk').close());
$('btn-new').addEventListener('click', () => openDiskDialog(null));

async function errorText(res) {
  try {
    return (await res.json()).error;
  } catch {
    return res.status === 401 ? 'Anmeldung abgelaufen – Seite neu laden.'
      : `Fehler ${res.status}`;
  }
}

// ---------------------------------------------------------------------------
// History tab (endless scrolling)

let historyNext = undefined;  // undefined = not loaded, null = exhausted
let historyLoading = false;

async function loadMoreHistory() {
  if (historyLoading || historyNext === null) return;
  historyLoading = true;
  const before = historyNext ? `&before=${historyNext}` : '';
  const res = await fetch(`api/history.php?limit=50${before}`);
  const { revisions, next } = await res.json();
  historyNext = next;
  $('historylist').insertAdjacentHTML('beforeend', revisions.map(r => `
    <div class="rev">
      <div class="head">
        <span>${fmtTime(r.changed_at)}</span>
        <span class="name">${esc(r.changed_by_name)}</span>
        <span class="action-${r.action}">${ACTION_LABEL[r.action] ?? r.action}${
          r.batch_filename ? ' (' + esc(r.batch_filename) + ')' : ''}</span>
        <span>${esc(r.produzent ?? '')} ${esc(r.typ ?? '')}</span>
      </div>
      ${r.diff.length ? `<div class="diff">${diffHtml(r.diff)}</div>` : ''}
    </div>`).join(''));
  $('historyend').textContent = historyNext === null
    ? ($('historylist').children.length
       ? 'Anfang der Historie erreicht.' : 'Noch keine Änderungen aufgezeichnet.')
    : 'Lade…';
  historyLoading = false;
}

new IntersectionObserver(entries => {
  if (entries.some(e => e.isIntersecting) && !$('pane-history').hidden)
    loadMoreHistory();
}).observe($('historyend'));

function resetHistory() {
  historyNext = undefined;
  $('historylist').innerHTML = '';
  $('historyend').textContent = 'Lade…';
}

// ---------------------------------------------------------------------------
// Import / export

let sheetjsPromise = null;
function loadSheetJS() {
  sheetjsPromise ??= new Promise((resolve, reject) => {
    const s = document.createElement('script');
    s.src = 'https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js';
    s.onload = () => resolve(window.XLSX);
    s.onerror = () => reject(new Error('XLSX-Bibliothek nicht ladbar'));
    document.head.appendChild(s);
  });
  return sheetjsPromise;
}

const EXPORT_COLUMNS = ['id', ...FIELDS.map(f => f.name)];

$('btn-export-csv').addEventListener('click', () => {
  location.href = 'api/export.php';
});

$('btn-export-xlsx').addEventListener('click', async () => {
  const XLSX = await loadSheetJS();
  const rows = DISKS.map(d =>
    Object.fromEntries(EXPORT_COLUMNS.map(c => [c, d[c] ?? null])));
  const ws = XLSX.utils.json_to_sheet(rows, { header: EXPORT_COLUMNS });
  const wb = XLSX.utils.book_new();
  XLSX.utils.book_append_sheet(wb, ws, 'hddb');
  XLSX.writeFile(wb, 'hddb-export.xlsx');
});

$('btn-import').addEventListener('click', () => $('import-file').click());

let importToken = null;

$('import-file').addEventListener('change', async ev => {
  const file = ev.target.files[0];
  ev.target.value = '';
  if (!file) return;
  try {
    const XLSX = await loadSheetJS();
    const wb = XLSX.read(await file.arrayBuffer());
    const sheet = wb.Sheets[wb.SheetNames[0]];
    const raw = XLSX.utils.sheet_to_json(sheet, { defval: '' });
    if (!raw.length) throw new Error('Keine Datenzeilen gefunden');
    const columns = EXPORT_COLUMNS.filter(c => c in raw[0]);
    const rows = raw.map(row => Object.fromEntries(
      columns.map(c => [c, row[c]])));
    const res = await fetch('auth/import-preview.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ filename: file.name, columns, rows }),
    });
    if (!res.ok) throw new Error(await errorText(res));
    showImportPreview(await res.json());
  } catch (e) {
    alert('Import fehlgeschlagen: ' + e.message);
  }
});

function showImportPreview({ token, counts, rows }) {
  importToken = token;
  $('import-summary').innerHTML =
    `<b>${counts.new}</b> neu, <b>${counts.changed}</b> geändert, ` +
    `${counts.unchanged} unverändert` +
    (counts.conflict
      ? `, <span class="status-conflict">${counts.conflict} nicht zuordenbar (werden übersprungen)</span>`
      : '');
  $('import-details').innerHTML = rows.length ? `<table>
    <thead><tr><th>Zeile</th><th>Status</th><th>Eintrag</th><th>Änderungen</th></tr></thead>
    <tbody>${rows.map(r => `<tr>
      <td class="num">${r.line}</td>
      <td class="status-${r.status}">${
        { new: 'neu', changed: 'geändert', conflict: 'Konflikt' }[r.status]}</td>
      <td>${esc(r.produzent ?? '')} ${esc(r.typ ?? '')}</td>
      <td>${r.status === 'conflict' ? esc(r.message)
          : r.diff ? diffHtml(r.diff) : ''}</td>
    </tr>`).join('')}</tbody></table>` : '';
  $('btn-import-apply').disabled = !counts.new && !counts.changed;
  $('dlg-import').showModal();
}

$('btn-import-cancel').addEventListener('click', () => $('dlg-import').close());

$('btn-import-apply').addEventListener('click', async () => {
  const res = await fetch('auth/import-apply.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ token: importToken }),
  });
  if (!res.ok) { alert(await errorText(res)); return; }
  const { created, updated } = await res.json();
  $('dlg-import').close();
  alert(`Import abgeschlossen: ${created} neu angelegt, ${updated} geändert.`);
  await reloadDisks();
});

// ---------------------------------------------------------------------------
// Tabs, auth, data loading

function showTab(which) {
  for (const t of ['disks', 'bios', 'history']) {
    $(`pane-${t}`).hidden = t !== which;
    $(`tab-${t}`).setAttribute('aria-selected', String(t === which));
  }
  if (which === 'history' && historyNext === undefined) loadMoreHistory();
}
$('tab-disks').onclick = () => showTab('disks');
$('tab-bios').onclick = () => showTab('bios');
$('tab-history').onclick = () => showTab('history');

async function reloadDisks() {
  const res = await fetch('api/disks.php');
  const data = await res.json();
  DISKS = data.disks;
  BIOS = data.bios;
  buildKeys();
  PRODUCERS = [...new Set(DISKS.map(d => d.produzent).filter(Boolean))]
    .sort((a, b) => a.localeCompare(b, 'de'));
  const fherst = $('fherst');
  const selected = fherst.value;
  fherst.innerHTML = '<option value="">Alle Hersteller</option>' +
    PRODUCERS.map(p => `<option>${esc(p)}</option>`).join('');
  if (PRODUCERS.includes(selected)) fherst.value = selected;
  renderDisks();
  if (!$('vendor').options.length) {
    $('vendor').innerHTML = [...new Set(BIOS.map(r => r.bios))].sort()
      .map(v => `<option>${esc(v)}</option>`).join('');
  }
  renderBios();
  resetHistory();
  if (!$('pane-history').hidden) loadMoreHistory();
}

async function checkLogin() {
  try {
    const res = await fetch('auth/whoami.php');
    if (res.ok) {
      USER = await res.json();
    } else if (res.status === 403) {
      $('account').innerHTML =
        '<span>Bearbeiten ist Vereinsmitgliedern vorbehalten.</span>';
      return;
    }
  } catch { /* offline etc. — stay read-only */ }
  if (USER) {
    $('account').innerHTML =
      `<span>Angemeldet als <b>${esc(USER.name)}</b></span>` +
      '<button id="btn-logout">Abmelden</button>';
    $('btn-logout').onclick = () => {
      location.href = 'oidc/redirect?logout=' +
        encodeURIComponent(location.origin + location.pathname);
    };
    $('btn-new').hidden = false;
    $('btn-import').hidden = false;
  } else {
    $('account').innerHTML = '<button id="btn-login">Anmelden</button>';
    $('btn-login').onclick = () => { location.href = 'auth/login.php'; };
  }
}

$('q').addEventListener('input', renderDisks);
$('fherst').addEventListener('change', renderDisks);
$('vendor').addEventListener('change', renderBios);

(async () => {
  await Promise.all([checkLogin(), reloadDisks()]);
  renderDisks();  // re-render in case login state affects rows
})();
