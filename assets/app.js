'use strict';

const state = {
  bg: null,        // {file, url, width, height}
  fields: [],      // field objects
  selected: null,  // field id
  step: 1,
};
let fieldSeq = 1;
const loadedFonts = new Set();

const $ = (id) => document.getElementById(id);
const stage = $('stage');

/* ====================================================================
   STEP NAVIGATION
==================================================================== */
function goStep(n) {
  if (n === 2 && !state.bg) { alert('Upload a background image first.'); return; }
  if (n === 3) {
    const { rows, headers } = parseCsv($('csv').value);
    if (!rows.length || !headers.includes('email')) {
      alert('Add at least one recipient with an “email” column first.'); goStep(2); return;
    }
  }
  state.step = n;
  document.querySelectorAll('.step').forEach((s) => {
    s.classList.toggle('active', Number(s.dataset.step) === n);
  });
  document.querySelectorAll('.step-tab').forEach((t) => {
    const tn = Number(t.dataset.go);
    t.classList.toggle('active', tn === n);
    t.classList.toggle('done', tn < n);
  });
  window.scrollTo({ top: 0, behavior: 'smooth' });
}

/* ====================================================================
   IMAGE UPLOAD  (with client-side downscale so size never blocks us)
==================================================================== */
function fileToImage(file) {
  return new Promise((resolve, reject) => {
    const url = URL.createObjectURL(file);
    const img = new Image();
    img.onload = () => { URL.revokeObjectURL(url); resolve(img); };
    img.onerror = () => { URL.revokeObjectURL(url); reject(new Error('Could not read that image.')); };
    img.src = url;
  });
}

async function optimise(file, maxSide = 2600) {
  const img = await fileToImage(file);
  const scale = Math.min(1, maxSide / Math.max(img.width, img.height));
  const w = Math.round(img.width * scale);
  const h = Math.round(img.height * scale);
  const canvas = document.createElement('canvas');
  canvas.width = w; canvas.height = h;
  canvas.getContext('2d').drawImage(img, 0, 0, w, h);
  // Keep PNG for formats that may carry transparency; JPEG otherwise (smaller).
  const keepPng = /image\/(png|webp|gif)/i.test(file.type);
  const type = keepPng ? 'image/png' : 'image/jpeg';
  const blob = await new Promise((r) => canvas.toBlob(r, type, 0.92));
  return { blob, ext: keepPng ? 'png' : 'jpg' };
}

async function handleFile(file) {
  if (!file || !file.type.startsWith('image/')) { alert('Please choose an image file.'); return; }
  const dz = $('dropzone');
  dz.classList.add('busy');
  try {
    const { blob, ext } = await optimise(file);
    const fd = new FormData();
    fd.append('background', blob, 'background.' + ext);
    const res = await fetch('api/upload.php', { method: 'POST', body: fd });
    const data = await res.json();
    if (!data.ok) { alert('Upload failed: ' + data.error); return; }
    state.bg = data;
    $('bgDims').textContent = data.width + ' × ' + data.height + ' px';
    $('dropzone').hidden = true;
    $('designer').hidden = false;
    $('toStep2').disabled = false;
    renderStage();
    if (!state.fields.length) addField();
  } catch (e) {
    alert(e.message || 'Upload error.');
  } finally {
    dz.classList.remove('busy');
  }
}

/* ====================================================================
   GOOGLE FONTS (live preview)
==================================================================== */
function ensureFont(family, weight) {
  const key = family + '@' + weight;
  if (loadedFonts.has(key)) return;
  loadedFonts.add(key);
  const link = document.createElement('link');
  link.rel = 'stylesheet';
  link.href = 'https://fonts.googleapis.com/css2?family=' +
    encodeURIComponent(family).replace(/%20/g, '+') +
    ':ital,wght@0,' + weight + ';1,' + weight + '&display=swap';
  document.head.appendChild(link);
}

