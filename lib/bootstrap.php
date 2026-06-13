<?php
/** Shared paths + small helpers. */
// Deprecation notices (e.g. PHP 8.5 changes) must never leak into JSON/binary
// responses and break the headers. Real warnings/errors still show.
error_reporting(E_ALL & ~E_DEPRECATED);

define('BASE_DIR', dirname(__DIR__));
define('UPLOAD_DIR', BASE_DIR . '/uploads');
define('FONT_DIR',   BASE_DIR . '/fonts');
define('OUTPUT_DIR', BASE_DIR . '/output');

foreach ([UPLOAD_DIR, FONT_DIR, OUTPUT_DIR] as $d) {
    if (!is_dir($d)) {
        mkdir($d, 0775, true);
    }
}

require_once __DIR__ . '/GoogleFont.php';
require_once __DIR__ . '/CertRenderer.php';
require_once __DIR__ . '/Mailer.php';
require_once __DIR__ . '/Config.php';

define('ENV_PATH', BASE_DIR . '/.env');

function app_config(): Config
{
    static $cfg = null;
    if ($cfg === null) {
        $cfg = new Config(ENV_PATH);
    }
    return $cfg;
}

function json_out($data, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function fail(string $msg, int $code = 400): void
{
    json_out(['ok' => false, 'error' => $msg], $code);
}

/** Remove send batches older than $maxAgeHours so output/ doesn't grow forever. */
function cleanup_old_batches(string $outputDir, int $maxAgeHours = 24): void
{
    foreach (glob($outputDir . '/batch_*') ?: [] as $dir) {
        if (is_dir($dir) && (time() - filemtime($dir)) > $maxAgeHours * 3600) {
            foreach (glob($dir . '/*') ?: [] as $f) {
                @unlink($f);
            }
            @rmdir($dir);
        }
    }
}

/** A curated list of popular Google Fonts shown in the designer dropdown. */
function google_font_list(): array
{
    return [
        'Roboto', 'Open Sans', 'Lato', 'Montserrat', 'Poppins', 'Oswald',
        'Raleway', 'Merriweather', 'Playfair Display', 'Lora', 'Nunito',
        'PT Serif', 'Source Sans 3', 'Cormorant Garamond', 'EB Garamond',
        'Dancing Script', 'Pacifico', 'Great Vibes', 'Sacramento',
        'Allura', 'Parisienne', 'Tangerine', 'Alex Brush', 'Cinzel',
        'Cinzel Decorative', 'Marcellus', 'Cardo', 'Libre Baskerville',
        'Crimson Text', 'Josefin Sans', 'Quicksand', 'Bebas Neue',
        'Abril Fatface', 'Yeseva One', 'Rozha One', 'Old Standard TT',
    ];
}
