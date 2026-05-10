<?php
require_once __DIR__ . '/lib/config.php';

$brand = qsl_config('site_brand_callsign', qsl_config('operator_callsign', 'N0CALL'));
$opCallsign = qsl_config('operator_callsign', 'N0CALL');
$backUrl    = qsl_config('back_url');
$backLabel  = qsl_config('back_label', 'Home');
$qrzUrl     = qsl_config('qrz_url', 'https://www.qrz.com');

$year = (int)date('Y');
$dateMin = "{$year}-01-01";
$dateMax = "{$year}-12-31";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($brand, ENT_QUOTES) ?> - QSL Card Validator</title>
    <style>
        @font-face {
            font-family: 'Roboto';
            font-style: normal;
            font-weight: 100 700;
            font-stretch: 100%;
            font-display: swap;
            src: url('fonts/roboto-latin-ext.woff2') format('woff2');
            unicode-range: U+0100-02BA, U+02BD-02C5, U+02C7-02CC, U+02CE-02D7, U+02DD-02FF, U+0304, U+0308, U+0329, U+1D00-1DBF, U+1E00-1E9F, U+1EF2-1EFF, U+2020, U+20A0-20AB, U+20AD-20C0, U+2113, U+2C60-2C7F, U+A720-A7FF;
        }
        @font-face {
            font-family: 'Roboto';
            font-style: normal;
            font-weight: 100 700;
            font-stretch: 100%;
            font-display: swap;
            src: url('fonts/roboto-latin.woff2') format('woff2');
            unicode-range: U+0000-00FF, U+0131, U+0152-0153, U+02BB-02BC, U+02C6, U+02DA, U+02DC, U+0304, U+0308, U+0329, U+2000-206F, U+20AC, U+2122, U+2191, U+2193, U+2212, U+2215, U+FEFF, U+FFFD;
        }
        @font-face {
            font-family: 'Material Icons';
            font-style: normal;
            font-weight: 400;
            font-display: block;
            src: url('fonts/material-icons.woff2') format('woff2');
        }
        .material-icons {
            font-family: 'Material Icons';
            font-weight: normal;
            font-style: normal;
            font-size: 24px;
            line-height: 1;
            letter-spacing: normal;
            text-transform: none;
            display: inline-block;
            white-space: nowrap;
            word-wrap: normal;
            direction: ltr;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            text-rendering: optimizeLegibility;
            font-feature-settings: 'liga';
        }
        /* Optional display font for the heading.
           Drop your own .otf/.woff2 into fonts/ and uncomment the @font-face below. */
        /*
        @font-face {
            font-family: 'HeadingFont';
            src: url('fonts/your-heading-font.woff2') format('woff2');
            font-weight: normal;
            font-style: normal;
            font-display: swap;
        }
        */

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            min-height: 100vh;
            /* Drop your own background image at BG.png to enable. */
            background: #e8e4df url('BG.png') no-repeat center center fixed;
            background-size: cover;
            font-family: "Roboto", sans-serif;
            font-weight: 300;
            color: #2c2c2c;
        }

        body::before {
            content: '';
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.4);
            z-index: 0;
        }

        .container {
            max-width: 680px;
            margin: 0 auto;
            padding: 60px 24px 80px;
            position: relative;
            z-index: 1;
        }

        .header { text-align: center; margin-bottom: 48px; }
        .header h1 {
            font-family: 'HeadingFont', "Roboto", sans-serif;
            font-weight: normal;
            font-size: 42px;
            line-height: 1.285;
            letter-spacing: -0.036em;
            color: #3c3c3c;
        }
        .header .subtitle {
            font-weight: 300;
            font-size: 20px;
            color: #fff;
            margin-top: 12px;
            letter-spacing: 0;
        }

        .card {
            background: rgba(255,255,255,0.55);
            backdrop-filter: blur(24px) saturate(1.4);
            -webkit-backdrop-filter: blur(24px) saturate(1.4);
            border: 1px solid rgba(255,255,255,0.7);
            border-radius: 6px;
            padding: 40px;
            box-shadow:
                0 1px 3px rgba(0,0,0,0.04),
                0 8px 40px rgba(0,0,0,0.06);
        }

        .card-title {
            font-weight: 500;
            font-size: 0.8rem;
            color: #000;
            margin-bottom: 28px;
            padding-bottom: 14px;
            border-bottom: 1px solid rgba(0,0,0,0.07);
            letter-spacing: 2px;
            text-transform: uppercase;
        }

        .form-row { display: flex; gap: 20px; margin-bottom: 24px; }
        .form-group { flex: 1; }

        .form-group label {
            display: block;
            font-size: 0.72rem;
            font-weight: 500;
            color: #2196F3;
            margin-bottom: 8px;
            letter-spacing: 1.5px;
            text-transform: uppercase;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 13px 16px;
            background: rgba(255,255,255,0.6);
            border: 1px solid rgba(0,0,0,0.1);
            border-radius: 4px;
            color: #1a1a1a;
            font-family: "Roboto", sans-serif;
            font-weight: 400;
            font-size: 0.95rem;
            transition: border-color 0.2s, box-shadow 0.2s;
            outline: none;
        }

        .form-group textarea { resize: vertical; min-height: 80px; }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            border-color: #2196F3;
            box-shadow: 0 0 0 3px rgba(33,150,243,0.1);
        }

        .form-group input::placeholder,
        .form-group textarea::placeholder { color: rgba(0,0,0,0.25); }

        .form-group input.callsign-input {
            text-transform: uppercase;
            font-weight: 500;
            font-size: 1.1rem;
            letter-spacing: 3px;
        }

        .delivery-section {
            margin-top: 8px;
            padding-top: 24px;
            border-top: 1px solid rgba(0,0,0,0.06);
        }

        .delivery-options { display: flex; gap: 12px; margin-bottom: 20px; }
        .delivery-option { flex: 1; position: relative; }
        .delivery-option input[type="radio"] {
            position: absolute; opacity: 0; width: 0; height: 0;
        }
        .delivery-option label {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 6px;
            padding: 18px 12px;
            background: rgba(255,255,255,0.4);
            border: 1px solid rgba(0,0,0,0.08);
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.2s;
            text-transform: none;
            letter-spacing: 0;
        }
        .delivery-option input:checked + label {
            border-color: #2196F3;
            background: rgba(33,150,243,0.06);
            box-shadow: 0 0 0 1px #2196F3;
        }
        .delivery-option label .icon { font-size: 1.4rem; color: rgba(0,0,0,0.3); }
        .delivery-option input:checked + label .icon { color: #2196F3; }
        .delivery-option label .text {
            font-size: 0.8rem;
            font-weight: 400;
            color: rgba(0,0,0,0.45);
        }
        .delivery-option input:checked + label .text { color: #2196F3; font-weight: 500; }

        .extra-field { display: none; margin-top: 4px; }
        .extra-field.visible { display: block; }

        .format-section { margin-top: 20px; }

        .field-hint {
            font-size: 0.75rem;
            color: rgba(0,0,0,0.4);
            margin-top: 6px;
            font-weight: 400;
        }

        .format-toggles { display: flex; gap: 10px; }
        .format-toggle { position: relative; }
        .format-toggle input { position: absolute; opacity: 0; width: 0; height: 0; }
        .format-toggle label {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 20px;
            background: rgba(255,255,255,0.4);
            border: 1px solid rgba(0,0,0,0.08);
            border-radius: 4px;
            font-size: 0.82rem;
            font-weight: 400;
            color: rgba(0,0,0,0.4);
            cursor: pointer;
            transition: all 0.2s;
            text-transform: none;
            letter-spacing: 0;
        }
        .format-toggle input:checked + label {
            border-color: #2196F3;
            color: #2196F3;
            font-weight: 500;
            box-shadow: 0 0 0 1px #2196F3;
        }

        .captcha-section { margin-top: 20px; }
        .captcha-row { display: flex; gap: 12px; align-items: flex-end; }
        .captcha-question {
            font-size: 0.95rem;
            font-weight: 500;
            color: #1a1a1a;
            padding: 13px 0;
            white-space: nowrap;
        }
        .captcha-input { max-width: 80px; }

        .submit-btn {
            width: 100%;
            margin-top: 32px;
            padding: 16px 32px;
            background: #2196F3;
            border: none;
            border-radius: 4px;
            font-family: "Roboto", sans-serif;
            font-size: 0.85rem;
            font-weight: 500;
            color: #fff;
            letter-spacing: 2px;
            text-transform: uppercase;
            cursor: pointer;
            transition: all 0.2s;
        }
        .submit-btn:hover { background: #1E88E5; box-shadow: 0 4px 20px rgba(33,150,243,0.25); }
        .submit-btn:active { background: #1976D2; }
        .submit-btn:disabled { opacity: 0.4; cursor: not-allowed; }

        .status-area { margin-top: 28px; display: none; }
        .status-area.visible { display: block; }
        .status-msg {
            padding: 14px 20px;
            border-radius: 4px;
            font-size: 0.88rem;
            font-weight: 400;
            line-height: 1.6;
        }
        .status-msg.loading { background: rgba(33,150,243,0.07); border: 1px solid rgba(33,150,243,0.15); color: #1976D2; }
        .status-msg.success { background: rgba(76,175,80,0.07); border: 1px solid rgba(76,175,80,0.15); color: #2E7D32; }
        .status-msg.error   { background: rgba(244,67,54,0.07); border: 1px solid rgba(244,67,54,0.15); color: #C62828; }

        .spinner {
            display: inline-block;
            width: 14px;
            height: 14px;
            border: 2px solid rgba(33,150,243,0.2);
            border-top-color: #2196F3;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
            vertical-align: middle;
            margin-right: 8px;
        }
        @keyframes spin { to { transform: rotate(360deg); } }

        .download-links { margin-top: 16px; display: flex; gap: 12px; flex-wrap: wrap; }
        .download-links a {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 24px;
            background: rgba(255,255,255,0.5);
            border: 2px solid #2196F3;
            border-radius: 4px;
            color: #2196F3;
            text-decoration: none;
            font-weight: 500;
            font-size: 0.85rem;
            letter-spacing: 1px;
            transition: all 0.2s;
        }
        .download-links a:hover { background: rgba(33,150,243,0.08); box-shadow: 0 2px 12px rgba(33,150,243,0.15); }

        .qso-preview {
            margin-top: 20px;
            padding: 20px;
            background: rgba(255,255,255,0.35);
            border: 1px solid rgba(0,0,0,0.05);
            border-radius: 4px;
            display: none;
        }
        .qso-preview.visible { display: block; }
        .qso-preview h4 {
            font-size: 0.7rem;
            font-weight: 500;
            color: rgba(0,0,0,0.3);
            letter-spacing: 2px;
            margin-bottom: 16px;
            text-transform: uppercase;
        }
        .qso-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; }
        .qso-field {
            padding: 10px 12px;
            background: rgba(255,255,255,0.45);
            border-radius: 4px;
        }
        .qso-field .label {
            font-size: 0.65rem;
            font-weight: 500;
            color: rgba(0,0,0,0.3);
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .qso-field .value {
            font-size: 0.95rem;
            font-weight: 500;
            color: #1a1a1a;
            margin-top: 3px;
        }

        .back-link {
            text-align: center;
            margin-top: 32px;
            display: flex;
            justify-content: center;
            gap: 12px;
        }
        .back-link a {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 10px 24px;
            border: 2px solid rgba(255,255,255,0.4);
            border-radius: 4px;
            color: #fff;
            text-decoration: none;
            font-weight: 500;
            font-size: 0.85rem;
            letter-spacing: 1px;
            transition: all 0.2s;
        }
        .back-link a:hover { border-color: #2196F3; color: #2196F3; }

        @media (max-width: 600px) {
            .container { padding: 40px 16px 60px; }
            .header h1 { font-size: 1.6rem; }
            .card { padding: 28px 20px; }
            .form-row { flex-direction: column; gap: 16px; }
            .delivery-options { flex-direction: column; }
            .qso-grid { grid-template-columns: repeat(2, 1fr); }
            .captcha-row { flex-direction: column; align-items: stretch; }
            .captcha-input { max-width: 100%; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>QSL Card Validator</h1>
            <p class="subtitle">Request a personalized QSL card from <?= htmlspecialchars($brand, ENT_QUOTES) ?></p>
        </div>

        <div class="card">
            <div class="card-title">Check QRZ.com for a logged QSO with <?= htmlspecialchars($brand, ENT_QUOTES) ?></div>

            <form id="qslForm" novalidate>
                <div class="form-row">
                    <div class="form-group">
                        <label for="callsign">Your Callsign</label>
                        <input type="text" id="callsign" name="callsign" class="callsign-input"
                               placeholder="CALLSIGN" required autocomplete="off" spellcheck="false">
                    </div>
                    <div class="form-group">
                        <label for="qso_date">QSO Date</label>
                        <input type="date" id="qso_date" name="qso_date" required
                               min="<?= $dateMin ?>" max="<?= $dateMax ?>">
                    </div>
                </div>

                <div class="delivery-section">
                    <div class="card-title" style="font-size:0.8rem; margin-bottom:16px;">Delivery Method</div>
                    <div class="delivery-options">
                        <div class="delivery-option">
                            <input type="radio" name="delivery" id="del_download" value="download" checked>
                            <label for="del_download">
                                <span class="icon"><i class="material-icons" style="font-size:20px">file_download</i></span>
                                <span class="text">Download</span>
                            </label>
                        </div>
                        <div class="delivery-option">
                            <input type="radio" name="delivery" id="del_email" value="email">
                            <label for="del_email">
                                <span class="icon"><i class="material-icons" style="font-size:20px">mail_outline</i></span>
                                <span class="text">Email</span>
                            </label>
                        </div>
                        <div class="delivery-option">
                            <input type="radio" name="delivery" id="del_postcard" value="postcard">
                            <label for="del_postcard">
                                <span class="icon"><i class="material-icons" style="font-size:20px">local_post_office</i></span>
                                <span class="text">Postcard</span>
                            </label>
                        </div>
                    </div>

                    <div class="form-group extra-field" id="emailField">
                        <label for="email">Email Address</label>
                        <input type="email" id="email" name="email" placeholder="your@email.com">
                        <p class="field-hint">Your QSL card will be sent as an attachment to this email address.</p>
                    </div>

                    <div class="form-group extra-field" id="addressField">
                        <label for="address">Mailing Address</label>
                        <textarea id="address" name="address" placeholder="Name&#10;Street&#10;City, ZIP&#10;Country"></textarea>
                        <p class="field-hint">Your request will be forwarded to <?= htmlspecialchars($brand, ENT_QUOTES) ?> and the QSL card will be mailed to this address.</p>
                    </div>
                </div>

                <div class="format-section" id="formatSection">
                    <div class="form-group">
                        <label>Output Format</label>
                        <div class="format-toggles">
                            <div class="format-toggle">
                                <input type="radio" name="format" id="fmt_pdf" value="pdf" checked>
                                <label for="fmt_pdf">PDF</label>
                            </div>
                            <div class="format-toggle">
                                <input type="radio" name="format" id="fmt_png" value="png">
                                <label for="fmt_png">PNG</label>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="captcha-section">
                    <div class="form-group">
                        <label>Security Check</label>
                        <div class="captcha-row">
                            <span class="captcha-question" id="captchaQuestion"></span>
                            <div class="form-group captcha-input">
                                <input type="text" id="captcha_answer" name="captcha_answer"
                                       placeholder="?" autocomplete="off" inputmode="numeric">
                            </div>
                        </div>
                    </div>
                </div>

                <button type="submit" class="submit-btn" id="submitBtn">
                    Generate QSL Card
                </button>
            </form>

            <div class="status-area" id="statusArea">
                <div class="status-msg" id="statusMsg"></div>
                <div class="qso-preview" id="qsoPreview">
                    <h4>QSO Details Found</h4>
                    <div class="qso-grid" id="qsoGrid"></div>
                </div>
                <div class="download-links" id="downloadLinks"></div>
            </div>
        </div>

        <div class="back-link">
            <?php if ($backUrl): ?>
                <a href="<?= htmlspecialchars($backUrl, ENT_QUOTES) ?>">
                    <i class="material-icons" style="font-size:16px;vertical-align:middle">arrow_back</i>
                    Back to <?= htmlspecialchars($backLabel, ENT_QUOTES) ?>
                </a>
            <?php endif; ?>
            <?php if ($qrzUrl): ?>
                <a href="<?= htmlspecialchars($qrzUrl, ENT_QUOTES) ?>">
                    <i class="material-icons" style="font-size:16px;vertical-align:middle">language</i>
                    QRZ.com
                </a>
            <?php endif; ?>
        </div>
    </div>

    <script>
        let captchaA, captchaB;
        function newCaptcha() {
            captchaA = Math.floor(Math.random() * 20) + 1;
            captchaB = Math.floor(Math.random() * 20) + 1;
            document.getElementById('captchaQuestion').textContent = captchaA + ' + ' + captchaB + ' =';
            document.getElementById('captcha_answer').value = '';
        }
        newCaptcha();

        document.querySelectorAll('input[name="delivery"]').forEach(r => {
            r.addEventListener('change', () => {
                const val = document.querySelector('input[name="delivery"]:checked').value;
                document.getElementById('emailField').classList.toggle('visible', val === 'email');
                document.getElementById('addressField').classList.toggle('visible', val === 'postcard');
                document.getElementById('formatSection').style.display = val === 'postcard' ? 'none' : '';
            });
        });

        document.getElementById('callsign').addEventListener('input', function() {
            this.value = this.value.toUpperCase().replace(/[^A-Z0-9\/]/g, '');
        });

        let rateLimitUntil = 0;
        let rateLimitTimer = null;

        function startCooldown() {
            const btn = document.getElementById('submitBtn');
            rateLimitUntil = Date.now() + 60000;
            btn.disabled = true;
            rateLimitTimer = setInterval(() => {
                const remaining = Math.ceil((rateLimitUntil - Date.now()) / 1000);
                if (remaining <= 0) {
                    clearInterval(rateLimitTimer);
                    rateLimitTimer = null;
                    btn.disabled = false;
                    btn.textContent = 'GENERATE QSL CARD';
                } else {
                    btn.textContent = 'PLEASE WAIT ' + remaining + 's';
                }
            }, 250);
        }

        document.getElementById('qslForm').addEventListener('submit', async function(e) {
            e.preventDefault();

            if (Date.now() < rateLimitUntil) return;

            const callsign = document.getElementById('callsign').value.trim();
            const qsoDate = document.getElementById('qso_date').value;
            const delivery = document.querySelector('input[name="delivery"]:checked').value;
            const email = document.getElementById('email').value.trim();
            const address = document.getElementById('address').value.trim();
            const format = document.querySelector('input[name="format"]:checked').value;
            const captchaVal = document.getElementById('captcha_answer').value.trim();

            if (!callsign || !qsoDate) {
                showStatus('error', 'Please enter both callsign and QSO date.');
                return;
            }
            if (delivery === 'email' && !email) {
                showStatus('error', 'Please enter an email address for delivery.');
                return;
            }
            if (delivery === 'postcard' && !address) {
                showStatus('error', 'Please enter a mailing address for the postcard.');
                return;
            }
            if (parseInt(captchaVal, 10) !== (captchaA + captchaB)) {
                showStatus('error', 'Incorrect answer to security check. Please try again.');
                newCaptcha();
                return;
            }

            const btn = document.getElementById('submitBtn');
            btn.disabled = true;
            btn.textContent = 'SEARCHING...';
            showStatus('loading', '<span class="spinner"></span> Querying QRZ.com logbook for QSO with ' + escHtml(callsign) + ' on ' + qsoDate + '...');
            hidePreview();
            hideDownloads();

            try {
                const formData = new FormData();
                formData.append('callsign', callsign);
                formData.append('qso_date', qsoDate);
                formData.append('delivery', delivery);
                formData.append('email', email);
                formData.append('address', address);
                formData.append('format', format);
                formData.append('captcha_a', captchaA);
                formData.append('captcha_b', captchaB);
                formData.append('captcha_answer', captchaVal);

                const resp = await fetch('generate.php', { method: 'POST', body: formData });
                const data = await resp.json();

                if (data.success) {
                    showStatus('success', data.message);
                    if (data.qso) showQsoPreview(data.qso);
                    if (data.downloads) showDownloads(data.downloads);
                } else {
                    showStatus('error', data.message || 'An error occurred.');
                }
            } catch (err) {
                showStatus('error', 'Connection error: ' + err.message);
            } finally {
                newCaptcha();
                startCooldown();
            }
        });

        function showStatus(type, html) {
            const area = document.getElementById('statusArea');
            const msg = document.getElementById('statusMsg');
            area.classList.add('visible');
            msg.className = 'status-msg ' + type;
            msg.innerHTML = html;
        }

        function hidePreview() { document.getElementById('qsoPreview').classList.remove('visible'); }
        function hideDownloads() { document.getElementById('downloadLinks').innerHTML = ''; }

        function showQsoPreview(qso) {
            const grid = document.getElementById('qsoGrid');
            grid.innerHTML = '';
            [
                ['Date', qso.date], ['Time', qso.time_on ? qso.time_on + ' (UTC)' : '-'], ['Band', qso.band],
                ['Mode', qso.mode], ['RST Sent', qso.rst_sent], ['RST Rcvd', qso.rst_rcvd],
                ['Frequency', qso.freq ? qso.freq + ' MHz' : '-'], ['Station', qso.call], ['Country', qso.country || '-']
            ].forEach(([label, value]) => {
                const div = document.createElement('div');
                div.className = 'qso-field';
                div.innerHTML = '<div class="label">' + escHtml(label) + '</div><div class="value">' + escHtml(value || '-') + '</div>';
                grid.appendChild(div);
            });
            document.getElementById('qsoPreview').classList.add('visible');
        }

        function showDownloads(downloads) {
            const c = document.getElementById('downloadLinks');
            c.innerHTML = '';
            downloads.forEach(d => {
                const a = document.createElement('a');
                a.href = d.url;
                a.target = '_blank';
                a.innerHTML = '<i class="material-icons" style="font-size:16px">' + (d.type === 'pdf' ? 'picture_as_pdf' : 'image') + '</i> Download ' + d.type.toUpperCase();
                c.appendChild(a);
            });
        }

        function escHtml(s) { const d = document.createElement('div'); d.textContent = s; return d.innerHTML; }
    </script>
</body>
</html>
