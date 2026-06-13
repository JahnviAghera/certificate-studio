'use strict';

const state = {
  bg: null,                 // {file, url, width, height}
  fields: [],               // field objects
  selected: null,           // field id
};
let fieldSeq = 1;
const loadedFonts = new Set();

const $ = (id) => document.getElementById(id);
const stage = $('stage');

/* ---------- Google Fonts (live preview) ---------- */
function fontLinkId(family, weight) {
  return 'gf-' + family.replace(/\W+/g, '_') + '-' + weight;
}
function ensureFont(family, weight) {
  const key = family + '@' + weight;
  if (loadedFonts.has(key)) return;
  loadedFonts.add(key);
  const link = document.createElement('link');
  link.rel = 'stylesheet';
  link.id = fontLinkId(family, weight);
  link.href = 'https://fonts.googleapis.com/css2?family=' +
    encodeURIComponent(family).replace(/%20/g, '+') +
    ':ital,wght@0,' + weight + ';1,' + weight + '&display=swap';
  document.head.appendChild(link);
}

/* ---------- Stage rendering ---------- */
function scaleFactor() {
  const img = stage.querySelector('img.bg');
  if (!img || !state.bg) return 1;
  return img.clientWidth / state.bg.width;
}

function renderStage() {
  stage.innerHTML = '';
  if (!state.bg) {
    stage.innerHTML = '<p class="placeholder">Upload a certificate background image to begin.</p>';
    return;
  }
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
    el.textContent = f.text || ' ';
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

/* ---------- Dragging ---------- */
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

/* ---------- Field CRUD + editor ---------- */
function addField() {
  const f = {
    id: 'f' + (fieldSeq++),
    text: '{{name}}',
    font: $('fFont').value || 'Montserrat',
    weight: 700, italic: false, size: 48, color: '#1a1a2e',
    align: 'center', x: 0.5, y: 0.5,
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
  $('fieldEditor').hidden = true;
  renderFieldList();
}

function selectField(id) {
  state.selected = id;
  const f = state.fields.find((x) => x.id === id);
  if (!f) return;
  $('fieldEditor').hidden = false;
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
      let v;
      if (elId === 'fItalic') v = $(elId).checked;
      else if (elId === 'fWeight' || elId === 'fSize') v = Number($(elId).value);
      else v = $(elId).value;
      f[prop] = v;
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
    list.innerHTML = '<div class="dim">No fields yet. Click “Add field”.</div>';
    return;
  }
  state.fields.forEach((f) => {
    const row = document.createElement('div');
    row.className = 'field-row' + (f.id === state.selected ? ' active' : '');
    row.innerHTML = '<span class="ft">' + escapeHtml(f.text || '(empty)') +
      '</span><span class="dim">' + escapeHtml(f.font) + '</span>';
    row.addEventListener('click', () => selectField(f.id));
    list.appendChild(row);
  });
}

/* ---------- Upload ---------- */
async function uploadBg(file) {
  const fd = new FormData();
  fd.append('background', file);
  const res = await fetch('api/upload.php', { method: 'POST', body: fd });
  const data = await res.json();
  if (!data.ok) { alert('Upload failed: ' + data.error); return; }
  state.bg = data;
  $('bgDims').textContent = data.width + ' × ' + data.height + ' px';
  $('addFieldBtn').disabled = false;
  $('serverPreviewBtn').disabled = false;
  $('sendBtn').disabled = false;
  renderStage();
  if (!state.fields.length) addField();
}

/* ---------- CSV parsing ---------- */
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
function refreshCsvStatus() {
  const { rows, headers } = parseCsv($('csv').value);
  const hasEmail = headers.includes('email');
  $('csvStatus').innerHTML = rows.length
    ? rows.length + ' recipient(s). Columns: ' + headers.join(', ') +
      (hasEmail ? '' : ' — <span style="color:#ff5c7c">missing “email” column!</span>')
    : 'No valid rows yet.';
}

/* ---------- Server render check ---------- */
async function serverPreview() {
  if (!state.bg) return;
  const { rows } = parseCsv($('csv').value);
  const values = rows[0] || {};
  const res = await fetch('api/preview.php', {
    method: 'POST', headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ background: state.bg.file, fields: state.fields, values }),
  });
  const data = await res.json();
  if (!data.ok) { alert('Render failed: ' + data.error); return; }
  $('spImg').src = data.url;
  $('serverPreview').hidden = false;
}

