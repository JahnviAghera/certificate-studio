<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../libs/PHPMailer/Exception.php';
require_once __DIR__ . '/../libs/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/../libs/PHPMailer/SMTP.php';

/**
 * Thin PHPMailer wrapper that sends a certificate PDF using the user's own
 * SMTP server and an app password.
 */
class Mailer
{
    private array $smtp;

    /**
     * @param array $smtp [host, port, secure(tls|ssl), username, password, from_email, from_name]
     */
    public function __construct(array $smtp)
    {
        $this->smtp = $smtp;
    }

    public function send(string $toEmail, string $toName, string $subject, string $htmlBody, string $attachmentPath, string $attachmentName): void
    {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = $this->smtp['host'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $this->smtp['username'];
        $mail->Password   = $this->smtp['password'];
        $mail->Port       = (int)$this->smtp['port'];
        $mail->CharSet    = 'UTF-8';

        $secure = strtolower($this->smtp['secure'] ?? 'tls');
        if ($secure === 'ssl') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } else {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        }

        $fromEmail = $this->smtp['from_email'] ?: $this->smtp['username'];
        $mail->setFrom($fromEmail, $this->smtp['from_name'] ?? '');
        $mail->addAddress($toEmail, $toName);

        if (!empty($attachmentPath) && is_file($attachmentPath)) {
            $mail->addAttachment($attachmentPath, $attachmentName);
        }

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $htmlBody;
        $mail->AltBody = trim(strip_tags($htmlBody));

        $mail->send();
    }
}
