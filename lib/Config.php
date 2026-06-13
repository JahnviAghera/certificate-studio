<?php
/**
 * Loads optional SMTP defaults from a .env file in the project root so the
 * sender doesn't have to retype credentials every time.
 *
 * Two formats are accepted:
 *
 *   1) KEY=VALUE lines:
 *        SMTP_USER=you@gmail.com
 *        SMTP_PASS=abcd efgh ijkl mnop
 *        SMTP_HOST=smtp.gmail.com
 *        SMTP_PORT=587
 *        SMTP_SECURE=tls
 *        SMTP_FROM_NAME=Acme Academy
 *
 *   2) A simple positional shorthand — first line the email, second the
 *      app password:
 *        you@gmail.com
 *        abcd efgh ijkl mnop
 *
 * The file is git-ignored and the password is never sent to the browser.
 */
class Config
{
    private array $data;

    public function __construct(string $envPath)
    {
        $this->data = self::parse($envPath);
    }

    private static function parse(string $path): array
    {
        $out = [
            'SMTP_HOST' => 'smtp.gmail.com',
            'SMTP_PORT' => '587',
            'SMTP_SECURE' => 'tls',
            'SMTP_USER' => '',
            'SMTP_PASS' => '',
            'SMTP_FROM_NAME' => '',
            'SENDGRID_API_KEY' => '',
            'SENDGRID_FROM' => '',
            'SENDGRID_FROM_NAME' => '',
        ];
        if (!is_file($path)) {
            return $out;
        }
        $positional = [];
        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') {
                continue;
            }
            if (strpos($line, '=') !== false) {
                [$k, $v] = explode('=', $line, 2);
                $k = strtoupper(trim($k));
                $v = trim(trim($v), "\"'");
                if (array_key_exists($k, $out)) {
                    $out[$k] = $v;
                }
            } else {
                $positional[] = $line;
            }
        }
        // Positional shorthand: email then password.
        if ($out['SMTP_USER'] === '' && isset($positional[0]) && filter_var($positional[0], FILTER_VALIDATE_EMAIL)) {
            $out['SMTP_USER'] = $positional[0];
            if ($out['SMTP_PASS'] === '' && isset($positional[1])) {
                $out['SMTP_PASS'] = $positional[1];
            }
        }
        // Allow real environment variables to override the file (handy on Render).
        foreach (array_keys($out) as $k) {
            $env = getenv($k);
            if ($env !== false && $env !== '') {
                $out[$k] = $env;
            }
        }
        return $out;
    }

    public function get(string $key, string $default = ''): string
    {
        return $this->data[$key] ?? $default;
    }

    /** Form-prefill payload — everything EXCEPT secrets (passwords / API keys). */
    public function publicDefaults(): array
    {
        $hasSendgrid = $this->get('SENDGRID_API_KEY') !== '';
        return [
            'host'      => $this->get('SMTP_HOST'),
            'port'      => $this->get('SMTP_PORT'),
            'secure'    => $this->get('SMTP_SECURE'),
            'username'  => $this->get('SMTP_USER'),
            'from_name' => $this->get('SMTP_FROM_NAME'),
            'hasServerPassword' => $this->get('SMTP_PASS') !== '',
            // SendGrid
            'hasSendgridKey' => $hasSendgrid,
            'sendgridFrom'   => $this->get('SENDGRID_FROM'),
            'sendgridFromName' => $this->get('SENDGRID_FROM_NAME'),
            // Default to whichever is configured (SendGrid wins on Render).
            'defaultMethod'  => $hasSendgrid ? 'sendgrid' : 'smtp',
        ];
    }
}
