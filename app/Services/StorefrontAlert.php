<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Alerte de création de vitrine (boutique, restaurant, …) : le vendeur reçoit
 * un e-mail « tu viens de créer ta vitrine » avec le lien de gestion, et en
 * bas un lien « ce n'était pas moi » qui prévient les administrateurs.
 * Le lien de signalement utilise un jeton à usage unique stocké en base
 * (valable 30 jours), consommable sans être connecté. L'envoi ne doit JAMAIS
 * faire échouer la création (les appelants enveloppent dans try/catch).
 */
final class StorefrontAlert
{
    private const TTL_DAYS = 30;

    public static function ensureTable(): void
    {
        db()->exec(
            'CREATE TABLE IF NOT EXISTS storefront_report_tokens (
                id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                token         CHAR(64) NOT NULL UNIQUE,
                user_id       BIGINT UNSIGNED NOT NULL,
                vitrine_type  VARCHAR(12) NOT NULL,
                vitrine_name  VARCHAR(80) NOT NULL,
                created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                used_at       DATETIME NULL,
                KEY idx_srt_user (user_id)
            )'
        );
    }

    /** Envoie l'e-mail de confirmation de création. Vrai si parti. */
    public static function send(array $user, string $type, string $name, string $manageUrl): bool
    {
        $email = trim((string) ($user['email'] ?? ''));
        if ($email === '') {
            return false; // compte téléphone uniquement : pas d'e-mail à prévenir
        }

        self::ensureTable();
        $token = bin2hex(random_bytes(32));
        db()->prepare(
            'INSERT INTO storefront_report_tokens (token, user_id, vitrine_type, vitrine_name)
             VALUES (:t, :u, :ty, :n)'
        )->execute([
            't' => $token,
            'u' => (int) $user['id'],
            'ty' => $type,
            'n' => mb_substr($name, 0, 80),
        ]);

        $reportUrl = url('/signaler-vitrine?token=' . $token);
        $typeLabel = t('vitrine.type.' . $type);
        $app = (string) config('app.name', 'Afriklink');

        $html = '<p>' . e(t('vitrine.mail.hello', ['user' => (string) ($user['full_name'] ?? '')])) . '</p>'
            . '<p>' . e(t('vitrine.mail.body', ['type' => $typeLabel, 'name' => $name, 'app' => $app])) . '</p>'
            . '<p><a href="' . e($manageUrl) . '" style="display:inline-block;padding:10px 18px;background:#0b7a4b;color:#ffffff;text-decoration:none;border-radius:8px;font-weight:bold">'
            . e(t('vitrine.mail.cta')) . '</a></p>'
            . '<p style="color:#666;font-size:13px">' . e($manageUrl) . '</p>'
            . '<hr style="border:none;border-top:1px solid #e5e7eb;margin:18px 0">'
            . '<p style="color:#666;font-size:13px">' . e(t('vitrine.mail.notme')) . '<br>'
            . '<a href="' . e($reportUrl) . '" style="color:#b42318;font-weight:bold">' . e(t('vitrine.mail.notme_cta')) . '</a></p>';

        $text = t('vitrine.mail.body', ['type' => $typeLabel, 'name' => $name, 'app' => $app])
            . "\n" . $manageUrl
            . "\n\n" . t('vitrine.mail.notme') . "\n" . $reportUrl;

        return MailService::send($email, t('vitrine.mail.subject', ['name' => $name]), $html, $text);
    }

    /** Consomme un jeton de signalement (usage unique, 30 jours). */
    public static function consume(string $token): ?array
    {
        if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
            return null;
        }
        try {
            self::ensureTable();
            $stmt = db()->prepare(
                'SELECT * FROM storefront_report_tokens
                  WHERE token = :t AND used_at IS NULL
                    AND created_at > DATE_SUB(NOW(), INTERVAL ' . self::TTL_DAYS . ' DAY)
                  LIMIT 1'
            );
            $stmt->execute(['t' => $token]);
            $row = $stmt->fetch();
            if ($row === false) {
                return null;
            }
            db()->prepare('UPDATE storefront_report_tokens SET used_at = NOW() WHERE id = :id')
                ->execute(['id' => (int) $row['id']]);
            return $row;
        } catch (\Throwable) {
            return null;
        }
    }

    /** Prévient chaque administrateur (ADMIN_EMAILS) du signalement. */
    public static function notifyAdmins(array $row, array $user, string $ip): void
    {
        $admins = (array) config('app.admin_emails', []);
        if ($admins === []) {
            return;
        }
        $subject = '🚨 ' . t('vitrine.admin.subject', ['name' => (string) $row['vitrine_name']]);
        $html = '<p>' . e(t('vitrine.admin.body')) . '</p><ul>'
            . '<li>' . e(t('vitrine.admin.f_type')) . ' : ' . e(t('vitrine.type.' . $row['vitrine_type'])) . '</li>'
            . '<li>' . e(t('vitrine.admin.f_name')) . ' : ' . e((string) $row['vitrine_name']) . '</li>'
            . '<li>' . e(t('vitrine.admin.f_user')) . ' : ' . e((string) ($user['email'] ?? ('#' . $row['user_id']))) . '</li>'
            . '<li>' . e(t('vitrine.admin.f_created')) . ' : ' . e((string) $row['created_at']) . '</li>'
            . '<li>IP : ' . e($ip) . '</li></ul>'
            . '<p>' . e(t('vitrine.admin.advice')) . '</p>';
        foreach ($admins as $to) {
            MailService::send((string) $to, $subject, $html);
        }
    }
}