/* ====================================================================
   STAGE + FIELDS
==================================================================== */
function scaleFactor() {
  const img = stage.querySelector('img.bg');
  if (!img || !state.bg) return 1;
  return img.clientWidth / state.bg.width;
}

function renderStage() {
  stage.innerHTML = '';
  if (!state.bg) return;
  const img = document.createElement('img');
  img.className = 'bg';
  img.src = state.bg.url;
  img.onload = renderFields;
  stage.appendChild(img);
  state.fields.forEach(addFieldEl);
  renderFields();
}

function addFieldEl(f) {
  const el = document.createElement('div');
  el.className = 'field ' + f.align;
  el.dataset.id = f.id;
  el.addEventListener('pointerdown', (e) => startDrag(e, f));
  stage.appendChild(el);
}

function renderFields() {
  const scale = scaleFactor();
  state.fields.forEach((f) => {
    ensureFont(f.font, f.weight);
    const el = stage.querySelector('.field[data-id="' + f.id + '"]');
    if (!el) return;
    el.textContent = f.text || ' ';
    el.className = 'field ' + f.align + (f.id === state.selected ? ' selected' : '');
    el.style.left = (f.x * 100) + '%';
    el.style.top = (f.y * 100) + '%';
    el.style.fontFamily = "'" + f.font + "'";
    el.style.fontWeight = f.weight;
    el.style.fontStyle = f.italic ? 'italic' : 'normal';
    el.style.color = f.color;
    el.style.fontSize = (f.size * scale) + 'px';
  });
}
window.addEventListener('resize', renderFields);

function startDrag(e, f) {
  e.preventDefault();
  selectField(f.id);
  const rect = stage.getBoundingClientRect();
  const move = (ev) => {
    f.x = Math.min(1, Math.max(0, (ev.clientX - rect.left) / rect.width));
    f.y = Math.min(1, Math.max(0, (ev.clientY - rect.top) / rect.height));
    renderFields();
  };
  const up = () => {
    window.removeEventListener('pointermove', move);
    window.removeEventListener('pointerup', up);
  };
  window.addEventListener('pointermove', move);
  window.addEventListener('pointerup', up);
}

function addField() {
  // Stagger new fields vertically so they don't stack on top of each other.
  const y = Math.min(0.85, 0.4 + state.fields.length * 0.12);
  const f = {
    id: 'f' + (fieldSeq++),
    text: '{{name}}', font: $('fFont').value || 'Montserrat',
    weight: 700, italic: false, size: 48, color: '#1a1a2e',
    align: 'center', x: 0.5, y,
  };
  state.fields.push(f);
  addFieldEl(f);
  selectField(f.id);
  renderFields();
  renderFieldList();
}

function deleteField() {
  if (!state.selected) return;
  state.fields = state.fields.filter((f) => f.id !== state.selected);
  const el = stage.querySelector('.field[data-id="' + state.selected + '"]');
  if (el) el.remove();
  state.selected = null;
  $('fieldEditorCard').hidden = true;
  renderFieldList();
}

function selectField(id) {
  state.selected = id;
  const f = state.fields.find((x) => x.id === id);
  if (!f) return;
  $('fieldEditorCard').hidden = false;
  $('fText').value = f.text;
  $('fFont').value = f.font;
  $('fWeight').value = f.weight;
  $('fSize').value = f.size;
  $('fColor').value = f.color;
  $('fAlign').value = f.align;
  $('fItalic').checked = f.italic;
  renderFields();
  renderFieldList();
}

function bindEditor() {
  const map = {
    fText: 'text', fFont: 'font', fWeight: 'weight', fSize: 'size',
    fColor: 'color', fAlign: 'align', fItalic: 'italic',
  };
  Object.entries(map).forEach(([elId, prop]) => {
    $(elId).addEventListener('input', () => {
      const f = state.fields.find((x) => x.id === state.selected);
      if (!f) return;
      if (elId === 'fItalic') f[prop] = $(elId).checked;
      else if (elId === 'fWeight' || elId === 'fSize') f[prop] = Number($(elId).value);
      else f[prop] = $(elId).value;
      renderFields();
      renderFieldList();
    });
  });
  $('deleteField').addEventListener('click', deleteField);
}

