# QSL Card Validator

A self-hosted PHP web app for amateur radio operators that lets a contacted
station verify their QSO with you and request a personalized QSL card.

The app queries the [QRZ.com Logbook API](https://www.qrz.com/) for a matching
QSO (callsign + date), overlays the QSO data on top of a PDF card template,
and offers the result as **download**, **email attachment**, or as a
**physical postcard** order routed to your inbox.

## Features

- **QRZ Logbook lookup** — verifies the QSO directly against your QRZ logbook
  via the Logbook API; only confirmed contacts can request a card.
- **PDF / PNG output** — uses FPDI to fill a PDF template with QSO data
  (date, time, band, mode, RST, rig, station, country, gridsquare, mode-class
  checkboxes); PNG is rendered via `pdftoppm` (with a `ghostscript` fallback).
- **Three delivery modes**
  - *Download* — direct PDF/PNG download
  - *Email* — sent as a MIME attachment from your configured address
  - *Postcard* — order email forwarded to you with the requester's mailing
    address so you can mail a physical card
- **Math CAPTCHA** — simple `a + b = ?` challenge, validated server-side.
- **Rate limiter** — one query per 60 seconds per browser session, with a
  live countdown on the submit button.
- **Output caching** — repeated requests for the same QSO serve the cached
  card without re-querying QRZ.
- **Usage log emails** — you receive an email per request (NEW / CACHED /
  NOT FOUND) with callsign, IP, user-agent and the matched QSO details.
- **Frosted-glass UI** — light theme, locally-hosted Roboto / Material Icons
  fonts, mobile responsive, no third-party CDN calls.

## Requirements

- PHP **8.0+** with `curl`, `mbstring` extensions
- [Composer](https://getcomposer.org/)
- A QRZ.com account with **XML Logbook Data Subscription**
  (the Logbook API key is required)
- Either `pdftoppm` (poppler-utils) or `ghostscript` for PNG output
- A working local MTA (e.g. Postfix) for email/postcard/log delivery —
  optionally with DKIM/SPF set up for your domain

## Installation

```bash
# 1. Clone
git clone https://github.com/CQ-DJ0SH/qsl-card-validator.git
cd qsl-card-validator

# 2. Install PHP dependencies (FPDF + FPDI)
composer install

# 3. Configure
cp config.example.php config.php
$EDITOR config.php   # set qrz_api_key, operator_callsign, order_email, ...

# 4. (Optional) Replace the bundled QSL card template
#    Drop your card design as template/qsl-card-template.pdf
#    Adjust SetXY() coordinates in generate.php → generateQslPdf()

# 5. Make output/ writable for the web server user
mkdir -p output
chown www-data:www-data output  # adjust user as needed
chmod 775 output
```

Point your web server's document root (or a virtual host alias) at the
project directory. The bundled `.htaccess` blocks direct access to
`vendor/`, `template/`, `lib/`, `config.php` and Composer files.

## Configuration (`config.php`)

| Key | Purpose |
|-----|---------|
| `qrz_api_key`         | QRZ Logbook API key (`XXXX-XXXX-XXXX-XXXX`) |
| `operator_callsign`   | Your callsign — used in card text, emails, file names |
| `operator_name`       | Real name used in email signatures |
| `operator_location`   | City/country line in email signatures |
| `operator_rig`        | Free-text rig string printed on the card |
| `order_email`         | From-address for outgoing mail; receives postcard orders |
| `log_email`           | Optional separate recipient for usage logs |
| `template_pdf`        | Path (relative to project root) to the QSL card PDF |
| `site_brand_callsign` | Branding shown on the page (HTML allowed for `Ø` etc.) |
| `back_url` / `back_label` | Optional "back to" link below the form |
| `qrz_url`             | Optional second link (defaults to `https://www.qrz.com`) |

## Project layout

```
qsl-card-validator/
├── index.php              # form / UI (PHP-rendered)
├── generate.php           # backend: validation, QRZ query, PDF/PNG, mail
├── config.example.php     # copy to config.php
├── lib/config.php         # tiny config loader
├── composer.json
├── .htaccess              # blocks vendor/, template/, lib/, config.php, …
├── template/
│   └── qsl-card-template.pdf
├── fonts/                 # Roboto, Material Icons
├── output/                # generated cards (gitignored)
└── BG.png                 # background image (provide your own)
```

## Calibrating field positions

Field coordinates in `generate.php` → `generateQslPdf()` are in millimetres
on a landscape A4 page. The bundled template is calibrated; if you provide
your own template, expect to tweak `SetXY()` values. Useful conversion:

> 1 px @ 300 DPI = 0.0847 mm

## Security notes

- `config.php` is in `.gitignore` — keep it out of version control.
- The `.htaccess` blocks direct access to `template/`, `lib/`, `vendor/` and
  `config.php`. Verify this on your web server (Apache: `AllowOverride All`).
- The Logbook API key alone grants read access to *your* logbook. Treat it
  as a secret.

## Credits

- [setasign/FPDI](https://www.setasign.com/products/fpdi/) — PDF template import
- [FPDF](http://www.fpdf.org/) — PDF generation
- Roboto / Material Icons — Apache 2.0

## License

GNU General Public License v3.0 or later — see [LICENSE](LICENSE).
