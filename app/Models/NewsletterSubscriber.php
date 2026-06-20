<?php
declare(strict_types=1);

namespace App\Models;

/**
 * newsletter_subscribers — inscriptions à la lettre d'information (promos,
 * nouveautés). Marketing = consentement + désinscription obligatoires (RGPD) :
 *   - `consent_at`      : horodatage de l'opt-in (preuve du consentement) ;
 *   - `status`          : 'subscribed' | 'unsubscribed' ;
 *   - `token`           : jeton unique pour le lien de désinscription 1-clic ;
 *   - `unsubscribed_at` : horodatage du retrait.
 * Table auto-créée + migration idempotente des colonnes.
 */
final class NewsletterSubscriber
{
    public static function ensureTable(): void
    {
        static $ready = false;
        if ($ready) {
            return;
        }
        $ready = true;
        ddl_safe(
            "CREATE TABLE IF NOT EXISTS newsletter_subscribers (
                id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                email           VARCHAR(191) NOT NULL UNIQUE,
                locale          CHAR(5) NULL,
                source          VARCHAR(32) NULL,
                status          VARCHAR(12) NOT NULL DEFAULT 'subscribed',
                token           CHAR(36) NULL UNIQUE,
                consent_at      DATETIME NULL,
                unsubscribed_at DATETIME NULL,
                created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            )"
        );
        self::migrate();
    }

    /** Ajoute les colonnes RGPD aux tables existantes (idempotent). */
    private static function migrate(): void
    {
        try {
            db()->query('SELECT status, token, consent_at, unsubscribed_at FROM newsletter_subscribers LIMIT 1');
        } catch (\Throwable) {
            foreach ([
                "ADD COLUMN status VARCHAR(12) NOT NULL DEFAULT 'subscribed'",
                'ADD COLUMN token CHAR(36) NULL',
                'ADD COLUMN consent_at DATETIME NULL',
                'ADD COLUMN unsubscribed_at DATETIME NULL',
            ] as $clause) {
                try {
                    db()->exec('ALTER TABLE newsletter_subscribers ' . $clause);
                } catch (\Throwable) {
                }
            }
            // Jeton + consentement pour les lignes déjà présentes (pré-RGPD).
            try {
                foreach (db()->query("SELECT id FROM newsletter_subscribers WHERE token IS NULL OR token = ''")->fetchAll() as $r) {
                    db()->prepare('UPDATE newsletter_subscribers SET token = :t, consent_at = COALESCE(consent_at, created_at) WHERE id = :id')
                        ->execute(['t' => uuid(), 'id' => (int) $r['id']]);
                }
            } catch (\Throwable) {
            }
        }
    }

    /**
     * Inscrit un e-mail (idempotent) avec consentement horodaté. Réactive un
     * désabonné qui se réinscrit. Renvoie le jeton de désinscription, ou null
     * si l'e-mail est invalide.
     */
    public static function subscribe(string $email, ?string $locale = null, string $source = 'footer'): ?string
    {
        $email = mb_strtolower(trim($email));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 191) {
            return null;
        }
        self::ensureTable();
        try {
            $existing = self::find($email);
            if ($existing !== null) {
                // Réinscription : on réactive + on rafraîchit le consentement.
                $token = (string) ($existing['token'] ?: uuid());
                db()->prepare("UPDATE newsletter_subscribers
                                  SET status='subscribed', consent_at=NOW(), unsubscribed_at=NULL, token=:t, locale=:l
                                WHERE id=:id")
                    ->execute(['t' => $token, 'l' => $locale, 'id' => (int) $existing['id']]);
                return $token;
            }
            $token = uuid();
            db()->prepare("INSERT INTO newsletter_subscribers (email, locale, source, status, token, consent_at)
                           VALUES (:e, :l, :s, 'subscribed', :t, NOW())")
                ->execute(['e' => $email, 'l' => $locale, 's' => mb_substr($source, 0, 32), 't' => $token]);
            return $token;
        } catch (\Throwable) {
            return null;
        }
    }

    public static function find(string $email): ?array
    {
        self::ensureTable();
        try {
            $stmt = db()->prepare('SELECT * FROM newsletter_subscribers WHERE email = :e LIMIT 1');
            $stmt->execute(['e' => mb_strtolower(trim($email))]);
            return $stmt->fetch() ?: null;
        } catch (\Throwable) {
            return null;
        }
    }

    /** Désinscription 1-clic via jeton. Renvoie l'e-mail retiré, ou null. */
    public static function unsubscribe(string $token): ?string
    {
        self::ensureTable();
        $token = trim($token);
        if ($token === '') {
            return null;
        }
        try {
            $stmt = db()->prepare('SELECT id, email FROM newsletter_subscribers WHERE token = :t LIMIT 1');
            $stmt->execute(['t' => $token]);
            $row = $stmt->fetch();
            if (!$row) {
                return null;
            }
            db()->prepare("UPDATE newsletter_subscribers SET status='unsubscribed', unsubscribed_at=NOW() WHERE id=:id")
                ->execute(['id' => (int) $row['id']]);
            return (string) $row['email'];
        } catch (\Throwable) {
            return null;
        }
    }

    /** Lien public de désinscription pour un jeton donné. */
    public static function unsubscribeUrl(string $token): string
    {
        return url('/desinscription/' . $token);
    }

    /** @return list<array> abonnés actifs (destinataires d'une campagne). */
    public static function subscribed(int $limit = 5000): array
    {
        self::ensureTable();
        $limit = max(1, min(50000, $limit));
        try {
            return db()->query("SELECT email, locale, token FROM newsletter_subscribers
                                WHERE status='subscribed' ORDER BY id ASC LIMIT {$limit}")->fetchAll() ?: [];
        } catch (\Throwable) {
            return [];
        }
    }

    /** @return array{subscribed:int,unsubscribed:int,total:int} */
    public static function counts(): array
    {
        self::ensureTable();
        try {
            $sub = (int) db()->query("SELECT COUNT(*) FROM newsletter_subscribers WHERE status='subscribed'")->fetchColumn();
            $uns = (int) db()->query("SELECT COUNT(*) FROM newsletter_subscribers WHERE status='unsubscribed'")->fetchColumn();
            return ['subscribed' => $sub, 'unsubscribed' => $uns, 'total' => $sub + $uns];
        } catch (\Throwable) {
            return ['subscribed' => 0, 'unsubscribed' => 0, 'total' => 0];
        }
    }
}
