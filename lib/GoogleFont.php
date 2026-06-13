<?php
/**
 * Fetches and caches TrueType (.ttf) files for Google Fonts so they can be
 * used by PHP's GD imagettftext(). The browser previews the same fonts via
 * the normal Google Fonts <link> stylesheet, so preview and output match.
 */
class GoogleFont
{
    private string $cacheDir;

    public function __construct(string $cacheDir)
    {
        $this->cacheDir = rtrim($cacheDir, '/');
        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0775, true);
        }
    }

    /**
     * Returns an absolute path to a cached .ttf for the given family/weight,
     * downloading it from Google if needed.
     *
     * @param string $family e.g. "Roboto", "Open Sans"
     * @param int    $weight e.g. 400, 700
     * @param bool   $italic
     */
    public function getTtfPath(string $family, int $weight = 400, bool $italic = false): string
    {
        $slug = $this->slug($family, $weight, $italic);
        $path = "{$this->cacheDir}/{$slug}.ttf";
        if (is_file($path) && filesize($path) > 0) {
            return $path;
        }

        $url = $this->resolveTtfUrl($family, $weight, $italic);
        $data = $this->httpGet($url);
        if ($data === null || strlen($data) < 1000) {
            throw new RuntimeException("Could not download TTF for font '{$family}'.");
        }
        file_put_contents($path, $data);
        return $path;
    }

    /**
     * Find a real .ttf for the family in the google/fonts GitHub repo.
     * Google's web CSS only serves woff2 (modern UA) or EOT (legacy UA),
     * neither of which GD can read — but the repo ships static/variable TTFs.
     */
    private function resolveTtfUrl(string $family, int $weight, bool $italic): string
    {
        $slug = preg_replace('/[^a-z0-9]/', '', strtolower($family));
        $files = null;
        foreach (['ofl', 'apache', 'ufl'] as $license) {
            $api = "https://api.github.com/repos/google/fonts/contents/{$license}/{$slug}";
            $json = $this->httpGet($api, 'cert-studio');
            $data = $json ? json_decode($json, true) : null;
            if (is_array($data) && isset($data[0]['name'])) {
                $files = $data;
                break;
            }
        }
        if (!$files) {
            throw new RuntimeException("Font '{$family}' was not found in the Google Fonts library.");
        }

        // Collect .ttf entries as name => download_url.
        $ttf = [];
        foreach ($files as $f) {
            if (isset($f['name'], $f['download_url']) && str_ends_with(strtolower($f['name']), '.ttf')) {
                $ttf[$f['name']] = $f['download_url'];
            }
        }
        if (!$ttf) {
            throw new RuntimeException("No TrueType file available for '{$family}'.");
        }

        // 1) Prefer an exact static weight/style match, e.g. "Roboto-Bold.ttf".
        $style = $this->styleName($weight, $italic);
        foreach ($ttf as $name => $url) {
            if (preg_match('/-' . preg_quote($style, '/') . '\.ttf$/i', $name)) {
                return $url;
            }
        }
        // 2) Otherwise pick a variable font of the right italic-ness (default
        //    instance is used; GD can't vary the weight axis).
        foreach ($ttf as $name => $url) {
            $isItalic = stripos($name, 'italic') !== false;
            if (strpos($name, '[') !== false && $isItalic === $italic) {
                return $url;
            }
        }
        // 3) Fall back to any Regular, then anything.
        foreach ($ttf as $name => $url) {
            if (stripos($name, 'regular') !== false && stripos($name, 'italic') === false) {
                return $url;
            }
        }
        return reset($ttf);
    }

    /** Google Fonts static-file style suffix for a numeric weight + italic. */
    private function styleName(int $weight, bool $italic): string
    {
        $names = [
            100 => 'Thin', 200 => 'ExtraLight', 300 => 'Light', 400 => 'Regular',
            500 => 'Medium', 600 => 'SemiBold', 700 => 'Bold', 800 => 'ExtraBold',
            900 => 'Black',
        ];
        $base = $names[$weight] ?? 'Regular';
        if ($italic) {
            return $base === 'Regular' ? 'Italic' : $base . 'Italic';
        }
        return $base;
    }

    private function httpGet(string $url, ?string $userAgent = null): ?string
    {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_TIMEOUT        => 20,
                CURLOPT_USERAGENT      => $userAgent ?? 'Mozilla/5.0',
            ]);
            $res = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            unset($ch);
            return ($res !== false && $code >= 200 && $code < 400) ? $res : null;
        }
        $ctx = stream_context_create(['http' => [
            'timeout' => 20,
            'header'  => 'User-Agent: ' . ($userAgent ?? 'Mozilla/5.0'),
        ]]);
        $res = @file_get_contents($url, false, $ctx);
        return $res === false ? null : $res;
    }

    private function slug(string $family, int $weight, bool $italic): string
    {
        $base = preg_replace('/[^a-z0-9]+/i', '-', strtolower(trim($family)));
        return trim($base, '-') . "-{$weight}" . ($italic ? 'i' : '');
    }
}