function renderFieldList() {
  const list = $('fieldList');
  list.innerHTML = '';
  if (!state.fields.length) {
    list.innerHTML = '<div class="dim small">No fields yet. Click “+ Add field”.</div>';
    return;
  }
  state.fields.forEach((f) => {
    const row = document.createElement('div');
    row.className = 'field-row' + (f.id === state.selected ? ' active' : '');
    row.innerHTML = '<span class="ft">' + escapeHtml(f.text || '(empty)') +
      '</span><span class="dim small">' + escapeHtml(f.font) + '</span>';
    row.addEventListener('click', () => selectField(f.id));
    list.appendChild(row);
  });
}

/* ====================================================================
   CSV
==================================================================== */
function splitCsvLine(line) {
  const out = []; let cur = ''; let q = false;
  for (let i = 0; i < line.length; i++) {
    const c = line[i];
    if (c === '"') { if (q && line[i + 1] === '"') { cur += '"'; i++; } else q = !q; }
    else if (c === ',' && !q) { out.push(cur); cur = ''; }
    else cur += c;
  }
  out.push(cur);
  return out;
}
function parseCsv(text) {
  const lines = text.trim().split(/\r?\n/).filter((l) => l.trim() !== '');
  if (lines.length < 2) return { rows: [], headers: [] };
  const headers = splitCsvLine(lines[0]).map((h) => h.trim());
  const rows = lines.slice(1).map((line) => {
    const cells = splitCsvLine(line);
    const obj = {};
    headers.forEach((h, i) => { obj[h] = (cells[i] || '').trim(); });
    return obj;
  });
  return { rows, headers };
}
function refreshCsv() {
  const { rows, headers } = parseCsv($('csv').value);
  const hasEmail = headers.includes('email');
  $('csvStatus').innerHTML = rows.length
    ? rows.length + ' recipient(s). Columns: ' + headers.join(', ') +
      (hasEmail ? '' : ' — <span style="color:#ff5c7c">missing “email” column!</span>')
    : 'No valid rows yet — add a header line plus at least one recipient.';

  // Preview table (first 6 rows).
  const tbl = $('csvPreview');
  if (!rows.length) { tbl.innerHTML = ''; return; }
  let html = '<tr>' + headers.map((h) => '<th>' + escapeHtml(h) + '</th>').join('') + '</tr>';
  rows.slice(0, 6).forEach((r) => {
    html += '<tr>' + headers.map((h) => '<td>' + escapeHtml(r[h] || '') + '</td>').join('') + '</tr>';
  });
  if (rows.length > 6) html += '<tr><td colspan="' + headers.length + '" class="dim">…and ' + (rows.length - 6) + ' more</td></tr>';
  tbl.innerHTML = html;
}

/* ====================================================================
   SERVER RENDER CHECK
==================================================================== */
async function serverPreview() {
  if (!state.bg) return;
  const { rows } = parseCsv($('csv').value);
  const res = await fetch('api/preview.php', {
    method: 'POST', headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ background: state.bg.file, fields: state.fields, values: rows[0] || {} }),
  });
  const data = await res.json();
  if (!data.ok) { alert('Render failed: ' + data.error); return; }
  $('spImg').src = data.url;
  $('serverPreview').hidden = false;
}

