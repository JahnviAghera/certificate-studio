<?php
require_once __DIR__ . '/../lib/bootstrap.php';

// Download artefacts from a send batch:
//   ?batch=<id>&type=sent|failed|all   -> a .zip of the matching PDFs
//   ?batch=<id>&type=log               -> the log.csv
$batchId = preg_replace('/[^\w-]/', '', $_GET['batch'] ?? '');
$type    = $_GET['type'] ?? 'all';
$batchDir = OUTPUT_DIR . '/batch_' . $batchId;

if ($batchId === '' || !is_dir($batchDir)) {
    fail('Batch not found (it may have expired).', 404);
}

$logPath = $batchDir . '/log.csv';

// --- Plain CSV log ---
if ($type === 'log') {
    if (!is_file($logPath)) {
        fail('Log not found.', 404);
    }
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="certificate-log-' . $batchId . '.csv"');
    header('Content-Length: ' . filesize($logPath));
    readfile($logPath);
    exit;
}

// --- Work out which PDFs to include from the log ---
if (!is_file($logPath)) {
    fail('Log not found.', 404);
}
$wantSent = $type === 'sent' || $type === 'all';
$wantFail = $type === 'failed' || $type === 'all';

$pdfs = [];
$fh = fopen($logPath, 'r');
fgetcsv($fh, 0, ',', '"', ''); // header
while (($r = fgetcsv($fh, 0, ',', '"', '')) !== false) {
    // columns: row,name,email,status,error,pdf_file,timestamp
    $status = $r[3] ?? '';
    $pdf    = $r[5] ?? '';
    if ($pdf === '') {
        continue;
    }
    $isSent = ($status === 'sent');
    if (($isSent && $wantSent) || (!$isSent && $wantFail)) {
        $pdfs[] = $pdf;
    }
}
fclose($fh);

if (!$pdfs) {
    fail('No ' . ($type === 'sent' ? 'sent' : ($type === 'failed' ? 'unsent' : '')) . ' certificates to download.', 404);
}

$zipName = "certificates-{$type}-{$batchId}.zip";
$zipPath = tempnam(sys_get_temp_dir(), 'cz') . '.zip';
$zip = new ZipArchive();
if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    fail('Could not create the zip archive.', 500);
}
foreach ($pdfs as $pdf) {
    $path = $batchDir . '/' . basename($pdf);
    if (is_file($path)) {
        $zip->addFile($path, $pdf);
    }
}
$zip->addFile($logPath, 'log.csv');
$zip->close();

header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . $zipName . '"');
header('Content-Length: ' . filesize($zipPath));
readfile($zipPath);
@unlink($zipPath);
exit;
