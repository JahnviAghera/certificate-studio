<?php
require_once __DIR__ . '/../lib/bootstrap.php';

if (empty($_FILES['background'])) {
    fail('No file uploaded.');
}
$f = $_FILES['background'];
if ($f['error'] !== UPLOAD_ERR_OK) {
    fail('Upload error code ' . $f['error']);
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
