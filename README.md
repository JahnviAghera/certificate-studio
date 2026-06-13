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

---

## 🔑 How to generate an App Password

An **app password** is a one-off password your email provider issues for a single
program. It lets this app sign in over SMTP **without** your real password and
without disabling security. You must have **two-factor authentication (2FA)
turned on** first — that's what unlocks app passwords.

> ⚠️ Use an app password, **never** your normal login password. Providers block
> "less secure app" logins, so a normal password will simply fail to send.
> Treat the app password like a password: paste it into the app, don't share it,
> and revoke it from your account if it leaks.

### Gmail (and Google Workspace)

| Setting   | Value             |
|-----------|-------------------|
| Host      | `smtp.gmail.com`  |
| Port      | `587`             |
| Security  | STARTTLS          |
| Username  | your full Gmail address |
| Password  | the 16-character app password (below) |

1. Turn on 2-Step Verification: <https://myaccount.google.com/signinoptions/two-step-verification> → follow the prompts (you'll need your phone).
2. Open **App passwords**: <https://myaccount.google.com/apppasswords>
   *(If the page says it's unavailable, 2-Step Verification isn't fully enabled yet.)*
3. Type a name like `Certificate Studio` and click **Create**.
4. Google shows a **16-character code in 4 groups** (e.g. `abcd efgh ijkl mnop`).
5. Copy it into the app's **App password** field. You can type it with or without
   the spaces — both work. Use your full Gmail address as the **Username**.

### Outlook / Microsoft 365 (outlook.com, hotmail, live)

| Setting   | Value                  |
|-----------|------------------------|
| Host      | `smtp.office365.com`   |
| Port      | `587`                  |
| Security  | STARTTLS               |

1. Turn on 2FA: <https://account.microsoft.com/security> → **Advanced security options**.
2. Under **App passwords**, choose **Create a new app password**.
3. Copy the generated password into the app. Username = your full Outlook address.

### Yahoo Mail

| Setting   | Value                  |
|-----------|------------------------|
| Host      | `smtp.mail.yahoo.com`  |
| Port      | `465`                  |
| Security  | SSL                    |

1. Go to **Account Security**: <https://login.yahoo.com/account/security>
2. Click **Generate app password** (2FA must be on), name it, and copy the code.
3. Username = your full Yahoo address; choose **SSL (465)** in the app's Security dropdown.

### Other providers / custom mail server

Use the host, port and security your provider documents for SMTP. Common ports:
**587 = STARTTLS**, **465 = SSL**. If your provider doesn't offer app passwords
and doesn't enforce 2FA, your normal SMTP password may work — but app passwords
are strongly preferred.

### Troubleshooting

| Error you see                          | Fix |
|----------------------------------------|-----|
| `Username and Password not accepted`   | You used your normal password — generate an **app password** instead. |
| `App passwords` page unavailable (Gmail) | Finish enabling **2-Step Verification** first. |
| `Could not authenticate` / `5.7.x`     | Check the address is your full email, and host/port/security match the table above. |
| Connection times out                   | Wrong port/security combo — try `587` + STARTTLS, or `465` + SSL. Some hosts/networks block outbound SMTP. |
| Mail sends but lands in Spam           | Add a real **From name**, and ideally send from a domain with SPF/DKIM configured. |

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
