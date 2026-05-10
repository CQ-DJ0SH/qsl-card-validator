<?php
/**
 * QSL Card Validator — example configuration.
 *
 * Copy this file to "config.php" and adjust the values below.
 * config.php is loaded by generate.php and index.php.
 *
 * SECURITY: never commit config.php — it is listed in .gitignore.
 */

return [
    // QRZ.com Logbook API key (per-logbook, not your account password).
    // Generate at https://logbook.qrz.com/ → Settings → Web Services.
    'qrz_api_key' => 'XXXX-XXXX-XXXX-XXXX',

    // Operator details (used in card text, emails, page UI).
    'operator_callsign' => 'N0CALL',
    'operator_name'     => 'Your Name',
    'operator_location' => 'Your City, Your Country',

    // Equipment string printed on the QSL card (free text).
    'operator_rig' => 'YAESU FT-950',

    // Outgoing email used for QSL delivery, postcard orders and usage logs.
    // The SMTP server (e.g. local Postfix) must accept mail from this address.
    'order_email' => 'qsl@example.com',

    // Optional separate logging recipient. Leave null to reuse order_email.
    'log_email' => null,

    // Path (relative to project root) to the QSL card PDF template.
    // Keep this PDF in template/. Field positions in generate.php
    // are calibrated for the bundled template — adjust them if you swap it out.
    'template_pdf' => 'template/qsl-card-template.pdf',

    // Branding shown on the page (HTML allowed for the callsign with Ø etc.).
    'site_brand_callsign' => 'N0CALL',

    // Optional: links shown below the form. Set to null to hide a button.
    'back_url'     => 'https://example.com',
    'back_label'   => 'Home',
    'qrz_url'      => 'https://www.qrz.com',
];
