<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/lib/config.php';

use setasign\Fpdi\Fpdi;

header('Content-Type: application/json');

// ---------------------------------------------------------------------------
// Config
// ---------------------------------------------------------------------------
define('QRZ_API_KEY', qsl_config('qrz_api_key', ''));
define('QRZ_API_URL', 'https://logbook.qrz.com/api');
define('TEMPLATE_PDF', __DIR__ . '/' . qsl_config('template_pdf', 'template/qsl-card-template.pdf'));
define('OUTPUT_DIR',  __DIR__ . '/output');
define('ORDER_EMAIL', qsl_config('order_email', 'qsl@example.com'));
define('LOG_EMAIL',   qsl_config('log_email')  ?: ORDER_EMAIL);
define('OPERATOR_CALLSIGN', qsl_config('operator_callsign', 'N0CALL'));
define('OPERATOR_NAME',     qsl_config('operator_name', 'Operator'));
define('OPERATOR_LOCATION', qsl_config('operator_location', ''));
define('OPERATOR_RIG',      qsl_config('operator_rig', ''));

if (QRZ_API_KEY === '' || QRZ_API_KEY === 'XXXX-XXXX-XXXX-XXXX') {
    jsonError('Server is not configured. Please set qrz_api_key in config.php.');
}

if (!is_dir(OUTPUT_DIR)) {
    mkdir(OUTPUT_DIR, 0775, true);
}

// ---------------------------------------------------------------------------
// Input validation
// ---------------------------------------------------------------------------
$callsign  = strtoupper(trim($_POST['callsign'] ?? ''));
$qsoDate   = trim($_POST['qso_date'] ?? '');
$delivery  = $_POST['delivery'] ?? 'download';
$email     = trim($_POST['email'] ?? '');
$address   = trim($_POST['address'] ?? '');
$format    = $_POST['format'] ?? 'pdf';

if ($callsign === '' || $qsoDate === '') {
    jsonError('Please provide both callsign and QSO date.');
}

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $qsoDate)) {
    jsonError('Invalid date format.');
}

if (!preg_match('/^[A-Z0-9\/]{3,12}$/', $callsign)) {
    jsonError('Invalid callsign format.');
}

if (!in_array($format, ['pdf', 'png'])) {
    jsonError('Invalid output format.');
}

if (!in_array($delivery, ['download', 'email', 'postcard'])) {
    jsonError('Invalid delivery method.');
}

if ($delivery === 'email' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    jsonError('Please provide a valid email address.');
}

if ($delivery === 'postcard' && $address === '') {
    jsonError('Please provide a mailing address for the postcard.');
}

// ---------------------------------------------------------------------------
// CAPTCHA verification (server-side)
// ---------------------------------------------------------------------------
$captchaA = (int)($_POST['captcha_a'] ?? 0);
$captchaB = (int)($_POST['captcha_b'] ?? 0);
$captchaAnswer = (int)($_POST['captcha_answer'] ?? -1);

if ($captchaA < 1 || $captchaB < 1 || $captchaA > 20 || $captchaB > 20) {
    jsonError('Invalid CAPTCHA. Please reload the page.');
}

if ($captchaAnswer !== ($captchaA + $captchaB)) {
    jsonError('Incorrect CAPTCHA answer.');
}

// ---------------------------------------------------------------------------
// Check if card already exists — skip API query if so
// ---------------------------------------------------------------------------
$adifDate = str_replace('-', '', $qsoDate);
$safeCall = preg_replace('/[^A-Z0-9]/', '', $callsign);
$safeDate = str_replace('-', '', $qsoDate);
$safeOp   = preg_replace('/[^A-Z0-9]/', '', strtoupper(OPERATOR_CALLSIGN));
$basename = "QSL_{$safeOp}_to_{$safeCall}_{$safeDate}";
$pdfPath = OUTPUT_DIR . "/{$basename}.pdf";
$pngPath = OUTPUT_DIR . "/{$basename}.png";

