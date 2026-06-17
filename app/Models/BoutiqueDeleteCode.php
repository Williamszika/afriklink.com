<?php
declare(strict_types=1);

namespace App\Models;

/**
 * boutique_delete_codes — codes à 6 chiffres, à usage unique et expirant, qui
 * confirment la SUPPRESSION d'une boutique. Seul le hash SHA-256 du code est
 * stocké ; le code en clair ne voyage que par e-mail. Le nombre d'essais par
 * code est plafonné pour contrer le brute-force.
 */
final class BoutiqueDeleteCode
{
    private const MAX_ATTEMPTS = 5;

    public static function ensureTable(): void
    {
        ddl_safe(
            'CREATE TABLE IF NOT EXISTS boutique_delete_codes (
                id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                user_id     BIGINT UNSIGNED NOT NULL,
                boutique_id BIGINT UNSIGNED NOT NULL,
                code_hash   CHAR(64) NOT NULL,
                attempts    TINYINT UNSIGNED NOT NULL DEFAULT 0,
                expires_at  DATETIME NOT NULL,
                consumed_at DATETIME NULL,
                created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY idx_bdc_lookup (user_id, boutique_id, consumed_at)
            )'
        );
    }

    /** Émet un code à 6 chiffres pour (user, boutique) et renvoie le code EN CLAIR. */
    public static function issue(int $userId, int $boutiqueId, int $ttlSeconds): string
    {
        self::ensureTable();
        // Invalide les codes précédents encore valides pour cette boutique.
        try {
            db()->prepare(
                'UPDATE boutique_delete_codes SET consumed_at = NOW()
                 WHERE user_id = :u AND boutique_id = :b AND consumed_at IS NULL'
            )->execute(['u' => $userId, 'b' => $boutiqueId]);
        } catch (\Throwable) {
            // table neuve : rien à invalider
        }

        $code    = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $expires = (new \DateTimeImmutable('now'))
            ->modify('+' . max(60, $ttlSeconds) . ' seconds')
            ->format('Y-m-d H:i:s');

        db()->prepare(
            'INSERT INTO boutique_delete_codes (user_id, boutique_id, code_hash, expires_at)
             VALUES (:u, :b, :h, :e)'
        )->execute([
            'u' => $userId,
            'b' => $boutiqueId,
            'h' => hash('sha256', $code),
            'e' => $expires,
        ]);

        return $code;
    }

    /**
     * Vérifie un code saisi pour (user, boutique). À usage unique : consommé si
     * correct. Plafonne les essais. Renvoie vrai uniquement si le code correspond
     * à un code valide, non expiré et non encore consommé.
     */
    public static function verify(int $userId, int $boutiqueId, string $code): bool
    {
        self::ensureTable();
        $code = preg_replace('/\D+/', '', $code) ?? '';
        if (strlen($code) !== 6) {
            return false;
        }

        $pdo = db();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                'SELECT id, code_hash, attempts FROM boutique_delete_codes
                 WHERE user_id = :u AND boutique_id = :b AND consumed_at IS NULL AND expires_at > NOW()
                 ORDER BY id DESC LIMIT 1 FOR UPDATE'
            );
            $stmt->execute(['u' => $userId, 'b' => $boutiqueId]);
            $row = $stmt->fetch();

            if ($row === false || (int) $row['attempts'] >= self::MAX_ATTEMPTS) {
                $pdo->commit();
                return false;
            }

            // On compte l'essai AVANT la comparaison (plafond anti-brute-force).
            $pdo->prepare('UPDATE boutique_delete_codes SET attempts = attempts + 1 WHERE id = :id')
                ->execute(['id' => $row['id']]);

            $ok = hash_equals((string) $row['code_hash'], hash('sha256', $code));
            if ($ok) {
                $pdo->prepare('UPDATE boutique_delete_codes SET consumed_at = NOW() WHERE id = :id')
                    ->execute(['id' => $row['id']]);
            }

            $pdo->commit();
            return $ok;
        } catch (\Throwable) {
            $pdo->rollBack();
            return false;
        }
    }
}