/* ====================================================================
   SMTP PROVIDER PRESETS
==================================================================== */
const PROVIDERS = {
  gmail:   { host: 'smtp.gmail.com',      port: 587, secure: 'tls' },
  outlook: { host: 'smtp.office365.com',  port: 587, secure: 'tls' },
  yahoo:   { host: 'smtp.mail.yahoo.com', port: 465, secure: 'ssl' },
  custom:  null,
};
function applyProvider() {
  const key = $('smProvider').value;
  const p = PROVIDERS[key];
  if (p) { $('smHost').value = p.host; $('smPort').value = p.port; $('smSecure').value = p.secure; }
  ['gmail', 'outlook', 'yahoo', 'custom'].forEach((g) => {
    $('guide' + g[0].toUpperCase() + g.slice(1)).hidden = (g !== key);
  });
}

/* ====================================================================
   SEND
==================================================================== */
async function send() {
  const { rows, headers } = parseCsv($('csv').value);
  if (!rows.length) { alert('Add at least one recipient.'); return; }
  if (!headers.includes('email')) { alert('Your CSV needs an “email” column.'); return; }

  const smtp = {
    host: $('smHost').value.trim(), port: $('smPort').value.trim(),
    secure: $('smSecure').value, username: $('smUser').value.trim(),
    password: $('smPass').value, from_email: $('smUser').value.trim(),
    from_name: $('smFromName').value.trim(),
  };
  if (!smtp.host || !smtp.username || (!smtp.password && !state.hasServerPassword)) {
    alert('Fill in the SMTP host, sender email and app password.'); return;
  }

  const btn = $('sendBtn');
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner"></span> Sending ' + rows.length + ' certificate(s)…';

  try {
    const res = await fetch('api/send.php', {
      method: 'POST', headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        background: state.bg.file, fields: state.fields, participants: rows, smtp,
        email: { subject: $('mailSubject').value, html: $('mailHtml').value },
        pdfName: $('pdfName').value,
      }),
    });
    showResult(await res.json());
  } catch (e) {
    showResult({ ok: false, error: e.message });
  } finally {
    btn.disabled = false;
    btn.textContent = 'Generate & send certificates';
  }
}

function showResult(data) {
  const box = $('result');
  box.hidden = false;
  if (!data.ok && data.error) {
    box.innerHTML = '<span class="err">Error: ' + escapeHtml(data.error) + '</span>';
    box.scrollIntoView({ behavior: 'smooth' }); return;
  }

  const sent = data.sent || 0;
  const failed = data.failed || 0;
  const dl = (type) => 'api/download.php?batch=' + encodeURIComponent(data.batch) + '&type=' + type;

  let html = '<div class="result-summary">' +
    '<span class="ok">✓ ' + sent + ' sent</span>' +
    (failed ? ' · <span class="err">✗ ' + failed + ' not sent</span>' : '') +
    ' · <span class="dim">' + data.total + ' total</span></div>';

  // Download bar
  html += '<div class="dl-bar">';
  if (sent)   html += '<a class="btn sm" href="' + dl('sent') + '">⬇ Sent PDFs (' + sent + ')</a>';
  if (failed) html += '<a class="btn sm" href="' + dl('failed') + '">⬇ Not-sent PDFs (' + failed + ')</a>';
  html += '<a class="btn sm" href="' + dl('all') + '">⬇ All PDFs</a>';
  html += '<a class="btn sm" href="' + dl('log') + '">⬇ Log (CSV)</a>';
  html += '</div>';

  // Complete log
  html += '<table class="log-table"><tr><th>#</th><th>Name</th><th>Email</th><th>Status</th><th>Detail</th></tr>';
  (data.results || []).forEach((r) => {
    const sentRow = r.status === 'sent';
    html += '<tr>' +
      '<td>' + r.row + '</td>' +
      '<td>' + escapeHtml(r.name || '') + '</td>' +
      '<td>' + escapeHtml(r.email || '') + '</td>' +
      '<td>' + (sentRow ? '<span class="ok">✓ sent</span>' : '<span class="err">✗ not sent</span>') + '</td>' +
      '<td class="dim">' + escapeHtml(r.error || (sentRow ? '' : '')) + (r.pdf ? '' : ' <i>(no PDF)</i>') + '</td>' +
    '</tr>';
  });
  box.innerHTML = html + '</table>';
  box.scrollIntoView({ behavior: 'smooth' });
}