$wantPdf = ($format === 'pdf') || ($delivery === 'postcard');
$wantPng = ($format === 'png');

$cardExists = ($wantPdf && file_exists($pdfPath)) || ($wantPng && file_exists($pngPath));

if ($cardExists) {
    $downloads = [];
    if ($delivery !== 'postcard') {
        if ($wantPdf && file_exists($pdfPath)) {
            $downloads[] = ['type' => 'pdf', 'url' => 'output/' . basename($pdfPath)];
        }
        if ($wantPng && file_exists($pngPath)) {
            $downloads[] = ['type' => 'png', 'url' => 'output/' . basename($pngPath)];
        }
    }

    sendLogEmail($callsign, $qsoDate, $delivery, null, true);

    $msg = "QSO with {$callsign} confirmed! Your QSL card is ready (cached).";
    if ($delivery === 'postcard') {
        $msg = "QSO with {$callsign} confirmed! Your postcard request has been submitted.";
    } elseif ($delivery === 'email' && $email) {
        $emailSent = sendQslEmail($email, $callsign, $qsoDate, $pdfPath, $pngPath, $wantPdf, $wantPng);
        $msg = $emailSent
            ? "QSO with {$callsign} confirmed! Your QSL card has been sent to {$email}."
            : "QSO with {$callsign} confirmed! Your QSL card is ready (email delivery failed).";
        if (!$emailSent && file_exists($pdfPath)) {
            $downloads[] = ['type' => 'pdf', 'url' => 'output/' . basename($pdfPath)];
        }
    }

    echo json_encode([
        'success'   => true,
        'message'   => $msg,
        'qso'       => ['call' => $callsign, 'date' => formatQsoDate($adifDate), 'time_on' => '', 'band' => '', 'mode' => '', 'freq' => '', 'rst_sent' => '', 'rst_rcvd' => '', 'country' => '', 'name' => ''],
        'downloads' => $downloads,
    ]);
    exit;
}

// ---------------------------------------------------------------------------
// Query QRZ.com Logbook API
// ---------------------------------------------------------------------------
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL            => QRZ_API_URL,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => http_build_query([
        'KEY'    => QRZ_API_KEY,
        'ACTION' => 'FETCH',
        'OPTION' => 'TYPE:ADIF',
    ]),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 30,
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($response === false || $httpCode !== 200) {
    jsonError('Failed to connect to QRZ.com API. Please try again later.');
}

if (strpos($response, 'RESULT=FAIL') !== false) {
    jsonError('QRZ.com API error. Could not fetch logbook data.');
}

// ---------------------------------------------------------------------------
// Parse ADIF response and find matching QSO
// ---------------------------------------------------------------------------
$qsoRecords = parseAdif($response);
$matchedQso = null;

foreach ($qsoRecords as $qso) {
    $qsoCall    = strtoupper(trim($qso['call'] ?? ''));
    $qsoDateVal = trim($qso['qso_date'] ?? '');

    if ($qsoCall === $callsign && $qsoDateVal === $adifDate) {
        $matchedQso = $qso;
        break;
    }
}

if (!$matchedQso) {
    sendLogEmail($callsign, $qsoDate, $delivery, null, false, true);
    jsonError(
        "No QSO found with {$callsign} on {$qsoDate} in the logbook. " .
        "Please verify the callsign and date are correct."
    );
}

// ---------------------------------------------------------------------------
// Generate the QSL card PDF
// ---------------------------------------------------------------------------
generateQslPdf($matchedQso, $pdfPath);

