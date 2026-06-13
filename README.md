# 🎓 Certificate Studio

A small PHP web app that lets you:

1. **Upload a background image** (your certificate template — JPG / PNG / WEBP).
2. **Add dynamic text fields** (`{{name}}`, `{{course}}`, …) and drag them onto the design.
3. **Pick any Google Font** for each field — the same font is used in the live preview *and* in the final PDF.
4. **Style each field** — font weight, italic, size, colour, alignment.
5. **Paste a recipient list** (CSV with an `email` column + any placeholder columns).
6. **Compose an HTML email** (with a ready-made styled template, placeholders supported).
7. **Generate a personalised PDF per recipient and email it** through your own SMTP server using PHPMailer + an **app password**.

No build step, no database. FPDF and PHPMailer are bundled in `/libs`, so it runs without Composer.

---

## Requirements

- **PHP 8.0+** with the **GD** and **cURL** extensions enabled.
- Outbound internet access (to download Google Font TTFs the first time each font is used).
- An SMTP account with an **app password** (e.g. Gmail, Outlook, your own mail server).

Check your PHP has GD + cURL:

```bash
php -m | grep -E 'gd|curl'
```

---

## Run it

From this folder:

```bash
php -S localhost:8000
```

Then open **http://localhost:8000** in your browser.

> The PHP built-in server is single-threaded. For sending to many recipients it's fine, but a real deployment behind Apache/Nginx + PHP-FPM is recommended.

---

## How to use

1. **Upload background** — click *Upload background* and choose your certificate image. A field is added automatically.
2. **Design fields** — select a field in the right panel, edit its text/font/size/colour, and **drag it** to position it on the certificate. The anchor point is the field's centre (or left/right edge if you change alignment).
3. **Render check** *(optional)* — click *Render check* to see the exact server-side PNG render (this is pixel-identical to the PDF), using your first recipient's data.
4. **Recipients** — edit the CSV. The header row defines placeholders; an `email` column is required. Example:
   ```csv
   name,email,course
   Jane Doe,jane@example.com,Web Development
   ```
5. **Sender (SMTP)** — enter your SMTP host/port, username and **app password**, and a "from" name.
6. **Email** — set the subject, PDF file name, and edit the HTML body (placeholders like `{{name}}` work here too).
7. **Generate & send** — each recipient gets a personalised PDF attached to your HTML email. A per-recipient success/error report appears below the button.

### Gmail quick setup
- Host: `smtp.gmail.com`, Port: `587`, Security: `STARTTLS`.
- Username: your full Gmail address.
- Password: a **16-character App Password** from <https://myaccount.google.com/apppasswords> (requires 2-Step Verification). Your normal password will **not** work.

---

## Project layout

```
index.php            Designer UI (served as the home page)
assets/
  app.js             Front-end logic: design, drag, CSV, send
  style.css          Styling
api/
  upload.php         Saves the background image
  fonts.php          Returns the Google Font list
  preview.php        Server-side PNG render (the "Render check")
  send.php           Renders a PDF per recipient and emails it
lib/
  bootstrap.php      Paths, helpers, curated font list
  GoogleFont.php     Downloads + caches Google Font .ttf files
  CertRenderer.php   GD text drawing + FPDF wrapping → PDF
  Mailer.php         PHPMailer SMTP wrapper
libs/                Bundled FPDF + PHPMailer (no Composer needed)
fonts/               Cached .ttf files (auto-created)
uploads/             Uploaded backgrounds (auto-created)
output/              Temp render/PDF files (auto-created)
```

---

## How fonts stay consistent

The browser previews each Google Font via the normal `fonts.googleapis.com` stylesheet. On the server, `GoogleFont.php` asks Google's CSS API (with a legacy user-agent so Google serves **TrueType**) for the matching `.ttf`, caches it in `/fonts`, and PHP's GD `imagettftext()` draws with that exact file — so preview and PDF match.

To add more fonts to the dropdown, edit `google_font_list()` in `lib/bootstrap.php` with any family name from <https://fonts.google.com>.

---

## Notes & limits

- Coordinates are stored as fractions of the image, so the design scales to the full-resolution image when rendering.
- Field text is single-line (no wrapping). For long values, use a smaller size or split into multiple fields.
- Credentials are sent from the browser to your own server only at send time and are **not stored** anywhere. Run this on a machine you trust, ideally over HTTPS.
- Some hosts use OTF-only fonts; if a font can't be fetched as TTF/OTF you'll get a clear error — pick another.