/* ====================================================================
   UTILS + INIT
==================================================================== */
function escapeHtml(s) {
  return String(s).replace(/[&<>"]/g, (c) =>
    ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
}

const DEFAULT_EMAIL_HTML =
`<div style="font-family:Arial,sans-serif;max-width:560px;margin:auto;border:1px solid #eee;border-radius:10px;overflow:hidden">
  <div style="background:#6c5ce7;color:#fff;padding:22px 26px">
    <h1 style="margin:0;font-size:20px">Congratulations, {{name}}! 🎉</h1>
  </div>
  <div style="padding:24px 26px;color:#333;line-height:1.6">
    <p>We're delighted to award you your certificate for <b>{{course}}</b>.</p>
    <p>Your personalised certificate is attached to this email as a PDF.</p>
    <p style="margin-top:24px">Warm regards,<br><b>The Team</b></p>
  </div>
  <div style="background:#f7f7fb;color:#999;font-size:12px;padding:14px 26px">
    This is an automated message — please do not reply.
  </div>
</div>`;

function init() {
  const sel = $('fFont');
  (window.GOOGLE_FONTS || []).forEach((f) => {
    const o = document.createElement('option');
    o.value = f; o.textContent = f; sel.appendChild(o);
  });
  $('mailHtml').value = DEFAULT_EMAIL_HTML;

  // Step navigation
  document.querySelectorAll('[data-go]').forEach((b) =>
    b.addEventListener('click', () => goStep(Number(b.dataset.go))));
  $('toStep2').addEventListener('click', () => goStep(2));
  $('toStep3').addEventListener('click', () => goStep(3));

  // Dropzone + upload
  const dz = $('dropzone'), input = $('bgInput');
  dz.addEventListener('click', () => input.click());
  $('changeBgBtn').addEventListener('click', () => input.click());
  input.addEventListener('change', (e) => { if (e.target.files[0]) handleFile(e.target.files[0]); });
  ['dragenter', 'dragover'].forEach((ev) => dz.addEventListener(ev, (e) => { e.preventDefault(); dz.classList.add('drag'); }));
  ['dragleave', 'drop'].forEach((ev) => dz.addEventListener(ev, (e) => { e.preventDefault(); dz.classList.remove('drag'); }));
  dz.addEventListener('drop', (e) => { if (e.dataTransfer.files[0]) handleFile(e.dataTransfer.files[0]); });

  // Designer
  $('addFieldBtn').addEventListener('click', addField);
  $('serverPreviewBtn').addEventListener('click', serverPreview);
  $('closeSP').addEventListener('click', () => { $('serverPreview').hidden = true; });
  bindEditor();
  renderFieldList();

  // Recipients
  $('csv').addEventListener('input', refreshCsv);
  refreshCsv();

  // Email & send
  $('smProvider').addEventListener('change', applyProvider);
  $('sendBtn').addEventListener('click', send);
  loadSmtpDefaults();
}

/* Pre-fill the SMTP form from server-side .env defaults (password stays server-side). */
async function loadSmtpDefaults() {
  try {
    const res = await fetch('api/config.php');
    const data = await res.json();
    if (!data.ok) return;
    const s = data.smtp || {};
    if (s.host) $('smHost').value = s.host;
    if (s.port) $('smPort').value = s.port;
    if (s.secure) $('smSecure').value = s.secure;
    if (s.username) $('smUser').value = s.username;
    if (s.from_name) $('smFromName').value = s.from_name;
    if (s.hasServerPassword) {
      state.hasServerPassword = true;
      $('smPass').placeholder = '•••••••• saved — leave blank to use it';
    }
  } catch {}
}
init();
