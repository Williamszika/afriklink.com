<?php
declare(strict_types=1);

namespace App\Models;

/**
 * email_verifications — single-use, expiring email verification tokens.
 * Only the SHA-256 hash of the token is stored; the raw token travels by email only.
 */
final class EmailVerification
{
    /** Issue a token for a user and return the RAW token (to embed in the link). */
    public static function issue(int $userId, int $ttlSeconds): string
    {
        $raw = bin2hex(random_bytes(32));
        $expires = (new \DateTimeImmutable('now'))
            ->modify('+' . $ttlSeconds . ' seconds')
            ->format('Y-m-d H:i:s');

        $stmt = db()->prepare(
            'INSERT INTO email_verifications (user_id, token_hash, expires_at)
             VALUES (:u, :h, :e)'
        );
        $stmt->execute([
            'u' => $userId,
            'h' => hash('sha256', $raw),
            'e' => $expires,
        ]);

        return $raw;
    }

    /** Consume a raw token: marks it + the user verified. Returns user id or null. */
    public static function consume(string $rawToken): ?int
    {
        if (strlen($rawToken) !== 64 || !ctype_xdigit($rawToken)) {
            return null;
        }
        $hash = hash('sha256', $rawToken);

        $pdo = db();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                'SELECT id, user_id FROM email_verifications
                 WHERE token_hash = :h AND verified_at IS NULL AND expires_at > NOW()
                 ORDER BY id DESC LIMIT 1 FOR UPDATE'
            );
            $stmt->execute(['h' => $hash]);
            $row = $stmt->fetch();

            if ($row === false) {
                $pdo->commit();
                return null;
            }

            $pdo->prepare('UPDATE email_verifications SET verified_at = NOW() WHERE id = :id')
                ->execute(['id' => $row['id']]);
            $pdo->prepare('UPDATE users SET email_verified_at = NOW() WHERE id = :id AND email_verified_at IS NULL')
                ->execute(['id' => $row['user_id']]);

            $pdo->commit();
            return (int) $row['user_id'];
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
}