/* ---------- Send ---------- */
async function send() {
  if (!state.bg) return;
  const { rows, headers } = parseCsv($('csv').value);
  if (!rows.length) { alert('Add at least one recipient.'); return; }
  if (!headers.includes('email')) { alert('Your CSV needs an “email” column.'); return; }

  const smtp = {
    host: $('smHost').value.trim(), port: $('smPort').value.trim(),
    secure: $('smSecure').value, username: $('smUser').value.trim(),
    password: $('smPass').value, from_email: $('smUser').value.trim(),
    from_name: $('smFromName').value.trim(),
  };
  if (!smtp.host || !smtp.username || !smtp.password) {
    alert('Fill in the SMTP host, username and app password.'); return;
  }

  const btn = $('sendBtn');
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner"></span> Sending ' + rows.length + ' certificate(s)…';

  const payload = {
    background: state.bg.file, fields: state.fields, participants: rows, smtp,
    email: { subject: $('mailSubject').value, html: $('mailHtml').value },
    pdfName: $('pdfName').value,
  };

  try {
    const res = await fetch('api/send.php', {
      method: 'POST', headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
    });
    const data = await res.json();
    showResult(data);
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
    return;
  }
  let html = '<div><b class="ok">' + data.sent + '</b> of ' + data.total + ' sent.</div><table>';
  (data.results || []).forEach((r) => {
    html += '<tr><td>' + escapeHtml(r.email) + '</td><td>' +
      (r.ok ? '<span class="ok">✓ sent</span>'
            : '<span class="err">✗ ' + escapeHtml(r.error || '') + '</span>') +
      '</td></tr>';
  });
  html += '</table>';
  box.innerHTML = html;
}

/* ---------- SMTP provider presets ---------- */
const PROVIDERS = {
  gmail:   { host: 'smtp.gmail.com',      port: 587, secure: 'tls' },
  outlook: { host: 'smtp.office365.com',  port: 587, secure: 'tls' },
  yahoo:   { host: 'smtp.mail.yahoo.com', port: 465, secure: 'ssl' },
  custom:  null,
};
function applyProvider() {
  const key = $('smProvider').value;
  const p = PROVIDERS[key];
  if (p) {
    $('smHost').value = p.host;
    $('smPort').value = p.port;
    $('smSecure').value = p.secure;
  }
  ['Gmail', 'Outlook', 'Yahoo', 'Custom'].forEach((g) => {
    $('guide' + g).hidden = (g.toLowerCase() !== key);
  });
}

/* ---------- Utils ---------- */
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

/* ---------- Init ---------- */
function init() {
  const sel = $('fFont');
  (window.GOOGLE_FONTS || []).forEach((f) => {
    const o = document.createElement('option');
    o.value = f; o.textContent = f;
    sel.appendChild(o);
  });
  $('mailHtml').value = DEFAULT_EMAIL_HTML;

  $('bgInput').addEventListener('change', (e) => {
    if (e.target.files[0]) uploadBg(e.target.files[0]);
  });
  $('smProvider').addEventListener('change', applyProvider);
  $('addFieldBtn').addEventListener('click', addField);
  $('serverPreviewBtn').addEventListener('click', serverPreview);
  $('closeSP').addEventListener('click', () => { $('serverPreview').hidden = true; });
  $('csv').addEventListener('input', refreshCsvStatus);
  $('sendBtn').addEventListener('click', send);

  bindEditor();
  renderFieldList();
  refreshCsvStatus();
}
init();
