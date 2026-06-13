<?php
require_once __DIR__ . '/../lib/bootstrap.php';

@set_time_limit(0);

$body = json_decode(file_get_contents('php://input'), true);
if (!is_array($body)) {
    fail('Invalid request body.');
}

$bgFile = basename($body['background'] ?? '');
$bgPath = UPLOAD_DIR . '/' . $bgFile;
if ($bgFile === '' || !is_file($bgPath)) {
    fail('Background image not found. Upload one first.');
}

$fields       = $body['fields'] ?? [];
$participants = $body['participants'] ?? [];
$smtp         = $body['smtp'] ?? [];
$email        = $body['email'] ?? [];
$pdfNameTpl   = $body['pdfName'] ?? 'Certificate - {{name}}';

// Fall back to server-side .env credentials for any field left blank in the form.
$cfg = app_config();
$defaults = [
    'host'     => $cfg->get('SMTP_HOST'),
    'port'     => $cfg->get('SMTP_PORT'),
    'secure'   => $cfg->get('SMTP_SECURE'),
    'username' => $cfg->get('SMTP_USER'),
    'password' => $cfg->get('SMTP_PASS'),
    'from_name' => $cfg->get('SMTP_FROM_NAME'),
];
foreach ($defaults as $k => $v) {
    if (empty($smtp[$k]) && $v !== '') {
        $smtp[$k] = $v;
    }
}
if (empty($smtp['from_email'])) {
    $smtp['from_email'] = $smtp['username'] ?? '';
}

if (empty($participants)) {
    fail('No participants provided.');
}
$labels = ['host' => 'SMTP host', 'username' => 'sender email', 'password' => 'app password'];
foreach (['host', 'port', 'username', 'password'] as $k) {
    if (empty($smtp[$k])) {
        fail('Missing ' . ($labels[$k] ?? $k) . '. Enter it in the form or add it to your .env file.');
    }
}
$subjectTpl = $email['subject'] ?? 'Your Certificate';
$htmlTpl    = $email['html'] ?? '<p>Hi {{name}}, please find your certificate attached.</p>';

$fillTpl = function (string $tpl, array $vals): string {
    return preg_replace_callback('/\{\{\s*([\w.-]+)\s*\}\}/', function ($m) use ($vals) {
        return isset($vals[$m[1]]) ? (string)$vals[$m[1]] : '';
    }, $tpl);
};

$renderer = new CertRenderer(new GoogleFont(FONT_DIR));
$mailer   = new Mailer($smtp);

// One folder per send so the generated PDFs + log can be downloaded afterwards.
cleanup_old_batches(OUTPUT_DIR);
$batchId  = date('Ymd-His') . '-' . bin2hex(random_bytes(3));
$batchDir = OUTPUT_DIR . '/batch_' . $batchId;
@mkdir($batchDir, 0775, true);

$usedNames = [];
$results = [];
$sent = 0;
foreach ($participants as $i => $p) {
    $to   = trim($p['email'] ?? '');
    $name = trim($p['name'] ?? '');
    $row  = ['row' => $i + 1, 'name' => $name, 'email' => $to, 'pdf' => null, 'status' => 'failed', 'error' => ''];

    // Build a unique, safe PDF filename from the template.
    $base = preg_replace('/[^\w .-]/', '_', $fillTpl($pdfNameTpl, $p));
    $base = trim($base) !== '' ? $base : ('certificate_' . ($i + 1));
    $pdfFile = $base . '.pdf';
    $n = 2;
    while (isset($usedNames[strtolower($pdfFile)])) {
        $pdfFile = $base . " ({$n}).pdf";
        $n++;
    }
    $usedNames[strtolower($pdfFile)] = true;
    $pdfPath = $batchDir . '/' . $pdfFile;

    // 1) Always try to render the certificate (so "not sent" ones stay downloadable).
    try {
        $renderer->renderPdf($bgPath, $fields, $p, $pdfPath);
        $row['pdf'] = $pdfFile;
    } catch (Throwable $e) {
        $row['error'] = 'Render failed: ' . $e->getMessage();
        $results[] = $row;
        continue;
    }

    // 2) Then try to email it.
    if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
        $row['error'] = 'Invalid or missing email address.';
        $results[] = $row;
        continue;
    }
    try {
        $mailer->send($to, $name, $fillTpl($subjectTpl, $p), $fillTpl($htmlTpl, $p), $pdfPath, $pdfFile);
        $row['status'] = 'sent';
        $sent++;
    } catch (Throwable $e) {
        $row['error'] = $e->getMessage();
    }
    $results[] = $row;
}

// Write a complete CSV log into the batch folder.
$logPath = $batchDir . '/log.csv';
$fh = fopen($logPath, 'w');
fputcsv($fh, ['row', 'name', 'email', 'status', 'error', 'pdf_file', 'timestamp'], ',', '"', '');
$ts = date('c');
foreach ($results as $r) {
    fputcsv($fh, [$r['row'], $r['name'], $r['email'], $r['status'], $r['error'], $r['pdf'] ?? '', $ts], ',', '"', '');
}
fclose($fh);

json_out([
    'ok'      => true,
    'batch'   => $batchId,
    'sent'    => $sent,
    'failed'  => count($results) - $sent,
    'total'   => count($participants),
    'results' => $results,
]);