if ($wantPng) {
    $cmd = sprintf(
        'pdftoppm -png -r 300 -singlefile %s %s 2>&1',
        escapeshellarg($pdfPath),
        escapeshellarg(OUTPUT_DIR . "/{$basename}")
    );
    exec($cmd, $out, $ret);
    if ($ret !== 0 || !file_exists($pngPath)) {
        $cmd2 = sprintf(
            'gs -dNOPAUSE -dBATCH -sDEVICE=png16m -r300 -sOutputFile=%s %s 2>&1',
            escapeshellarg($pngPath),
            escapeshellarg($pdfPath)
        );
        exec($cmd2, $out2, $ret2);
    }
}

sendLogEmail($callsign, $qsoDate, $delivery, $matchedQso);

// ---------------------------------------------------------------------------
// Delivery
// ---------------------------------------------------------------------------
$emailSent = false;
$postcardOrdered = false;

if ($delivery === 'email' && $email) {
    $emailSent = sendQslEmail($email, $callsign, $qsoDate, $pdfPath, $pngPath, $wantPdf, $wantPng);
}

if ($delivery === 'postcard') {
    $postcardOrdered = sendPostcardOrder($callsign, $qsoDate, $address, $matchedQso);
}

// ---------------------------------------------------------------------------
// Build response
// ---------------------------------------------------------------------------
$downloads = [];
if ($delivery !== 'postcard') {
    if ($wantPdf && file_exists($pdfPath)) {
        $downloads[] = ['type' => 'pdf', 'url' => 'output/' . basename($pdfPath)];
    }
    if ($wantPng && file_exists($pngPath)) {
        $downloads[] = ['type' => 'png', 'url' => 'output/' . basename($pngPath)];
    }
}

$dateFormatted = formatQsoDate($matchedQso['qso_date'] ?? '');
$timeOn = formatTime($matchedQso['time_on'] ?? '');

$msg = "QSO with {$callsign} confirmed!";
if ($delivery === 'download') {
    $msg .= " Your QSL card is ready for download.";
} elseif ($delivery === 'email') {
    if ($emailSent) {
        $msg .= " Your QSL card has been sent to {$email}.";
    } else {
        $msg .= " Your QSL card has been generated. (Email delivery failed — please download instead.)";
        if (file_exists($pdfPath)) {
            $downloads[] = ['type' => 'pdf', 'url' => 'output/' . basename($pdfPath)];
        }
    }
} elseif ($delivery === 'postcard') {
    if ($postcardOrdered) {
        $msg .= " Your postcard order has been submitted. The QSL card will be mailed to the address provided. 73!";
    } else {
        $msg .= " Your QSL card has been generated, but the postcard order could not be submitted. Please try again later.";
    }
}

echo json_encode([
    'success'   => true,
    'message'   => $msg,
    'qso'       => [
        'call'     => $matchedQso['call'] ?? '',
        'date'     => $dateFormatted,
        'time_on'  => $timeOn,
        'band'     => $matchedQso['band'] ?? '',
        'mode'     => $matchedQso['mode'] ?? '',
        'freq'     => $matchedQso['freq'] ?? '',
        'rst_sent' => $matchedQso['rst_sent'] ?? '',
        'rst_rcvd' => $matchedQso['rst_rcvd'] ?? '',
        'country'  => $matchedQso['country'] ?? '',
        'name'     => $matchedQso['name'] ?? '',
    ],
    'downloads' => $downloads,
]);
exit;

// ===========================================================================
// Functions
// ===========================================================================

function jsonError(string $msg): void
{
    echo json_encode(['success' => false, 'message' => $msg]);
    exit;
}

function parseAdif(string $raw): array
{
    if (preg_match('/ADIF=(.+)/s', $raw, $m)) {
        $adifRaw = $m[1];
    } else {
        $adifRaw = $raw;
    }

    $adifRaw = html_entity_decode($adifRaw, ENT_QUOTES | ENT_HTML5, 'UTF-8');

    $recordBlocks = preg_split('/<eor>/i', $adifRaw);
    $records = [];

    foreach ($recordBlocks as $block) {
        $block = trim($block);
        if ($block === '') continue;

        $record = [];
        if (preg_match_all('/<([^:>]+):(\d+)>([^<]*)/i', $block, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $fieldName = strtolower(trim($match[1]));
                $length    = (int)$match[2];
                $value     = substr(trim($match[3]), 0, $length);
                $record[$fieldName] = $value;
            }
        }

        if (!empty($record) && isset($record['call'])) {
            $records[] = $record;
        }
    }

    return $records;
}

