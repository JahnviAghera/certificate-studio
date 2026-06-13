<?php require_once __DIR__ . '/lib/bootstrap.php'; ?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Certificate Studio</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="assets/style.css">
</head>
<body>
<header class="topbar">
  <div class="brand">🎓 Certificate Studio</div>
  <div class="steps">
    <span>1 · Design</span><span>2 · Recipients</span><span>3 · Email &amp; Send</span>
  </div>
</header>

<main class="layout">
  <!-- LEFT: stage / preview -->
  <section class="stage-wrap">
    <div class="stage-toolbar">
      <label class="btn">Upload background
        <input type="file" id="bgInput" accept="image/*" hidden>
      </label>
      <button class="btn ghost" id="addFieldBtn" disabled>+ Add field</button>
      <button class="btn ghost" id="serverPreviewBtn" disabled>Render check</button>
      <span class="dim" id="bgDims"></span>
    </div>
    <div class="stage-scroll">
      <div id="stage" class="stage">
        <p class="placeholder">Upload a certificate background image to begin.</p>
      </div>
    </div>
    <div id="serverPreview" class="server-preview" hidden>
      <div class="sp-head">Server render (exact output) <button class="x" id="closeSP">×</button></div>
      <img id="spImg" alt="server preview">
    </div>
  </section>

  <!-- RIGHT: controls -->
  <aside class="panel">
    <!-- Field editor -->
    <div class="card">
      <h2>Dynamic fields</h2>
      <p class="hint">Use <code>{{name}}</code>, <code>{{course}}</code> etc. Placeholders are filled per recipient.</p>
      <div id="fieldList" class="field-list"></div>
      <div id="fieldEditor" class="field-editor" hidden>
        <label>Text / placeholder
          <input type="text" id="fText" placeholder="{{name}}">
        </label>
        <div class="grid2">
          <label>Font
            <select id="fFont"></select>
          </label>
          <label>Weight
            <select id="fWeight">
              <option value="400">Regular</option>
              <option value="700">Bold</option>
            </select>
          </label>
        </div>
        <div class="grid3">
          <label>Size (px)
            <input type="number" id="fSize" min="6" max="400" value="48">
          </label>
          <label>Color
            <input type="color" id="fColor" value="#1a1a2e">
          </label>
          <label>Align
            <select id="fAlign">
              <option value="center">Center</option>
              <option value="left">Left</option>
              <option value="right">Right</option>
            </select>
          </label>
        </div>
        <label class="chk"><input type="checkbox" id="fItalic"> Italic</label>
        <button class="btn danger sm" id="deleteField">Delete field</button>
      </div>
    </div>

    <!-- Recipients -->
    <div class="card">
      <h2>Recipients</h2>
      <p class="hint">CSV with a header row. Must include an <code>email</code> column. Other columns become placeholders.</p>
      <textarea id="csv" rows="6" spellcheck="false">name,email,course
Jane Doe,jane@example.com,Web Development
John Smith,john@example.com,Data Science</textarea>
      <div class="dim" id="csvStatus"></div>
    </div>

    <!-- SMTP -->
    <div class="card">
      <h2>Sender (SMTP)</h2>
      <p class="hint">Use an <b>app password</b>, not your login password. (Gmail: smtp.gmail.com / 587 / TLS.)</p>
      <div class="grid2">
        <label>SMTP host <input id="smHost" value="smtp.gmail.com"></label>
        <label>Port <input id="smPort" type="number" value="587"></label>
      </div>
      <div class="grid2">
        <label>Security
          <select id="smSecure"><option value="tls">STARTTLS (587)</option><option value="ssl">SSL (465)</option></select>
        </label>
        <label>From name <input id="smFromName" placeholder="Acme Academy"></label>
      </div>
      <label>Username / email <input id="smUser" type="email" placeholder="you@gmail.com"></label>
      <label>App password <input id="smPass" type="password" placeholder="xxxx xxxx xxxx xxxx"></label>
    </div>

    <!-- Email body -->
    <div class="card">
      <h2>Email</h2>
      <label>Subject <input id="mailSubject" value="Your certificate, {{name}} 🎉"></label>
      <label>PDF file name <input id="pdfName" value="Certificate - {{name}}"></label>
      <label>HTML body (placeholders supported)
        <textarea id="mailHtml" rows="10" spellcheck="false"></textarea>
      </label>
    </div>

    <button class="btn primary big" id="sendBtn" disabled>Generate &amp; send certificates</button>
    <div id="result" class="result" hidden></div>
  </aside>
</main>

<script>window.GOOGLE_FONTS = <?php echo json_encode(google_font_list()); ?>;</script>
<script src="assets/app.js"></script>
</body>
</html>
