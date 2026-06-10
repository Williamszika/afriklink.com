<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Transactional e-mail.
 *
 * Driver is chosen by MAIL_DRIVER (.env):
 *   - "log"  (default): writes the message to storage/logs/mail.log. Lets the whole
 *     auth flow (verification, password reset) be developed and tested with no SMTP.
 *   - "api": sends over HTTPS via a transactional e-mail API (Brevo-compatible),
 *     using native cURL — no Composer dependency, works on Vercel's serverless PHP.
 *     Needs MAIL_API_KEY (and optionally MAIL_API_URL to point at another provider).
 *   - "smtp": sends via PHPMailer if installed (composer require phpmailer/phpmailer);
 *     otherwise it falls back to "log" and records a warning.
 *
 * A send failure never breaks the calling flow: drivers catch their own errors,
 * log a warning and return false.
 */
final class MailService
{
    private const DEFAULT_API_URL = 'https://api.brevo.com/v3/smtp/email';

    /** Short, secret-free description of the last send failure (for diagnostics). */
    public static ?string $lastError = null;

    public static function send(string $toEmail, string $subject, string $htmlBody, ?string $textBody = null): bool
    {
        self::$lastError = null;
        $driver = $_ENV['MAIL_DRIVER'] ?? 'log';

        if ($driver === 'api') {
            if (($_ENV['MAIL_API_KEY'] ?? '') !== '') {
                return self::sendApi($toEmail, $subject, $htmlBody, $textBody);
            }
            log_message('warning', 'MAIL_DRIVER=api but MAIL_API_KEY is empty; falling back to log.');
            return self::sendLog($toEmail, $subject, $htmlBody, $textBody);
        }

        if ($driver === 'smtp' && class_exists(\PHPMailer\PHPMailer\PHPMailer::class)) {
            return self::sendSmtp($toEmail, $subject, $htmlBody, $textBody);
        }

        if ($driver === 'smtp') {
            log_message('warning', 'MAIL_DRIVER=smtp but PHPMailer is not installed; falling back to log.');
        }

        return self::sendLog($toEmail, $subject, $htmlBody, $textBody);
    }

    /** Brevo-compatible JSON API over native cURL (serverless-safe, no Composer). */
    private static function sendApi(string $to, string $subject, string $html, ?string $text): bool
    {
        try {
            $payload = json_encode([
                'sender' => [
                    'email' => $_ENV['MAIL_FROM'] ?? 'no-reply@afriklink.com',
                    'name'  => $_ENV['MAIL_FROM_NAME'] ?? 'Afriklink',
                ],
                'to'          => [['email' => $to]],
                'subject'     => $subject,
                'htmlContent' => $html,
                'textContent' => $text ?? strip_tags($html),
            ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

            $ch = curl_init($_ENV['MAIL_API_URL'] ?? self::DEFAULT_API_URL);
            curl_setopt_array($ch, [
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => $payload,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_TIMEOUT        => 10,
                CURLOPT_HTTPHEADER     => [
                    'accept: application/json',
                    'content-type: application/json',
                    'api-key: ' . ($_ENV['MAIL_API_KEY'] ?? ''),
                ],
            ]);
            $body   = curl_exec($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $error  = curl_error($ch);
            curl_close($ch);

            if ($status >= 200 && $status < 300) {
                return true;
            }
            self::$lastError = 'HTTP ' . $status . ' — '
                . ($error !== '' ? $error : substr((string) $body, 0, 200));
            log_message('error', 'mail api send failed', [
                'status' => $status,
                'error'  => $error !== '' ? $error : substr((string) $body, 0, 300),
            ]);
            return false;
        } catch (\Throwable $e) {
            self::$lastError = 'exception — ' . $e->getMessage();
            log_message('error', 'mail api exception: ' . $e->getMessage());
            return false;
        }
    }

    private static function sendLog(string $to, string $subject, string $html, ?string $text): bool
    {
        $entry = sprintf(
            "----- MAIL %s -----\nTo: %s\nFrom: %s\nSubject: %s\n\n%s\n",
            (new \DateTimeImmutable('now'))->format('Y-m-d H:i:s'),
            $to,
            $_ENV['MAIL_FROM'] ?? 'no-reply@afrikalink.example',
            $subject,
            $text ?? strip_tags($html)
        );
        @file_put_contents(STORAGE_PATH . '/logs/mail.log', $entry, FILE_APPEND | LOCK_EX);
        return true;
    }

    private static function sendSmtp(string $to, string $subject, string $html, ?string $text): bool
    {
        try {
            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
            $mail->isSMTP();
            $mail->Host       = $_ENV['MAIL_HOST'] ?? '';
            $mail->Port       = (int) ($_ENV['MAIL_PORT'] ?? 587);
            $mail->SMTPAuth   = true;
            $mail->Username   = $_ENV['MAIL_USER'] ?? '';
            $mail->Password   = $_ENV['MAIL_PASS'] ?? '';
            $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->CharSet    = 'UTF-8';

            $mail->setFrom(
                $_ENV['MAIL_FROM'] ?? 'no-reply@afrikalink.example',
                $_ENV['MAIL_FROM_NAME'] ?? 'AfrikaLink'
            );
            $mail->addAddress($to);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $html;
            $mail->AltBody = $text ?? strip_tags($html);

            $mail->send();
            return true;
        } catch (\Throwable $e) {
            log_message('error', 'SMTP send failed: ' . $e->getMessage(), ['to' => $to]);
            return false;
        }
    }
}