/**
 * Generate the QSL card PDF by overlaying QSO data on the template.
 *
 * Field coordinates (mm) are calibrated for the bundled template PDF.
 * Adjust SetXY() values if you swap in a different template.
 */
function generateQslPdf(array $qso, string $outputPath): void
{
    $pdf = new Fpdi();
    $pdf->setSourceFile(TEMPLATE_PDF);
    $tplId = $pdf->importPage(1);
    $size  = $pdf->getTemplateSize($tplId);

    $pdf->AddPage('L', [$size['width'], $size['height']]);
    $pdf->useTemplate($tplId, 0, 0, $size['width'], $size['height']);

    $dateStr     = formatQsoDateForCard($qso['qso_date'] ?? '');
    $timeStr     = formatTime($qso['time_on'] ?? '');
    $band        = strtoupper($qso['band'] ?? '');
    $mode        = strtoupper($qso['mode'] ?? '');
    $rstSent     = $qso['rst_sent'] ?? '';
    $rig         = OPERATOR_RIG;
    $station     = strtoupper($qso['call'] ?? '');
    $modeDisplay = $mode . (str_starts_with($mode, 'FT') ? ' (8-GFSK)' : '');

    $pdf->SetFont('Helvetica', 'B', 7.5);
    $pdf->SetTextColor(20, 20, 80);

    $pdf->SetXY(155.9, 99.2);
    $pdf->Cell(93, 5, $dateStr, 0, 0, 'L');

    $pdf->SetXY(151.37, 104.51);
    $pdf->Cell(26, 5, $timeStr, 0, 0, 'L');

    $pdf->SetXY(177.28, 104.60);
    $pdf->Cell(24, 5, $band, 0, 0, 'L');

    $pdf->SetXY(159.42, 109.5);
    $pdf->Cell(80, 5, $modeDisplay, 0, 0, 'L');

    $rstRcvd    = $qso['rst_rcvd'] ?? '';
    $rstDisplay = $rstRcvd . '/' . $rstSent;
    $pdf->SetXY(155.35, 114.32);
    $pdf->Cell(40, 5, $rstDisplay, 0, 0, 'L');

    $pdf->SetXY(176.07, 114.4);
    $pdf->Cell(50, 5, $rig, 0, 0, 'L');

    $country      = $qso['country'] ?? '';
    $countryShort = $country !== '' ? substr($country, 0, 20) : '';
    $grid         = $qso['gridsquare'] ?? '';
    $stationDisplay = $station;
    if ($countryShort !== '') $stationDisplay .= ' ' . $countryShort;
    if ($grid !== '')         $stationDisplay .= ' (' . $grid . ')';
    $pdf->SetXY(157.71, 119.63);
    $pdf->Cell(81, 5, $stationDisplay, 0, 0, 'L');

    $modeCategory = getModeCategory($mode);
    $checkboxPositions = [
        'CW'    => 172.19,
        'SSB'   => 186.19,
        'DATA'  => 204.19,
        'OTHER' => 222.19,
    ];

    if (isset($checkboxPositions[$modeCategory])) {
        $pdf->SetFont('ZapfDingbats', '', 7);
        $pdf->SetTextColor(20, 20, 80);
        $pdf->SetXY($checkboxPositions[$modeCategory], 125.47);
        $pdf->Cell(4, 4, '4', 0, 0, 'C');
    }

    $pdf->Output('F', $outputPath);
}

