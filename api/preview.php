<?php
require_once __DIR__ . '/../lib/bootstrap.php';

$body = json_decode(file_get_contents('php://input'), true);
if (!is_array($body)) {
    fail('Invalid request body.');
}

$bgFile = basename($body['background'] ?? '');
$bgPath = UPLOAD_DIR . '/' . $bgFile;
if ($bgFile === '' || !is_file($bgPath)) {
    fail('Background image not found. Upload one first.');
}

$fields = $body['fields'] ?? [];
$values = $body['values'] ?? [];
if (empty($values)) {
    // Fall back to sample text so the preview shows something.
    foreach ($fields as $f) {
        if (preg_match('/\{\{\s*([\w.-]+)\s*\}\}/', $f['text'] ?? '', $m)) {
            $values[$m[1]] = ucfirst($m[1]);
        }
    }
}

try {
    $renderer = new CertRenderer(new GoogleFont(FONT_DIR));
    $name = 'preview_' . bin2hex(random_bytes(6)) . '.png';
    $out  = OUTPUT_DIR . '/' . $name;
    $renderer->renderPng($bgPath, $fields, $values, $out);
    json_out(['ok' => true, 'url' => 'output/' . $name . '?t=' . time()]);
} catch (Throwable $e) {
    fail($e->getMessage(), 500);
}
