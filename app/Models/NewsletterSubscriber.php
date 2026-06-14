<?php
declare(strict_types=1);

namespace App\Models;

/**
 * newsletter_subscribers — inscriptions à la lettre d'information (promos,
 * nouveautés). Inscription simple (un e-mail), idempotente. Table auto-créée.
 */
final class NewsletterSubscriber
{
    public static function ensureTable(): void
    {
        ddl_safe(
            'CREATE TABLE IF NOT EXISTS newsletter_subscribers (
                id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                email        VARCHAR(191) NOT NULL UNIQUE,
                locale       CHAR(5) NULL,
                source       VARCHAR(32) NULL,
                created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            )'
        );
    }

    /** Inscrit un e-mail (idempotent). Renvoie false si l'e-mail est invalide. */
    public static function subscribe(string $email, ?string $locale = null, string $source = 'footer'): bool
    {
        $email = mb_strtolower(trim($email));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 191) {
            return false;
        }
        self::ensureTable();
        try {
            // INSERT IGNORE : ré-inscription d'un e-mail déjà présent = sans effet, sans erreur.
            db()->prepare('INSERT IGNORE INTO newsletter_subscribers (email, locale, source) VALUES (:e, :l, :s)')
                ->execute(['e' => $email, 'l' => $locale, 's' => mb_substr($source, 0, 32)]);
            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
