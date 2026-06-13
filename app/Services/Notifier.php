<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Notifications courtes par SMS / WhatsApp — ossature multi-canal, prête à
 * brancher. Le canal est choisi par NOTIFY_DRIVER (.env) :
 *
 *   - "log" (défaut) : écrit le message dans storage/logs/notify.log. Permet
 *     de développer et tester tout le parcours sans compte SMS/WhatsApp.
 *   - "brevo_sms"     : SMS transactionnel via l'API Brevo (clé NOTIFY_API_KEY,
 *                       ou MAIL_API_KEY réutilisée ; expéditeur NOTIFY_SMS_SENDER).
 *   - "twilio"        : SMS via Twilio (TWILIO_SID, TWILIO_TOKEN, TWILIO_FROM).
 *   - "whatsapp"      : message WhatsApp via l'API Cloud de Meta
 *                       (WHATSAPP_TOKEN, WHATSAPP_PHONE_ID).
 *
 * Tout est en cURL natif (sans Composer, compatible serverless). Un échec
 * d'envoi ne casse JAMAIS le flux appelant : chaque canal attrape ses erreurs,
 * journalise et renvoie false.
 */
final class Notifier
{
    public static ?string $lastError = null;

    /** Le canal configuré peut-il réellement émettre (clés présentes) ? */
    public static function isLive(): bool
    {
        return self::driver() !== 'log';
    }

    public static function driver(): string
    {
        $d = strtolower(trim((string) ($_ENV['NOTIFY_DRIVER'] ?? 'log')));
        return match ($d) {
            'brevo_sms' => ($_ENV['NOTIFY_API_KEY'] ?? $_ENV['MAIL_API_KEY'] ?? '') !== '' ? 'brevo_sms' : 'log',
            'twilio'    => ($_ENV['TWILIO_SID'] ?? '') !== '' && ($_ENV['TWILIO_TOKEN'] ?? '') !== '' ? 'twilio' : 'log',
            'whatsapp'  => ($_ENV['WHATSAPP_TOKEN'] ?? '') !== '' && ($_ENV['WHATSAPP_PHONE_ID'] ?? '') !== '' ? 'whatsapp' : 'log',
            default     => 'log',
        };
    }

    /** Envoie un message court à un numéro (format international, ex. +221…). */
    public static function send(string $toPhone, string $text): bool
    {
        self::$lastError = null;
        $phone = self::normalize($toPhone);
        if ($phone === '') {
            return false;
        }
        return match (self::driver()) {
            'brevo_sms' => self::sendBrevoSms($phone, $text),
            'twilio'    => self::sendTwilio($phone, $text),
            'whatsapp'  => self::sendWhatsApp($phone, $text),
            default     => self::sendLog($phone, $text),
        };
    }

    /** Numéro au format E.164 « léger » : un seul +, puis des chiffres. */
    public static function normalize(string $raw): string
    {
        $plus = str_starts_with(trim($raw), '+');
        $digits = preg_replace('/\D+/', '', $raw) ?? '';
        if ($digits === '') {
            return '';
        }
        return ($plus ? '+' : '') . $digits;
    }

    private static function sendLog(string $phone, string $text): bool
    {
        $entry = sprintf(
            "----- NOTIFY %s (%s) -----\nTo: %s\n\n%s\n",
            (new \DateTimeImmutable('now'))->format('Y-m-d H:i:s'),
            (string) ($_ENV['NOTIFY_DRIVER'] ?? 'log'),
            $phone,
            $text
        );
        @file_put_contents(STORAGE_PATH . '/logs/notify.log', $entry, FILE_APPEND | LOCK_EX);
        return true;
    }

    private static function sendBrevoSms(string $phone, string $text): bool
    {
        // Brevo attend le numéro sans le « + ».
        return self::curlJson(
            'https://api.brevo.com/v3/transactionalSMS/sms',
            [
                'sender'    => mb_substr((string) ($_ENV['NOTIFY_SMS_SENDER'] ?? 'Afriklink'), 0, 11),
                'recipient' => ltrim($phone, '+'),
                'content'   => mb_substr($text, 0, 700),
                'type'      => 'transactional',
            ],
            ['accept: application/json', 'content-type: application/json',
             'api-key: ' . trim((string) ($_ENV['NOTIFY_API_KEY'] ?? $_ENV['MAIL_API_KEY'] ?? ''))]
        );
    }

    private static function sendWhatsApp(string $phone, string $text): bool
    {
        // API Cloud de Meta : message texte (la fenêtre 24 h / un modèle peuvent
        // être requis selon le compte — branché ici, prêt pour les clés).
        $phoneId = trim((string) ($_ENV['WHATSAPP_PHONE_ID'] ?? ''));
        return self::curlJson(
            'https://graph.facebook.com/v21.0/' . rawurlencode($phoneId) . '/messages',
            [
                'messaging_product' => 'whatsapp',
                'to'                => ltrim($phone, '+'),
                'type'              => 'text',
                'text'              => ['body' => mb_substr($text, 0, 1000)],
            ],
            ['content-type: application/json',
             'authorization: Bearer ' . trim((string) ($_ENV['WHATSAPP_TOKEN'] ?? ''))]
        );
    }

    private static function sendTwilio(string $phone, string $text): bool
    {
        $sid = trim((string) ($_ENV['TWILIO_SID'] ?? ''));
        try {
            $ch = curl_init('https://api.twilio.com/2010-04-01/Accounts/' . rawurlencode($sid) . '/Messages.json');
            curl_setopt_array($ch, [
                CURLOPT_POST           => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_TIMEOUT        => 10,
                CURLOPT_USERPWD        => $sid . ':' . trim((string) ($_ENV['TWILIO_TOKEN'] ?? '')),
                CURLOPT_POSTFIELDS     => http_build_query([
                    'To'   => $phone,
                    'From' => (string) ($_ENV['TWILIO_FROM'] ?? ''),
                    'Body' => mb_substr($text, 0, 1000),
                ]),
            ]);
            $body = curl_exec($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $err = curl_error($ch);
            curl_close($ch);
            if ($status >= 200 && $status < 300) {
                return true;
            }
            self::$lastError = 'HTTP ' . $status . ' — ' . ($err !== '' ? $err : substr((string) $body, 0, 200));
            log_message('error', 'notify twilio failed', ['status' => $status]);
            return false;
        } catch (\Throwable $e) {
            self::$lastError = 'exception — ' . $e->getMessage();
            return false;
        }
    }

    /** POST JSON générique en cURL natif. @param array<string,mixed> $payload @param list<string> $headers */
    private static function curlJson(string $apiUrl, array $payload, array $headers): bool
    {
        try {
            $ch = curl_init($apiUrl);
            curl_setopt_array($ch, [
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_TIMEOUT        => 10,
                CURLOPT_HTTPHEADER     => $headers,
            ]);
            $body = curl_exec($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $err = curl_error($ch);
            curl_close($ch);
            if ($status >= 200 && $status < 300) {
                return true;
            }
            self::$lastError = 'HTTP ' . $status . ' — ' . ($err !== '' ? $err : substr((string) $body, 0, 200));
            log_message('error', 'notify send failed', ['status' => $status, 'error' => substr((string) $body, 0, 200)]);
            return false;
        } catch (\Throwable $e) {
            self::$lastError = 'exception — ' . $e->getMessage();
            log_message('error', 'notify exception: ' . $e->getMessage());
            return false;
        }
    }
}
