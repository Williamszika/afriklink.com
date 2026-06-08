<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Transactional e-mail.
 *
 * Driver is chosen by MAIL_DRIVER (.env):
 *   - "log"  (default): writes the message to storage/logs/mail.log. Lets the whole
 *     auth flow (verification, password reset) be developed and tested with no SMTP.
 *   - "smtp": sends via PHPMailer if installed (composer require phpmailer/phpmailer);
 *     otherwise it falls back to "log" and records a warning.
 */
final class MailService
{
    public static function send(string $toEmail, string $subject, string $htmlBody, ?string $textBody = null): bool
    {
        $driver = $_ENV['MAIL_DRIVER'] ?? 'log';

        if ($driver === 'smtp' && class_exists(\PHPMailer\PHPMailer\PHPMailer::class)) {
            return self::sendSmtp($toEmail, $subject, $htmlBody, $textBody);
        }

        if ($driver === 'smtp') {
            log_message('warning', 'MAIL_DRIVER=smtp but PHPMailer is not installed; falling back to log.');
        }

        return self::sendLog($toEmail, $subject, $htmlBody, $textBody);
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
