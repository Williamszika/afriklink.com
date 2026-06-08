<?php
declare(strict_types=1);

namespace App\Models;

/**
 * password_resets — single-use, expiring password reset tokens (security.md §4).
 * Stored hashed; issuing a new one invalidates any outstanding tokens for the user.
 */
final class PasswordReset
{
    /** Issue a token and return the RAW token (to embed in the reset link). */
    public static function issue(int $userId, int $ttlSeconds): string
    {
        $raw = bin2hex(random_bytes(32));
        $expires = (new \DateTimeImmutable('now'))
            ->modify('+' . $ttlSeconds . ' seconds')
            ->format('Y-m-d H:i:s');

        $pdo = db();
        // Invalidate previous outstanding resets for this user.
        $pdo->prepare('UPDATE password_resets SET used_at = NOW() WHERE user_id = :u AND used_at IS NULL')
            ->execute(['u' => $userId]);

        $pdo->prepare(
            'INSERT INTO password_resets (user_id, token_hash, expires_at) VALUES (:u, :h, :e)'
        )->execute([
            'u' => $userId,
            'h' => hash('sha256', $raw),
            'e' => $expires,
        ]);

        return $raw;
    }

    /** Consume a raw token. Returns the user id (caller then sets the new password), or null. */
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
                'SELECT id, user_id FROM password_resets
                 WHERE token_hash = :h AND used_at IS NULL AND expires_at > NOW()
                 ORDER BY id DESC LIMIT 1 FOR UPDATE'
            );
            $stmt->execute(['h' => $hash]);
            $row = $stmt->fetch();

            if ($row === false) {
                $pdo->commit();
                return null;
            }

            $pdo->prepare('UPDATE password_resets SET used_at = NOW() WHERE id = :id')
                ->execute(['id' => $row['id']]);

            $pdo->commit();
            return (int) $row['user_id'];
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
}
