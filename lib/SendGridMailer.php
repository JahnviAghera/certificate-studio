<?php
/**
 * Sends mail through the SendGrid v3 HTTP API (https://api.sendgrid.com).
 * Uses HTTPS (port 443), so it works on hosts like Render that block the
 * SMTP ports. Drop-in compatible with Mailer::send().
 *
 * The From address must be a SendGrid "verified sender" or belong to an
 * authenticated domain, otherwise SendGrid rejects the request.
 */
class SendGridMailer
{
    private string $apiKey;
    private string $fromEmail;
    private string $fromName;

    public function __construct(string $apiKey, string $fromEmail, string $fromName = '')
    {
        $this->apiKey    = $apiKey;
        $this->fromEmail = $fromEmail;
        $this->fromName  = $fromName;
    }

    public function send(string $toEmail, string $toName, string $subject, string $htmlBody, string $attachmentPath, string $attachmentName): void
    {
        $payload = [
            'personalizations' => [[
                'to' => [['email' => $toEmail, 'name' => $toName ?: $toEmail]],
            ]],
            'from'    => ['email' => $this->fromEmail, 'name' => $this->fromName ?: $this->fromEmail],
            'subject' => $subject !== '' ? $subject : '(no subject)',
            'content' => [['type' => 'text/html', 'value' => $htmlBody !== '' ? $htmlBody : ' ']],
        ];

        if ($attachmentPath !== '' && is_file($attachmentPath)) {
            $payload['attachments'] = [[
                'content'     => base64_encode(file_get_contents($attachmentPath)),
                'type'        => 'application/pdf',
                'filename'    => $attachmentName,
                'disposition' => 'attachment',
            ]];
        }

        $ch = curl_init('https://api.sendgrid.com/v3/mail/send');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $this->apiKey,
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS => json_encode($payload),
        ]);
        $res  = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);

        if ($res === false) {
            throw new RuntimeException('SendGrid request failed: ' . $err);
        }
        // SendGrid returns 202 Accepted on success.
        if ($code < 200 || $code >= 300) {
            $msg = "SendGrid error (HTTP {$code})";
            $j = json_decode($res, true);
            if (isset($j['errors'][0]['message'])) {
                $msg .= ': ' . $j['errors'][0]['message'];
            }
            throw new RuntimeException($msg);
        }
    }
}