function getModeCategory(string $mode): string
{
    $mode = strtoupper($mode);
    $cw   = ['CW'];
    $ssb  = ['SSB', 'LSB', 'USB', 'AM', 'FM'];
    $data = ['FT8', 'FT4', 'JT65', 'JT9', 'PSK31', 'PSK63', 'RTTY', 'OLIVIA',
             'JS8', 'WSPR', 'MSK144', 'Q65', 'SSTV', 'MFSK'];

    if (in_array($mode, $cw))   return 'CW';
    if (in_array($mode, $ssb))  return 'SSB';
    if (in_array($mode, $data)) return 'DATA';
    return 'OTHER';
}

function formatQsoDateForCard(string $adifDate): string
{
    if (strlen($adifDate) !== 8) return $adifDate;
    $ts = mktime(0, 0, 0,
        (int)substr($adifDate, 4, 2),
        (int)substr($adifDate, 6, 2),
        (int)substr($adifDate, 0, 4)
    );
    return strtoupper(date('D', $ts)) . ' ' . date('d/m/Y', $ts);
}

function formatQsoDate(string $adifDate): string
{
    if (strlen($adifDate) !== 8) return $adifDate;
    return substr($adifDate, 0, 4) . '-' . substr($adifDate, 4, 2) . '-' . substr($adifDate, 6, 2);
}

function formatTime(string $t): string
{
    $t = str_pad($t, 4, '0', STR_PAD_LEFT);
    return substr($t, 0, 2) . ':' . substr($t, 2, 2);
}

function sendQslEmail(string $to, string $callsign, string $date, string $pdfPath, string $pngPath, bool $attachPdf, bool $attachPng): bool
{
    $boundary = md5(uniqid(time()));
    $eol = "\r\n";
    $op  = OPERATOR_CALLSIGN;

    $subject = "Your QSL Card from {$op} - QSO on {$date}";

    $headers  = "From: {$op} QSL Service <" . ORDER_EMAIL . ">" . $eol;
    $headers .= "MIME-Version: 1.0" . $eol;
    $headers .= "Content-Type: multipart/mixed; boundary=\"{$boundary}\"" . $eol;

    $body  = "--{$boundary}" . $eol;
    $body .= "Content-Type: text/plain; charset=UTF-8" . $eol;
    $body .= "Content-Transfer-Encoding: 8bit" . $eol . $eol;
    $body .= "Hello {$callsign}," . $eol . $eol;
    $body .= "Please find attached your QSL card confirming our QSO on {$date}." . $eol;
    $body .= "Thank you for the contact!" . $eol . $eol;
    $body .= "73 de {$op}" . $eol;
    if (OPERATOR_NAME !== '')     $body .= OPERATOR_NAME . $eol;
    if (OPERATOR_LOCATION !== '') $body .= OPERATOR_LOCATION . $eol;

    if ($attachPdf && file_exists($pdfPath)) {
        $body .= "--{$boundary}" . $eol;
        $body .= "Content-Type: application/pdf; name=\"" . basename($pdfPath) . "\"" . $eol;
        $body .= "Content-Transfer-Encoding: base64" . $eol;
        $body .= "Content-Disposition: attachment; filename=\"" . basename($pdfPath) . "\"" . $eol . $eol;
        $body .= chunk_split(base64_encode(file_get_contents($pdfPath))) . $eol;
    }

    if ($attachPng && file_exists($pngPath)) {
        $body .= "--{$boundary}" . $eol;
        $body .= "Content-Type: image/png; name=\"" . basename($pngPath) . "\"" . $eol;
        $body .= "Content-Transfer-Encoding: base64" . $eol;
        $body .= "Content-Disposition: attachment; filename=\"" . basename($pngPath) . "\"" . $eol . $eol;
        $body .= chunk_split(base64_encode(file_get_contents($pngPath))) . $eol;
    }

    $body .= "--{$boundary}--" . $eol;

    return @mail($to, $subject, $body, $headers);
}

