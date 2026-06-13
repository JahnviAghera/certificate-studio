<?php
require_once __DIR__ . '/../lib/bootstrap.php';

// If the POST body was larger than post_max_size, PHP discards it entirely:
// $_FILES and $_POST come back empty even though bytes were sent.
if (empty($_FILES) && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    fail('Image exceeded the server upload limit (post_max_size=' . ini_get('post_max_size') .
         '). It should have been optimised in the browser — try a smaller image, or raise the server limit.', 413);
}
if (empty($_FILES['background'])) {
    fail('No file uploaded.');
}
$f = $_FILES['background'];
if ($f['error'] !== UPLOAD_ERR_OK) {
    $msgs = [
        UPLOAD_ERR_INI_SIZE => 'Image is larger than the server allows (upload_max_filesize=' . ini_get('upload_max_filesize') . ').',
        UPLOAD_ERR_FORM_SIZE => 'Image is too large.',
        UPLOAD_ERR_PARTIAL   => 'Upload was interrupted — please retry.',
        UPLOAD_ERR_NO_FILE   => 'No file was received.',
        UPLOAD_ERR_NO_TMP_DIR => 'Server has no temp folder for uploads.',
        UPLOAD_ERR_CANT_WRITE => 'Server could not write the upload to disk.',
    ];
    fail($msgs[$f['error']] ?? ('Upload error code ' . $f['error']), 400);
}
if ($f['size'] > 15 * 1024 * 1024) {
    fail('Image too large (max 15 MB).');
}

$info = @getimagesize($f['tmp_name']);
if (!$info) {
    fail('Uploaded file is not an image.');
}
$ext = [
    IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png',
    IMAGETYPE_GIF => 'gif',  IMAGETYPE_WEBP => 'webp',
][$info[2]] ?? null;
if (!$ext) {
    fail('Unsupported image type. Use JPG, PNG, GIF or WEBP.');
}

$id   = bin2hex(random_bytes(8));
$name = "bg_{$id}.{$ext}";
$dest = UPLOAD_DIR . '/' . $name;
if (!move_uploaded_file($f['tmp_name'], $dest)) {
    fail('Could not save upload.', 500);
}

json_out([
    'ok'     => true,
    'file'   => $name,
    'url'    => 'uploads/' . $name,
    'width'  => $info[0],
    'height' => $info[1],
]);
