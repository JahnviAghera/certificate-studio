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
      <p class="hint">Use an <b>app password</b>, not your login password.</p>
      <label>Email provider
        <select id="smProvider">
          <option value="gmail">Gmail / Google Workspace</option>
          <option value="outlook">Outlook / Microsoft 365</option>
          <option value="yahoo">Yahoo Mail</option>
          <option value="custom">Other / custom server</option>
        </select>
      </label>
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

      <details class="guide">
        <summary>🔑 How do I get an app password?</summary>
        <div class="guide-body">
          <p>An <b>app password</b> is a one-time password your email provider issues for a single app, so you never share your real password. You must turn on <b>two-factor authentication (2FA)</b> first — that's what unlocks app passwords. Your normal login password will <b>not</b> work for SMTP.</p>
          <div id="guideGmail" class="guide-steps">
            <ol>
              <li>Enable <a href="https://myaccount.google.com/signinoptions/two-step-verification" target="_blank" rel="noopener">2-Step Verification</a>.</li>
              <li>Open <a href="https://myaccount.google.com/apppasswords" target="_blank" rel="noopener">Google App passwords</a>.</li>
              <li>Name it <code>Certificate Studio</code> → <b>Create</b>.</li>
              <li>Copy the <b>16-character code</b> (e.g. <code>abcd efgh ijkl mnop</code>) into the App password field above. Username = your full Gmail address.</li>
            </ol>
          </div>
          <div id="guideOutlook" class="guide-steps" hidden>
            <ol>
              <li>Enable 2FA in <a href="https://account.microsoft.com/security" target="_blank" rel="noopener">Microsoft security</a> → <b>Advanced security options</b>.</li>
              <li>Under <b>App passwords</b>, choose <b>Create a new app password</b>.</li>
              <li>Copy it into the App password field. Username = your full Outlook address.</li>
            </ol>
          </div>
          <div id="guideYahoo" class="guide-steps" hidden>
            <ol>
              <li>Open <a href="https://login.yahoo.com/account/security" target="_blank" rel="noopener">Yahoo Account Security</a> (2FA must be on).</li>
              <li>Click <b>Generate app password</b>, name it, and copy the code.</li>
              <li>Paste it above. Username = your full Yahoo address (Security = SSL / 465).</li>
            </ol>
          </div>
          <div id="guideCustom" class="guide-steps" hidden>
            <p>Use the host, port and security your mail provider documents for SMTP. Common ports: <b>587 = STARTTLS</b>, <b>465 = SSL</b>. Prefer an app password if your provider offers one.</p>
          </div>
        </div>
      </details>
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