function sendPostcardOrder(string $callsign, string $date, string $address, array $qso): bool
{
    $eol = "\r\n";
    $op  = OPERATOR_CALLSIGN;

    $subject = "QSL Postcard Order: {$callsign} - QSO on {$date}";

    $headers  = "From: {$op} QSL Service <" . ORDER_EMAIL . ">" . $eol;
    $headers .= "Reply-To: " . ORDER_EMAIL . $eol;
    $headers .= "Content-Type: text/plain; charset=UTF-8" . $eol;

    $body  = "NEW QSL POSTCARD ORDER" . $eol;
    $body .= "======================" . $eol . $eol;
    $body .= "Callsign:  {$callsign}" . $eol;
    $body .= "QSO Date:  {$date}" . $eol;
    $body .= "Time:      " . formatTime($qso['time_on'] ?? '') . " UTC" . $eol;
    $body .= "Band:      " . ($qso['band'] ?? '') . $eol;
    $body .= "Mode:      " . ($qso['mode'] ?? '') . $eol;
    $body .= "Frequency: " . ($qso['freq'] ?? '') . " MHz" . $eol;
    $body .= "RST Sent:  " . ($qso['rst_sent'] ?? '') . $eol;
    $body .= "RST Rcvd:  " . ($qso['rst_rcvd'] ?? '') . $eol;
    $body .= "Name:      " . ($qso['name'] ?? '') . $eol;
    $body .= "Country:   " . ($qso['country'] ?? '') . $eol . $eol;
    $body .= "MAILING ADDRESS" . $eol;
    $body .= "---------------" . $eol;
    $body .= $address . $eol . $eol;
    $body .= "-- QSL Card Validator" . $eol;

    return @mail(ORDER_EMAIL, $subject, $body, $headers);
}

function sendLogEmail(string $callsign, string $date, string $delivery, ?array $qso = null, bool $cached = false, bool $notFound = false): void
{
    $eol = "\r\n";
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    $time = date('Y-m-d H:i:s T');
    $op  = OPERATOR_CALLSIGN;

    $status = $notFound ? 'NOT FOUND' : ($cached ? 'CACHED' : 'NEW');
    $subject = "QSL Log: {$callsign} on {$date} [{$status}]";

    $headers  = "From: {$op} QSL Service <" . ORDER_EMAIL . ">" . $eol;
    $headers .= "Content-Type: text/plain; charset=UTF-8" . $eol;

    $body  = "QSL-CARD-VALIDATOR USAGE LOG" . $eol;
    $body .= "============================" . $eol . $eol;
    $body .= "Time:      {$time}" . $eol;
    $body .= "Callsign:  {$callsign}" . $eol;
    $body .= "QSO Date:  {$date}" . $eol;
    $body .= "Delivery:  {$delivery}" . $eol;
    $body .= "Cached:    " . ($cached ? 'Yes' : 'No') . $eol;
    $body .= "IP:        {$ip}" . $eol;
    $body .= "Browser:   {$ua}" . $eol;

    if ($qso && !$cached) {
        $body .= $eol . "QSO DETAILS" . $eol;
        $body .= "-----------" . $eol;
        $body .= "Band:      " . ($qso['band'] ?? '') . $eol;
        $body .= "Mode:      " . ($qso['mode'] ?? '') . $eol;
        $body .= "Time:      " . formatTime($qso['time_on'] ?? '') . " UTC" . $eol;
        $body .= "RST Sent:  " . ($qso['rst_sent'] ?? '') . $eol;
        $body .= "RST Rcvd:  " . ($qso['rst_rcvd'] ?? '') . $eol;
        $body .= "Name:      " . ($qso['name'] ?? '') . $eol;
        $body .= "Country:   " . ($qso['country'] ?? '') . $eol;
    }

    @mail(LOG_EMAIL, $subject, $body, $headers);
}
