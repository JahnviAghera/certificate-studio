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

$results = [];
$sent = 0;
foreach ($participants as $i => $p) {
    $to = trim($p['email'] ?? '');
    $row = ['row' => $i + 1, 'email' => $to];
    if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
        $results[] = $row + ['ok' => false, 'error' => 'Invalid email address.'];
        continue;
    }
    try {
        $pdfFile = OUTPUT_DIR . '/cert_' . bin2hex(random_bytes(6)) . '.pdf';
        $renderer->renderPdf($bgPath, $fields, $p, $pdfFile);

        $attachName = preg_replace('/[^\w .-]/', '_', $fillTpl($pdfNameTpl, $p)) . '.pdf';
        $subject = $fillTpl($subjectTpl, $p);
        $html    = $fillTpl($htmlTpl, $p);
        $name    = $p['name'] ?? '';

        $mailer->send($to, $name, $subject, $html, $pdfFile, $attachName);
        @unlink($pdfFile);

        $sent++;
        $results[] = $row + ['ok' => true];
    } catch (Throwable $e) {
        $results[] = $row + ['ok' => false, 'error' => $e->getMessage()];
    }
}

json_out(['ok' => true, 'sent' => $sent, 'total' => count($participants), 'results' => $results]);
