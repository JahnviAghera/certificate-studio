<?php
require_once __DIR__ . '/GoogleFont.php';
require_once __DIR__ . '/../libs/fpdf.php';

/**
 * Renders a certificate by drawing dynamic text fields onto a background
 * image with GD (using real TrueType fonts), then wrapping the result in a
 * one-page PDF sized to the image.
 */
class CertRenderer
{
    private GoogleFont $fonts;

    public function __construct(GoogleFont $fonts)
    {
        $this->fonts = $fonts;
    }

    /**
     * @param string $bgPath   path to background jpg/png
     * @param array  $fields   list of field definitions (see designer UI)
     * @param array  $values   placeholder values e.g. ['name' => 'Jane Doe']
     * @return resource|\GdImage GD image with text drawn
     */
    public function renderImage(string $bgPath, array $fields, array $values)
    {
        $img = $this->loadImage($bgPath);
        $W = imagesx($img);
        $H = imagesy($img);

        foreach ($fields as $f) {
            $text = $this->fill($f['text'] ?? '', $values);
            if ($text === '') {
                continue;
            }
            $family = $f['font'] ?? 'Roboto';
            $weight = (int)($f['weight'] ?? 400);
            $italic = !empty($f['italic']);
            $ttf    = $this->fonts->getTtfPath($family, $weight, $italic);

            $sizePx = (float)($f['size'] ?? 40);
            $sizePt = $sizePx * 0.75;               // GD/FreeType wants points
            $angle  = 0.0;
            [$r, $g, $b] = $this->hex2rgb($f['color'] ?? '#000000');
            $color = imagecolorallocate($img, $r, $g, $b);

            $anchorX = (float)($f['x'] ?? 0.5) * $W;
            $anchorY = (float)($f['y'] ?? 0.5) * $H;
            $align   = $f['align'] ?? 'center';

            $bbox = imagettfbbox($sizePt, $angle, $ttf, $text);
            $textW = $bbox[2] - $bbox[0];
            // Horizontal alignment relative to the anchor point.
            switch ($align) {
                case 'left':   $drawX = $anchorX; break;
                case 'right':  $drawX = $anchorX - $textW; break;
                default:       $drawX = $anchorX - $textW / 2; break; // center
            }
            $drawX -= $bbox[0];
            // Vertically centre the glyph box on the anchor.
            $top = $bbox[7];
            $bottom = $bbox[1];
            $drawY = $anchorY - ($top + $bottom) / 2;

            imagettftext($img, $sizePt, $angle, (int)round($drawX), (int)round($drawY), $color, $ttf, $text);
        }

        return $img;
    }

    /** Render to a PDF file sized to the background image. */
    public function renderPdf(string $bgPath, array $fields, array $values, string $outPdfPath): void
    {
        $img = $this->renderImage($bgPath, $fields, $values);
        $W = imagesx($img);
        $H = imagesy($img);

        $tmpPng = tempnam(sys_get_temp_dir(), 'cert') . '.png';
        imagepng($img, $tmpPng);
        @imagedestroy($img);

        $orientation = $W >= $H ? 'L' : 'P';
        $pdf = new FPDF($orientation, 'pt', [$W, $H]);
        $pdf->AddPage();
        $pdf->Image($tmpPng, 0, 0, $W, $H, 'PNG');
        $pdf->Output('F', $outPdfPath);
        @unlink($tmpPng);
    }

    /** Render to a PNG file (used by the live preview endpoint). */
    public function renderPng(string $bgPath, array $fields, array $values, string $outPngPath): void
    {
        $img = $this->renderImage($bgPath, $fields, $values);
        imagepng($img, $outPngPath);
        @imagedestroy($img);
    }

    private function loadImage(string $path)
    {
        $info = @getimagesize($path);
        if (!$info) {
            throw new RuntimeException('Background is not a valid image.');
        }
        switch ($info[2]) {
            case IMAGETYPE_JPEG: return imagecreatefromjpeg($path);
            case IMAGETYPE_PNG:  return imagecreatefrompng($path);
            case IMAGETYPE_GIF:  return imagecreatefromgif($path);
            case IMAGETYPE_WEBP: return imagecreatefromwebp($path);
            default: throw new RuntimeException('Unsupported image type. Use JPG, PNG, GIF or WEBP.');
        }
    }

    private function fill(string $tpl, array $values): string
    {
        return preg_replace_callback('/\{\{\s*([\w.-]+)\s*\}\}/', function ($m) use ($values) {
            return isset($values[$m[1]]) ? (string)$values[$m[1]] : '';
        }, $tpl);
    }

    private function hex2rgb(string $hex): array
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        return [
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
        ];
    }
}
